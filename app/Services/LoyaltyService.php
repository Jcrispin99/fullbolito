<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\Partner;
use App\Models\Sale;

final class LoyaltyService
{
    /**
     * Procesa una venta: evalúa reglas de los programas activos y otorga puntos.
     *
     * @return LoyaltyTransaction[]
     */
    public function processSale(Sale $sale, Partner $customer, string $module = 'sales'): array
    {
        $programs = LoyaltyProgram::active()
            ->current()
            ->forModule($module)
            ->with(['rules.productVariants', 'rules.productTemplates', 'rules.categories'])
            ->get();

        $transactions = [];

        foreach ($programs as $program) {
            $transaction = $this->evaluateAndEarn($program, $customer, $sale);
            if ($transaction) {
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    /**
     * Evalúa las reglas de un programa y otorga puntos si alguna regla aplica.
     */
    public function evaluateAndEarn(LoyaltyProgram $program, Partner $customer, Sale $sale): ?LoyaltyTransaction
    {
        if (! $program->isAvailable()) {
            return null;
        }

        $totalPoints = 0.0;

        foreach ($program->rules as $rule) {
            $points = $this->evaluateRule($rule, $sale);
            $totalPoints += $points;
        }

        $totalPoints = round($totalPoints, 2);

        if ($totalPoints <= 0) {
            return null;
        }

        $card = $this->getOrCreateCard($program, $customer);
        $newBalance = round((float) $card->points + $totalPoints, 2);

        $transaction = LoyaltyTransaction::create([
            'loyalty_program_id' => $program->id,
            'loyalty_card_id' => $card->id,
            'partner_id' => $customer->id,
            'type' => 'earn',
            'points' => $totalPoints,
            'balance' => $newBalance,
            'description' => "Compra {$sale->serie}-{$sale->correlative}",
            'source_type' => Sale::class,
            'source_id' => $sale->id,
        ]);

        $card->update(['points' => $newBalance]);
        $program->increment('current_uses');

        return $transaction;
    }

    /**
     * Evalúa una regla individual contra una venta.
     */
    public function evaluateRule(LoyaltyRule $rule, Sale $sale): float
    {
        // Check minimum amount
        if ((float) $rule->minimum_amount > 0 && (float) $sale->total < (float) $rule->minimum_amount) {
            return 0;
        }

        // Check conditions (days_of_week, etc.)
        $conditions = $rule->conditions ?? [];
        if (isset($conditions['days_of_week']) && is_array($conditions['days_of_week'])) {
            $dayOfWeek = (int) $sale->date->dayOfWeekIso;
            if (! in_array($dayOfWeek, $conditions['days_of_week'])) {
                return 0;
            }
        }

        // Calculate points based on mode
        return match ($rule->reward_point_mode) {
            'order' => round((float) $rule->reward_point_amount, 2),
            'money' => round((float) $sale->total * (float) $rule->reward_point_amount, 2),
            'unit' => round((float) ($sale->products?->sum('quantity') ?? 0) * (float) $rule->reward_point_amount, 2),
            default => 0.0,
        };
    }

    /**
     * Canjea puntos de una tarjeta.
     */
    public function redeemPoints(
        LoyaltyCard $card,
        int $points,
        string $description = 'Redención',
        ?Sale $sale = null,
    ): LoyaltyTransaction
    {
        $availablePoints = (int) floor((float) $card->points);

        if ($availablePoints < $points) {
            throw new \InvalidArgumentException("Saldo insuficiente. Saldo: {$card->points}, solicitado: {$points}");
        }

        $newBalance = round((float) $card->points - $points, 2);

        $transaction = LoyaltyTransaction::create([
            'loyalty_program_id' => $card->loyalty_program_id,
            'loyalty_card_id' => $card->id,
            'partner_id' => $card->partner_id,
            'type' => 'redeem',
            'points' => -$points,
            'balance' => $newBalance,
            'description' => $description,
            'source_type' => $sale ? Sale::class : null,
            'source_id' => $sale?->id,
        ]);

        $card->update(['points' => $newBalance]);

        return $transaction;
    }

    /**
     * Revierte puntos asociados a una venta cancelada:
     * - Earn: se descuenta del saldo lo ganado.
     * - Redeem: se devuelve al saldo lo canjeado.
     * Detecta reversiones previas mirando transacciones 'adjust' con
     * source_type=Sale, source_id=$sale->id para ser idempotente.
     */
    public function reverseSalePoints(Sale $sale, Partner $customer): array
    {
        $alreadyReversed = LoyaltyTransaction::where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', 'adjust')
            ->exists();

        if ($alreadyReversed) {
            return [];
        }

        $transactions = LoyaltyTransaction::where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->whereIn('type', ['earn', 'redeem'])
            ->get();

        $reversals = [];

        foreach ($transactions as $tx) {
            $card = $tx->card;
            if (! $card) {
                continue;
            }

            // Para earn: tx->points es positivo → restamos.
            // Para redeem: tx->points es negativo → sumamos (al negar).
            $newBalance = round(max(0, (float) $card->points - (float) $tx->points), 2);

            $reversals[] = LoyaltyTransaction::create([
                'loyalty_program_id' => $tx->loyalty_program_id,
                'loyalty_card_id' => $card->id,
                'partner_id' => $customer->id,
                'type' => 'adjust',
                'points' => -$tx->points,
                'balance' => $newBalance,
                'description' => "Reversión ({$tx->type}) - Venta {$sale->serie}-{$sale->correlative}",
                'source_type' => Sale::class,
                'source_id' => $sale->id,
            ]);

            $card->update(['points' => $newBalance]);
        }

        return $reversals;
    }

    /**
     * Simula qué programas aplican a una venta sin ejecutar nada.
     * Usado por el frontend para previsualizar.
     *
     * @return array{cards: array, auto_programs: array, estimated_earn: array}
     */
    public function simulate(Partner $customer, float $total, int $totalQty, string $module = 'sales'): array
    {
        // Tarjetas del cliente con saldo y rewards disponibles
        $cards = LoyaltyCard::where('partner_id', $customer->id)
            ->where('is_active', true)
            ->with(['program.rewards'])
            ->get()
            ->map(function (LoyaltyCard $card) {
                $availableRewards = $card->program?->rewards
                    ->filter(fn ($r) => (float) $r->required_points <= (float) $card->points)
                    ->values() ?? collect();

                return [
                    'card_id' => $card->id,
                    'card_code' => $card->code,
                    'program_id' => $card->loyalty_program_id,
                    'program_name' => $card->program?->name,
                    'program_type' => $card->program?->program_type,
                    'point_name' => $card->program?->point_name ?? 'Puntos',
                    'points' => (float) $card->points,
                    'available_rewards' => $availableRewards->map(fn ($r) => [
                        'reward_id' => $r->id,
                        'reward_type' => $r->reward_type,
                        'required_points' => (float) $r->required_points,
                        'description' => $r->description,
                        'discount' => $r->discount,
                        'discount_mode' => $r->discount_mode,
                    ])->toArray(),
                ];
            })
            ->toArray();

        // Programas auto que aplican a este monto
        $programs = LoyaltyProgram::active()
            ->current()
            ->forModule($module)
            ->where('trigger', 'auto')
            ->with(['rules', 'rewards'])
            ->get();

        $autoPrograms = [];
        $estimatedEarn = [];

        foreach ($programs as $program) {
            if (! $program->isAvailable()) {
                continue;
            }

            $totalPoints = 0;
            $matchedRules = [];

            foreach ($program->rules as $rule) {
                $points = $this->evaluateRuleSimple($rule, $total, $totalQty);
                if ($points > 0) {
                    $totalPoints += $points;
                    $matchedRules[] = [
                        'rule_id' => $rule->id,
                        'points' => $points,
                        'mode' => $rule->reward_point_mode,
                    ];
                }
            }

            if ($totalPoints <= 0) {
                continue;
            }

            $rewards = $program->rewards->map(fn ($r) => [
                'reward_id' => $r->id,
                'reward_type' => $r->reward_type,
                'description' => $r->description,
                'discount' => $r->discount,
                'discount_mode' => $r->discount_mode,
                'discount_applicability' => $r->discount_applicability,
                'reward_product_id' => $r->reward_product_id,
                'reward_product_qty' => $r->reward_product_qty,
                'required_points' => (float) $r->required_points,
            ])->toArray();

            $entry = [
                'program_id' => $program->id,
                'program_name' => $program->name,
                'program_type' => $program->program_type,
                'points_earned' => $totalPoints,
                'point_name' => $program->point_name,
                'matched_rules' => $matchedRules,
                'rewards' => $rewards,
            ];

            if ($program->program_type === 'loyalty') {
                $estimatedEarn[] = $entry;
            } else {
                $autoPrograms[] = $entry;
            }
        }

        return [
            'cards' => $cards,
            'auto_programs' => $autoPrograms,
            'estimated_earn' => $estimatedEarn,
        ];
    }

    /**
     * Valida un código promo o cupón.
     *
     * @return array{valid: bool, error?: string, program?: array, card?: array}
     */
    public function validateCode(string $code, Partner $customer, float $total): array
    {
        // Buscar en reglas de programas (promo_code)
        $rule = \App\Models\LoyaltyRule::where('code', $code)
            ->with(['program.rewards'])
            ->first();

        if ($rule && $rule->program && $rule->program->isAvailable()) {
            $program = $rule->program;

            if ((float) $rule->minimum_amount > 0 && $total < (float) $rule->minimum_amount) {
                return ['valid' => false, 'error' => "Monto mínimo: S/ {$rule->minimum_amount}"];
            }

            if ($program->max_uses_per_customer !== null) {
                $uses = LoyaltyTransaction::where('loyalty_program_id', $program->id)
                    ->where('partner_id', $customer->id)
                    ->where('type', 'earn')
                    ->count();
                if ($uses >= $program->max_uses_per_customer) {
                    return ['valid' => false, 'error' => 'Ya alcanzaste el límite de uso de este código.'];
                }
            }

            return [
                'valid' => true,
                'type' => 'promo_code',
                'program' => [
                    'id' => $program->id,
                    'name' => $program->name,
                    'rewards' => $program->rewards->map(fn ($r) => [
                        'reward_type' => $r->reward_type,
                        'description' => $r->description,
                        'discount' => $r->discount,
                        'discount_mode' => $r->discount_mode,
                    ])->toArray(),
                ],
            ];
        }

        // Buscar en tarjetas (cupón)
        $card = LoyaltyCard::where('code', $code)
            ->with(['program.rewards', 'program.rules'])
            ->first();

        if (! $card) {
            return ['valid' => false, 'error' => 'Código no encontrado.'];
        }

        if (! $card->isUsable()) {
            $reason = ! $card->is_active ? 'inactiva' : ($card->isExpired() ? 'expirada' : 'sin saldo');
            return ['valid' => false, 'error' => "Tarjeta {$reason}."];
        }

        $program = $card->program;
        if (! $program || ! $program->isAvailable()) {
            return ['valid' => false, 'error' => 'El programa de esta tarjeta no está activo.'];
        }

        // Verificar monto mínimo de las reglas
        foreach ($program->rules as $programRule) {
            if ((float) $programRule->minimum_amount > 0 && $total < (float) $programRule->minimum_amount) {
                return ['valid' => false, 'error' => "Monto mínimo: S/ {$programRule->minimum_amount}"];
            }
        }

        return [
            'valid' => true,
            'type' => 'coupon',
            'card' => [
                'id' => $card->id,
                'code' => $card->code,
                'points' => (float) $card->points,
                'program_name' => $program->name,
                'rewards' => $program->rewards->map(fn ($r) => [
                    'reward_type' => $r->reward_type,
                    'description' => $r->description,
                    'discount' => $r->discount,
                    'discount_mode' => $r->discount_mode,
                ])->toArray(),
            ],
        ];
    }

    /**
     * Evalúa una regla contra un monto y cantidad (sin necesitar Sale).
     */
    private function evaluateRuleSimple(LoyaltyRule $rule, float $total, int $totalQty): float
    {
        if ((float) $rule->minimum_amount > 0 && $total < (float) $rule->minimum_amount) {
            return 0.0;
        }

        if ($rule->minimum_qty > 0 && $totalQty < $rule->minimum_qty) {
            return 0.0;
        }

        $conditions = $rule->conditions ?? [];
        if (isset($conditions['days_of_week']) && is_array($conditions['days_of_week'])) {
            $dayOfWeek = (int) now()->dayOfWeekIso;
            if (! in_array($dayOfWeek, $conditions['days_of_week'])) {
                return 0.0;
            }
        }

        return match ($rule->reward_point_mode) {
            'order' => round((float) $rule->reward_point_amount, 2),
            'money' => round($total * (float) $rule->reward_point_amount, 2),
            'unit' => round($totalQty * (float) $rule->reward_point_amount, 2),
            default => 0.0,
        };
    }

    /**
     * Obtiene o crea la tarjeta de un cliente para un programa.
     */
    public function getOrCreateCard(LoyaltyProgram $program, Partner $customer): LoyaltyCard
    {
        return LoyaltyCard::firstOrCreate(
            [
                'loyalty_program_id' => $program->id,
                'partner_id' => $customer->id,
            ],
            [
                'code' => LoyaltyCard::generateCode(),
                'points' => 0,
                'is_active' => true,
            ]
        );
    }
}
