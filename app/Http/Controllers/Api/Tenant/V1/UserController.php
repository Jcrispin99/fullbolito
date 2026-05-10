<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\UserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

final class UserController extends ApiController
{
    public function index(): JsonResponse
    {
        $perPage = request()->input('per_page', 15);
        $search = (string) request()->input('search', '');
        $roleFilter = request()->input('role');

        $query = User::query()
            ->with(['roles', 'companies:id,business_name'])
            ->orderBy('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if (is_string($roleFilter) && $roleFilter !== '') {
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleFilter));
        }

        if ($perPage === '-1' || $perPage === 'total') {
            return $this->success(UserResource::collection($query->get()));
        }

        $users = $query->paginate((int) $perPage);

        return $this->success(
            UserResource::collection($users)->response()->getData(true),
        );
    }

    public function formOptions(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', ['superadmin'])
            ->orderBy('name')
            ->get();

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('business_name')
            ->get(['id', 'business_name']);

        return $this->success([
            'roles' => RoleResource::collection($roles),
            'companies' => $companies,
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $payload['password'],
        ]);

        if (! empty($payload['roles'] ?? [])) {
            $user->syncRoles((array) $payload['roles']);
        }

        if (array_key_exists('company_ids', $payload)) {
            $user->companies()->sync((array) ($payload['company_ids'] ?? []));
        }

        $user->load(['roles', 'companies:id,business_name']);

        return $this->created(new UserResource($user));
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'companies:id,business_name']);

        return $this->success(new UserResource($user));
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $payload = $request->validated();

        $update = [
            'name' => $payload['name'],
            'email' => $payload['email'],
        ];

        if (! empty($payload['password'] ?? null)) {
            $update['password'] = $payload['password'];
        }

        $user->update($update);

        if (array_key_exists('roles', $payload)) {
            $user->syncRoles((array) ($payload['roles'] ?? []));
        }

        if (array_key_exists('company_ids', $payload)) {
            $user->companies()->sync((array) ($payload['company_ids'] ?? []));
        }

        $user->load(['roles', 'companies:id,business_name']);

        return $this->success(new UserResource($user));
    }

    public function destroy(User $user): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if ((int) $authUser->id === (int) $user->id) {
            return $this->forbidden('No puedes eliminarte a ti mismo.');
        }

        $user->delete();

        return $this->noContent();
    }
}
