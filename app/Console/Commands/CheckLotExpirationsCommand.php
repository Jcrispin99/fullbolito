<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Lot;
use App\Models\LotAlert;
use App\Models\ProductProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recorre los lotes del tenant actual y:
 *  1. Marca como `expired` los lotes vencidos.
 *  2. Bloquea automáticamente los lotes según `expiration_block_days` del template.
 *  3. Genera alertas (deduplicadas por día) para lotes próximos a vencer
 *     o vencidos, basándose en `expiration_alert_days` del template.
 *
 * Pensado para correrse vía `tenants:run lots:check-expirations` (stancl/tenancy).
 */
final class CheckLotExpirationsCommand extends Command
{
    protected $signature = 'lots:check-expirations
                            {--dry-run : Sólo reporta sin escribir cambios.}';

    protected $description = 'Marca lotes vencidos, los bloquea según política y genera alertas (tenant actual).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();

        $this->info('▶ Revisando lotes' . ($dryRun ? ' (dry-run)' : '') . '...');

        // 1) Marca como `expired` los activos que ya vencieron.
        $expiredCount = $this->markExpiredLots($today, $dryRun);

        // 2) Auto-bloqueo según expiration_block_days del template.
        $blockedCount = $this->autoBlockNearExpiry($today, $dryRun);

        // 3) Alertas: expiring + expired (deduplicadas por (lot, type, alert_date)).
        $alertsCreated = $this->generateAlerts($today, $dryRun);

        $this->info("✔ Vencidos marcados: {$expiredCount}");
        $this->info("✔ Bloqueados por política: {$blockedCount}");
        $this->info("✔ Alertas creadas: {$alertsCreated}");

        return self::SUCCESS;
    }

    /**
     * Marca como `expired` los lotes activos cuya fecha de vencimiento ya pasó.
     */
    private function markExpiredLots(Carbon $today, bool $dryRun): int
    {
        $query = Lot::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today);

        if ($dryRun) {
            return $query->count();
        }

        $count = 0;
        $query->chunkById(200, function ($lots) use (&$count): void {
            foreach ($lots as $lot) {
                $lot->status = 'expired';
                $lot->save();
                $count++;
            }
        });

        return $count;
    }

    /**
     * Bloquea lotes cuyo template define `expiration_block_days`
     * y se encuentran dentro de esa ventana antes del vencimiento.
     *
     * Ejemplo: block_days = 5 → bloquea cuando faltan ≤ 5 días para vencer.
     */
    private function autoBlockNearExpiry(Carbon $today, bool $dryRun): int
    {
        $rows = DB::table('lots')
            ->join('product_products', 'product_products.id', '=', 'lots.product_product_id')
            ->join('product_templates', 'product_templates.id', '=', 'product_products.product_template_id')
            ->whereNull('lots.deleted_at')
            ->where('lots.status', 'active')
            ->whereNotNull('lots.expires_at')
            ->whereNotNull('product_templates.expiration_block_days')
            ->whereRaw('DATE_ADD(?, INTERVAL product_templates.expiration_block_days DAY) >= lots.expires_at', [$today->toDateString()])
            ->whereDate('lots.expires_at', '>=', $today) // si ya está vencido, lo manejó el paso 1
            ->select('lots.id')
            ->pluck('lots.id');

        if ($rows->isEmpty()) {
            return 0;
        }

        if ($dryRun) {
            return $rows->count();
        }

        return Lot::query()
            ->whereIn('id', $rows->all())
            ->update(['status' => 'blocked', 'updated_at' => now()]);
    }

    /**
     * Genera alertas para:
     *   - lotes ya vencidos sin alerta del día (tipo "expired")
     *   - lotes que entran en la ventana `expiration_alert_days` (tipo "expiring")
     *   - lotes recién bloqueados por política (tipo "blocked")
     *
     * Dedupe natural por unique (lot_id, alert_type, alert_date).
     */
    private function generateAlerts(Carbon $today, bool $dryRun): int
    {
        $alertDate = $today->toDateString();
        $created = 0;

        // ── 1. Expirados ─────────────────────────────────────────
        $expiredLots = Lot::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today)
            ->whereIn('status', ['expired', 'blocked'])
            ->get(['id', 'company_id', 'product_product_id', 'lot_number', 'expires_at']);

        foreach ($expiredLots as $lot) {
            $days = (int) $today->diffInDays($lot->expires_at, false); // negativo
            $created += $this->upsertAlert(
                lotId: (int) $lot->id,
                productProductId: (int) $lot->product_product_id,
                companyId: (int) $lot->company_id,
                type: 'expired',
                days: $days,
                alertDate: $alertDate,
                message: "Lote {$lot->lot_number} vencido el {$lot->expires_at->format('Y-m-d')}.",
                dryRun: $dryRun,
            );
        }

        // ── 2. Próximos a vencer (según template.expiration_alert_days) ───
        $rows = DB::table('lots')
            ->join('product_products', 'product_products.id', '=', 'lots.product_product_id')
            ->join('product_templates', 'product_templates.id', '=', 'product_products.product_template_id')
            ->whereNull('lots.deleted_at')
            ->where('lots.status', 'active')
            ->whereNotNull('lots.expires_at')
            ->whereNotNull('product_templates.expiration_alert_days')
            ->whereDate('lots.expires_at', '>=', $today)
            ->whereRaw('DATE_ADD(?, INTERVAL product_templates.expiration_alert_days DAY) >= lots.expires_at', [$today->toDateString()])
            ->select(
                'lots.id as lot_id',
                'lots.company_id',
                'lots.product_product_id',
                'lots.lot_number',
                'lots.expires_at',
            )
            ->get();

        foreach ($rows as $row) {
            $expiresAt = Carbon::parse($row->expires_at);
            $days = (int) $today->diffInDays($expiresAt, false); // positivo
            $created += $this->upsertAlert(
                lotId: (int) $row->lot_id,
                productProductId: (int) $row->product_product_id,
                companyId: (int) $row->company_id,
                type: 'expiring',
                days: $days,
                alertDate: $alertDate,
                message: "Lote {$row->lot_number} vence en {$days} día(s) ({$expiresAt->format('Y-m-d')}).",
                dryRun: $dryRun,
            );
        }

        // ── 3. Bloqueados (lotes recientemente bloqueados con expires_at futuro) ──
        $blockedLots = Lot::query()
            ->where('status', 'blocked')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', $today)
            ->get(['id', 'company_id', 'product_product_id', 'lot_number', 'expires_at']);

        foreach ($blockedLots as $lot) {
            $days = (int) $today->diffInDays($lot->expires_at, false);
            $created += $this->upsertAlert(
                lotId: (int) $lot->id,
                productProductId: (int) $lot->product_product_id,
                companyId: (int) $lot->company_id,
                type: 'blocked',
                days: $days,
                alertDate: $alertDate,
                message: "Lote {$lot->lot_number} bloqueado automáticamente (vence en {$days} día(s)).",
                dryRun: $dryRun,
            );
        }

        return $created;
    }

    /**
     * Inserta una alerta si no existe ya para ese (lote, tipo, fecha).
     * Devuelve 1 si insertó, 0 si ya existía o si es dry-run.
     */
    private function upsertAlert(
        int $lotId,
        int $productProductId,
        int $companyId,
        string $type,
        int $days,
        string $alertDate,
        string $message,
        bool $dryRun,
    ): int {
        if ($dryRun) {
            $exists = LotAlert::query()
                ->where('lot_id', $lotId)
                ->where('alert_type', $type)
                ->where('alert_date', $alertDate)
                ->exists();

            return $exists ? 0 : 1;
        }

        try {
            LotAlert::query()->create([
                'lot_id' => $lotId,
                'product_product_id' => $productProductId,
                'company_id' => $companyId,
                'alert_type' => $type,
                'days_until_expiry' => $days,
                'alert_date' => $alertDate,
                'message' => $message,
                'status' => 'unread',
            ]);

            return 1;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Ya existe la alerta del día → dedupe.
            return 0;
        }
    }
}
