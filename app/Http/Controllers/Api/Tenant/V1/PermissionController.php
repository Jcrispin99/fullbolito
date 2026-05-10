<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

final class PermissionController extends ApiController
{
    public function index(): JsonResponse
    {
        $grouped = (bool) request()->boolean('grouped', false);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        if (! $grouped) {
            return $this->success(PermissionResource::collection($permissions));
        }

        $byGroup = $permissions
            ->groupBy(fn (Permission $p) => $this->groupOf($p->name))
            ->map(fn ($items) => $items->pluck('name')->values()->all());

        return $this->success(['groups' => $byGroup]);
    }

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
