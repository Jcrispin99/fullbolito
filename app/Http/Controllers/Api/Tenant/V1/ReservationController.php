<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\ReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Company;
use App\Models\Court;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\PosConfig;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReservationController extends ApiController
{
    private const HOLD_MINUTES_DEFAULT = 10;

    private const VALID_STATUSES = ['held', 'confirmed', 'paid', 'played', 'cancelled', 'no_show'];

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 25);
        $search = $request->input('search');
        $courtId = $request->input('court_id');
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Reservation::query()
            ->companyFiltered()
            ->with(['court', 'partner', 'createdByUser', 'sale']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        if ($courtId) {
            $query->where('court_id', $courtId);
        }

        if ($status && in_array($status, self::VALID_STATUSES, true)) {
            $query->where('status', $status);
        } elseif ($status === 'blocking') {
            $query->blocking();
        }

        if ($dateFrom) {
            $query->where('start_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('start_at', '<=', $dateTo);
        }

        $reservations = $query
            ->orderBy('start_at', 'desc')
            ->paginate((int) $perPage)
            ->appends($request->query());

        return $this->success(
            ReservationResource::collection($reservations)->response()->getData(true)
        );
    }

    public function formOptions(): JsonResponse
    {
        $courts = Court::query()
            ->companyFiltered()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sport', 'company_id', 'slot_duration_minutes']);

        return $this->success([
            'courts' => $courts,
            'statuses' => array_map(
                fn ($s) => ['value' => $s, 'label' => $this->statusLabel($s)],
                self::VALID_STATUSES,
            ),
        ], 'Form options retrieved successfully');
    }

    public function store(ReservationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $court = Court::query()->findOrFail($data['court_id']);

        if (! $court->is_active) {
            return $this->error('La cancha está inactiva.', 422);
        }

        $companyId = $data['company_id'] ?? $court->company_id;

        $journal = $this->resolveJournal($companyId, 'reservation');

        if (! $journal) {
            return $this->error(
                "No hay un journal de tipo 'reservation' configurado para la company {$companyId} ni para su company madre.",
                422,
            );
        }

        // Lock + verifica que no haya solapamiento, y obtiene el correlativo
        // del sequence del journal (atómico, mismo patrón que SaleController).
        $reservation = DB::transaction(function () use ($data, $court, $companyId, $journal) {
            $start = Carbon::parse($data['start_at']);
            $end = Carbon::parse($data['end_at']);

            $conflict = Reservation::query()
                ->where('court_id', $court->id)
                ->blocking()
                ->overlapping($start, $end)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'El horario solicitado se cruza con otra reserva activa.',
                    'errors' => ['start_at' => ['Slot ocupado']],
                ], 409));
            }

            ['serie' => $serie, 'correlative' => $correlative] = $this->nextDocumentNumber($journal);

            // Si el admin no envía partner_id pero sí customer_name/phone,
            // auto-resolvemos el Partner (creamos uno o reusamos por teléfono).
            $partnerId = $data['partner_id'] ?? null;
            if (! $partnerId) {
                $partnerId = Partner::resolveForGuest(
                    $data['customer_name'] ?? null,
                    $data['customer_phone'] ?? null,
                    $data['customer_email'] ?? null,
                    $companyId,
                )->id;
            }

            $payload = array_merge($data, [
                'company_id' => $companyId,
                'journal_id' => $journal->id,
                'serie' => $serie,
                'correlative' => $correlative,
                'partner_id' => $partnerId,
                'status' => 'held',
                'held_until' => $data['held_until']
                    ?? now()->addMinutes(self::HOLD_MINUTES_DEFAULT),
                'created_by_user_id' => optional($this->user())->id,
            ]);

            return Reservation::create($payload);
        });

        $reservation->load(['court', 'partner', 'createdByUser', 'sale']);

        return $this->created(new ReservationResource($reservation));
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $reservation->load(['court', 'partner', 'createdByUser', 'sale']);

        return $this->success(new ReservationResource($reservation));
    }

    /**
     * Edición parcial: solo campos de detalle (cliente, notas, total).
     * Para cambiar el estado se usan los endpoints específicos.
     */
    public function update(ReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validated();

        // Si el caller cambia start_at/end_at/court_id, re-validar solapamiento.
        $changingTime = isset($data['start_at']) || isset($data['end_at']) || isset($data['court_id']);

        if ($changingTime) {
            $courtId = $data['court_id'] ?? $reservation->court_id;
            $start = Carbon::parse($data['start_at'] ?? $reservation->start_at);
            $end = Carbon::parse($data['end_at'] ?? $reservation->end_at);

            $conflict = Reservation::query()
                ->where('court_id', $courtId)
                ->where('id', '!=', $reservation->id)
                ->blocking()
                ->overlapping($start, $end)
                ->exists();

            if ($conflict) {
                return $this->error('El nuevo horario se cruza con otra reserva activa.', 409);
            }
        }

        // status no se cambia por PATCH (se usa /confirm, /cancel, etc.)
        unset($data['status']);

        $reservation->update($data);
        $reservation->load(['court', 'partner', 'createdByUser', 'sale']);

        return $this->success(new ReservationResource($reservation));
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        $reservation->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Reservation::whereIn('id', $request->ids)->delete();

        return $this->noContent();
    }

    // ------------- Transiciones de estado -------------

    public function confirm(Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'held') {
            return $this->error("No se puede confirmar una reserva en estado '{$reservation->status}'.", 409);
        }

        $reservation->update([
            'status' => 'confirmed',
            'held_until' => null,
        ]);

        $reservation->load(['court', 'partner', 'createdByUser', 'sale']);

        return $this->success(new ReservationResource($reservation));
    }

    public function cancel(Reservation $reservation, Request $request): JsonResponse
    {
        if (in_array($reservation->status, ['cancelled', 'played', 'no_show'], true)) {
            return $this->error("La reserva ya está en estado '{$reservation->status}'.", 409);
        }

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
            'held_until' => null,
        ]);

        $reservation->load(['court', 'partner', 'createdByUser', 'sale']);

        return $this->success(new ReservationResource($reservation));
    }

    public function markPaid(Reservation $reservation): JsonResponse
    {
        if (! in_array($reservation->status, ['held', 'confirmed'], true)) {
            return $this->error("No se puede marcar como pagada desde el estado '{$reservation->status}'.", 409);
        }

        if ($reservation->sale_id) {
            return $this->error("La reserva ya tiene una venta asociada (#{$reservation->sale_id}).", 409);
        }

        $reservation->load('court.productProduct');

        $court = $reservation->court;
        if (! $court || ! $court->product_product_id) {
            return $this->error('La cancha no tiene product_product configurado.', 422);
        }

        // El warehouse para la Sale se resuelve desde la sede (Company) de la cancha.
        $warehouse = Warehouse::query()
            ->where('company_id', $court->company_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $warehouse) {
            return $this->error(
                "La sede de la cancha no tiene almacén configurado. No se puede emitir la venta.",
                422,
            );
        }

        // Journal de venta — preferimos la boleta ('receipt') configurada como
        // default en la primera caja (PosConfig) activa del warehouse, mismo
        // mecanismo que usa el POS (journal_pos_configs.is_default). Si la
        // sede no tiene ninguna caja/journal de boleta configurado, caemos a
        // NV (Nota de Venta) para no bloquear el cobro.
        $saleJournal = $this->resolveDefaultReceiptJournal($warehouse)
            ?? $this->resolveJournal($reservation->company_id, 'sale', 'NV');

        if (! $saleJournal) {
            return $this->error("No hay un journal de venta ('sale') configurado para la company {$reservation->company_id} ni para su company madre.", 422);
        }

        // Tax default (típicamente 18% IGV). Si no hay default, tomamos el primer activo.
        $tax = Tax::query()->where('is_active', true)->where('is_default', true)->first()
            ?? Tax::query()->where('is_active', true)->first();

        $taxRate = $tax ? (float) $tax->rate_percent : 0.0;
        $total = (float) $reservation->total;

        // Cálculo bottom-up desde el total bruto para evitar drift de redondeo:
        // subtotal = round(total / (1+rate/100), 2); tax = total - subtotal.
        $subtotal = $taxRate > 0
            ? round($total / (1 + $taxRate / 100), 2)
            : $total;
        $taxAmount = round($total - $subtotal, 2);

        $userId = optional(request()->user())->id;

        $sale = DB::transaction(function () use ($reservation, $court, $saleJournal, $tax, $taxRate, $subtotal, $taxAmount, $total, $userId, $warehouse) {
            ['serie' => $serie, 'correlative' => $correlative] = $this->nextDocumentNumber($saleJournal);

            $sale = Sale::create([
                'serie' => $serie,
                'correlative' => $correlative,
                'journal_id' => $saleJournal->id,
                'date' => now(),
                'partner_id' => $reservation->partner_id,
                'warehouse_id' => $warehouse->id,
                'company_id' => $reservation->company_id,
                'user_id' => $userId,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'status' => 'posted',
                'payment_status' => 'paid',
                'notes' => "Reserva {$reservation->code} - {$court->name}",
            ]);

            $sale->products()->create([
                'product_product_id' => $court->product_product_id,
                'quantity' => 1,
                'price' => $subtotal,
                'subtotal' => $subtotal,
                'tax_id' => $tax?->id,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            $reservation->update([
                'status' => 'paid',
                'held_until' => null,
                'sale_id' => $sale->id,
            ]);

            return $sale;
        });

        $reservation->load(['court', 'partner', 'createdByUser', 'sale']);

        return $this->success(new ReservationResource($reservation));
    }

    public function markPlayed(Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'paid' && $reservation->status !== 'confirmed') {
            return $this->error("Solo reservas confirmadas o pagadas pueden marcarse como jugadas.", 409);
        }

        $reservation->update(['status' => 'played']);
        $reservation->load(['court', 'partner', 'createdByUser', 'sale']);

        return $this->success(new ReservationResource($reservation));
    }

    public function markNoShow(Reservation $reservation): JsonResponse
    {
        if (! in_array($reservation->status, ['confirmed', 'paid'], true)) {
            return $this->error("Solo reservas confirmadas o pagadas pueden marcarse como no-show.", 409);
        }

        $reservation->update(['status' => 'no_show']);
        $reservation->load(['court', 'partner', 'createdByUser', 'sale']);

        return $this->success(new ReservationResource($reservation));
    }

    // ------------- Helpers -------------

    /**
     * Journal de boleta ('receipt') por defecto de la primera caja (PosConfig)
     * activa del warehouse — mismo criterio que usa el POS para resolver el
     * journal cuando hay una PosSession abierta (journal_pos_configs.is_default).
     */
    private function resolveDefaultReceiptJournal(Warehouse $warehouse): ?Journal
    {
        $posConfig = PosConfig::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $posConfig) {
            return null;
        }

        $journal = $posConfig->journals()
            ->wherePivot('document_type', 'receipt')
            ->wherePivot('is_default', true)
            ->with('sequence')
            ->first();

        return $journal && $journal->sequence ? $journal : null;
    }

    /**
     * Busca un journal por type (y opcionalmente preferido por code) para la
     * company indicada. Si no existe, sube al parent (walk-up). Esto soporta
     * el patrón del SaaS base donde los journals (NV, RES, F001, etc.) viven
     * solo en la company madre y las sucursales los comparten.
     */
    private function resolveJournal(int $companyId, string $type, ?string $preferredCode = null): ?Journal
    {
        $company = Company::query()->find($companyId);
        if (! $company) {
            return null;
        }

        $candidates = collect([$company, $company->rootCompany()])
            ->unique(fn ($c) => $c->id);

        foreach ($candidates as $c) {
            if ($preferredCode) {
                $journal = Journal::query()
                    ->where('company_id', $c->id)
                    ->where('type', $type)
                    ->where('code', $preferredCode)
                    ->with('sequence')
                    ->first();

                if ($journal && $journal->sequence) {
                    return $journal;
                }
            }

            $journal = Journal::query()
                ->where('company_id', $c->id)
                ->where('type', $type)
                ->with('sequence')
                ->first();

            if ($journal && $journal->sequence) {
                return $journal;
            }
        }

        return null;
    }

    /**
     * Obtiene serie+correlativo del journal y consume el sequence.
     * La serie es el `code` del journal (no el `prefix` del sequence) — evita
     * el fallback "F001" del método getNextParts() cuando el sequence no tiene
     * prefix. Mismo patrón que usa SaleController.
     *
     * @return array{serie: string, correlative: string}
     */
    private function nextDocumentNumber(Journal $journal): array
    {
        // lockForUpdate: bajo transacciones concurrentes (doble click, dos
        // reservas marcándose pagadas a la vez) dos requests podían leer el
        // mismo next_number antes de que cualquiera incrementara, generando
        // el mismo correlativo y chocando contra la unique key al insertar.
        $sequence = \App\Models\Sequence::query()
            ->lockForUpdate()
            ->findOrFail($journal->sequence_id);
        $serie = $journal->code;
        $correlative = str_pad(
            (string) $sequence->next_number,
            $sequence->sequence_size,
            '0',
            STR_PAD_LEFT,
        );

        $sequence->increment('next_number', $sequence->step);

        return ['serie' => $serie, 'correlative' => $correlative];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'held' => 'En espera',
            'confirmed' => 'Confirmada',
            'paid' => 'Pagada',
            'played' => 'Jugada',
            'cancelled' => 'Cancelada',
            'no_show' => 'No se presentó',
            default => $status,
        };
    }

    private function user(): ?\App\Models\User
    {
        /** @var \App\Models\User|null $u */
        $u = request()->user();

        return $u;
    }
}
