<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\SystemSetting;
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

    public function test_admin_can_rename_custom_role_and_migrate_users_and_permissions(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        settings_fake([
            'security.available_roles' => [
                'analis',
                'penyelia',
                'manajer_teknis',
                'admin',
                'auditor_mutu',
            ],
        ]);

        $permission = Permission::query()->create([
            'name' => 'dashboard.view',
            'display_name' => 'Lihat Dashboard',
            'module' => 'dashboard',
            'action' => 'view',
        ]);

        RolePermission::query()->create([
            'role' => 'auditor_mutu',
            'permission_id' => $permission->id,
        ]);

        $member = User::factory()->create([
            'role' => 'auditor_mutu',
        ]);

        $this->actingAs($admin)
            ->patch(route('analysts.roles.update', ['role' => 'auditor_mutu']), [
                'role_name' => 'Auditor Utama',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'role' => 'auditor_utama',
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role' => 'auditor_utama',
            'permission_id' => $permission->id,
        ]);

        $this->assertDatabaseMissing('role_permissions', [
            'role' => 'auditor_mutu',
            'permission_id' => $permission->id,
        ]);

        settings_fake_clear();
        $availableRoles = SystemSetting::query()->where('key', 'security.available_roles')->value('value');
        $this->assertIsArray($availableRoles);
        $this->assertContains('auditor_utama', $availableRoles);
        $this->assertNotContains('auditor_mutu', $availableRoles);
    }

    public function test_admin_can_delete_custom_role_when_unused(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        settings_fake([
            'security.available_roles' => [
                'analis',
                'penyelia',
                'manajer_teknis',
                'admin',
                'role_uji_hapus',
            ],
        ]);

        $permission = Permission::query()->create([
            'name' => 'tracking.view',
            'display_name' => 'Lihat Tracking',
            'module' => 'tracking',
            'action' => 'view',
        ]);

        RolePermission::query()->create([
            'role' => 'role_uji_hapus',
            'permission_id' => $permission->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('analysts.roles.destroy', ['role' => 'role_uji_hapus']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseMissing('role_permissions', [
            'role' => 'role_uji_hapus',
            'permission_id' => $permission->id,
        ]);

        settings_fake_clear();
        $availableRoles = SystemSetting::query()->where('key', 'security.available_roles')->value('value');
        $this->assertIsArray($availableRoles);
        $this->assertNotContains('role_uji_hapus', $availableRoles);
    }

    public function test_admin_cannot_rename_core_role(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        settings_fake([
            'security.available_roles' => [
                'analis',
                'penyelia',
                'manajer_teknis',
                'admin',
            ],
        ]);

        $this->actingAs($admin)
            ->patch(route('analysts.roles.update', ['role' => 'admin']), [
                'role_name' => 'Administrator Baru',
            ])
            ->assertSessionHasErrors('role_name')
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', [
            'key' => 'security.available_roles',
        ]);
    }

    public function test_admin_cannot_delete_role_that_is_still_used_by_users(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        settings_fake([
            'security.available_roles' => [
                'analis',
                'penyelia',
                'manajer_teknis',
                'admin',
                'auditor_mutu',
            ],
        ]);

        $permission = Permission::query()->create([
            'name' => 'dashboard.view',
            'display_name' => 'Lihat Dashboard',
            'module' => 'dashboard',
            'action' => 'view',
        ]);

        RolePermission::query()->create([
            'role' => 'auditor_mutu',
            'permission_id' => $permission->id,
        ]);

        User::factory()->create([
            'role' => 'auditor_mutu',
        ]);

        $this->actingAs($admin)
            ->delete(route('analysts.roles.destroy', ['role' => 'auditor_mutu']))
            ->assertSessionHasErrors('role_name')
            ->assertRedirect();

        $this->assertDatabaseHas('role_permissions', [
            'role' => 'auditor_mutu',
            'permission_id' => $permission->id,
        ]);
    }

    public function test_admin_cannot_rename_role_to_an_existing_role_name(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        settings_fake([
            'security.available_roles' => [
                'analis',
                'penyelia',
                'manajer_teknis',
                'admin',
                'auditor_mutu',
            ],
        ]);

        $this->actingAs($admin)
            ->patch(route('analysts.roles.update', ['role' => 'auditor_mutu']), [
                'role_name' => 'Admin',
            ])
            ->assertSessionHasErrors('role_name')
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', [
            'key' => 'security.available_roles',
        ]);
    }
}
