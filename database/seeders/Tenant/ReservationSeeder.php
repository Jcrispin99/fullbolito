<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Court;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\Reservation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Genera reservas demo a partir del momento exacto en que se ejecuta
 * el seeder (today = now()). Crea pasado, presente y futuro relativo:
 *
 *   - Últimos 7 días: reservas en estado `played`, `no_show` y `cancelled`
 *   - Próximos 14 días: mezcla de `held`, `confirmed` y `paid`
 *
 * Es idempotente: limpia reservas previas con notas "DEMO_SEED" antes
 * de regenerar, así puedes correrlo varias veces sin acumular basura.
 */
final class ReservationSeeder extends Seeder
{
    private const DEMO_TAG = 'DEMO_SEED';

    public function run(): void
    {
        $courts = Court::query()
            ->where('is_active', true)
            ->with('productProduct')
            ->get();

        if ($courts->isEmpty()) {
            $this->command?->warn('ReservationSeeder: no hay canchas activas, se omite.');

            return;
        }

        $partners = Partner::query()
            ->where('is_customer', true)
            ->where('status', 'active')
            ->take(20)
            ->get();

        if ($partners->isEmpty()) {
            $this->command?->warn('ReservationSeeder: no hay partners customer, se omite.');

            return;
        }

        // Limpieza idempotente de reservas demo previas
        $deleted = Reservation::query()
            ->where('notes', 'like', '%'.self::DEMO_TAG.'%')
            ->delete();

        if ($deleted > 0) {
            $this->command?->line("   ↺ ReservationSeeder: limpiadas {$deleted} reservas demo previas.");
        }

        $created = 0;
        $skipped = 0;

        // -------- PASADO (últimos 7 días) --------
        $pastStatuses = ['played', 'played', 'played', 'no_show', 'cancelled'];
        for ($daysAgo = 7; $daysAgo >= 1; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo);
            foreach ($courts as $court) {
                // 1-3 reservas por cancha en cada día pasado
                $reservationsForDay = rand(1, 3);
                $usedHours = [];
                for ($i = 0; $i < $reservationsForDay; $i++) {
                    $hour = $this->pickFreeHour($usedHours, 8, 22);
                    if ($hour === null) {
                        continue;
                    }
                    $usedHours[] = $hour;

                    $status = $pastStatuses[array_rand($pastStatuses)];
                    $r = $this->createReservation(
                        $court,
                        $partners->random(),
                        $date->copy()->setTime($hour, 0),
                        $status,
                    );
                    $r ? $created++ : $skipped++;
                }
            }
        }

        // -------- HOY --------
        $todayStatuses = ['held', 'confirmed', 'paid', 'confirmed', 'paid'];
        $now = Carbon::now();
        foreach ($courts as $court) {
            $usedHours = [];
            // 1-2 reservas pasadas hoy (mañana, ya jugadas si es noche)
            for ($i = 0; $i < 2; $i++) {
                $hour = $this->pickFreeHour($usedHours, 8, max(8, $now->hour - 2));
                if ($hour === null) {
                    break;
                }
                $usedHours[] = $hour;

                $r = $this->createReservation(
                    $court,
                    $partners->random(),
                    $now->copy()->setTime($hour, 0),
                    $now->hour > 14 ? 'played' : 'paid',
                );
                $r ? $created++ : $skipped++;
            }

            // 1-3 reservas futuras en lo que queda del día
            $reservationsForDay = rand(1, 3);
            for ($i = 0; $i < $reservationsForDay; $i++) {
                $hour = $this->pickFreeHour($usedHours, max($now->hour + 1, 9), 22);
                if ($hour === null) {
                    break;
                }
                $usedHours[] = $hour;

                $status = $todayStatuses[array_rand($todayStatuses)];
                $r = $this->createReservation(
                    $court,
                    $partners->random(),
                    $now->copy()->setTime($hour, 0),
                    $status,
                );
                $r ? $created++ : $skipped++;
            }
        }

        // -------- FUTURO (próximos 14 días) --------
        $futureStatuses = ['held', 'confirmed', 'confirmed', 'paid'];
        for ($daysAhead = 1; $daysAhead <= 14; $daysAhead++) {
            $date = Carbon::now()->addDays($daysAhead);
            foreach ($courts as $court) {
                $reservationsForDay = rand(2, 5);
                $usedHours = [];
                for ($i = 0; $i < $reservationsForDay; $i++) {
                    $hour = $this->pickFreeHour($usedHours, 7, 22);
                    if ($hour === null) {
                        continue;
                    }
                    $usedHours[] = $hour;

                    $status = $futureStatuses[array_rand($futureStatuses)];
                    $r = $this->createReservation(
                        $court,
                        $partners->random(),
                        $date->copy()->setTime($hour, 0),
                        $status,
                    );
                    $r ? $created++ : $skipped++;
                }
            }
        }

        $this->command?->info("✅ ReservationSeeder: {$created} reservas creadas".($skipped > 0 ? " ({$skipped} omitidas por solapamiento o config)." : '.'));
    }

    /**
     * Elige una hora aleatoria libre entre $min y $max, evitando las usadas.
     * Retorna null si no quedan horas libres.
     */
    private function pickFreeHour(array $used, int $min, int $max): ?int
    {
        $candidates = array_diff(range($min, $max), $used);
        if (empty($candidates)) {
            return null;
        }

        return $candidates[array_rand($candidates)];
    }

    private function createReservation(
        Court $court,
        Partner $partner,
        Carbon $startAt,
        string $status,
    ): ?Reservation {
        $journal = $this->resolveJournal($court->company_id, 'reservation');
        if (! $journal) {
            return null;
        }

        $slotMinutes = $court->slot_duration_minutes ?? 60;
        $endAt = $startAt->copy()->addMinutes($slotMinutes);

        // Defensa: evitar solapamientos accidentales
        $conflict = Reservation::query()
            ->where('court_id', $court->id)
            ->whereIn('status', ['held', 'confirmed', 'paid', 'played'])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->exists();

        if ($conflict) {
            return null;
        }

        $sequence = $journal->sequence;
        $serie = $journal->code;
        $correlative = str_pad(
            (string) $sequence->next_number,
            $sequence->sequence_size,
            '0',
            STR_PAD_LEFT,
        );
        $sequence->increment('next_number', $sequence->step);

        $price = (float) ($court->productProduct?->price ?? 50);

        $payload = [
            'journal_id' => $journal->id,
            'serie' => $serie,
            'correlative' => $correlative,
            'court_id' => $court->id,
            'company_id' => $court->company_id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => $status,
            'partner_id' => $partner->id,
            'customer_name' => $partner->name ?? $partner->business_name,
            'customer_phone' => $partner->phone,
            'customer_email' => $partner->email,
            'total' => $price,
            'notes' => self::DEMO_TAG,
            'held_until' => $status === 'held'
                ? Carbon::now()->addMinutes(10)
                : null,
            'cancelled_at' => $status === 'cancelled' ? $startAt->copy()->subHours(2) : null,
            'cancellation_reason' => $status === 'cancelled'
                ? 'Cliente no podrá asistir (demo)'
                : null,
        ];

        return Reservation::create($payload);
    }

    private function resolveJournal(int $companyId, string $type): ?Journal
    {
        return Journal::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->with('sequence')
            ->first()
            ?? Journal::query()
                ->where('type', $type)
                ->with('sequence')
                ->first();
    }
}
