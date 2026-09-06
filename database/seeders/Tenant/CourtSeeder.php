<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Category;
use App\Models\Company;
use App\Models\Court;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CourtSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->whereNull('parent_id')->first()
            ?? Company::query()->first();

        if (! $company) {
            $this->command?->warn('CourtSeeder: no hay companies, se omite.');

            return;
        }

        $category = Category::query()
            ->where('name', 'Canchas')
            ->orWhere('name', 'Deportes y Aire Libre')
            ->orWhere('name', 'Servicios')
            ->first();

        if (! $category) {
            $category = Category::create([
                'name' => 'Canchas',
                'is_active' => true,
            ]);
        }

        $demo = [
            [
                'name' => 'Cancha 1 - Fútbol 7',
                'code' => 'C-01',
                'sport' => 'futbol_7',
                'surface' => 'gras_sintetico',
                'capacity' => 14,
                'slot_duration_minutes' => 60,
                'price' => 80.00,
                'description' => 'Cancha techada con iluminación LED.',
            ],
            [
                'name' => 'Cancha 2 - Fútbol 7',
                'code' => 'C-02',
                'sport' => 'futbol_7',
                'surface' => 'gras_sintetico',
                'capacity' => 14,
                'slot_duration_minutes' => 60,
                'price' => 80.00,
                'description' => 'Cancha techada con iluminación LED.',
            ],
            [
                'name' => 'Cancha 3 - Pádel',
                'code' => 'P-01',
                'sport' => 'padel',
                'surface' => 'cemento',
                'capacity' => 4,
                'slot_duration_minutes' => 90,
                'price' => 60.00,
                'description' => 'Cancha de pádel con paredes de cristal.',
            ],
            [
                'name' => 'Cancha 4 - Fulbito',
                'code' => 'F-01',
                'sport' => 'futsal',
                'surface' => 'parquet',
                'capacity' => 10,
                'slot_duration_minutes' => 60,
                'price' => 70.00,
                'description' => 'Cancha de fulbito al aire libre.',
            ],
        ];

        $created = 0;

        foreach ($demo as $data) {
            if (Court::query()->where('company_id', $company->id)->where('code', $data['code'])->exists()) {
                continue;
            }

            DB::transaction(function () use ($data, $company, $category, &$created): void {
                $template = ProductTemplate::create([
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'category_id' => $category->id,
                    'is_active' => true,
                    'is_service' => true,
                    'tracks_inventory' => false,
                    'is_pos_visible' => false,
                    'tracked_by_lot' => false,
                ]);

                $product = ProductProduct::create([
                    'product_template_id' => $template->id,
                    'sku' => 'COURT-'.Str::upper(Str::random(8)),
                    'price' => $data['price'],
                    'cost_price' => 0,
                    'is_principal' => true,
                ]);

                Court::create([
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']),
                    'code' => $data['code'],
                    'description' => $data['description'],
                    'sport' => $data['sport'],
                    'surface' => $data['surface'],
                    'capacity' => $data['capacity'],
                    'slot_duration_minutes' => $data['slot_duration_minutes'],
                    'company_id' => $company->id,
                    'product_product_id' => $product->id,
                    'is_active' => true,
                ]);

                $created++;
            });
        }

        $this->command?->info("✅ CourtSeeder: {$created} canchas creadas.");
    }
}
