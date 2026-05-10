<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\MovementRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\MovementResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Movement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MovementService;
use App\Services\SequenceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

/**
 * CRUD + workflow para Movements sueltos (no parte de una Transferencia).
 *
 * Movements pareados con un Transfer se gestionan exclusivamente desde
 * TransferController para preservar la atomicidad del header (no se pueden
 * crear/editar/eliminar movements de transfer en aislado por esta API).
 */
final class MovementController extends ApiController
{
    /**
     * @var array<int, string>
     */
    private const MOVEMENT_LOAD_RELATIONS = [
        'warehouse',
        'company',
        'createdUser',
        'submittedUser',
        'postedUser',
        'rejectedUser',
        'cancelledUser',
        'transfer',
        'productables.productProduct.template',
        'productables.productProduct.attributeValues',
        'productables.lot',
    ];

    /**
     * @var array<int, string>
     */
    private const MOVEMENT_LIST_RELATIONS = [
        'warehouse',
        'company',
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $search = $request->input('search');
        $type = $request->input('type');
        $status = $request->input('status');
        $standalone = $request->boolean('standalone');
        $from = $request->input('from');
        $to = $request->input('to');
        $companyIds = $request->get('_company_ids');

        $query = Movement::query()
            ->forCompanies(is_array($companyIds) ? $companyIds : null)
            ->with(self::MOVEMENT_LIST_RELATIONS)
            ->orderBy('created_at', 'desc');

        if ($type && in_array($type, ['entry', 'exit'], true)) {
            $query->where('type', $type);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($standalone) {
            $query->whereNull('transfer_id');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('correlative', 'like', "%{$search}%")
                    ->orWhere('observation', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
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
        $submittedTotal = (clone $query)->where('status', 'submitted')->count();
        $postedTotal = (clone $query)->where('status', 'posted')->count();
        $rejectedTotal = (clone $query)->where('status', 'rejected')->count();
        $cancelledTotal = (clone $query)->where('status', 'cancelled')->count();

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Movements listing retrieved successfully',
            'data' => MovementResource::collection($paginator->items()),
            'links' => $paginator->linkCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'draft_total' => $draftTotal,
                'submitted_total' => $submittedTotal,
                'posted_total' => $postedTotal,
                'rejected_total' => $rejectedTotal,
                'cancelled_total' => $cancelledTotal,
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

    public function show(Movement $movement): JsonResponse
    {
        $movement->load(self::MOVEMENT_LOAD_RELATIONS);

        $activities = Activity::forSubject($movement)
            ->with('causer')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Record retrieved successfully',
            'data' => new MovementResource($movement),
            'meta' => [
                'activities' => $activities,
            ],
        ]);
    }

    public function store(MovementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = (string) $validated['type'];
        $companyId = (int) $validated['company_id'];

        // Series independientes para entradas y salidas manuales
        $journalType = $type === 'entry' ? 'movement_entry' : 'movement_exit';
        $journal = Journal::where('type', $journalType)->where('company_id', $companyId)->first();

        if (! $journal) {
            throw ValidationException::withMessages([
                'journal' => "No se encontró un diario de tipo '{$journalType}' para esta compañía.",
            ]);
        }

        $parts = SequenceService::getNextParts($journal->id);

        $movementId = null;

        DB::transaction(function () use ($validated, $type, $companyId, $journal, $parts, $request, &$movementId) {
            $movement = Movement::create([
                'type' => $type,
                'serie' => $parts['serie'],
                'correlative' => $parts['correlative'],
                'date' => $validated['date'] ?? now(),
                'reason' => $validated['reason'] ?? null,
                'observation' => $validated['observation'] ?? null,
                'warehouse_id' => (int) $validated['warehouse_id'],
                'company_id' => $companyId,
                'journal_id' => $journal->id,
                'transfer_id' => null,  // movements sueltos
                'status' => 'draft',
                'total' => 0,
                'created_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            ]);

            $total = 0.0;
            foreach ($validated['products'] as $row) {
                $qty = (float) $row['quantity'];
                $price = (float) ($row['price'] ?? 0);
                $lineTotal = $qty * $price;

                $movement->productables()->create([
                    'product_product_id' => (int) $row['product_product_id'],
                    'lot_id' => isset($row['lot_id']) ? (int) $row['lot_id'] : null,
                    'quantity' => $qty,
                    'price' => $price,
                    'subtotal' => $lineTotal,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $movement->update(['total' => $total]);
            $movementId = $movement->id;
        });

        $movement = Movement::query()->with(self::MOVEMENT_LOAD_RELATIONS)->findOrFail($movementId);

        return $this->created(new MovementResource($movement));
    }

    public function update(MovementRequest $request, Movement $movement): JsonResponse
    {
        if (! $movement->isDraft()) {
            return $this->error('Sólo se pueden editar movimientos en borrador.', 422);
        }

        if ($movement->isPartOfTransfer()) {
            return $this->error('Los movimientos de una transferencia se editan desde el header.', 422);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($movement, $validated) {
            $movement->update([
                'warehouse_id' => $validated['warehouse_id'] ?? $movement->warehouse_id,
                'reason' => $validated['reason'] ?? $movement->reason,
                'observation' => $validated['observation'] ?? $movement->observation,
                'date' => $validated['date'] ?? $movement->date,
            ]);

            if (isset($validated['products'])) {
                $movement->productables()->delete();

                $total = 0.0;
                foreach ($validated['products'] as $row) {
                    $qty = (float) $row['quantity'];
                    $price = (float) ($row['price'] ?? 0);
                    $lineTotal = $qty * $price;

                    $movement->productables()->create([
                        'product_product_id' => (int) $row['product_product_id'],
                        'lot_id' => isset($row['lot_id']) ? (int) $row['lot_id'] : null,
                        'quantity' => $qty,
                        'price' => $price,
                        'subtotal' => $lineTotal,
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'total' => $lineTotal,
                    ]);

                    $total += $lineTotal;
                }

                $movement->update(['total' => $total]);
            }
        });

        return $this->success(
            new MovementResource($movement->fresh()?->load(self::MOVEMENT_LOAD_RELATIONS))
        );
    }

    public function destroy(Movement $movement): JsonResponse
    {
        if (! $movement->isDraft()) {
            return $this->error('Sólo se pueden eliminar movimientos en borrador.', 422);
        }

        if ($movement->isPartOfTransfer()) {
            return $this->error('Los movimientos de una transferencia se eliminan desde el header.', 422);
        }

        DB::transaction(function () use ($movement) {
            $movement->productables()->delete();
            $movement->delete();
        });

        return $this->noContent();
    }

    // ─── Workflow ──────────────────────────────────────────────────────────

    public function submit(Movement $movement, MovementService $service): JsonResponse
    {
        try {
            $service->submit($movement, (int) request()->user()->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        activity()->performedOn($movement)->causedBy(request()->user())->log('Movimiento Enviado a Aprobación');

        return $this->success(
            new MovementResource($movement->fresh()?->load(self::MOVEMENT_LOAD_RELATIONS))
        );
    }

    public function post(Movement $movement, MovementService $service): JsonResponse
    {
        try {
            $service->post($movement, (int) request()->user()->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        activity()->performedOn($movement)->causedBy(request()->user())->log('Movimiento Aprobado');

        return $this->success(
            new MovementResource($movement->fresh()?->load(self::MOVEMENT_LOAD_RELATIONS))
        );
    }

    public function reject(Request $request, Movement $movement, MovementService $service): JsonResponse
    {
        $reason = (string) $request->input('rejection_reason', '');

        try {
            $service->reject($movement, (int) $request->user()->id, $reason !== '' ? $reason : null);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        activity()->performedOn($movement)->causedBy($request->user())->log('Movimiento Rechazado');

        return $this->success(
            new MovementResource($movement->fresh()?->load(self::MOVEMENT_LOAD_RELATIONS))
        );
    }

    public function cancel(Movement $movement, MovementService $service): JsonResponse
    {
        try {
            $service->cancel($movement, (int) request()->user()->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        activity()->performedOn($movement)->causedBy(request()->user())->log('Movimiento Cancelado');

        return $this->success(
            new MovementResource($movement->fresh()?->load(self::MOVEMENT_LOAD_RELATIONS))
        );
    }

    public function reopen(Movement $movement, MovementService $service): JsonResponse
    {
        try {
            $service->reopen($movement);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        activity()->performedOn($movement)->causedBy(request()->user())->log('Movimiento Reabierto a Borrador');

        return $this->success(
            new MovementResource($movement->fresh()?->load(self::MOVEMENT_LOAD_RELATIONS))
        );
    }
}
