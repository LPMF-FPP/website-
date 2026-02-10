<?php

use App\Models\User;
use App\Models\WhatsappWhitelist;
use App\Services\WhatsApp\WhitelistService;
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
        'recipients' => [
            '*' => ['id', 'phone_number', 'name', 'receive_inventory_alerts', 'is_super_admin'],
        ],
        'history' => ['data', 'current_page', 'last_page', 'total'],
    ]);
});

it('inventory alerts endpoint always includes the super admin recipient', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('whatsapp.inventory-alerts'));

    $response->assertOk();

    $recipients = $response->json('recipients');
    expect($recipients)->toBeArray();

    $superAdmin = collect($recipients)->firstWhere('is_super_admin', true);
    expect($superAdmin)->not->toBeNull();

    $expectedSuperAdminNumber = app(WhitelistService::class)->normalizePhoneNumber(
        (string) settings('notifications.whatsapp.admin_number', '6285956592404')
    );

    expect($superAdmin['phone_number'])->toBe($expectedSuperAdminNumber);
    expect($superAdmin['receive_inventory_alerts'])->toBeTrue();
});

it('inventory alerts endpoint marks legacy whitelist super admin row and forces receive_inventory_alerts true', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $legacySuperAdmin = WhatsappWhitelist::create([
        'phone_number' => settings('notifications.whatsapp.admin_number', '6285956592404'),
        'name' => 'Legacy Admin',
        'added_by' => 'seed',
        'receive_inventory_alerts' => false,
    ]);

    WhatsappWhitelist::create([
        'phone_number' => '6281111111111',
        'name' => 'Other Admin',
        'added_by' => 'seed',
        'receive_inventory_alerts' => false,
    ]);

    $response = $this->actingAs($user)->getJson(route('whatsapp.inventory-alerts'));

    $response->assertOk();

    $recipients = $response->json('recipients');

    $superAdmins = collect($recipients)->where('is_super_admin', true)->values();
    expect($superAdmins)->toHaveCount(1);

    $superAdmin = $superAdmins->first();
    expect($superAdmin['id'])->toBe($legacySuperAdmin->id);
    expect($superAdmin['receive_inventory_alerts'])->toBeTrue();
});
