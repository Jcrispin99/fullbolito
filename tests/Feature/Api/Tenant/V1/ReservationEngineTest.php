<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Court;
use App\Models\CourtSchedule;
use App\Models\Journal;
use App\Models\Reservation;
use App\Models\Sequence;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;

/**
 * Scaffold mínimo para el dominio de reservas: una company, una cancha
 * activa con su product_product asociado, un horario que cubre todo el
 * día para el day_of_week solicitado, y el journal 'reservation' (RES)
 * que PublicCourtController::resolveReservationJournal() necesita para
 * numerar las reservas públicas.
 */
function makeCourtScaffold(int $dayOfWeek): array
{
    $company = Company::factory()->create();

    $court = Court::factory()->for($company)->create();

    CourtSchedule::factory()->for($court)->create([
        'day_of_week' => $dayOfWeek,
        'start_time' => '08:00:00',
        'end_time' => '22:00:00',
        'price' => 50,
    ]);

    $journal = Journal::factory()->create([
        'type' => 'reservation',
        'code' => 'RES',
        'document_type_code' => null,
        'is_fiscal' => false,
        'company_id' => $company->id,
        'sequence_id' => Sequence::factory()->create(['prefix' => 'RES', 'sequence_size' => 8])->id,
    ]);

    return compact('company', 'court', 'journal');
}

/**
 * Próximo horario a las 10:00 cuyo day_of_week coincide con el schedule
 * del scaffold (evita crear reservas en el pasado o fuera de horario).
 */
function nextSlotStart(int $dayOfWeek): Carbon
{
    $date = now()->addDay()->startOfDay();
    while ((int) $date->dayOfWeek !== $dayOfWeek) {
        $date = $date->addDay();
    }

    return $date->setTime(10, 0);
}

it('rejects a public reservation that overlaps an existing held slot', function (): void {
    $dayOfWeek = (int) now()->addDay()->dayOfWeek;
    $scaffold = makeCourtScaffold($dayOfWeek);
    $start = nextSlotStart($dayOfWeek);

    $payload = [
        'start_at' => $start->toIso8601String(),
        'customer_name' => 'Juan Pérez',
        'customer_phone' => '987654321',
    ];

    $first = $this->tenantPostJson(
        "/api/v1/public/courts/{$scaffold['court']->id}/reservations",
        $payload,
    );
    $first->assertCreated()->assertJsonPath('data.status', 'held');

    $second = $this->tenantPostJson(
        "/api/v1/public/courts/{$scaffold['court']->id}/reservations",
        [...$payload, 'customer_name' => 'Otro Cliente'],
    );
    $second->assertStatus(409);

    expect(Reservation::query()->where('court_id', $scaffold['court']->id)->count())->toBe(1);
});

it('allows a new reservation on a slot whose previous hold already expired', function (): void {
    $dayOfWeek = (int) now()->addDay()->dayOfWeek;
    $scaffold = makeCourtScaffold($dayOfWeek);
    $start = nextSlotStart($dayOfWeek);

    Reservation::factory()->expiredHold()->create([
        'court_id' => $scaffold['court']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'start_at' => $start,
        'end_at' => $start->copy()->addHour(),
    ]);

    $availability = $this->tenantGetJson(
        "/api/v1/public/courts/{$scaffold['court']->id}/availability?date={$start->toDateString()}",
    );

    $availability->assertOk();
    $slot = collect($availability->json('data.slots'))
        ->firstWhere('start', $start->format('H:i'));

    expect($slot)->not->toBeNull()
        ->and($slot['available'])->toBeTrue()
        ->and($slot['status'])->toBe('available');

    $response = $this->tenantPostJson(
        "/api/v1/public/courts/{$scaffold['court']->id}/reservations",
        [
            'start_at' => $start->toIso8601String(),
            'customer_name' => 'Cliente Nuevo',
            'customer_phone' => '987654321',
        ],
    );

    $response->assertCreated();
});

it('cancels expired holds and leaves active reservations untouched via the cleanup command', function (): void {
    $dayOfWeek = (int) now()->addDay()->dayOfWeek;
    $scaffold = makeCourtScaffold($dayOfWeek);
    $start = nextSlotStart($dayOfWeek);

    $expired = Reservation::factory()->expiredHold()->create([
        'court_id' => $scaffold['court']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'start_at' => $start,
        'end_at' => $start->copy()->addHour(),
    ]);

    $stillHeld = Reservation::factory()->held()->create([
        'court_id' => $scaffold['court']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'start_at' => $start->copy()->addHours(2),
        'end_at' => $start->copy()->addHours(3),
    ]);

    $confirmed = Reservation::factory()->confirmed()->create([
        'court_id' => $scaffold['court']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'start_at' => $start->copy()->addHours(4),
        'end_at' => $start->copy()->addHours(5),
    ]);

    Artisan::call('reservations:cleanup-expired-holds');

    expect($expired->fresh()->status)->toBe('cancelled')
        ->and($expired->fresh()->cancellation_reason)->not->toBeNull()
        ->and($stillHeld->fresh()->status)->toBe('held')
        ->and($confirmed->fresh()->status)->toBe('confirmed');
});

it('rejects invalid state transitions on the admin reservation endpoints', function (): void {
    $this->actingAsTenantUser();
    $dayOfWeek = (int) now()->addDay()->dayOfWeek;
    $scaffold = makeCourtScaffold($dayOfWeek);
    $start = nextSlotStart($dayOfWeek);

    $cancelled = Reservation::factory()->cancelled()->create([
        'court_id' => $scaffold['court']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'start_at' => $start,
        'end_at' => $start->copy()->addHour(),
    ]);

    $this->tenantPatchJson("/api/v1/reservations/{$cancelled->id}/confirm")
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    $held = Reservation::factory()->held()->create([
        'court_id' => $scaffold['court']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'start_at' => $start->copy()->addHours(2),
        'end_at' => $start->copy()->addHours(3),
    ]);

    $this->tenantPatchJson("/api/v1/reservations/{$held->id}/mark-played")
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    expect($cancelled->fresh()->status)->toBe('cancelled')
        ->and($held->fresh()->status)->toBe('held');
});
