<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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

    $this->postJson(route('whatsapp.updates.prepare'), [
        'action_uuid' => '00000000-0000-4000-8000-000000000099',
    ])->assertUnauthorized();
});

it('rejects an update request without explicit confirmation', function (): void {
    $user = User::factory()->create(['role' => 'admin']);
    grantGowaRequestPermission($user);

    $response = $this->actingAs($user)->postJson(route('whatsapp.updates.request'), [
        'preparation_id' => '00000000-0000-4000-8000-000000000021',
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

    $checkRoute = app('router')->getRoutes()->getByName('whatsapp.updates.check');
    expect($checkRoute?->gatherMiddleware())->toContain('web')
        ->and($checkRoute?->gatherMiddleware())->toContain('throttle:gowa-update-check')
        ->and($checkRoute?->gatherMiddleware())->toContain('permission:gowa-update.status');

    $prepareRoute = app('router')->getRoutes()->getByName('whatsapp.updates.prepare');
    expect($prepareRoute?->gatherMiddleware())->toContain('web')
        ->and($prepareRoute?->gatherMiddleware())->toContain('throttle:gowa-update')
        ->and($prepareRoute?->gatherMiddleware())->toContain('permission:gowa-update.request');

    $preparationRoute = app('router')->getRoutes()->getByName('whatsapp.updates.preparations.show');
    expect($preparationRoute?->gatherMiddleware())->toContain('permission:gowa-update.status');
});

it('audits feature-scoped validation rejections without exposing request secrets', function (): void {
    $user = User::factory()->create(['role' => 'admin']);
    grantGowaRequestPermission($user);

    $this->actingAs($user)->postJson(route('whatsapp.updates.request'), [
        'preparation_id' => 'not-a-uuid',
        'action_uuid' => 'not-a-uuid',
        'confirmation' => false,
    ])->assertUnprocessable();

    expect(\App\Models\ActivityLog::query()->where('action', 'GOWA_UPDATE_HTTP_REJECTED')->latest()->first()?->meta)
        ->toMatchArray(['status' => 422]);
});

it('returns separate read and mutation capability flags', function (): void {
    $user = User::factory()->create(['role' => 'admin']);
    foreach (['gowa-update.status', 'gowa-update.request', 'gowa-update.detail'] as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], [
            'display_name' => $name,
            'module' => 'gowa-update',
            'action' => 'test',
        ]);
        $user->permissions()->syncWithoutDetaching([$permission->id => ['granted' => true]]);
    }

    $response = $this->actingAs($user)->getJson(route('whatsapp.updates.status'));

    $response->assertOk()->assertJsonPath('data.can_request', true)
        ->assertJsonPath('data.can_retry', false)
        ->assertJsonPath('data.can_detail', true);
});

it('returns a safe response when the upstream check cannot connect', function (): void {
    $user = User::factory()->create(['role' => 'admin']);
    foreach (['gowa-update.status'] as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => 'gowa-update', 'action' => 'status']);
        $user->permissions()->syncWithoutDetaching([$permission->id => ['granted' => true]]);
    }
    Http::fake(fn () => throw new ConnectionException('offline'));

    $this->actingAs($user)->getJson(route('whatsapp.updates.check'))
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'upstream_release_unavailable');
});
