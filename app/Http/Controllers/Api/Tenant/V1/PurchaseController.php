<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\PurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Lot;
use App\Models\Partner;
use App\Models\ProductProduct;
use App\Models\Productable;
use App\Models\Purchase;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\SequenceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

final class PurchaseController extends ApiController
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const PURCHASE_LOAD_RELATIONS = [
        'partner',
        'warehouse',
        'company',
        'productables.productProduct.template.uom',
        'productables.productProduct.attributeValues',
        'productables.tax',
        'productables.uom',
        'productables.lot',
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 25);
        $search = $request->input('search');
        $status = $request->input('status');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = Purchase::query()
            ->companyFiltered()
            ->with(['partner', 'warehouse', 'company'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhereHas('partner', function ($partnerQuery) use ($search) {
                        $partnerQuery->where('business_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
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

        $paginator = $query->paginate((int) $perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Purchases listing retrieved successfully',
            'data' => PurchaseResource::collection($paginator->items()),
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
            ],
        ]);
    }

    public function formOptions(): JsonResponse
    {
        $companyIds = request()->get('_company_ids');

        $suppliers = Partner::query()
            ->companyFiltered()
            ->suppliers()
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

        // Warning: This falls back gracefully if Tax model is not fully implemented/migrated yet
        $taxes = [];
        if (class_exists(Tax::class)) {
            $taxes = Tax::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        }

        return $this->success([
            'suppliers' => \App\Http\Resources\SupplierResource::collection($suppliers),
            'warehouses' => \App\Http\Resources\WarehouseResource::collection($warehouses),
            'companies' => \App\Http\Resources\CompanyResource::collection($companies),
            'users' => \App\Http\Resources\UserResource::collection($users),
            'uoms' => \App\Http\Resources\UnitOfMeasureResource::collection($uoms),
            'taxes' => $taxes,
        ], 'Form options retrieved successfully');
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load(self::PURCHASE_LOAD_RELATIONS);

        $activities = Activity::forSubject($purchase)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Record retrieved successfully',
            'data' => new PurchaseResource($purchase),
            'meta' => [
                'activities' => $activities,
            ],
        ]);
    }

    public function store(PurchaseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $purchaseId = null;

        $companyId = $validated['company_id'];

        DB::transaction(function () use ($validated, &$purchaseId, $companyId) {
            $defaultJournal = Journal::where('type', 'purchase')
                ->where('company_id', $companyId)
                ->first();

            if (! $defaultJournal) {
                // Warning logic, but since it's an API, best approach is throwing standard ValidationException or abort
                throw ValidationException::withMessages([
                    'journal' => 'No se encontró un diario de compras para esta compañía. Por favor crea uno primero.',
                ]);
            }

            // Using dummy sequence numbers if SequenceService isn't fully set up yet
            $serie = 'F001';
            $correlative = '0000001';

            if (class_exists(SequenceService::class)) {
                $numberParts = SequenceService::getNextParts($defaultJournal->id);
                $serie = $numberParts['serie'];
                $correlative = $numberParts['correlative'];
            }

            $purchase = Purchase::create([
                'partner_id' => $validated['partner_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'journal_id' => $defaultJournal->id,
                'company_id' => $companyId,
                'buyer_id' => $validated['buyer_id'] ?? null,
                'vendor_bill_number' => $validated['vendor_bill_number'] ?? null,
                'vendor_bill_date' => $validated['vendor_bill_date'] ?? null,
                'observation' => $validated['observation'] ?? null,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'serie' => $serie,
                'correlative' => $correlative,
                'total' => 0,
            ]);

            $total = 0;
            foreach ($validated['products'] as $productData) {
                $line = $this->prepareLineData($productData);
                $tax = isset($line['tax_id']) && class_exists(Tax::class)
                    ? Tax::find($line['tax_id'])
                    : null;

                $quantity = $line['quantity'];
                $price = $line['price'];
                $subtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $subtotal * ($taxRate / 100);
                $lineTotal = $subtotal + $taxAmount;

                $purchase->productables()->create([
                    'product_product_id' => $line['product_product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                    'uom_id' => $line['uom_id'],
                    'quantity_uom' => $line['quantity_uom'],
                    'price_uom' => $line['price_uom'],
                    'uom_factor' => $line['uom_factor'],
                    'lot_number_input' => $line['lot_number_input'],
                    'lot_manufactured_at' => $line['lot_manufactured_at'],
                    'lot_expires_at' => $line['lot_expires_at'],
                ]);

                $total += $lineTotal;
            }

            $purchase->update(['total' => $total]);
            $purchaseId = $purchase->id;
        });

        $purchase = Purchase::query()
            ->with(self::PURCHASE_LOAD_RELATIONS)
            ->findOrFail($purchaseId);

        return $this->created(new PurchaseResource($purchase));
    }

    public function update(PurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        if ($purchase->status !== 'draft') {
            return $this->error('Solo se pueden editar compras en estado borrador.', 422);
        }

        // Guard: if any productable already has lots assigned, the product lines
        // cannot be edited from the main form — lots must be managed from the
        // dedicated Lots page first.
        $hasLotAllocations = $purchase->productables()
            ->whereNotNull('lot_id')
            ->exists();

        if ($hasLotAllocations) {
            return $this->error(
                'Esta compra ya tiene lotes asignados. Elimina los lotes desde la página de lotes antes de editar los productos.',
                422,
            );
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $purchase) {
            $purchase->update([
                'partner_id' => $validated['partner_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'vendor_bill_number' => $validated['vendor_bill_number'] ?? null,
                'vendor_bill_date' => $validated['vendor_bill_date'] ?? null,
                'observation' => $validated['observation'] ?? null,
            ]);

            $purchase->productables()->delete();

            $total = 0;
            foreach ($validated['products'] as $productData) {
                $line = $this->prepareLineData($productData);
                $tax = isset($line['tax_id']) && class_exists(Tax::class)
                    ? Tax::find($line['tax_id'])
                    : null;

                $quantity = $line['quantity'];
                $price = $line['price'];
                $subtotal = $quantity * $price;

                $taxRate = $tax ? $tax->rate_percent : 0;
                $taxAmount = $subtotal * ($taxRate / 100);
                $lineTotal = $subtotal + $taxAmount;

                $purchase->productables()->create([
                    'product_product_id' => $line['product_product_id'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                    'uom_id' => $line['uom_id'],
                    'quantity_uom' => $line['quantity_uom'],
                    'price_uom' => $line['price_uom'],
                    'uom_factor' => $line['uom_factor'],
                    'lot_number_input' => $line['lot_number_input'],
                    'lot_manufactured_at' => $line['lot_manufactured_at'],
                    'lot_expires_at' => $line['lot_expires_at'],
                ]);

                $total += $lineTotal;
            }

            $purchase->update(['total' => $total]);
        });

        return $this->success(new PurchaseResource($purchase->fresh()->load(self::PURCHASE_LOAD_RELATIONS)));
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        if ($purchase->status !== 'draft') {
            return $this->error('Solo se pueden eliminar compras en estado borrador.', 422);
        }

        DB::transaction(function () use ($purchase) {
            $purchase->productables()->delete();
            $purchase->delete();
        });

        return $this->noContent();
    }

    public function post(Purchase $purchase, KardexService $kardexService): JsonResponse
    {
        if ($purchase->status !== 'draft') {
            return $this->error('Solo se pueden publicar compras en estado borrador.', 422);
        }

        // Validate: every tracked line must have allocations covering its quantity_uom
        $purchase->load('productables.productProduct.template');
        $grouped = [];
        foreach ($purchase->productables as $pable) {
            $tracked = (bool) ($pable->productProduct?->template?->tracked_by_lot ?? false);
            if (! $tracked) {
                continue;
            }
            $pid = (int) $pable->product_product_id;
            if (! isset($grouped[$pid])) {
                $grouped[$pid] = [
                    'expected' => 0.0,
                    'allocated' => 0.0,
                    'name' => $pable->productProduct?->template?->name ?? "Producto #{$pid}",
                ];
            }
            $qty = (float) ($pable->quantity_uom ?? $pable->quantity ?? 0);
            $grouped[$pid]['expected'] += $qty;
            $hasLot = $pable->lot_id !== null
                || ($pable->lot_number_input !== null && trim((string) $pable->lot_number_input) !== '');
            if ($hasLot) {
                $grouped[$pid]['allocated'] += $qty;
            }
        }

        $incomplete = [];
        foreach ($grouped as $info) {
            if (abs($info['expected'] - $info['allocated']) > 0.001) {
                $incomplete[] = $info['name'];
            }
        }
        if (! empty($incomplete)) {
            return $this->error(
                'Los siguientes productos requieren asignación completa de lotes antes de publicar: '
                .implode(', ', $incomplete),
                422,
            );
        }

        DB::transaction(function () use ($purchase, $kardexService) {
            $purchase->update([
                'status' => 'posted',
            ]);

            activity()
                ->performedOn($purchase)
                ->causedBy(request()->user())
                ->log('Compra Publicada');

            $purchase->load('productables.productProduct.product');

            foreach ($purchase->productables as $productable) {
                $lotId = $this->resolveLotForPurchaseLine($purchase, $productable);

                $kardexService->registerEntry(
                    $purchase,
                    [
                        'id' => $productable->product_product_id,
                        'quantity' => $productable->quantity,
                        'price' => $productable->price,
                        'subtotal' => $productable->subtotal,
                    ],
                    (int) $purchase->warehouse_id,
                    "Compra {$purchase->serie}-{$purchase->correlative}",
                    $lotId,
                );
            }
        });

        return $this->success(new PurchaseResource($purchase->fresh()->load(self::PURCHASE_LOAD_RELATIONS)));
    }

    public function cancel(Purchase $purchase, KardexService $kardexService): JsonResponse
    {
        if ($purchase->status !== 'posted') {
            return $this->error('Solo se pueden cancelar compras publicadas.', 422);
        }

        DB::transaction(function () use ($purchase, $kardexService) {
            $purchase->load('productables');

            foreach ($purchase->productables as $productable) {
                $allocations = $productable->lot_id !== null
                    ? [['lot_id' => (int) $productable->lot_id, 'quantity' => (float) $productable->quantity]]
                    : null;

                $kardexService->registerExit(
                    $purchase,
                    [
                        'id' => $productable->product_product_id,
                        'quantity' => $productable->quantity,
                    ],
                    (int) $purchase->warehouse_id,
                    "Cancelación de Compra {$purchase->serie}-{$purchase->correlative}",
                    $allocations,
                );
            }

            $purchase->update(['status' => 'cancelled']);

            activity()
                ->performedOn($purchase)
                ->causedBy(request()->user())
                ->log('Compra Cancelada');
        });

        return $this->success(new PurchaseResource($purchase->fresh()->load(self::PURCHASE_LOAD_RELATIONS)));
    }

    /**
     * Crea u obtiene el lote correspondiente a una línea de compra y persiste
     * el lot_id en el productable. Devuelve null si el producto no lleva lote.
     */
    private function resolveLotForPurchaseLine(Purchase $purchase, Productable $productable): ?int
    {
        /** @var ProductProduct $product */
        $product = $productable->productProduct;

        if (! $product || ! $product->isTrackedByLot()) {
            return null;
        }

        // Si ya tiene lot_id (re-post tras cancel, o asignado previamente), reusar.
        if ($productable->lot_id !== null) {
            return (int) $productable->lot_id;
        }

        $lotNumber = $productable->lot_number_input;

        if ($lotNumber === null || trim($lotNumber) === '') {
            $lotNumber = $this->generateLotNumber($purchase->company_id);
        }

        $qty = (float) ($productable->quantity ?? 0);
        $subtotal = (float) ($productable->subtotal ?? 0);
        $price = (float) ($productable->price ?? 0);
        $initialCost = $qty > 0 ? $subtotal / $qty : $price;

        $lot = Lot::firstOrCreate(
            [
                'product_product_id' => $product->id,
                'lot_number' => $lotNumber,
            ],
            [
                'company_id' => $purchase->company_id,
                'manufactured_at' => $productable->lot_manufactured_at,
                'expires_at' => $productable->lot_expires_at,
                'supplier_id' => $purchase->partner_id,
                'purchase_id' => $purchase->id,
                'initial_quantity' => $qty,
                'initial_cost' => $initialCost,
                'status' => 'active',
            ]
        );

        $productable->update(['lot_id' => $lot->id]);

        return (int) $lot->id;
    }

    private function generateLotNumber(int $companyId): string
    {
        $journal = Journal::where('type', 'lot')
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->first();

        if (! $journal) {
            throw ValidationException::withMessages([
                'lots' => 'No se encontró el diario "LOT" para autogenerar el número de lote. Ejecuta el JournalSeeder.',
            ]);
        }

        $parts = SequenceService::getNextParts($journal->id);

        return "{$parts['serie']}-{$parts['correlative']}";
    }

    public function pay(Purchase $purchase): JsonResponse
    {
        if ($purchase->status !== 'posted') {
            return $this->error('Solo se pueden pagar compras publicadas.', 422);
        }

        if ($purchase->payment_status === 'paid') {
            return $this->error('La compra ya está pagada.', 422);
        }

        DB::transaction(function () use ($purchase) {
            $purchase->update(['payment_status' => 'paid']);

            activity()
                ->performedOn($purchase)
                ->causedBy(request()->user())
                ->log('Compra Pagada');
        });

        return $this->success(new PurchaseResource($purchase->fresh()->load(self::PURCHASE_LOAD_RELATIONS)));
    }

    public function draft(Purchase $purchase): JsonResponse
    {
        if ($purchase->status !== 'cancelled') {
            return $this->error('Solo se pueden restaurar a borrador compras canceladas.', 422);
        }

        $purchase->update(['status' => 'draft']);

        activity()
            ->performedOn($purchase)
            ->causedBy(request()->user())
            ->log('Compra Restaurada a Borrador');

        return $this->success(new PurchaseResource($purchase->fresh()->load(self::PURCHASE_LOAD_RELATIONS)));
    }

    /**
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
                'uom_id' => $uomId,
                'quantity_uom' => $quantityUom,
                'price_uom' => $priceUom,
                'uom_factor' => $factor,
                'lot_number_input' => $productData['lot_number'] ?? null,
                'lot_manufactured_at' => $productData['manufactured_at'] ?? null,
                'lot_expires_at' => $productData['expires_at'] ?? null,
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
            'uom_id' => null,
            'quantity_uom' => null,
            'price_uom' => null,
            'uom_factor' => 1,
            'lot_number_input' => $productData['lot_number'] ?? null,
            'lot_manufactured_at' => $productData['manufactured_at'] ?? null,
            'lot_expires_at' => $productData['expires_at'] ?? null,
        ];
    }

    /**
     * GET: Return purchase lines grouped by product for the Lots page.
     * Each group reports expected quantity (from lines) and allocations
     * (individual lot rows in productables).
     */
    public function lots(Purchase $purchase): JsonResponse
    {
        $purchase->load(self::PURCHASE_LOAD_RELATIONS);

        $groups = [];
        foreach ($purchase->productables as $pable) {
            $tracked = (bool) ($pable->productProduct?->template?->tracked_by_lot ?? false);
            if (! $tracked) {
                continue;
            }

            $pid = (int) $pable->product_product_id;
            $taxId = $pable->tax_id;
            $uomId = $pable->uom_id;
            $priceUom = $pable->price_uom !== null ? (float) $pable->price_uom : (float) $pable->price;
            $key = "{$pid}|".($taxId ?? '').'|'.($uomId ?? '').'|'.number_format($priceUom, 6, '.', '');

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'product_product_id' => $pid,
                    'product_name' => $pable->productProduct?->template?->name ?? "Producto #{$pid}",
                    'sku' => $pable->productProduct?->sku,
                    'tax_id' => $taxId,
                    'uom_id' => $uomId,
                    'uom_symbol' => $pable->uom?->symbol ?? $pable->uom?->name,
                    'price_uom' => $priceUom,
                    'price' => (float) $pable->price,
                    'uom_factor' => (float) $pable->uom_factor,
                    'expected_quantity' => 0.0,
                    'allocations' => [],
                ];
            }

            $qty = (float) ($pable->quantity_uom ?? $pable->quantity);
            $groups[$key]['expected_quantity'] += $qty;

            $groups[$key]['allocations'][] = [
                'productable_id' => $pable->id,
                'lot_id' => $pable->lot_id,
                'lot_number' => $pable->lot?->lot_number ?? $pable->lot_number_input,
                'manufactured_at' => $pable->lot?->manufactured_at?->format('Y-m-d')
                    ?? $pable->lot_manufactured_at?->format('Y-m-d'),
                'expires_at' => $pable->lot?->expires_at?->format('Y-m-d')
                    ?? $pable->lot_expires_at?->format('Y-m-d'),
                'quantity' => $qty,
                'is_official' => $pable->lot_id !== null,
            ];
        }

        return $this->success([
            'id' => $purchase->id,
            'serie' => $purchase->serie,
            'correlative' => $purchase->correlative,
            'sequence_code' => "{$purchase->serie}-{$purchase->correlative}",
            'status' => $purchase->status,
            'partner' => new \App\Http\Resources\SupplierResource($purchase->partner),
            'warehouse' => new \App\Http\Resources\WarehouseResource($purchase->warehouse),
            'groups' => array_values($groups),
        ], 'Lot allocations retrieved successfully');
    }

    /**
     * PUT: Replace lot allocations for each tracked product in a draft purchase.
     * Preserves per-line pricing/tax/uom from the first productable of that group.
     */
    public function updateLots(Request $request, Purchase $purchase): JsonResponse
    {
        if ($purchase->status !== 'draft') {
            return $this->error('Solo se pueden editar lotes en compras borrador.', 422);
        }

        $data = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.product_product_id' => ['required', 'exists:product_products,id'],
            'groups.*.allocations' => ['required', 'array', 'min:1'],
            'groups.*.allocations.*.lot_number' => ['nullable', 'string', 'max:50'],
            'groups.*.allocations.*.manufactured_at' => ['nullable', 'date'],
            'groups.*.allocations.*.expires_at' => [
                'nullable',
                'date',
                'after_or_equal:groups.*.allocations.*.manufactured_at',
            ],
            'groups.*.allocations.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        DB::transaction(function () use ($data, $purchase) {
            $purchase->load('productables');

            // Index productables by product_product_id to reuse their line settings
            $byProduct = [];
            foreach ($purchase->productables as $pable) {
                $pid = (int) $pable->product_product_id;
                $byProduct[$pid][] = $pable;
            }

            $taxCache = [];

            foreach ($data['groups'] as $group) {
                $pid = (int) $group['product_product_id'];
                $existing = $byProduct[$pid] ?? [];
                if (empty($existing)) {
                    continue;
                }

                // Use first productable of this product as the "template" for line data
                $template = $existing[0];

                // Delete all existing productables for this product
                foreach ($existing as $pable) {
                    $pable->delete();
                }

                $taxId = $template->tax_id;
                if ($taxId !== null && ! array_key_exists($taxId, $taxCache) && class_exists(Tax::class)) {
                    $taxCache[$taxId] = Tax::find($taxId);
                }
                $tax = $taxId !== null ? ($taxCache[$taxId] ?? null) : null;
                $taxRate = $tax ? (float) $tax->rate_percent : 0.0;

                $priceUom = (float) ($template->price_uom ?? $template->price);
                $uomFactor = (float) ($template->uom_factor ?: 1);
                $basePrice = $uomFactor > 0 ? $priceUom / $uomFactor : $priceUom;

                foreach ($group['allocations'] as $alloc) {
                    $qtyUom = (float) $alloc['quantity'];
                    $qtyBase = $qtyUom * $uomFactor;
                    $subtotal = $qtyBase * $basePrice;
                    $taxAmount = $subtotal * ($taxRate / 100);
                    $lineTotal = $subtotal + $taxAmount;

                    $purchase->productables()->create([
                        'product_product_id' => $pid,
                        'quantity' => $qtyBase,
                        'price' => $basePrice,
                        'subtotal' => $subtotal,
                        'tax_id' => $taxId,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'total' => $lineTotal,
                        'uom_id' => $template->uom_id,
                        'quantity_uom' => $template->uom_id ? $qtyUom : null,
                        'price_uom' => $template->uom_id ? $priceUom : null,
                        'uom_factor' => $uomFactor,
                        'lot_number_input' => $alloc['lot_number'] ?? null,
                        'lot_manufactured_at' => $alloc['manufactured_at'] ?? null,
                        'lot_expires_at' => $alloc['expires_at'] ?? null,
                    ]);
                }
            }

            // Recompute purchase total from remaining productables
            $newTotal = $purchase->productables()->sum('total');
            $purchase->update(['total' => $newTotal]);
        });

        return $this->lots($purchase->fresh());
    }
}
