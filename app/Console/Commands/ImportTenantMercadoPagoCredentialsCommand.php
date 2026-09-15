<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\MercadoPago\TenantMercadoPagoConnectionService;
use Illuminate\Console\Command;
use RuntimeException;

final class ImportTenantMercadoPagoCredentialsCommand extends Command
{
    protected $signature = 'tenant-payments:import-mercadopago-env
                            {tenant : ID del tenant destino}
                            {--environment=test : test o production}';

    protected $description = 'Copia las credenciales Mercado Pago del entorno a la base de un tenant, cifradas e inactivas hasta validarlas.';

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
            $connection = $connections->importFromConfig($environment);

            $this->info("Credenciales {$connection->environment} importadas y cifradas para tenant {$tenantId}.");
            $this->warn('La conexión permanece inactiva hasta ejecutar tenant-payments:test-mercadopago.');

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
