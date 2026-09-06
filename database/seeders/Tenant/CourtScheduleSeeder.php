<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Court;
use App\Models\CourtSchedule;
use Illuminate\Database\Seeder;

final class CourtScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $courts = Court::query()->where('is_active', true)->get();

        if ($courts->isEmpty()) {
            $this->command?->warn('CourtScheduleSeeder: no hay canchas, se omite.');

            return;
        }

        // Plantilla de franjas: matutina, vespertina, nocturna (premium en weekends/noche).
        $weekdaySlots = [
            ['start' => '06:00', 'end' => '12:00', 'priceMultiplier' => 1.0],
            ['start' => '12:00', 'end' => '18:00', 'priceMultiplier' => 1.1],
            ['start' => '18:00', 'end' => '23:00', 'priceMultiplier' => 1.3], // nocturno
        ];

        $weekendSlots = [
            ['start' => '08:00', 'end' => '14:00', 'priceMultiplier' => 1.2],
            ['start' => '14:00', 'end' => '20:00', 'priceMultiplier' => 1.4],
            ['start' => '20:00', 'end' => '23:00', 'priceMultiplier' => 1.5],
        ];

        $weekdays = [1, 2, 3, 4, 5]; // Lun-Vie
        $weekend = [0, 6];           // Dom + Sáb

        $created = 0;

        foreach ($courts as $court) {
            $basePrice = (float) ($court->productProduct?->price ?? 50);

            // Limpiamos cualquier horario previo para que el seeder sea idempotente
            CourtSchedule::query()->where('court_id', $court->id)->delete();

            foreach ($weekdays as $day) {
                foreach ($weekdaySlots as $slot) {
                    CourtSchedule::create([
                        'court_id' => $court->id,
                        'day_of_week' => $day,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'price' => round($basePrice * $slot['priceMultiplier'], 2),
                        'slot_duration_minutes' => $court->slot_duration_minutes,
                        'is_active' => true,
                    ]);
                    $created++;
                }
            }

            foreach ($weekend as $day) {
                foreach ($weekendSlots as $slot) {
                    CourtSchedule::create([
                        'court_id' => $court->id,
                        'day_of_week' => $day,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'price' => round($basePrice * $slot['priceMultiplier'], 2),
                        'slot_duration_minutes' => $court->slot_duration_minutes,
                        'is_active' => true,
                    ]);
                    $created++;
                }
            }
        }

        $this->command?->info("✅ CourtScheduleSeeder: {$created} horarios creados para {$courts->count()} canchas.");
    }
}
