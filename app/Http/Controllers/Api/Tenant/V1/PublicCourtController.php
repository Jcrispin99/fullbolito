<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Company;
use App\Models\Court;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints públicos (sin auth) consumidos por el sitio del tenant para
 * que el cliente final pueda explorar canchas y reservar.
 */
final class PublicCourtController extends ApiController
{
    // Mismo plazo que ReservationController::HOLD_MINUTES_DEFAULT — el hold
    // público debe coincidir con el default documentado (memoria, sección 5.5.2).
    private const HOLD_MINUTES_DEFAULT = 10;

    /**
     * Lista de canchas activas del tenant. Para el catálogo público.
     */
    public function index(Request $request): JsonResponse
    {
        $sport = $request->input('sport');
        $companyId = $request->input('company_id');

        $courts = Court::query()
            ->where('is_active', true)
            ->with('productProduct', 'company')
            ->when($sport, fn ($q) => $q->where('sport', $sport))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get()
            ->map(fn (Court $c) => $this->publicSummary($c));

        return $this->success(['courts' => $courts]);
    }

    /**
     * Detalle de una cancha (info pública).
     */
    public function show(Court $court): JsonResponse
    {
        if (! $court->is_active) {
            return $this->notFound('Cancha no disponible.');
        }

        $court->load('productProduct', 'company');

        return $this->success($this->publicSummary($court, full: true));
    }

    /**
     * Detalle por slug (para URLs amigables del sitio público del tenant:
     * /canchas/{slug}). Si hay duplicados por company, toma el primero activo.
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $court = Court::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('productProduct', 'company')
            ->orderBy('id')
            ->first();

        if (! $court) {
            return $this->notFound('Cancha no disponible.');
        }

        return $this->success($this->publicSummary($court, full: true));
    }

    /**
     * Disponibilidad de una cancha para una fecha. Combina los horarios
     * configurados (CourtSchedule) con las reservas que están bloqueando
     * el slot ahora mismo.
     *
     * GET /api/v1/public/courts/{court}/availability?date=YYYY-MM-DD
     */
    public function availability(Request $request, Court $court): JsonResponse
    {
        if (! $court->is_active) {
            return $this->notFound('Cancha no disponible.');
        }

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();

        if ($date->isBefore(now()->startOfDay())) {
            return $this->error('La fecha no puede ser pasada.', 422);
        }

        $dayOfWeek = (int) $date->dayOfWeek; // 0=domingo, 6=sábado

        $schedules = $court->schedules()
            ->where('is_active', true)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        $reservations = Reservation::query()
            ->where('court_id', $court->id)
            ->currentlyBlocking()
            ->where('start_at', '<', $date->copy()->endOfDay())
            ->where('end_at', '>', $date)
            ->get(['start_at', 'end_at', 'status']);

        $slots = [];
        foreach ($schedules as $schedule) {
            $slots = array_merge(
                $slots,
                $this->generateSlots($court, $schedule, $date, $reservations),
            );
        }

        return $this->success([
            'court' => $this->publicSummary($court),
            'date' => $date->toDateString(),
            'day_of_week' => $dayOfWeek,
            'is_closed' => $schedules->isEmpty(),
            'slots' => $slots,
        ]);
    }

    /**
     * Crea una reserva como invitado (sin auth). El slot solicitado debe caer
     * dentro de una CourtSchedule activa y alinear con sus boundaries de
     * duración. El total se calcula del schedule — el cliente no lo manda
     * (anti-tamper). Queda en estado `held` con un timeout corto para que
     * el cliente complete el pago.
     *
     * POST /api/v1/public/courts/{court}/reservations
     */
    public function storeReservation(Request $request, Court $court): JsonResponse
    {
        if (! $court->is_active) {
            return $this->notFound('Cancha no disponible.');
        }

        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $start = Carbon::parse($data['start_at']);

        if ($start->isBefore(now())) {
            return $this->error('El horario solicitado ya pasó.', 422);
        }

        $schedule = $this->findMatchingSchedule($court, $start);
        if (! $schedule) {
            return $this->error('La cancha no está abierta a esa hora.', 422);
        }

        $duration = (int) ($schedule->slot_duration_minutes ?? $court->slot_duration_minutes);
        $end = $start->copy()->addMinutes($duration);

        // Verifica que el slot termina dentro del schedule.
        $scheduleEnd = $start->copy()->setTimeFromTimeString((string) $schedule->end_time);
        if ($end->gt($scheduleEnd)) {
            return $this->error('El slot no cabe en el horario disponible.', 422);
        }

        // Verifica alineación con los boundaries del schedule (no horas arbitrarias).
        $scheduleStart = $start->copy()->setTimeFromTimeString((string) $schedule->start_time);
        $minutesFromStart = $scheduleStart->diffInMinutes($start, false);
        if ($minutesFromStart < 0 || $minutesFromStart % $duration !== 0) {
            return $this->error("El slot debe alinearse con las franjas de {$duration} minutos.", 422);
        }

        $price = (float) $schedule->price;

        $journal = $this->resolveReservationJournal($court->company_id);
        if (! $journal) {
            return $this->error('El sistema no está configurado para procesar reservas en esta sede.', 500);
        }

        try {
            $reservation = DB::transaction(function () use ($court, $start, $end, $data, $price, $journal) {
                $conflict = Reservation::query()
                    ->where('court_id', $court->id)
                    ->currentlyBlocking()
                    ->overlapping($start, $end)
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'El slot acaba de ser tomado por otro cliente. Refresca la disponibilidad.',
                    ], 409));
                }

                ['serie' => $serie, 'correlative' => $correlative] = $this->nextDocumentNumber($journal);

                $partner = Partner::resolveForGuest(
                    $data['customer_name'],
                    $data['customer_phone'],
                    $data['customer_email'] ?? null,
                    $court->company_id,
                );

                return Reservation::create([
                    'court_id' => $court->id,
                    'company_id' => $court->company_id,
                    'journal_id' => $journal->id,
                    'serie' => $serie,
                    'correlative' => $correlative,
                    'start_at' => $start,
                    'end_at' => $end,
                    'status' => 'held',
                    'held_until' => now()->addMinutes(self::HOLD_MINUTES_DEFAULT),
                    'partner_id' => $partner->id,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'] ?? null,
                    'total' => $price,
                    'notes' => $data['notes'] ?? null,
                    'created_by_user_id' => null,
                ]);
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        }

        return $this->created([
            'code' => $reservation->code,
            'court' => ['id' => $court->id, 'name' => $court->name],
            'start_at' => $reservation->start_at->toIso8601String(),
            'end_at' => $reservation->end_at->toIso8601String(),
            'total' => $reservation->total,
            'status' => $reservation->status,
            'held_until' => $reservation->held_until?->toIso8601String(),
            'customer' => [
                'name' => $reservation->customer_name,
                'phone' => $reservation->customer_phone,
                'email' => $reservation->customer_email,
            ],
            'message' => 'Reserva en espera. Completa el pago antes de '
                . $reservation->held_until?->format('H:i')
                . ' para confirmar el slot.',
        ]);
    }

    /**
     * Consulta pública de una reserva por su código (RES-00000001). Útil para
     * que el cliente pueda ver el estado de su reserva sin tener cuenta.
     *
     * GET /api/v1/public/reservations/{code}
     */
    public function showReservation(string $code): JsonResponse
    {
        $parts = explode('-', $code, 2);
        if (count($parts) !== 2) {
            return $this->notFound('Código de reserva inválido.');
        }

        [$serie, $correlative] = $parts;

        $reservation = Reservation::query()
            ->where('serie', $serie)
            ->where('correlative', $correlative)
            ->with('court')
            ->first();

        if (! $reservation) {
            return $this->notFound('Reserva no encontrada.');
        }

        return $this->success([
            'code' => $reservation->code,
            'court' => $reservation->court ? [
                'id' => $reservation->court->id,
                'name' => $reservation->court->name,
                'sport' => $reservation->court->sport,
            ] : null,
            'start_at' => $reservation->start_at?->toIso8601String(),
            'end_at' => $reservation->end_at?->toIso8601String(),
            'status' => $reservation->status,
            'total' => $reservation->total,
            'held_until' => $reservation->held_until?->toIso8601String(),
            'customer_name' => $reservation->customer_name,
        ]);
    }

    /**
     * Busca la CourtSchedule activa donde cae el momento solicitado.
     */
    private function findMatchingSchedule(Court $court, Carbon $start)
    {
        $dayOfWeek = (int) $start->dayOfWeek;
        $startTime = $start->format('H:i:s');

        return $court->schedules()
            ->where('is_active', true)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>', $startTime)
            ->first();
    }

    /**
     * Resuelve el journal RES con walk-up al parent si la sede no lo tiene.
     * Mismo patrón que ReservationController.
     */
    private function resolveReservationJournal(int $companyId): ?Journal
    {
        $company = Company::query()->find($companyId);
        if (! $company) {
            return null;
        }

        $candidates = collect([$company, $company->rootCompany()])
            ->unique(fn ($c) => $c->id);

        foreach ($candidates as $c) {
            $j = Journal::query()
                ->where('company_id', $c->id)
                ->where('type', 'reservation')
                ->with('sequence')
                ->first();

            if ($j && $j->sequence) {
                return $j;
            }
        }

        return null;
    }

    /**
     * @return array{serie: string, correlative: string}
     */
    private function nextDocumentNumber(Journal $journal): array
    {
        // lockForUpdate: evita que dos reservas públicas concurrentes lean
        // el mismo next_number antes de incrementarlo (ver mismo fix en
        // ReservationController::nextDocumentNumber).
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

    /**
     * Genera los slots de un schedule para una fecha, marcando cuáles están
     * libres y cuáles bloqueados por reservas existentes.
     *
     * @param  \Illuminate\Support\Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function generateSlots(Court $court, $schedule, Carbon $date, $reservations): array
    {
        $duration = (int) ($schedule->slot_duration_minutes ?? $court->slot_duration_minutes);
        if ($duration <= 0) {
            return [];
        }

        $cursor = $date->copy()->setTimeFromTimeString((string) $schedule->start_time);
        $end = $date->copy()->setTimeFromTimeString((string) $schedule->end_time);

        $now = now();
        $slots = [];

        while ($cursor->copy()->addMinutes($duration)->lte($end)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes($duration);

            $isPast = $slotStart->isBefore($now);
            [$available, $blockedBy] = $this->checkAvailability($slotStart, $slotEnd, $reservations);

            $slots[] = [
                'start' => $slotStart->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'start_at' => $slotStart->toIso8601String(),
                'end_at' => $slotEnd->toIso8601String(),
                'price' => (string) $schedule->price,
                'available' => $available && ! $isPast,
                'status' => $isPast ? 'past' : ($available ? 'available' : $blockedBy),
            ];

            $cursor = $slotEnd;
        }

        return $slots;
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function checkAvailability(Carbon $start, Carbon $end, $reservations): array
    {
        foreach ($reservations as $r) {
            // overlap: slot.start < res.end AND slot.end > res.start
            if ($start->lt($r->end_at) && $end->gt($r->start_at)) {
                $status = $r->status === 'held' ? 'held' : 'reserved';

                return [false, $status];
            }
        }

        return [true, null];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSummary(Court $court, bool $full = false): array
    {
        $base = [
            'id' => $court->id,
            'slug' => $court->slug,
            'name' => $court->name,
            'sport' => $court->sport,
            'surface' => $court->surface,
            'capacity' => $court->capacity,
            'slot_duration_minutes' => $court->slot_duration_minutes,
            'base_price' => $court->productProduct?->price,
        ];

        if ($full) {
            $base['description'] = $court->description;
            $base['company'] = $court->company ? [
                'id' => $court->company->id,
                'name' => $court->company->trade_name ?? $court->company->business_name,
                'address' => $court->company->address,
                'phone' => $court->company->phone,
            ] : null;
        }

        return $base;
    }
}
