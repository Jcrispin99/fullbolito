<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Company;
use App\Models\Warehouse;

final class CompanyObserver
{
    /**
     * Al crear una nueva sede (Company) se le crea un warehouse default
     * para soportar la cadena de ventas (Sale exige warehouse_id).
     * El usuario final no ve este warehouse — vive solo para satisfacer
     * el modelo contable.
     */
    public function created(Company $company): void
    {
        if ($company->warehouses()->exists()) {
            return;
        }

        Warehouse::create([
            'name' => "Almacén {$company->business_name}",
            'company_id' => $company->id,
            'is_active' => true,
        ]);
    }
}
