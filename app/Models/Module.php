<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'icon',
        'addon_price',
        'stripe_price_id',
        'stripe_product_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'addon_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Modules live in the central database — pin the connection so queries
     * from inside a tenant context don't hit the tenant DB.
     */
    public function getConnectionName(): string
    {
        return config('tenancy.database.central_connection') ?? parent::getConnectionName();
    }

    public function isAddon(): bool
    {
        return (float) $this->addon_price > 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    public function plans(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_module');
    }
}
