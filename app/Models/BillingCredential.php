<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Credenciales para el servicio de facturación electrónica del tenant.
 *
 * Diseñado como configuración global del tenant (típicamente un único
 * registro). Los campos sensibles (`sol_pass`, `client_secret`) se cifran
 * at-rest vía `encrypted` cast. El certificado vive en disco privado del
 * tenant (`Storage::disk('local')`, que el FilesystemTenancyBootstrapper
 * redirige a `storage/{tenant}/app/private/`).
 *
 * @property int $id
 * @property string $name
 * @property string $sol_user
 * @property string $sol_pass
 * @property string $cert_path
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property bool $production
 * @property bool $is_active
 */
final class BillingCredential extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'billing_credentials';

    protected $fillable = [
        'name',
        'sol_user',
        'sol_pass',
        'cert_path',
        'client_id',
        'client_secret',
        'production',
        'is_active',
    ];

    protected $casts = [
        'sol_pass' => 'encrypted',
        'client_secret' => 'encrypted',
        'production' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Oculto por defecto en la serialización; el Resource decide si exponerlos.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'sol_pass',
        'client_secret',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeProduction(Builder $query): Builder
    {
        return $query->where('production', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // No logueamos secretos. Sólo metadatos útiles para auditoría.
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'sol_user',
                'cert_path',
                'client_id',
                'production',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
