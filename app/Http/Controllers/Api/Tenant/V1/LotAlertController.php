<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\LotAlertResource;
use App\Models\LotAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LotAlertController extends ApiController
{
    /**
     * @var array<int, string>
     */
    private const ALERT_LOAD_RELATIONS = [
        'lot',
        'productProduct.template',
    ];

    /**
     * Listado paginado.
     *
     * Query params:
     *   status      : unread|read|all (default: all)
     *   alert_type  : expiring|expired|blocked
     *   from / to   : rango por alert_date
     *   per_page    : número | "total"
     */
    public function index(Request $request): JsonResponse
    {
        $query = LotAlert::query()
            ->companyFiltered()
            ->with(self::ALERT_LOAD_RELATIONS)
            ->orderByRaw("FIELD(alert_type, 'expired', 'blocked', 'expiring')")
            ->orderBy('alert_date', 'desc')
            ->orderBy('id', 'desc');

        $status = $request->query('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($alertType = $request->query('alert_type')) {
            $query->where('alert_type', $alertType);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('alert_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('alert_date', '<=', $to);
        }

        $perPage = $request->query('per_page', 25);
        if ($perPage === 'total') {
            return $this->success(LotAlertResource::collection($query->get()));
        }

        $paginator = $query->paginate((int) $perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Lot alerts retrieved successfully',
            'data' => LotAlertResource::collection($paginator->items()),
            'links' => $paginator->linkCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Conteo de alertas no leídas (para badge en navbar).
     * GET /lot-alerts/badge
     */
    public function badge(): JsonResponse
    {
        $unreadCount = LotAlert::query()
            ->companyFiltered()
            ->unread()
            ->count();

        $byType = LotAlert::query()
            ->companyFiltered()
            ->unread()
            ->selectRaw('alert_type, COUNT(*) as total')
            ->groupBy('alert_type')
            ->pluck('total', 'alert_type');

        return $this->success([
            'unread_count' => $unreadCount,
            'by_type' => [
                'expired' => (int) ($byType['expired'] ?? 0),
                'expiring' => (int) ($byType['expiring'] ?? 0),
                'blocked' => (int) ($byType['blocked'] ?? 0),
            ],
        ]);
    }

    /**
     * Marca una alerta como leída.
     * PATCH /lot-alerts/{lotAlert}/read
     */
    public function markRead(LotAlert $lotAlert): JsonResponse
    {
        $lotAlert->markAsRead();

        return $this->success(
            new LotAlertResource($lotAlert->fresh()->load(self::ALERT_LOAD_RELATIONS)),
            'Alerta marcada como leída'
        );
    }

    /**
     * Marca todas las alertas no leídas como leídas (filtra por company del request).
     * POST /lot-alerts/mark-all-read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = LotAlert::query()
            ->companyFiltered()
            ->unread()
            ->update([
                'status' => 'read',
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->success(['updated' => $updated], 'Alertas marcadas como leídas');
    }

    /**
     * Eliminar una alerta.
     * DELETE /lot-alerts/{lotAlert}
     */
    public function destroy(LotAlert $lotAlert): JsonResponse
    {
        $lotAlert->delete();

        return $this->noContent();
    }
}
