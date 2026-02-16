<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_new_role_type_and_use_it_for_staff_creation(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $permissionView = Permission::query()->create([
            'name' => 'dashboard.view',
            'display_name' => 'Lihat Dashboard',
            'module' => 'dashboard',
            'action' => 'view',
        ]);

        $permissionCreate = Permission::query()->create([
            'name' => 'pengujian.create',
            'display_name' => 'Tambah Pengujian',
            'module' => 'pengujian',
            'action' => 'create',
        ]);

        RolePermission::query()->create([
            'role' => 'analis',
            'permission_id' => $permissionView->id,
        ]);
        RolePermission::query()->create([
            'role' => 'analis',
            'permission_id' => $permissionCreate->id,
        ]);

        $this->actingAs($admin)
            ->post(route('analysts.roles.store'), [
                'role_name' => 'Auditor Mutu',
                'clone_from' => 'analis',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $availableRoles = settings('security.available_roles', []);
        $this->assertIsArray($availableRoles);
        $this->assertContains('auditor_mutu', $availableRoles);

        $this->assertDatabaseHas('role_permissions', [
            'role' => 'auditor_mutu',
            'permission_id' => $permissionView->id,
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role' => 'auditor_mutu',
            'permission_id' => $permissionCreate->id,
        ]);

        $email = 'auditor-'.uniqid().'@example.com';

        $this->actingAs($admin)
            ->post(route('analysts.store'), [
                'name' => 'User Auditor',
                'email' => $email,
                'role' => 'auditor_mutu',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('analysts.index'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'role' => 'auditor_mutu',
        ]);
    }
}
