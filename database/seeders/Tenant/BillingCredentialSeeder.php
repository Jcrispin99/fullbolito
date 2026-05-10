<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\BillingCredential;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Crea un registro placeholder de credenciales de facturación electrónica.
 *
 * Usa los valores estándar del sandbox SUNAT (clave SOL "MODDATOS") y
 * genera un certificado dummy para que el formulario tenga datos a editar.
 * El cert real lo subirá el usuario desde la UI; esto es sólo bootstrap.
 */
final class BillingCredentialSeeder extends Seeder
{
    public function run(): void
    {
        // Si ya existe un registro, no hacemos nada (idempotente).
        if (BillingCredential::query()->exists()) {
            return;
        }

        $certPath = $this->ensureDummyCertificate();

        BillingCredential::create([
            'name' => 'Credenciales SUNAT (sandbox)',
            // Usuario MODDATOS: estándar de SUNAT para entorno beta.
            'sol_user' => 'MODDATOS',
            'sol_pass' => 'MODDATOS',
            'cert_path' => $certPath,
            'client_id' => null,
            'client_secret' => null,
            'production' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Crea un certificado dummy en disk privado del tenant si no existe.
     * Devuelve la ruta relativa.
     */
    private function ensureDummyCertificate(): string
    {
        $relativePath = 'billing/certs/sandbox-placeholder.pem';

        if (! Storage::disk('local')->exists($relativePath)) {
            Storage::disk('local')->put(
                $relativePath,
                "-----BEGIN CERTIFICATE-----\n".
                "PLACEHOLDER - Reemplazar con certificado real desde la UI.\n".
                "-----END CERTIFICATE-----\n",
            );
        }

        return $relativePath;
    }
}
