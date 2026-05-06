<?php

namespace Tests\Feature\Seeders;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_seeder_grants_all_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'labmutufarmapol@gmail.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin-lpmf', $admin->role);

        $expectedPermissionIds = Permission::pluck('id')->sort()->values()->all();
        $grantedPermissionIds = $admin->permissions()->pluck('permissions.id')->sort()->values()->all();

        $this->assertSame($expectedPermissionIds, $grantedPermissionIds);
    }
}
