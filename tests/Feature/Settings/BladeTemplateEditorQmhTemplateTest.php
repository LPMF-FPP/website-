<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists qmh-document as an editable blade template', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = test()->actingAs($admin)->getJson('/api/settings/blade-templates');

    $response->assertOk();

    $keys = collect($response->json('templates'))->pluck('key')->all();

    expect($keys)->toContain('qmh-document');
});
