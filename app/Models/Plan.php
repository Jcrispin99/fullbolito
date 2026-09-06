<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $price
 * @property int $duration_days
 * @property bool $is_active
 * @property bool $includes_all_modules
 */
final class Plan extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'duration_days',
        'is_active',
        'includes_all_modules',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'includes_all_modules' => 'boolean',
        'duration_days' => 'integer',
    ];

    /**
     * Plans live in the central database — pin the connection so direct
     * queries from a tenant context don't hit the tenant DB.
     */
    public function getConnectionName(): string
    {
        $connection = config('tenancy.database.central_connection');

        return is_string($connection) ? $connection : 'mysql';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('plans')
            ->logOnly(['name', 'slug', 'price', 'duration_days', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Modules included in this plan. The wildcard case (`includes_all_modules`
     * = true) bypasses this relation and resolves to every active module.
     */
    public function modules(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_module');
    }
}
