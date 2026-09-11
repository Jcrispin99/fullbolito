<?php

declare(strict_types=1);

use App\Models\Site;

it('returns the public site and homepage in one request', function (): void {
    $site = Site::query()->create([
        'name' => 'Complejo Demo',
        'status' => 'published',
    ]);

    $homepage = $site->pages()->create([
        'title' => 'Inicio',
        'slug' => 'home',
        'status' => 'published',
        'is_homepage' => true,
    ]);

    $homepage->sections()->create([
        'site_id' => $site->id,
        'block_type_key' => 'text',
        'sort_order' => 1,
        'layout' => [],
        'content' => ['text' => 'Bienvenido'],
    ]);

    $this->tenantGetJson('/api/v1/public/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.site.name', 'Complejo Demo')
        ->assertJsonPath('data.page.slug', 'home')
        ->assertJsonPath('data.page.is_homepage', true)
        ->assertJsonPath('data.page.sections.0.content.text', 'Bienvenido');
});
