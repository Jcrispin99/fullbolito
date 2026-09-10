<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

final class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['key' => 'dashboard',  'label' => 'Panel principal',    'icon' => 'layout-dashboard', 'sort_order' => 10],
            ['key' => 'inventory',  'label' => 'Inventario',         'icon' => 'package',          'sort_order' => 20],
            ['key' => 'sales',      'label' => 'Ventas',             'icon' => 'shopping-cart',    'sort_order' => 30],
            ['key' => 'purchases',  'label' => 'Compras',            'icon' => 'truck',            'sort_order' => 40],
            ['key' => 'transfers',  'label' => 'Movimientos',        'icon' => 'arrow-left-right', 'sort_order' => 50, 'addon_price' => 7.99],
            ['key' => 'pos',        'label' => 'Punto de venta',     'icon' => 'scan-line',        'sort_order' => 60],
            ['key' => 'loyalty',    'label' => 'Fidelización',       'icon' => 'gift',             'sort_order' => 70, 'addon_price' => 9.99],
            ['key' => 'builder',    'label' => 'Constructor de sitios web', 'icon' => 'layout-template', 'sort_order' => 80, 'addon_price' => 14.99],
        ];

        foreach ($modules as $module) {
            Module::query()->updateOrCreate(
                ['key' => $module['key']],
                array_merge([
                    'description' => null,
                    'addon_price' => 0,
                    'is_active' => true,
                ], $module),
            );
        }
    }
}
