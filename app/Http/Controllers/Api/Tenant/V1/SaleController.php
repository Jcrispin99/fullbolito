<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\SaleRefundRequest;
use App\Http\Requests\Api\Tenant\V1\SaleRequest;
use App\Http\Resources\SaleResource;
use App\Jobs\SendInvoiceToSunatJob;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\PosSession;
use App\Models\PosSessionPayment;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GreenterInvoiceService;
use App\Services\KardexService;
use App\Services\LoyaltyService;
use App\Services\SaleRefundService;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder as QrBuilder;
use Endroid\QrCode\Writer\PngWriter;
use Luecano\NumeroALetras\NumeroALetras;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SaleController extends ApiController
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const SALE_LOAD_RELATIONS = [
        'partner',
        'warehouse',
        'company',
        'journal',
        'user',
        'products.productProduct.template.uom',
        'products.productProduct.attributeValues',
        'products.tax',
        'products.uom',
        'originalSale.journal',
        'creditNotes.journal',
        'creditNotes.products',
        'loyaltyTransactions.program',
        'loyaltyTransactions.card',
    ];

    /**
     * Listar Ventas
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 25);
        $search = $request->input('search');
        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $from = $request->input('from');
        $to = $request->input('to');
        $fromPos = $request->boolean('from_pos');

        $relations = ['partner', 'warehouse', 'company', 'journal', 'user'];
        if ($fromPos) {
            $relations[] = 'posSession.posConfig';
        }

        $query = Sale::query()
            ->companyFiltered()
            ->with($relations)
            ->orderBy('created_at', 'desc');

        if ($fromPos) {
            $query->whereNotNull('pos_session_id');
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhereHas('partner', function ($partnerQuery) use ($search) {
                        $partnerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%");
                    });
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

        $draftTotal = (clone $query)->where('status', 'draft')->count();
        $postedTotal = (clone $query)->where('status', 'posted')->count();
        $cancelledTotal = (clone $query)->where('status', 'cancelled')->count();

        $paginator = $query->paginate((int) $perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Sales listing retrieved successfully',
            'data' => SaleResource::collection($paginator->items()),
            'links' => $paginator->linkCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'draft_total' => $draftTotal,
                'posted_total' => $postedTotal,
                'cancelled_total' => $cancelledTotal,
            ],
        ]);
    }

    /**
     * Opciones de Formulario
     */
    public function formOptions(): JsonResponse
    {
        $companyIds = request()->get('_company_ids');

        $customers = Partner::query()
            ->companyFiltered()
            ->customers()
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()->companyFiltered()->latest()->get();
        $companies = Company::query()
            ->where('is_active', true)
            ->when($companyIds, fn ($q) => $q->whereIn('id', $companyIds))
            ->get();
        $users = User::query()
            ->latest()
            ->get();
        $uoms = UnitOfMeasure::query()
            ->where('is_active', true)
            ->get();

        $taxes = [];
        if (class_exists(Tax::class)) {
            $taxes = Tax::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        }

        return $this->success([
            'customers' => \App\Http\Resources\CustomerResource::collection($customers),
            'warehouses' => \App\Http\Resources\WarehouseResource::collection($warehouses),
            'companies' => \App\Http\Resources\CompanyResource::collection($companies),
            'users' => \App\Http\Resources\UserResource::collection($users),
            'uoms' => \App\Http\Resources\UnitOfMeasureResource::collection($uoms),
            'taxes' => $taxes,
        ], 'Form options retrieved successfully');
    }

    /**
     * Ver Venta
     */
    public function show(Sale $sale): JsonResponse
    {
        $sale->load(self::SALE_LOAD_RELATIONS);

        $activities = Activity::forSubject($sale)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Record retrieved successfully',
            'data' => new SaleResource($sale),
            'meta' => [
                'activities' => $activities,
            ],
        ]);
    }

    /**
     * Crear Venta
     */
    public function store(SaleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $saleId = null;

        $companyId = $validated['company_id'] ?? $request->user()?->company_id;

        if (! $companyId) {
            return $this->error('No se pudo determinar la compañía.', 422);
        }

        $sellerId = $validated['seller_id'] ?? $request->user()?->id;
        if (! $sellerId) {
            return $this->error('No se pudo determinar el vendedor de la venta.', 422);
        }

        DB::transaction(function () use ($validated, &$saleId, $companyId, $sellerId) {
            $defaultJournal = null;

            if (!empty($validated['pos_session_id'])) {
                // Find Session > Config > Default Journal
                $posSession = PosSession::with('posConfig.journals')->find($validated['pos_session_id']);
                if ($posSession && $posSession->posConfig) {
                    // Try to retrieve the 'is_default' journal from pivot, or fallback to first one
                    $defaultJournal = $posSession->posConfig->journals()
                        ->wherePivot('is_default', true)
                        ->first() ?? $posSession->posConfig->journals->first();
                }
            }

            if (!$defaultJournal) {
                // Standard Sale Journal Lookup
                $defaultJournal = Journal::where('type', 'sale')
                    ->where('company_id', $companyId)
                    ->first();
            }

            if (! $defaultJournal) {
                throw ValidationException::withMessages([
                    'journal' => 'No se encontró un diario de ventas disponible (ni global, ni asignado a la Caja).',
                ]);
            }

            $serie = 'B001';
            $correlative = '0000001';

            if ($defaultJournal->sequence) {
                $serie = $defaultJournal->code;
                $correlative = str_pad((string) $defaultJournal->sequence->next_number, $defaultJournal->sequence->sequence_size, '0', STR_PAD_LEFT);

                // Consumir permanentemente el número (avanzar +1)
                $defaultJournal->sequence->increment('next_number', $defaultJournal->sequence->step);
            }

            $sale = Sale::create([
                'partner_id' => $validated['partner_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'pos_session_id' => $validated['pos_session_id'] ?? null,
                'journal_id' => $defaultJournal->id,
                'company_id' => $companyId,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'user_id' => $sellerId,
                'serie' => $serie,
                'correlative' => $correlative,
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['products'] as $productData) {
                $line = $this->prepareLineData($productData);
                $tax = isset($line['tax_id']) && class_exists(Tax::class)
                    ? Tax::find($line['tax_id'])
                    : null;

                $quantity = $line['quantity'];
                $price = $line['price'];
                $lineSubtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;

                $sale->products()->create([
                    'product_product_id' => $line['product_product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                    'uom_id' => $line['uom_id'],
                    'quantity_uom' => $line['quantity_uom'],
                    'price_uom' => $line['price_uom'],
                    'uom_factor' => $line['uom_factor'],
                    'lot_id' => $line['lot_id'] ?? null,
                ]);

                $subtotal += $lineSubtotal;
                $totalTax += $taxAmount;
            }

            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);
            $saleId = $sale->id;

            // Log Partial POS Payments if requested via POS
            if (!empty($validated['pos_session_id']) && !empty($validated['payments'])) {
                foreach ($validated['payments'] as $payment) {
                    PosSessionPayment::create([
                        'pos_session_id' => $validated['pos_session_id'],
                        'sale_id' => $sale->id,
                        'payment_method_id' => $payment['payment_method_id'],
                        'amount' => $payment['amount'],
                    ]);
                }

                // If POS sets the payment immediately
                $sale->update(['payment_status' => 'paid']);
            }
        });

        $sale = Sale::query()
            ->with(self::SALE_LOAD_RELATIONS)
            ->findOrFail($saleId);

        return $this->created(new SaleResource($sale));
    }

    /**
     * Actualizar Venta
     */
    public function update(SaleRequest $request, Sale $sale): JsonResponse
    {
        $validated = $request->validated();

        if ($sale->status !== 'draft') {
            $sale->update([
                'notes' => $validated['notes'] ?? $sale->notes,
            ]);

            return $this->success(new SaleResource($sale->fresh()->load(self::SALE_LOAD_RELATIONS)));
        }

        DB::transaction(function () use ($validated, $sale) {
            $sale->update([
                'partner_id' => $validated['partner_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'company_id' => $validated['company_id'] ?? $sale->company_id,
                'user_id' => $validated['seller_id'] ?? $sale->user_id,
                'notes' => $validated['notes'] ?? null,
            ]);

            $sale->products()->delete();

            $subtotal = 0;
            $totalTax = 0;

            foreach ($validated['products'] as $productData) {
                $line = $this->prepareLineData($productData);
                $tax = isset($line['tax_id']) && class_exists(Tax::class)
                    ? Tax::find($line['tax_id'])
                    : null;

                $quantity = $line['quantity'];
                $price = $line['price'];
                $lineSubtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;

                $sale->products()->create([
                    'product_product_id' => $line['product_product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                    'uom_id' => $line['uom_id'],
                    'quantity_uom' => $line['quantity_uom'],
                    'price_uom' => $line['price_uom'],
                    'uom_factor' => $line['uom_factor'],
                    'lot_id' => $line['lot_id'] ?? null,
                ]);

                $subtotal += $lineSubtotal;
                $totalTax += $taxAmount;
            }

            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $subtotal + $totalTax,
            ]);
        });

        return $this->success(new SaleResource($sale->fresh()->load(self::SALE_LOAD_RELATIONS)));
    }

    /**
     * Eliminar Venta
     */
    public function destroy(Sale $sale): JsonResponse
    {
        if ($sale->status !== 'draft') {
            return $this->error('Solo se pueden eliminar ventas en estado borrador.', 422);
        }

        DB::transaction(function () use ($sale) {
            $sale->products()->delete();
            $sale->delete();
        });

        return $this->noContent();
    }

    /**
     * Publicar Venta
     */
    public function post(Sale $sale, KardexService $kardexService, LoyaltyService $loyaltyService): JsonResponse
    {
        if ($sale->status !== 'draft') {
            return $this->error('Solo se pueden publicar ventas en estado borrador.', 422);
        }

        DB::transaction(function () use ($sale, $kardexService, $loyaltyService) {
            $sale->update([
                'status' => 'posted',
            ]);

            activity()
                ->performedOn($sale)
                ->causedBy(request()->user())
                ->log('Venta Publicada y Aprobada Internamente');

            $sale->load('products.productProduct.template');

            // Kardex — registrar salida de inventario
            foreach ($sale->products as $productable) {
                $tracksInventory = $productable->productProduct?->template?->tracks_inventory ?? true;
                if (! $tracksInventory) {
                    continue;
                }

                // Si el productable tiene un lote pre-asignado (selección manual del frontend),
                // pasarlo como allocation explícita; si no, KardexService aplicará FEFO.
                $allocations = $productable->lot_id !== null
                    ? [['lot_id' => (int) $productable->lot_id, 'quantity' => (float) $productable->quantity]]
                    : null;

                if ($allocations !== null) {
                    $kardexService->assertLotSellable((int) $productable->lot_id);
                }

                $used = $kardexService->registerExit(
                    $sale,
                    [
                        'id' => $productable->product_product_id,
                        'quantity' => $productable->quantity,
                    ],
                    (int) $sale->warehouse_id,
                    "Venta {$sale->serie}-{$sale->correlative}",
                    $allocations,
                );

                // Si FEFO consumió un único lote, persistirlo en la línea para trazabilidad UI.
                if ($productable->lot_id === null && count($used) === 1) {
                    $productable->lot_id = $used[0]['lot_id'];
                    $productable->save();
                }
            }

            // Lealtad — procesar programas activos y otorgar puntos
            if ($sale->partner_id) {
                $loyaltyService->processSale($sale, $sale->partner);
            }
        });

        // Despachar envío a SUNAT en background tras commit. El job decide
        // si es fiscal y aplica la lógica idempotente del servicio.
        SendInvoiceToSunatJob::dispatch($sale->fresh());

        return $this->success(new SaleResource($sale->fresh()->load(self::SALE_LOAD_RELATIONS)));
    }

    /**
     * Reenviar venta a SUNAT (manual, idempotente).
     *
     * Útil para reintentar ventas con `sunat_status` en error, o para forzar
     * el envío de ventas previas que no llegaron a procesarse. Si la venta
     * ya está aceptada, el servicio devuelve sin hacer nada.
     */
    public function sendToSunat(Sale $sale, GreenterInvoiceService $greenter): JsonResponse
    {
        if ($sale->status !== 'posted') {
            return $this->error('Solo se pueden enviar a SUNAT ventas publicadas.', 422);
        }

        // Ejecución síncrona: el endpoint manual ya implica que el usuario
        // espera el resultado en pantalla.
        $greenter->sendInvoiceFromSale($sale);

        return $this->success(new SaleResource($sale->fresh()->load(self::SALE_LOAD_RELATIONS)));
    }

    /**
     * Descargar XML firmado del comprobante.
     *
     * Conservación obligatoria SUNAT (Res. 097-2012/SUNAT y act.).
     */
    public function downloadXml(Sale $sale): StreamedResponse|JsonResponse
    {
        if (! $sale->signed_xml_path) {
            return $this->error('Esta venta no tiene XML firmado almacenado.', 404);
        }

        if (! Storage::disk('local')->exists($sale->signed_xml_path)) {
            return $this->error('El archivo XML ya no existe en el almacenamiento.', 404);
        }

        $filename = basename($sale->signed_xml_path);

        return Storage::disk('local')->download($sale->signed_xml_path, $filename, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Representación impresa del comprobante (HTML).
     *
     * Renderiza la blade `tenant.sunat.representation` con los datos del sale
     * para que el frontend la incruste vía `srcdoc` de un iframe — ofrece al
     * usuario una vista "humana" del documento sin exponer XML técnico.
     */
    public function preview(Sale $sale): \Illuminate\Http\Response
    {
        $html = view('tenant.sunat.representation', $this->buildRepresentationData($sale))->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Descarga el comprobante como PDF (A4) con el mismo blade que el visor.
     * El blade tiene `@media print` que asegura A4 sin sombras ni márgenes
     * extra. mPDF ignora el bloque <script> de auto-fit (no necesario en PDF).
     */
    public function pdf(Sale $sale, \Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $data = $this->buildRepresentationData($sale);
        $html = view('tenant.sunat.representation', $data)->render();

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

        $filename = trim(($sale->serie ?? '').'-'.($sale->correlative ?? '')) ?: ('sale-'.$sale->id);
        $pdfBinary = (string) $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'.pdf"',
            'Content-Length' => (string) strlen($pdfBinary),
        ]);
    }

    /**
     * Datos compartidos entre la vista HTML (visor) y la generación PDF.
     *
     * @return array<string, mixed>
     */
    private function buildRepresentationData(Sale $sale): array
    {
        $sale->loadMissing([
            'company',
            'partner',
            'journal',
            'products.productProduct.template',
            'products.uom',
            'products.tax',
            'products.lot',
            'originalSale.journal',
        ]);

        $journal = $sale->journal;
        $docType = (string) ($journal?->document_type_code ?? '');
        $docTypeName = match ($docType) {
            '01' => 'FACTURA ELECTRÓNICA',
            '03' => 'BOLETA DE VENTA ELECTRÓNICA',
            '07' => 'NOTA DE CRÉDITO ELECTRÓNICA',
            '08' => 'NOTA DE DÉBITO ELECTRÓNICA',
            default => mb_strtoupper((string) ($journal?->name ?? 'COMPROBANTE')),
        };

        $hashRaw = data_get($sale->sunat_response, 'hash')
            ?? data_get($sale->sunat_response, 'raw.response.hash');
        $hash = is_string($hashRaw) ? $hashRaw : null;

        $qrDataUri = $this->buildSunatQr($sale, $docType, $hash);

        return [
            'sale' => $sale,
            'company' => $sale->company,
            'partner' => $sale->partner,
            'journal' => $journal,
            'items' => $sale->products,
            'docTypeName' => $docTypeName,
            'hash' => $hash,
            'qrDataUri' => $qrDataUri,
            'amountWords' => $this->amountInWords((float) $sale->total),
        ];
    }

    /**
     * Genera el QR SUNAT como data URI (PNG base64) — formato pipe-separated
     * exigido por R 097-2012/SUNAT para representación impresa:
     *
     *   RUC|TipoDoc|Serie|Correlativo|MtoIGV|MtoTotal|FechaEmision|
     *   TipoDocReceptor|NumDocReceptor|Hash
     *
     * Si el documento aún no está enviado/aceptado por SUNAT (sin hash),
     * devuelve null para que el blade muestre placeholder.
     */
    private function buildSunatQr(Sale $sale, string $docType, ?string $hash): ?string
    {
        /** @var \App\Models\Company|null $company */
        $company = $sale->company;
        /** @var \App\Models\Partner|null $partner */
        $partner = $sale->partner;

        if (! $company || ! $sale->serie || ! $sale->correlative) {
            return null;
        }

        $issueDate = $sale->date ?? $sale->created_at;
        $fields = [
            (string) ($company->ruc ?: ''),
            $docType,
            (string) $sale->serie,
            (string) $sale->correlative,
            number_format((float) $sale->tax_amount, 2, '.', ''),
            number_format((float) $sale->total, 2, '.', ''),
            $issueDate?->format('Y-m-d') ?? '',
            $partner !== null ? (string) ($partner->document_type ?: '') : '',
            $partner !== null ? (string) ($partner->document_number ?: '') : '',
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
     * Convierte un importe a su representación en letras formato SUNAT
     * (ej. "MIL DOSCIENTOS TREINTA Y CUATRO CON 56/100 SOLES") usando
     * luecano/numero-a-letras::toInvoice — ya cumple el formato regulatorio.
     */
    private function amountInWords(float $amount, string $currency = 'SOLES'): string
    {
        $formatter = new NumeroALetras();
        $formatter->apocope = true;

        return mb_strtoupper($formatter->toInvoice($amount, 2, $currency));
    }

    /**
     * Descargar CDR (Constancia de Recepción) firmado por SUNAT.
     */
    public function downloadCdr(Sale $sale): StreamedResponse|JsonResponse
    {
        if (! $sale->cdr_zip_path) {
            return $this->error('Esta venta no tiene CDR almacenado.', 404);
        }

        if (! Storage::disk('local')->exists($sale->cdr_zip_path)) {
            return $this->error('El archivo CDR ya no existe en el almacenamiento.', 404);
        }

        $filename = basename($sale->cdr_zip_path);

        return Storage::disk('local')->download($sale->cdr_zip_path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Cancelar Venta
     */
    public function cancel(Sale $sale, KardexService $kardexService, LoyaltyService $loyaltyService): JsonResponse
    {
        if ($sale->status !== 'posted') {
            return $this->error('Solo se pueden cancelar ventas previamente publicadas.', 422);
        }

        DB::transaction(function () use ($sale, $kardexService, $loyaltyService) {
            $sale->load('products.productProduct.template');

            // Kardex — devolver inventario al(los) lote(s) original(es).
            foreach ($sale->products as $productable) {
                $tracksInventory = $productable->productProduct?->template?->tracks_inventory ?? true;
                if (! $tracksInventory) {
                    continue;
                }

                $productProductId = (int) $productable->product_product_id;
                $warehouseId = (int) $sale->warehouse_id;

                // Recuperar las salidas originales por lote para esta línea
                $allocations = $kardexService->getExitAllocationsForModel($sale, $productProductId, $warehouseId);
                // Remanente sin lote (POS con allowNegativeStock pudo escribir una parte sin lote)
                $noLotQty = $kardexService->getNoLotExitQuantityForModel($sale, $productProductId, $warehouseId);

                if ($allocations === [] && $noLotQty <= 0) {
                    // Producto sin lote → devolución clásica al promedio ponderado
                    $lastRecord = $kardexService->getLastRecord($productProductId, $warehouseId);
                    $currentCost = $lastRecord['cost'] ?? 0;

                    $kardexService->registerEntry(
                        $sale,
                        [
                            'id' => $productProductId,
                            'quantity' => $productable->quantity,
                            'price' => $currentCost,
                            'subtotal' => $productable->quantity * $currentCost,
                        ],
                        $warehouseId,
                        "Devolución por Venta Cancelada {$sale->serie}-{$sale->correlative}"
                    );

                    continue;
                }

                // Producto con lote → devolver cada porción a su lote original.
                foreach ($allocations as $alloc) {
                    $lotId = (int) $alloc['lot_id'];
                    $qty = (float) $alloc['quantity'];

                    $lot = \App\Models\Lot::find($lotId);
                    $unitCost = (float) ($lot?->initial_cost ?? 0);

                    $kardexService->registerEntry(
                        $sale,
                        [
                            'id' => $productProductId,
                            'quantity' => $qty,
                            'price' => $unitCost,
                            'subtotal' => $qty * $unitCost,
                        ],
                        $warehouseId,
                        "Devolución por Venta Cancelada {$sale->serie}-{$sale->correlative}",
                        $lotId,
                    );
                }

                // Devolver el remanente que se registró sin lote (ventas POS con stock negativo)
                if ($noLotQty > 0) {
                    $lastRecord = $kardexService->getLastRecord($productProductId, $warehouseId);
                    $currentCost = $lastRecord['cost'] ?? 0;

                    $kardexService->registerEntry(
                        $sale,
                        [
                            'id' => $productProductId,
                            'quantity' => $noLotQty,
                            'price' => $currentCost,
                            'subtotal' => $noLotQty * $currentCost,
                        ],
                        $warehouseId,
                        "Devolución (sin lote) por Venta Cancelada {$sale->serie}-{$sale->correlative}"
                    );
                }
            }

            // Lealtad — revertir puntos ganados
            if ($sale->partner_id) {
                $loyaltyService->reverseSalePoints($sale, $sale->partner);
            }

            $sale->update(['status' => 'cancelled']);

            activity()
                ->performedOn($sale)
                ->causedBy(request()->user())
                ->log('Venta Cancelada (Inventario Retornado, Puntos Revertidos)');
        });

        return $this->success(new SaleResource($sale->fresh()->load(self::SALE_LOAD_RELATIONS)));
    }

    /**
     * Pagar Venta
     */
    public function pay(Sale $sale): JsonResponse
    {
        if ($sale->status !== 'posted') {
            return $this->error('Solo se pueden pagar ventas publicadas.', 422);
        }

        if ($sale->payment_status === 'paid') {
            return $this->error('La venta ya está pagada.', 422);
        }

        DB::transaction(function () use ($sale) {
            $sale->update(['payment_status' => 'paid']);

            activity()
                ->performedOn($sale)
                ->causedBy(request()->user())
                ->log('Venta Pagada');
        });

        return $this->success(new SaleResource($sale->fresh()->load(self::SALE_LOAD_RELATIONS)));
    }

    /**
     * Emitir Nota de Crédito (devolución total o parcial)
     *
     * Postea inmediatamente la NC: actualiza kardex (devuelve a lotes originales)
     * y revierte lealtad. Las cantidades enviadas no pueden exceder lo vendido
     * menos lo ya devuelto en NCs previas.
     */
    public function refund(SaleRefundRequest $request, Sale $sale, SaleRefundService $service): JsonResponse
    {
        $note = $service->createFromOriginal(
            $sale,
            [
                'lines' => $request->validated('lines'),
                'notes' => $request->validated('notes'),
            ],
            $request->user(),
        );

        return $this->created(new SaleResource($note->load(self::SALE_LOAD_RELATIONS)));
    }

    /**
     * Parse and map Line amounts handling UoM
     *
     * @param  array<string, mixed>  $productData
     * @return array<string, mixed>
     */
    private function prepareLineData(array $productData): array
    {
        $uomId = isset($productData['uom_id']) ? (int) $productData['uom_id'] : null;

        if ($uomId) {
            $uom = UnitOfMeasure::findOrFail($uomId);
            $factor = $uom->factorToBase();

            if (! isset($productData['quantity_uom']) || ! isset($productData['price_uom'])) {
                throw ValidationException::withMessages([
                    'products' => 'quantity_uom y price_uom son requeridos cuando se selecciona una unidad de medida.',
                ]);
            }

            $quantityUom = (float) $productData['quantity_uom'];
            $priceUom = (float) $productData['price_uom'];

            // Convert to base
            $quantityBase = $quantityUom * $factor;
            $priceBase = $priceUom / $factor;

            return [
                'product_product_id' => $productData['product_product_id'],
                'quantity' => $quantityBase,
                'price' => $priceBase,
                'tax_id' => $productData['tax_id'] ?? null,
                'lot_id' => isset($productData['lot_id']) ? (int) $productData['lot_id'] : null,
                'uom_id' => $uomId,
                'quantity_uom' => $quantityUom,
                'price_uom' => $priceUom,
                'uom_factor' => $factor,
            ];
        }

        // Legacy/Default mode
        if (! isset($productData['quantity']) || ! isset($productData['price'])) {
            throw ValidationException::withMessages([
                'products' => 'quantity y price son requeridos cuando no se selecciona una unidad de medida.',
            ]);
        }

        $quantity = (float) $productData['quantity'];
        $price = (float) $productData['price'];

        return [
            'product_product_id' => $productData['product_product_id'],
            'quantity' => $quantity,
            'price' => $price,
            'tax_id' => $productData['tax_id'] ?? null,
            'lot_id' => isset($productData['lot_id']) ? (int) $productData['lot_id'] : null,
            'uom_id' => null,
            'quantity_uom' => null,
            'price_uom' => null,
            'uom_factor' => 1,
        ];
    }
}
