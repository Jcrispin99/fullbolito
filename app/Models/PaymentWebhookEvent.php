<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'action',
        'resource_id',
        'payload',
        'processed_at',
        'error',
    ];

    public function getConnectionName(): string
    {
        $connection = config('tenancy.database.central_connection');

        return is_string($connection) ? $connection : 'mysql';
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
