<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for models that have a company_id column and support
 * filtering by one or more company IDs via the request header.
 */
trait FiltersByCompany
{
    public function scopeForCompanies(Builder $query, ?array $companyIds): Builder
    {
        if (empty($companyIds)) {
            return $query;
        }

        return $query->whereIn("{$this->getTable()}.company_id", $companyIds);
    }

    /**
     * Apply the company filter from the current request automatically.
     */
    public function scopeCompanyFiltered(Builder $query): Builder
    {
        $ids = request()->get('_company_ids');

        if (empty($ids)) {
            return $query;
        }

        return $query->whereIn("{$this->getTable()}.company_id", $ids);
    }
}
