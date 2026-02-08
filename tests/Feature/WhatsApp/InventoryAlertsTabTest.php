<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('manage-settings', fn () => true);
});

it('inventory alerts endpoint returns preview and history payload', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('whatsapp.inventory-alerts'));

    $response->assertOk();
    $response->assertJsonStructure([
        'expiry_days',
        'low_stock',
        'expiring',
        'history' => ['data', 'current_page', 'last_page', 'total'],
    ]);
});
