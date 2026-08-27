<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantGowaRequestPermission(User $user): void
{
    $permission = Permission::firstOrCreate(['name' => 'gowa-update.request'], [
        'display_name' => 'Request GOWA update',
        'module' => 'gowa-update',
        'action' => 'request',
    ]);
    $user->permissions()->syncWithoutDetaching([$permission->id => ['granted' => true]]);
}

it('denies unauthenticated update reads without operation metadata', function (): void {
    $response = $this->getJson(route('whatsapp.updates.status'));

    $response->assertUnauthorized();
});

it('rejects an update request without explicit confirmation', function (): void {
    $user = User::factory()->create(['role' => 'admin']);
    grantGowaRequestPermission($user);

    $response = $this->actingAs($user)->postJson(route('whatsapp.updates.request'), [
        'release_id' => 'approved-release',
        'action_uuid' => '00000000-0000-4000-8000-000000000000',
        'confirmation' => false,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('confirmation');
});

it('keeps update routes inside the web CSRF middleware group', function (): void {
    $route = app('router')->getRoutes()->getByName('whatsapp.updates.request');

    expect($route?->gatherMiddleware())->toContain('web')
        ->and($route?->gatherMiddleware())->toContain('throttle:gowa-update')
        ->and($route?->gatherMiddleware())->toContain('permission:gowa-update.request');
});
