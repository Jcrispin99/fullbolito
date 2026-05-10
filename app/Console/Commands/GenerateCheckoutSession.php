<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

final class GenerateCheckoutSession extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stripe:checkout 
                            {tenant_id : ID del tenant}
                            {price_id : ID del precio en Stripe}';

    /**
     * The console command description.
     */
    protected $description = 'Genera una sesión de checkout de Stripe para testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $priceId = $this->argument('price_id');

        // Buscar el tenant
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->error("❌ Tenant '{$tenantId}' no encontrado.");
            $this->newLine();
            
            // Mostrar tenants disponibles
            $tenants = Tenant::query()->limit(10)->get(['id']);
            if ($tenants->isNotEmpty()) {
                $this->info("Tenants disponibles:");
                foreach ($tenants as $t) {
                    $this->line("  - {$t->id}");
                }
            }
            
            return self::FAILURE;
        }

        // Generar la sesión de checkout
        $this->info("🔄 Generando sesión de checkout...");
        $this->newLine();

        try {
            /** @var \App\Models\Tenant $tenant */
            $checkout = $tenant->newSubscription('default', $priceId)
                ->allowPromotionCodes()
                ->checkout([
                    'success_url' => url('/billing/success?session_id={CHECKOUT_SESSION_ID}'),
                    'cancel_url' => url('/billing/cancel'),
                ]);

            $this->info("✅ Sesión de checkout creada exitosamente!");
            $this->newLine();
            
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("📋 INFORMACIÓN DE LA SESIÓN");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->newLine();
            
            $this->line("Tenant ID:   {$tenantId}");
            $this->line("Price ID:    {$priceId}");
            $this->line("Session ID:  {$checkout->id}");
            $this->newLine();
            
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🔗 CHECKOUT URL:");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->newLine();
            
            $this->line($checkout->url);
            $this->newLine();
            
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("💳 TARJETAS DE PRUEBA:");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->newLine();
            
            $this->line("✅ Pago exitoso:       4242 4242 4242 4242");
            $this->line("🔐 Con 3D Secure:      4000 0025 0000 3155");
            $this->line("❌ Tarjeta declinada:  4000 0000 0000 9995");
            $this->newLine();
            
            $this->line("Fecha exp: Cualquier fecha futura (ej: 12/28)");
            $this->line("CVC:       Cualquier 3 dígitos (ej: 123)");
            $this->line("ZIP:       Cualquier código (ej: 12345)");
            $this->newLine();
            
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->newLine();
            
            $this->info("👉 Copia la URL de arriba y ábrela en tu navegador para completar el pago.");
            $this->newLine();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error al generar la sesión:");
            $this->error($e->getMessage());
            $this->newLine();
            
            return self::FAILURE;
        }
    }
}
