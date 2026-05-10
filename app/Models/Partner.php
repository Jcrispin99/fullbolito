<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\FiltersByCompany;
use Spatie\Activitylog\Traits\LogsActivity;

final class Partner extends Model
{
    use HasFactory, LogsActivity, FiltersByCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'is_customer',
        'is_supplier',
        'document_type',
        'document_number',
        'name',
        'email',
        'phone',
        'address',
        'ubigeo',
        'birth_date',
        'gender',
        'payment_terms',
        'credit_limit',
        'tax_id',
        'business_license',
        'provider_category',
        'status',
        'notes',
    ];

    protected $casts = [
        'is_customer' => 'boolean',
        'is_supplier' => 'boolean',
        'credit_limit' => 'decimal:2',
        'birth_date' => 'date',
    ];

    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->where('is_supplier', true);
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('is_customer', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function loyaltyCards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'is_customer',
                'is_supplier',
                'document_type',
                'document_number',
                'name',
                'email',
                'phone',
                'address',
                'ubigeo',
                'payment_terms',
                'provider_category',
                'status',
                'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Partner {$eventName}");
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? "{$this->document_type}: {$this->document_number}";
    }
}
