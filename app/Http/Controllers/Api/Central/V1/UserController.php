<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Central\V1\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class UserController extends ApiController
{
    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can list users.');
        }

        $perPage = request()->input('per_page', 15);
        $search = request()->input('search');

        $query = User::query()
            ->with('tenants')
            ->orderBy('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($perPage === '-1' || $perPage === 'total') {
            $users = $query->get();

            return $this->success(UserResource::collection($users));
        }

        $users = $query->paginate((int) $perPage);

        return $this->success(UserResource::collection($users)
            ->response()
            ->getData(true));
    }

    public function store(UserRequest $request): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if (! $authUser->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can create users.');
        }

        $payload = $request->validated();

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $payload['password'],
        ]);

        $user->syncRoles((array) ($payload['roles'] ?? ['user']));

        return $this->created(new UserResource($user->load('roles')));
    }

    public function show(User $user): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if (! $authUser->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can view users.');
        }

        $user->load('tenants');

        return $this->success(new UserResource($user));
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if (! $authUser->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can update users.');
        }

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

        return $this->success(new UserResource($user->load('roles')));
    }

    public function destroy(User $user): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if (! $authUser->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can delete users.');
        }

        if ((int) $authUser->id === (int) $user->id) {
            return $this->forbidden('You cannot delete your own user.');
        }

        $user->delete();

        return $this->noContent();
    }
}
