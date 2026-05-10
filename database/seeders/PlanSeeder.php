<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Módulos addon-only (loyalty, builder, transfers) NUNCA se incluyen
        // en planes — siempre se compran como add-on.
        $plans = [
            [
                'name' => 'Free Trial',
                'slug' => 'free-trial',
                'price' => 0.00,
                'duration_days' => 14,
                'is_active' => true,
                'modules' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
            ],
            [
                'name' => 'Básico Mensual',
                'slug' => 'basico-mensual',
                'price' => 29.99,
                'duration_days' => 30,
                'is_active' => true,
                'modules' => ['dashboard', 'inventory', 'sales', 'pos'],
            ],
            [
                'name' => 'Pro Mensual',
                'slug' => 'pro-mensual',
                'price' => 59.99,
                'duration_days' => 30,
                'is_active' => true,
                'modules' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
            ],
            [
                'name' => 'Enterprise Anual',
                'slug' => 'enterprise-anual',
                'price' => 499.99,
                'duration_days' => 365,
                'is_active' => true,
                'modules' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
            ],
        ];

        foreach ($plans as $data) {
            $modules = $data['modules'];
            unset($data['modules']);
            $data['includes_all_modules'] = false;

            $plan = Plan::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );

            $moduleIds = Module::query()->whereIn('key', $modules)->pluck('id')->all();
            $plan->modules()->sync($moduleIds);
        }
    }
}
