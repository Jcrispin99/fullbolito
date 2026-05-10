<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Cubre el flujo de autenticación que vive bajo `routes/api.php` y comparte
 * el mismo controlador para central y tenant. Estos tests corren en contexto
 * de tenant: el usuario se crea en la BD del tenant, se loguea contra el host
 * del tenant, y todo recurso protegido se valida desde el subdominio.
 *
 * Nota: el grupo de auth está limitado a 5/min; cada test resetea el limiter
 * para evitar falsos negativos cuando varios tests golpean el mismo path.
 */
beforeEach(function (): void {
    RateLimiter::clear('login|127.0.0.1');
    RateLimiter::clear('register|127.0.0.1');
});

it('registers a new user and returns a token', function (): void {
    $payload = [
        'name' => 'Alice Tester',
        'email' => 'alice@example.com',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
    ];

    $response = $this->tenantPostJson('/api/register', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.user.email', 'alice@example.com')
        ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'token']]);

    expect(User::where('email', 'alice@example.com')->first())->not->toBeNull();
});

it('rejects registration when password confirmation does not match', function (): void {
    $this->tenantPostJson('/api/register', [
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'different',
    ])->assertStatus(422);
});

it('rejects registration with a duplicate email', function (): void {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->tenantPostJson('/api/register', [
        'name' => 'Dup',
        'email' => 'dup@example.com',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
    ])->assertStatus(422);
});

it('logs in a registered user with valid credentials', function (): void {
    User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('correct-pass-1'),
    ]);

    $response = $this->tenantPostJson('/api/login', [
        'email' => 'login@example.com',
        'password' => 'correct-pass-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'login@example.com')
        ->assertJsonStructure(['data' => ['token']]);
});

it('rejects login with wrong password', function (): void {
    User::factory()->create([
        'email' => 'wrong@example.com',
        'password' => Hash::make('correct-pass-1'),
    ]);

    $this->tenantPostJson('/api/login', [
        'email' => 'wrong@example.com',
        'password' => 'wrong-pass',
    ])->assertUnauthorized();
});

it('rejects login when the user does not exist', function (): void {
    $this->tenantPostJson('/api/login', [
        'email' => 'ghost@example.com',
        'password' => 'whatever',
    ])->assertUnauthorized();
});

it('returns the authenticated user on /me', function (): void {
    $user = User::factory()->create();
    $this->actingAsTenantUser($user);

    $this->tenantGetJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('returns 401 on /me without a token', function (): void {
    $this->tenantGetJson('/api/me')->assertUnauthorized();
});

it('logs out the current user', function (): void {
    $user = User::factory()->create();
    $this->actingAsTenantUser($user);

    $this->tenantPostJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('success', true);
});
