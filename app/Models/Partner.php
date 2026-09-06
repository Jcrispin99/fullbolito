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

    /**
     * Resuelve el Partner a usar para una reserva guest:
     *   - Si hay phone, busca un cliente existente con ese phone (lo reusa).
     *   - Si no existe, crea uno nuevo con los datos del guest.
     *   - Si no hay phone, cae al cliente default "Varios" (seedeado).
     *
     * Útil para que las reservas públicas queden vinculadas a un Partner desde
     * el inicio, en vez de ser totalmente anónimas. La sale heredada del
     * mark-paid también queda atada a ese Partner.
     */
    public static function resolveForGuest(
        ?string $name,
        ?string $phone,
        ?string $email,
        ?int $companyId,
    ): self {
        $phone = $phone ? trim($phone) : null;

        if ($phone !== null && $phone !== '') {
            // 1) Match exacto por phone (preferido)
            $existing = self::query()
                ->where('phone', $phone)
                ->where('is_customer', true)
                ->first();

            if ($existing) {
                $updates = [];
                if ($name && $existing->name !== $name) {
                    $updates['name'] = $name;
                }
                if ($email && $existing->email !== $email) {
                    $updates['email'] = $email;
                }
                if ($updates) {
                    $existing->update($updates);
                }

                return $existing;
            }

            // 2) Genera un document_number único derivado del teléfono.
            // El unique(doc_type, doc_number) impide reusar '00000000' (que
            // pertenece a "Varios"). Usamos los dígitos del phone como
            // identificador sintético — es lo bastante único para guests.
            $phoneDigits = preg_replace('/\D/', '', $phone) ?: '';
            $docNumber = substr($phoneDigits !== '' ? $phoneDigits : 'G' . uniqid(), 0, 20);

            // 3) Safety net: si ya existe un partner con ese (DNI, docNumber)
            // (ej. duplicado por otro flujo), lo reusamos.
            $byDoc = self::query()
                ->where('document_type', 'DNI')
                ->where('document_number', $docNumber)
                ->first();

            if ($byDoc) {
                $updates = ['phone' => $phone];
                if ($name && $byDoc->name !== $name) $updates['name'] = $name;
                if ($email && $byDoc->email !== $email) $updates['email'] = $email;
                $byDoc->update($updates);

                return $byDoc;
            }

            return self::create([
                'company_id' => $companyId,
                'is_customer' => true,
                'is_supplier' => false,
                'document_type' => 'DNI',
                'document_number' => $docNumber,
                'name' => $name ?? 'Cliente sin nombre',
                'phone' => $phone,
                'email' => $email,
                'status' => 'active',
            ]);
        }

        // Sin teléfono → cliente "Varios"
        $varios = self::query()
            ->where('document_type', 'DNI')
            ->where('document_number', '00000000')
            ->where('name', 'Varios')
            ->where('is_customer', true)
            ->first();

        if ($varios) {
            return $varios;
        }

        return self::create([
            'company_id' => $companyId,
            'is_customer' => true,
            'is_supplier' => false,
            'document_type' => 'DNI',
            'document_number' => '00000000',
            'name' => 'Varios',
            'status' => 'active',
        ]);
    }
}
