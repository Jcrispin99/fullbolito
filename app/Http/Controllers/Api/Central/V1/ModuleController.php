<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Central\V1\ModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ModuleController extends ApiController
{
    public function index(): JsonResponse
    {
        $perPage = request()->input('per_page', 15);
        $search = request()->input('search');

        $query = Module::query()->orderBy('sort_order')->orderBy('label');

        $status = request()->input('status', 'active');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'archived') {
            $query->where('is_active', false);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            });
        }

        if ($perPage === '-1' || $perPage === 'total') {
            return $this->success(ModuleResource::collection($query->get()));
        }

        $modules = $query->paginate((int) $perPage);

        return $this->success(ModuleResource::collection($modules)
            ->response()
            ->getData(true));
    }

    public function store(ModuleRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can create modules.');
        }

        $module = Module::query()->create($request->validated());

        return $this->created(new ModuleResource($module));
    }

    public function show(Module $module): JsonResponse
    {
        return $this->success(new ModuleResource($module));
    }

    public function update(ModuleRequest $request, Module $module): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can update modules.');
        }

        $module->update($request->validated());

        return $this->success(new ModuleResource($module));
    }

    public function destroy(Module $module): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can delete modules.');
        }

        // If the module is bound to any plan, deactivate instead of deleting
        // so we don't break tenants that depend on it.
        if ($module->plans()->exists()) {
            $module->update(['is_active' => false]);

            return $this->success(null, 'Module is bound to plans. It was deactivated instead of deleted.');
        }

        $module->delete();

        return $this->success(null, 'Module deleted successfully.');
    }

    public function toggleStatus(Module $module): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can toggle module status.');
        }

        $module->update(['is_active' => ! $module->is_active]);

        return $this->success(new ModuleResource($module), 'Module status updated successfully.');
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Module::whereIn('id', $request->ids)->delete();

        return $this->noContent();
    }
}
