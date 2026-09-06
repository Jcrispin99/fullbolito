<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Company extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'business_name',
        'trade_name',
        'ruc',
        'address',
        'phone',
        'email',
        'ubigeo',
        'is_active',
        'parent_id',
        'branch_code',
        'is_main',
        'logo_path',
        'brand_color',
        'invoice_footer',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_main' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Company::class, 'parent_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_id');
    }

    public function isBranch(): bool
    {
        return ! is_null($this->parent_id);
    }

    public function isMain(): bool
    {
        return (bool) $this->is_main;
    }

    /**
     * Devuelve la company raíz subiendo por parent_id. Útil cuando ciertos
     * recursos del tenant viven solo en la company madre (ej. journals que
     * tienen code globalmente único) y las sucursales los comparten.
     */
    public function rootCompany(): self
    {
        $node = $this;
        while ($node->parent_id !== null) {
            $node = $node->parent;
            if ($node === null) {
                break;
            }
        }

        return $node ?? $this;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('companies')
            ->logOnly([
                'business_name',
                'trade_name',
                'ruc',
                'address',
                'phone',
                'email',
                'branch_code',
                'ubigeo',
                'is_active',
                'is_main',
                'logo_path',
                'brand_color',
                'invoice_footer',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
