<?php

declare(strict_types=1);

return [
    'plan' => App\Models\Plan::class,
    'tenant' => App\Models\Tenant::class,
    'user' => App\Models\User::class,
    'company' => App\Models\Company::class,
    'warehouse' => App\Models\Warehouse::class,
    'attribute' => App\Models\Attribute::class,
    'category' => App\Models\Category::class,
    'unit_of_measure' => App\Models\UnitOfMeasure::class,
    'tax' => App\Models\Tax::class,
    'product_template' => App\Models\ProductTemplate::class,
    'partner' => App\Models\Partner::class,
    'purchase' => App\Models\Purchase::class,
    'transfer' => App\Models\Transfer::class,
    'movement' => App\Models\Movement::class,
    'paymentMethod' => App\Models\PaymentMethod::class,
    'posConfig' => App\Models\PosConfig::class,
    'court' => App\Models\Court::class,
    'courtSchedule' => App\Models\CourtSchedule::class,
    'reservation' => App\Models\Reservation::class,
    'loyalty_program' => App\Models\LoyaltyProgram::class,

];
