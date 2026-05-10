<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\TransferRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\TransferResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Company;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GreenterDespatchService;
use App\Services\TransferService;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder as QrBuilder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TransferController extends ApiController
{
    /**
     * Eager-loads para una vista detallada del header con sus dos movements.
     *
     * @var array<int, string>
     */
    private const TRANSFER_LOAD_RELATIONS = [
        'company',
        'createdUser',
        'exitMovement.warehouse',
        'exitMovement.postedUser',
        'exitMovement.productables.productProduct.template',
        'exitMovement.productables.productProduct.attributeValues',
        'exitMovement.productables.lot',
        'entryMovement.warehouse',
        'entryMovement.postedUser',
        'entryMovement.productables.productProduct.template',
        'entryMovement.productables.productProduct.attributeValues',
        'entryMovement.productables.lot',
    ];

    /**
     * Eager-loads ligeros para el listado.
     *
     * @var array<int, string>
     */
    private const TRANSFER_LIST_RELATIONS = [
        'company',
        'exitMovement.warehouse',
        'entryMovement.warehouse',
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $search = $request->input('search');
        $status = $request->input('status');
        $from = $request->input('from');
        $to = $request->input('to');
        $companyIds = $request->get('_company_ids');

        $query = Transfer::query()
            ->visibleToCompanies(is_array($companyIds) ? $companyIds : null)
            ->with(self::TRANSFER_LIST_RELATIONS)
            ->orderBy('created_at', 'desc');

        if ($status && $status !== 'all') {
            $this->applyDerivedStatusFilter($query, $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhere('observation', 'like', "%{$search}%");
            });
        }

        if ($from || $to) {
            $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
            $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

            if ($fromDate && $toDate) {
                $query->whereBetween('created_at', [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            } elseif ($toDate) {
                $query->where('created_at', '<=', $toDate);
            }
        }

        // Totales por estado derivado (snapshot de los filtros aplicados)
        $draftTotal = $this->countByDerivedStatus(clone $query, 'draft');
        $inTransitTotal = $this->countByDerivedStatus(clone $query, 'in_transit');
        $completedTotal = $this->countByDerivedStatus(clone $query, 'completed');
        $cancelledTotal = $this->countByDerivedStatus(clone $query, 'cancelled');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Transfers listing retrieved successfully',
            'data' => TransferResource::collection($paginator->items()),
            'links' => $paginator->linkCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'draft_total' => $draftTotal,
                'in_transit_total' => $inTransitTotal,
                'completed_total' => $completedTotal,
                'cancelled_total' => $cancelledTotal,
                // Aliases legacy para FE no migrado aún
                'sent_total' => $inTransitTotal,
                'received_total' => $completedTotal,
            ],
        ]);
    }

    public function formOptions(): JsonResponse
    {
        $companyIds = request()->get('_company_ids');

        $warehouses = Warehouse::query()
            ->when($companyIds, fn ($q) => $q->whereIn('company_id', $companyIds))
            ->with('company')
            ->orderBy('name')
            ->get();

        $companies = Company::query()
            ->where('is_active', true)
            ->when($companyIds, fn ($q) => $q->whereIn('id', $companyIds))
            ->get();

        $users = User::query()->latest()->get();

        return $this->success([
            'warehouses' => WarehouseResource::collection($warehouses),
            'companies' => CompanyResource::collection($companies),
            'users' => UserResource::collection($users),
        ], 'Form options retrieved successfully');
    }

    public function show(Transfer $transfer): JsonResponse
    {
        $transfer->load(self::TRANSFER_LOAD_RELATIONS);

        $activities = Activity::forSubject($transfer)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Record retrieved successfully',
            'data' => new TransferResource($transfer),
            'meta' => [
                'activities' => $activities,
            ],
        ]);
    }

    public function store(TransferRequest $request, TransferService $transferService): JsonResponse
    {
        $validated = $request->validated();

        try {
            $transfer = $transferService->create($validated, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created(
            new TransferResource($transfer->load(self::TRANSFER_LOAD_RELATIONS))
        );
    }

    public function update(TransferRequest $request, Transfer $transfer, TransferService $transferService): JsonResponse
    {
        try {
            $transferService->update($transfer, $request->validated());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            new TransferResource($transfer->fresh()?->load(self::TRANSFER_LOAD_RELATIONS))
        );
    }

    public function destroy(Transfer $transfer): JsonResponse
    {
        if (! $transfer->isDraft()) {
            return $this->error('Sólo se pueden eliminar transferencias en estado borrador.', 422);
        }

        $transfer->loadMissing(['exitMovement', 'entryMovement']);

        \DB::transaction(function () use ($transfer) {
            $transfer->exitMovement?->productables()->delete();
            $transfer->entryMovement?->productables()->delete();
            $transfer->exitMovement?->delete();
            $transfer->entryMovement?->delete();
            $transfer->delete();
        });

        return $this->noContent();
    }

    public function send(Transfer $transfer, TransferService $transferService): JsonResponse
    {
        try {
            $transferService->send($transfer, (int) request()->user()->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        activity()
            ->performedOn($transfer)
            ->causedBy(request()->user())
            ->log('Transferencia Enviada');

        return $this->success(
            new TransferResource($transfer->fresh()?->load(self::TRANSFER_LOAD_RELATIONS))
        );
    }

    public function receive(Transfer $transfer, TransferService $transferService): JsonResponse
    {
        try {
            $transferService->receive($transfer, (int) request()->user()->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        activity()
            ->performedOn($transfer)
            ->causedBy(request()->user())
            ->log('Transferencia Recibida');

        return $this->success(
            new TransferResource($transfer->fresh()?->load(self::TRANSFER_LOAD_RELATIONS))
        );
    }

    public function cancel(Transfer $transfer, TransferService $transferService): JsonResponse
    {
        try {
            $transferService->cancel($transfer, (int) request()->user()->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        activity()
            ->performedOn($transfer)
            ->causedBy(request()->user())
            ->log('Transferencia Cancelada');

        return $this->success(
            new TransferResource($transfer->fresh()?->load(self::TRANSFER_LOAD_RELATIONS))
        );
    }

    /**
     * Restaurar a borrador no aplica con el modelo nuevo: si el ciclo del par
     * de movements ya cruzó posted, la única vía es cancel (que escribe contra-
     * asientos). Endpoint mantiene 422 para no romper el FE viejo.
     */
    public function draft(Transfer $transfer): JsonResponse
    {
        return $this->error(
            'No se puede restaurar a borrador desde el modelo de movimientos. Use cancelar en su lugar.',
            422,
        );
    }

    // ─── Guía de Remisión Electrónica (GRE) ────────────────────────────────

    /**
     * Persiste los datos GRE (motivo, modalidad, transporte, conductor) sobre
     * el header. Permitido sólo si la transfer NO ha sido enviada aún a SUNAT
     * o vino en error (para reintentar). Una vez `accepted` la guía es
     * inmutable — se anula con baja, no se edita.
     */
    public function updateGre(Request $request, Transfer $transfer): JsonResponse
    {
        if (in_array($transfer->gre_status, ['accepted', 'ticket_pending', 'processing'], true)) {
            return $this->error('La guía ya fue enviada. No se puede modificar.', 422);
        }

        $data = $request->validate([
            'gre_motive_code' => ['required', 'string', 'in:04'],
            'gre_modality' => ['required', 'string', 'in:private'],
            'gre_transfer_start_date' => ['required', 'date'],
            'gre_gross_weight' => ['required', 'numeric', 'min:0'],
            'gre_packages' => ['nullable', 'integer', 'min:0'],
            'gre_vehicle_plate' => ['required', 'string', 'max:10'],
            'gre_driver_doc_type' => ['required', 'string', 'size:1'],
            'gre_driver_doc_number' => ['required', 'string', 'max:15'],
            'gre_driver_license' => ['required', 'string', 'max:20'],
            'gre_driver_name' => ['required', 'string', 'max:255'],
        ]);

        $transfer->fill($data)->save();

        return $this->success(
            new TransferResource($transfer->fresh()?->load(self::TRANSFER_LOAD_RELATIONS))
        );
    }

    /**
     * Envía la GRE a SUNAT (síncrono, devuelve ticket). Para producción
     * conviene encolar — pero por ahora se ejecuta inline igual que sales.
     */
    public function sendGre(Transfer $transfer, GreenterDespatchService $despatchService): JsonResponse
    {
        $exit = $transfer->exitMovement;
        if (! $exit || $exit->status !== 'posted') {
            return $this->error('El movement de salida debe estar posted antes de emitir GRE.', 422);
        }

        $ok = $despatchService->sendDespatch($transfer);

        activity()
            ->performedOn($transfer)
            ->causedBy(request()->user())
            ->log($ok ? 'GRE Enviada (ticket pendiente)' : 'GRE: error al enviar');

        return $this->success(
            new TransferResource($transfer->fresh()?->load(self::TRANSFER_LOAD_RELATIONS)),
            $ok ? 'GRE enviada — ticket en cola SUNAT.' : 'No se pudo enviar la GRE.'
        );
    }

    /**
     * Consulta el estado del ticket emitido. Cuando SUNAT acepta, archiva
     * el CDR y deja `gre_status=accepted`.
     */
    public function pollGre(Transfer $transfer, GreenterDespatchService $despatchService): JsonResponse
    {
        if (empty($transfer->gre_ticket)) {
            return $this->error('La transferencia no tiene ticket GRE para consultar.', 422);
        }

        $despatchService->pollTicket($transfer);

        return $this->success(
            new TransferResource($transfer->fresh()?->load(self::TRANSFER_LOAD_RELATIONS))
        );
    }

    public function downloadGreXml(Transfer $transfer): StreamedResponse|JsonResponse
    {
        if (empty($transfer->gre_signed_xml_path)
            || ! Storage::disk('local')->exists((string) $transfer->gre_signed_xml_path)) {
            return $this->error('XML firmado no disponible.', 404);
        }

        return Storage::disk('local')->download(
            (string) $transfer->gre_signed_xml_path,
            basename((string) $transfer->gre_signed_xml_path),
            ['Content-Type' => 'application/xml']
        );
    }

    public function downloadGreCdr(Transfer $transfer): StreamedResponse|JsonResponse
    {
        if (empty($transfer->gre_cdr_zip_path)
            || ! Storage::disk('local')->exists((string) $transfer->gre_cdr_zip_path)) {
            return $this->error('CDR no disponible.', 404);
        }

        return Storage::disk('local')->download(
            (string) $transfer->gre_cdr_zip_path,
            basename((string) $transfer->gre_cdr_zip_path),
            ['Content-Type' => 'application/zip']
        );
    }

    /**
     * Representación impresa de la GRE (HTML para visor en iframe srcdoc).
     *
     * Sólo visible para la company remitente — la dirección del transfer es
     * implícita en el grafo (exitMovement.warehouse.company_id). Si la company
     * actual no es el remitente, devolvemos 403 (es una guía "in" para esta
     * company y se imprime desde el portal del remitente, no desde aquí).
     */
    public function preview(Request $request, Transfer $transfer): \Illuminate\Http\Response|JsonResponse
    {
        if ($denied = $this->denyIfNotRemitente($request, $transfer)) {
            return $denied;
        }

        $html = view('tenant.sunat.despatch', $this->buildRepresentationData($transfer))->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Descarga la GRE como PDF (A4) usando el mismo blade que el visor.
     */
    public function pdf(Request $request, Transfer $transfer): \Symfony\Component\HttpFoundation\Response|JsonResponse
    {
        if ($denied = $this->denyIfNotRemitente($request, $transfer)) {
            return $denied;
        }

        $data = $this->buildRepresentationData($transfer);
        $html = view('tenant.sunat.despatch', $data)->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'tempDir' => storage_path('app/mpdf'),
        ]);
        $mpdf->WriteHTML($html);

        $filename = trim(($transfer->serie ?? '').'-'.($transfer->correlative ?? '')) ?: ('gre-'.$transfer->id);
        $pdfBinary = (string) $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            'Content-Length' => (string) strlen($pdfBinary),
        ]);
    }

    /**
     * Datos compartidos por preview() y pdf() — análogo a SaleController.
     *
     * @return array<string, mixed>
     */
    private function buildRepresentationData(Transfer $transfer): array
    {
        $transfer->loadMissing([
            'company',
            'exitMovement.warehouse',
            'exitMovement.productables.productProduct.template',
            'exitMovement.productables.uom',
            'exitMovement.productables.lot',
            'entryMovement.warehouse',
        ]);

        /** @var \App\Models\Movement|null $exit */
        $exit = $transfer->exitMovement;
        /** @var \App\Models\Movement|null $entry */
        $entry = $transfer->entryMovement;

        $hashRaw = data_get($transfer->gre_response, 'hash')
            ?? data_get($transfer->gre_response, 'raw.response.hash');
        $hash = is_string($hashRaw) ? $hashRaw : null;

        $qrDataUri = $this->buildGreQr($transfer, $hash);

        return [
            'transfer' => $transfer,
            'company' => $transfer->company,
            'exitWh' => $exit?->warehouse,
            'entryWh' => $entry?->warehouse,
            'items' => $exit ? $exit->productables : collect(),
            'docTypeName' => 'GUÍA DE REMISIÓN ELECTRÓNICA - REMITENTE',
            'hash' => $hash,
            'qrDataUri' => $qrDataUri,
        ];
    }

    /**
     * Genera el QR SUNAT específico de GRE — formato pipe-separated SIN
     * importes (la guía no factura). Estructura:
     *
     *   RUC|TipoDoc(09)|Serie|Correlativo|FechaEmision|
     *   TipoDocDestinatario|NumDocDestinatario|Hash
     *
     * En motivo 04, destinatario = mismo RUC del remitente (TipoDoc 6).
     */
    private function buildGreQr(Transfer $transfer, ?string $hash): ?string
    {
        /** @var \App\Models\Company|null $company */
        $company = $transfer->company;
        if (! $company || ! $transfer->serie || ! $transfer->correlative) {
            return null;
        }

        $issueDate = $transfer->date ?? $transfer->created_at;
        // En motivo 04 (mismo RUC) el destinatario es la propia company. Para
        // futura ampliación a otros motivos, podríamos resolver por partner.
        $destDocType = '6';
        $ruc = (string) ($company->ruc ?: '');
        $destDocNumber = $ruc;

        $fields = [
            $ruc,
            '09',
            (string) $transfer->serie,
            (string) $transfer->correlative,
            $issueDate?->format('Y-m-d') ?? '',
            $destDocType,
            $destDocNumber,
            (string) ($hash ?? ''),
        ];
        $payload = implode('|', $fields);

        try {
            $builder = new QrBuilder(
                writer: new PngWriter(),
                data: $payload,
                size: 180,
                margin: 4,
            );

            return $builder->build()->getDataUri();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Guard: la GRE-Remitente sólo se imprime/descarga desde la company que
     * despacha (origen). Si la company actual no es propietaria del warehouse
     * de salida, devolvemos 403 — esa guía la imprime el remitente real.
     */
    private function denyIfNotRemitente(Request $request, Transfer $transfer): ?JsonResponse
    {
        $companyIds = $request->get('_company_ids');
        if (! is_array($companyIds) || empty($companyIds)) {
            return null; // sin scope multi-company → no aplicar guard
        }

        $transfer->loadMissing(['exitMovement.warehouse']);
        /** @var \App\Models\Movement|null $exit */
        $exit = $transfer->exitMovement;
        /** @var \App\Models\Warehouse|null $exitWh */
        $exitWh = $exit?->warehouse;
        $exitCompanyId = $exitWh?->company_id;

        if ($exitCompanyId === null) {
            return $this->error('Transferencia sin almacén de salida — no se puede emitir GRE.', 422);
        }

        $allowed = [];
        foreach ($companyIds as $id) {
            if (is_numeric($id)) {
                $allowed[] = (int) $id;
            }
        }
        if (! in_array((int) $exitCompanyId, $allowed, true)) {
            return $this->error(
                'Esta guía se emite desde otra empresa del grupo. La impresión corresponde al remitente.',
                403,
            );
        }

        return null;
    }

    // ─── Helpers privados ──────────────────────────────────────────────────

    /**
     * Aplica un filtro por estado lógico derivado a un query de Transfer.
     * Estados aceptados: draft | pending_exit | in_transit | completed |
     * with_observation | cancelled. Se reconocen aliases legacy: sent →
     * in_transit, received → completed.
     */
    private function applyDerivedStatusFilter(\Illuminate\Database\Eloquent\Builder $query, string $status): void
    {
        $alias = match ($status) {
            'sent' => 'in_transit',
            'received' => 'completed',
            default => $status,
        };

        match ($alias) {
            'draft' => $query->whereHas('exitMovement', fn ($q) => $q->where('status', 'draft'))
                ->whereHas('entryMovement', fn ($q) => $q->where('status', 'draft')),

            'pending_exit' => $query->whereHas('exitMovement', fn ($q) => $q->where('status', 'submitted')),

            'in_transit' => $query
                ->whereHas('exitMovement', fn ($q) => $q->where('status', 'posted'))
                ->whereDoesntHave('entryMovement', fn ($q) => $q->whereIn('status', ['posted', 'cancelled'])),

            'completed' => $query
                ->whereHas('exitMovement', fn ($q) => $q->where('status', 'posted'))
                ->whereHas('entryMovement', fn ($q) => $q->where('status', 'posted')),

            'with_observation' => $query
                ->whereHas('exitMovement', fn ($q) => $q->where('status', 'posted'))
                ->whereHas('entryMovement', fn ($q) => $q->where('status', 'rejected')),

            'cancelled' => $query->where(function ($q) {
                $q->whereHas('exitMovement', fn ($qq) => $qq->where('status', 'cancelled'))
                    ->orWhereHas('entryMovement', fn ($qq) => $qq->where('status', 'cancelled'));
            }),

            default => null,
        };
    }

    private function countByDerivedStatus(\Illuminate\Database\Eloquent\Builder $query, string $status): int
    {
        $this->applyDerivedStatusFilter($query, $status);

        return $query->count();
    }
}
