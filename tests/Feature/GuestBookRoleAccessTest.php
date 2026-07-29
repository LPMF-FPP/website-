<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GuestBookRoleAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_existing_custom_role_can_create_guest_book_entries(): void
    {
        $user = User::factory()->create(['role' => 'role_pencatat_production']);

        $this->assertFalse($user->hasPermission('guest-book.create'));

        $migration = require database_path(
            'migrations/2026_07_28_000002_grant_guest_book_create_to_all_roles.php'
        );
        $migration->up();

        $this->assertTrue($user->hasPermission('guest-book.create'));

        $this->actingAs($user)
            ->get(route('guest-book.create'))
            ->assertOk();

        $this->actingAs($user)
            ->from(route('guest-book.create'))
            ->post(route('guest-book.store'))
            ->assertRedirect(route('guest-book.create'))
            ->assertSessionHasErrors('visit_date');
    }

    public function test_existing_custom_role_receives_guest_book_view_access(): void
    {
        $user = User::factory()->create(['role' => 'role_kustom_production']);

        $this->assertFalse($user->hasPermission('guest-book.view'));

        $migration = require database_path(
            'migrations/2026_07_28_000001_grant_guest_book_view_to_all_roles.php'
        );
        $migration->up();

        $this->assertTrue($user->hasPermission('guest-book.view'));

        $this->actingAs($user)
            ->get(route('guest-book.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Buku Tamu');
    }

    public function test_explicit_user_revocation_remains_effective(): void
    {
        $migration = require database_path(
            'migrations/2026_07_28_000001_grant_guest_book_view_to_all_roles.php'
        );
        $migration->up();

        $permission = Permission::where('name', 'guest-book.view')->firstOrFail();
        $user = User::factory()->create(['role' => 'role_dengan_pengecualian']);

        RolePermission::firstOrCreate([
            'role' => $user->role,
            'permission_id' => $permission->id,
        ]);
        $user->revokePermission('guest-book.view');

        $migration->up();

        $this->assertFalse($user->hasPermission('guest-book.view'));

        $this->actingAs($user)
            ->get(route('guest-book.index'))
            ->assertRedirect()
            ->assertSessionHas('error', 'Anda tidak memiliki akses ke halaman ini.');
    }
}
