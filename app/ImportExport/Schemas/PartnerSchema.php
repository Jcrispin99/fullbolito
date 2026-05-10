<?php

declare(strict_types=1);

namespace App\ImportExport\Schemas;

use App\ImportExport\Support\FieldDefinition;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class PartnerSchema extends AbstractResourceSchema
{
    public function key(): string
    {
        return 'partner';
    }

    public function label(): string
    {
        return 'Partners (Customers & Suppliers)';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('id', 'ID', 'integer', exportable: true, importable: false),
            new FieldDefinition('name', 'Name', 'string', required: true),
            new FieldDefinition('document_type', 'Document Type', 'enum', enumValues: ['DNI', 'RUC', 'CE', 'Passport']),
            new FieldDefinition('document_number', 'Document Number', 'string', unique: true),
            new FieldDefinition('email', 'Email', 'string'),
            new FieldDefinition('phone', 'Phone', 'string'),
            new FieldDefinition('address', 'Address', 'string'),
            new FieldDefinition('ubigeo', 'Ubigeo', 'string', help: 'INEI code (6 digits)'),
            new FieldDefinition('birth_date', 'Birth Date', 'date'),
            new FieldDefinition('gender', 'Gender', 'enum', enumValues: ['M', 'F', 'Other']),
            new FieldDefinition('is_customer', 'Is Customer', 'boolean'),
            new FieldDefinition('is_supplier', 'Is Supplier', 'boolean'),
            new FieldDefinition('payment_terms', 'Payment Terms (days)', 'integer'),
            new FieldDefinition('credit_limit', 'Credit Limit', 'decimal'),
            new FieldDefinition('tax_id', 'Tax ID', 'string'),
            new FieldDefinition('business_license', 'Business License', 'string'),
            new FieldDefinition('provider_category', 'Provider Category', 'string'),
            new FieldDefinition('status', 'Status', 'enum', enumValues: ['active', 'inactive', 'suspended', 'blacklisted']),
            new FieldDefinition('notes', 'Notes', 'string'),
            new FieldDefinition('company.business_name', 'Company', 'relation', importable: false, relationResource: 'company'),
            new FieldDefinition('purchases_count', 'Compras realizadas', 'integer', importable: false, help: 'Total de compras registradas'),
            new FieldDefinition('loyalty_cards_count', 'Tarjetas de lealtad', 'integer', importable: false),
            new FieldDefinition('loyalty_transactions_count', 'Transacciones de lealtad', 'integer', importable: false),
            new FieldDefinition('created_at', 'Created At', 'datetime', importable: false),
            new FieldDefinition('updated_at', 'Updated At', 'datetime', importable: false),
        ];
    }

    public function query(): Builder
    {
        return Partner::query()
            ->with('company')
            ->withCount(['purchases', 'loyaltyCards', 'loyaltyTransactions']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('is_customer', $filters) && $filters['is_customer'] !== null) {
            $query->where('is_customer', (bool) $filters['is_customer']);
        }

        if (array_key_exists('is_supplier', $filters) && $filters['is_supplier'] !== null) {
            $query->where('is_supplier', (bool) $filters['is_supplier']);
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    public function validateRow(array $row, string $mode): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'document_type' => ['nullable', 'string', 'in:DNI,RUC,CE,Passport'],
            'document_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'ubigeo' => ['nullable', 'string', 'size:6'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:M,F,Other'],
            'is_customer' => ['nullable', 'boolean'],
            'is_supplier' => ['nullable', 'boolean'],
            'payment_terms' => ['nullable', 'integer', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'business_license' => ['nullable', 'string', 'max:100'],
            'provider_category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:active,inactive,suspended,blacklisted'],
            'notes' => ['nullable', 'string'],
        ];

        $validator = Validator::make($row, $rules);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->all() as $msg) {
                $errors[] = (string) $msg;
            }
            return $errors;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function persistRow(array $row, string $mode, ?string $uniqueKey = null): Model
    {
        $attrs = $this->normalizeAttributes($row);

        if ($mode === 'create') {
            return Partner::create($attrs);
        }

        $key = $uniqueKey ?: 'document_number';
        if (empty($attrs[$key]) && $key !== 'id') {
            throw new \RuntimeException("Cannot {$mode} without a value for unique key [{$key}].");
        }

        $existing = $key === 'id'
            ? Partner::find($attrs['id'] ?? null)
            : Partner::where($key, $attrs[$key])->first();

        if ($mode === 'update') {
            if (! $existing) {
                throw new \RuntimeException("No partner found with {$key}=[{$attrs[$key]}].");
            }
            $existing->fill($attrs)->save();
            return $existing;
        }

        // upsert
        if ($existing) {
            $existing->fill($attrs)->save();
            return $existing;
        }
        return Partner::create($attrs);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $row): array
    {
        $boolean = ['is_customer', 'is_supplier'];
        foreach ($boolean as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                $row[$key] = filter_var($row[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (['payment_terms'] as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                $row[$key] = (int) $row[$key];
            }
        }

        foreach (['credit_limit'] as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                $row[$key] = (float) $row[$key];
            }
        }

        // Empty strings to null for clean DB inserts
        foreach ($row as $k => $v) {
            if ($v === '') {
                $row[$k] = null;
            }
        }

        return $row;
    }
}
