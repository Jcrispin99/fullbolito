<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\PosConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PosLoyaltyController extends ApiController
{
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pos_config_id' => ['required', 'integer', 'exists:pos_configs,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'total' => ['required', 'numeric', 'min:0'],
        ]);

        $config = PosConfig::findOrFail($validated['pos_config_id']);
        $partnerId = $validated['partner_id'] ?? $config->default_customer_id;
        $total = (float) $validated['total'];

        $program = LoyaltyProgram::active()
            ->current()
            ->forModule('pos')
            ->ofType('loyalty')
            ->with('rewards')
            ->first();

        if (! $program || ! $partnerId) {
            return $this->success([
                'enabled' => false,
                'program' => null,
                'card' => null,
                'points_to_earn' => 0,
                'redeem' => null,
            ]);
        }

        $card = LoyaltyCard::where('loyalty_program_id', $program->id)
            ->where('partner_id', $partnerId)
            ->where('is_active', true)
            ->first();

        $balance = $card ? (float) $card->points : 0.0;

        // Earn rate: sum all rules as processSale would
        $pointsToEarn = 0.0;
        foreach ($program->rules as $rule) {
            if ((float) $rule->minimum_amount > 0 && $total < (float) $rule->minimum_amount) {
                continue;
            }
            $pointsToEarn += match ($rule->reward_point_mode) {
                'order' => (float) $rule->reward_point_amount,
                'money' => $total * (float) $rule->reward_point_amount,
                default => 0.0,
            };
        }
        $pointsToEarn = round($pointsToEarn, 2);

        // Redeem rate: per_point reward applied to order
        $reward = $program->rewards
            ->first(fn (LoyaltyReward $r) => $r->reward_type === 'discount'
                && $r->discount_mode === 'per_point'
                && $r->discount_applicability === 'order');

        $redeem = null;
        if ($reward) {
            $rate = (float) $reward->discount;
            // Cap por saldo Y por total de venta: nunca ofrecer más puntos
            // de los que se pueden aplicar efectivamente al total.
            $maxPoints = $rate > 0
                ? (int) floor(min($balance, $total / $rate))
                : (int) floor($balance);
            $maxAmount = round($maxPoints * $rate, 2);
            $redeem = [
                'rate' => $rate,
                'max_points' => $maxPoints,
                'max_amount' => $maxAmount,
            ];
        }

        return $this->success([
            'enabled' => true,
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'point_name' => $program->point_name,
            ],
            'card' => $card ? [
                'id' => $card->id,
                'code' => $card->code,
                'balance' => $balance,
            ] : null,
            'points_to_earn' => $pointsToEarn,
            'redeem' => $redeem,
        ]);
    }
}
