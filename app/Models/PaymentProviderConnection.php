<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A payment-provider account connected to the current tenant.
 *
 * Secrets are encrypted at rest with Laravel's APP_KEY and are never included
 * in array/JSON serialization.
 *
 * @property int $id
 * @property string $provider
 * @property string $environment
 * @property string $access_token
 * @property string|null $public_key
 * @property string|null $webhook_secret
 * @property string|null $external_account_id
 * @property string|null $account_nickname
 * @property string|null $country_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $validated_at
 * @property string|null $last_validation_error
 * @property array<string, mixed>|null $metadata
 */
final class PaymentProviderConnection extends Model
{
    protected $fillable = [
        'provider',
        'environment',
        'access_token',
        'public_key',
        'webhook_secret',
        'external_account_id',
        'account_nickname',
        'country_id',
        'is_active',
        'validated_at',
        'last_validation_error',
        'metadata',
    ];

    protected $hidden = [
        'access_token',
        'public_key',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'public_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'validated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
