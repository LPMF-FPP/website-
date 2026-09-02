<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('grants all GOWA updater permissions only to the admin role by default', function (): void {
    $permissionIds = Permission::query()
        ->where('module', 'gowa-update')
        ->pluck('id');

    expect($permissionIds)->toHaveCount(5)
        ->and(RolePermission::query()->where('role', 'admin')->whereIn('permission_id', $permissionIds)->count())->toBe(5)
        ->and(RolePermission::query()->where('role', 'supervisor')->whereIn('permission_id', $permissionIds)->count())->toBe(0);

    $admin = User::factory()->create(['role' => 'admin']);
    $supervisor = User::factory()->create(['role' => 'supervisor']);

    foreach (Permission::query()->where('module', 'gowa-update')->pluck('name') as $permission) {
        expect($admin->hasPermission($permission))->toBeTrue()
            ->and($supervisor->hasPermission($permission))->toBeFalse();
    }
});
