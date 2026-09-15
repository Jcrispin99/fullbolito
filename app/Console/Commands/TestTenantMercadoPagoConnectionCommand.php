<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\MercadoPago\TenantMercadoPagoConnectionService;
use Illuminate\Console\Command;
use RuntimeException;

final class TestTenantMercadoPagoConnectionCommand extends Command
{
    protected $signature = 'tenant-payments:test-mercadopago
                            {tenant : ID del tenant}
                            {--environment=test : test o production}';

    protected $description = 'Valida de forma segura la cuenta Mercado Pago guardada en la base del tenant.';

    public function handle(TenantMercadoPagoConnectionService $connections): int
    {
        $tenantId = (string) $this->argument('tenant');
        $environment = (string) $this->option('environment');
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->error("No existe el tenant [{$tenantId}].");

            return self::FAILURE;
        }

        try {
            tenancy()->initialize($tenant);
            $result = $connections->test($connections->find($environment));

            $this->table(
                ['tenant', 'ambiente', 'seller_id', 'nickname', 'país', 'validado'],
                [[
                    $tenantId,
                    $result['environment'],
                    $result['account_id'],
                    $result['nickname'] ?? '—',
                    $result['country_id'] ?? '—',
                    $result['validated_at'],
                ]],
            );

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
