<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleController extends ApiController
{
    public function index(): JsonResponse
    {
        $perPage = request()->input('per_page', 15);
        $search = (string) request()->input('search', '');

        $query = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->select('roles.*')
            ->selectSub(
                DB::table('model_has_roles')
                    ->whereColumn('role_id', 'roles.id')
                    ->where('model_type', User::class)
                    ->selectRaw('COUNT(*)'),
                'users_count',
            );

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($perPage === '-1' || $perPage === 'total') {
            return $this->success(RoleResource::collection($query->get()));
        }

        $roles = $query->paginate((int) $perPage);

        return $this->success(
            RoleResource::collection($roles)->response()->getData(true),
        );
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $role = Role::create([
            'name' => $payload['name'],
            'guard_name' => 'web',
        ]);

        if (array_key_exists('permissions', $payload)) {
            $role->syncPermissions((array) ($payload['permissions'] ?? []));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->load('permissions:id,name');

        return $this->created(new RoleResource($role));
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions:id,name');
        $role->users_count = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->count();

        return $this->success(new RoleResource($role));
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $payload = $request->validated();

        if (! in_array($role->name, RoleResource::SYSTEM_ROLES, true)) {
            $role->update(['name' => $payload['name']]);
        }

        if (array_key_exists('permissions', $payload)) {
            $role->syncPermissions((array) ($payload['permissions'] ?? []));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->load('permissions:id,name');

        return $this->success(new RoleResource($role));
    }

    public function destroy(Role $role): JsonResponse
    {
        if (in_array($role->name, RoleResource::SYSTEM_ROLES, true)) {
            return $this->forbidden('No se puede eliminar un rol del sistema.');
        }

        $hasUsers = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->exists();
        if ($hasUsers) {
            return $this->forbidden('No se puede eliminar un rol asignado a usuarios.');
        }

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->noContent();
    }

    public function formOptions(): JsonResponse
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        $grouped = $permissions
            ->groupBy(fn (Permission $p) => $this->groupOf($p->name))
            ->map(fn ($items) => $items->pluck('name')->values()->all());

        return $this->success([
            'permissions_grouped' => $grouped,
        ]);
    }

    /**
     * Deriva el grupo (recurso) a partir del nombre del permiso.
     * Ej. read_users → users, archive_builder_form_submissions → builder_form_submissions.
     */
    private function groupOf(string $name): string
    {
        $verbs = [
            'create_', 'read_', 'update_', 'delete_', 'toggle_', 'batch_delete_',
            'access_', 'attach_', 'detach_', 'switch_', 'process_', 'preview_',
            'submit_', 'post_', 'reject_', 'cancel_', 'reopen_', 'draft_', 'pay_',
            'send_', 'receive_', 'open_', 'close_', 'archive_', 'mark_',
            'publish_', 'unpublish_', 'duplicate_', 'revert_', 'reorder_',
            'reset_', 'manage_', 'export_', 'simulate_', 'validate_', 'adjust_',
            'find_', 'search_', 'download_', 'apply_', 'save_', 'run_', 'poll_',
        ];

        foreach ($verbs as $verb) {
            if (str_starts_with($name, $verb)) {
                return substr($name, strlen($verb));
            }
        }

        return $name;
    }
}
