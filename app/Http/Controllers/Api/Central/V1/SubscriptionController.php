<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SubscriptionController extends ApiController
{
    /**
     * Paginated list of all tenant subscriptions across the SaaS, plus
     * a small headline summary (active count, trial count, MRR estimate).
     * Superadmin only.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->isSuperAdmin()) {
            return $this->forbidden('Superadmin only.');
        }

        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));

        $query = Subscription::query()
            ->with(['tenant:id,business_name', 'plan:id,name,slug,price,duration_days'])
            ->orderByDesc('id');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        $activeCount = Subscription::query()->where('status', 'active')->count();
        $trialCount = Subscription::query()->where('status', 'trial')->count();
        $mrr = (float) Subscription::query()
            ->where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('plans.duration_days', '<=', 31)
            ->sum('plans.price');

        return $this->success([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => [
                'active_count' => $activeCount,
                'trial_count' => $trialCount,
                'monthly_recurring_revenue' => $mrr,
            ],
        ]);
    }

    /**
     * Renovar manualmente la suscripción de un tenant.
     * Esto es útil para pagos manuales (transferencia, efectivo) o correcciones.
     */
    public function renew(Request $request, Tenant $tenant): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Verificar permisos (Solo Superadmin)
        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can renew subscriptions manually.');
        }

        // 2. Validar input
        $validated = $request->validate([
            'duration_days' => ['required', 'integer', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:255'], // Ej: "Transferencia #12345"
        ]);

        $days = (int) $validated['duration_days'];
        $subscription = $tenant->subscription;

        if (! $subscription) {
            return $this->error('Tenant has no subscription history.', 404);
        }

        // 3. Calcular nueva fecha
        // Si ya estaba vencida, empieza desde hoy. Si no, suma a la fecha actual de fin.
        $startDate = now();
        if ($subscription->ends_at && $subscription->ends_at->isFuture()) {
            $startDate = $subscription->ends_at;
        }

        $newEndDate = $startDate->copy()->addDays($days);

        // 4. Actualizar suscripción
        $subscription->update([
            'status' => 'active',
            'ends_at' => $newEndDate,
        ]);

        // 5. Registrar "pago" (opcional pero recomendado para historial)
        if ($request->filled('payment_reference')) {
            $subscription->payments()->create([
                'amount' => 0, // O pedir el monto en el request
                'currency' => 'USD',
                'method' => 'manual',
                'status' => 'completed',
                'transaction_id' => $request->payment_reference,
            ]);
        }

        return $this->success([
            'tenant_id' => $tenant->id,
            'status' => $subscription->status,
            'ends_at' => $subscription->ends_at->toIso8601String(),
            'message' => "Subscription extended by {$days} days.",
        ]);
    }
}
