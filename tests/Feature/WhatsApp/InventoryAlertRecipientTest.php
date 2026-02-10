<?php

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappWhitelist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('manage-settings', fn () => true);

    if (function_exists('settings_forget_cache')) {
        settings_forget_cache();
    }
});

it('forbids authenticated users without manage-settings ability', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    Gate::define('manage-settings', fn () => false);

    $item = WhatsappWhitelist::create([
        'phone_number' => '628111222333',
        'name' => 'Admin 1',
        'added_by' => 'seed',
        'receive_inventory_alerts' => true,
    ]);

    $response = $this->actingAs($user)->patchJson(
        route('whatsapp.settings.whitelist.inventory-alert', ['whitelist' => $item->id]),
        ['receive_inventory_alerts' => false]
    );

    $response->assertForbidden();
});

it('allows an authenticated user to toggle inventory alert recipients', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $item = WhatsappWhitelist::create([
        'phone_number' => '628111222333',
        'name' => 'Admin 1',
        'added_by' => 'seed',
        'receive_inventory_alerts' => true,
    ]);

    $response = $this->actingAs($user)->patchJson(
        route('whatsapp.settings.whitelist.inventory-alert', ['whitelist' => $item->id]),
        ['receive_inventory_alerts' => false]
    );

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'item' => [
            'id' => $item->id,
            'phone_number' => '628111222333',
            'receive_inventory_alerts' => false,
        ],
    ]);

    $this->assertDatabaseHas('whatsapp_whitelists', [
        'id' => $item->id,
        'receive_inventory_alerts' => 0,
    ]);

    $response2 = $this->actingAs($user)->patchJson(
        route('whatsapp.settings.whitelist.inventory-alert', ['whitelist' => $item->id]),
        ['receive_inventory_alerts' => true]
    );

    $response2->assertOk();
    $response2->assertJson([
        'success' => true,
        'item' => [
            'id' => $item->id,
            'phone_number' => '628111222333',
            'receive_inventory_alerts' => true,
        ],
    ]);

    $this->assertDatabaseHas('whatsapp_whitelists', [
        'id' => $item->id,
        'receive_inventory_alerts' => 1,
    ]);
});

it('redirects unauthenticated users to login', function () {
    /** @var \Tests\TestCase $this */
    $item = WhatsappWhitelist::create([
        'phone_number' => '628111222333',
        'name' => 'Admin 1',
        'added_by' => 'seed',
        'receive_inventory_alerts' => true,
    ]);

    $response = $this->patch(route('whatsapp.settings.whitelist.inventory-alert', ['whitelist' => $item->id]), [
        'receive_inventory_alerts' => false,
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/login');
});

it('validates receive_inventory_alerts is required', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $item = WhatsappWhitelist::create([
        'phone_number' => '628111222333',
        'name' => 'Admin 1',
        'added_by' => 'seed',
        'receive_inventory_alerts' => true,
    ]);

    $response = $this->actingAs($user)->patchJson(
        route('whatsapp.settings.whitelist.inventory-alert', ['whitelist' => $item->id]),
        []
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['receive_inventory_alerts']);
});

it('rejects turning off inventory alerts for the super admin number', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    SystemSetting::updateOrCreate(
        ['key' => 'notifications.whatsapp.admin_number'],
        ['value' => '+62 859-5659-2404']
    );

    if (function_exists('settings_forget_cache')) {
        settings_forget_cache();
    }

    $item = WhatsappWhitelist::create([
        'phone_number' => '6285956592404',
        'name' => 'Super Admin',
        'added_by' => 'seed',
        'receive_inventory_alerts' => true,
    ]);

    $response = $this->actingAs($user)->patchJson(
        route('whatsapp.settings.whitelist.inventory-alert', ['whitelist' => $item->id]),
        ['receive_inventory_alerts' => false]
    );

    $response->assertStatus(422);

    $this->assertDatabaseHas('whatsapp_whitelists', [
        'id' => $item->id,
        'receive_inventory_alerts' => 1,
    ]);
});
