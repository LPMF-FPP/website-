<?php

use App\Models\User;
use App\Models\WhatsappWhitelist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('manage-settings', fn () => true);
});

it('returns whitelist and super admin payload', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    WhatsappWhitelist::create([
        'phone_number' => '628111222333',
        'name' => 'Admin 1',
        'added_by' => 'seed',
    ]);

    $response = $this->actingAs($user)->getJson('/whatsapp/settings/whitelist');

    $response->assertOk();
    $response->assertJsonStructure([
        'whitelist',
        'super_admin' => ['phone_number', 'name'],
    ]);
    $response->assertJsonFragment(['phone_number' => '628111222333']);
});

it('adds a whitelist admin via web ui', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/whatsapp/settings/whitelist', [
        'phone' => '08123456789',
        'name' => 'Admin A',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['success' => true]);

    $this->assertDatabaseHas('whatsapp_whitelists', [
        'phone_number' => '628123456789',
        'name' => 'Admin A',
    ]);
});

it('rejects duplicate phone number', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    WhatsappWhitelist::create([
        'phone_number' => '628123456789',
        'name' => 'Admin A',
        'added_by' => 'seed',
    ]);

    $response = $this->actingAs($user)->postJson('/whatsapp/settings/whitelist', [
        'phone' => '08123456789',
        'name' => 'Admin A 2',
    ]);

    $response->assertStatus(422);
});

it('rejects adding super admin number', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/whatsapp/settings/whitelist', [
        'phone' => '6285956592404',
        'name' => 'Super Admin',
    ]);

    $response->assertStatus(422);
});

it('removes a whitelisted admin', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $item = WhatsappWhitelist::create([
        'phone_number' => '628777888999',
        'name' => 'Admin Del',
        'added_by' => 'seed',
    ]);

    $response = $this->actingAs($user)->deleteJson('/whatsapp/settings/whitelist/'.$item->id);

    $response->assertOk();
    $response->assertJsonFragment(['success' => true]);

    $this->assertDatabaseMissing('whatsapp_whitelists', [
        'id' => $item->id,
    ]);
});
