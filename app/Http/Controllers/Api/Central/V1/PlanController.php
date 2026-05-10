<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Central\V1\PlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class PlanController extends ApiController
{
    /**
     * Listar todos los planes.
     */
    public function index(): JsonResponse
    {
        // Opcional: Permitir ver planes públicos sin ser superadmin (para registro)
        // Pero el CRUD completo sí requiere admin.

        $perPage = request()->input('per_page', 15);
        $search = request()->input('search');

        $query = Plan::query()
            ->with('modules')
            ->orderBy('price');

        $status = request()->input('status', 'active');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'archived') {
            $query->where('is_active', false);
        }
        // if status is 'all', we don't apply any is_active filter

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Si per_page es -1 o 'total', devolvemos todo (con un límite alto por seguridad o collection directa)
        if ($perPage === '-1' || $perPage === 'total') {
            $plans = $query->get();

            // Manually wrap in a paginator-like structure or just return collection
            // The frontend store expects checks for pagination.
            // If strictly following Laravel resources, collection usually returns { data: [] }
            return $this->success(PlanResource::collection($plans));
        }

        $plans = $query->paginate((int) $perPage);

        return $this->success(PlanResource::collection($plans)
            ->response()
            ->getData(true));
    }

    /**
     * Crear un nuevo plan.
     */
    public function store(PlanRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can create plans.');
        }

        $data = $request->validated();
        $moduleIds = $data['module_ids'] ?? [];
        unset($data['module_ids']);

        $plan = Plan::query()->create($data);

        // When `includes_all_modules` is true the pivot is irrelevant —
        // getPlanFeatures() short-circuits to every active module. We still
        // sync to keep the explicit list as a fallback if the flag is later
        // turned off.
        $plan->modules()->sync($moduleIds);

        return $this->created(new PlanResource($plan->load('modules')));
    }

    /**
     * Mostrar detalles de un plan.
     */
    public function show(Plan $plan): JsonResponse
    {
        return $this->success(new PlanResource($plan->load('modules')));
    }

    /**
     * Actualizar un plan existente.
     */
    public function update(PlanRequest $request, Plan $plan): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can update plans.');
        }

        $data = $request->validated();
        $moduleIds = $data['module_ids'] ?? null;
        unset($data['module_ids']);

        $plan->update($data);

        // Only resync when the client sent a module list — passing nothing
        // (e.g. a partial update of price) shouldn't wipe the mapping.
        if ($moduleIds !== null) {
            $plan->modules()->sync($moduleIds);
        }

        return $this->success(new PlanResource($plan->load('modules')));
    }

    /**
     * Eliminar (o desactivar) un plan.
     */
    public function destroy(Plan $plan): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can delete plans.');
        }

        // Soft delete lógico o físico.
        // Si tiene suscripciones activas, mejor no borrarlo físicamente.
        if ($plan->subscriptions()->exists()) {
            // Alternativa: Marcar como inactivo
            $plan->update(['is_active' => false]);

            return $this->success(null, 'Plan has active subscriptions. It was deactivated instead of deleted.');
        }

        $plan->delete();

        return $this->success(null, 'Plan deleted successfully.');
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Plan::whereIn('id', $request->ids)->delete();
        return $this->noContent();
    }

    /**
     * Alternar el estado activo/inactivo de un plan.
     */
    public function toggleStatus(Plan $plan): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can toggle plan status.');
        }

        $plan->update(['is_active' => ! $plan->is_active]);

        return $this->success(new PlanResource($plan), 'Plan status updated successfully.');
    }
}
