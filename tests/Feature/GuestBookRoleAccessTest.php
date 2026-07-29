<?php

namespace Tests\Feature;

use App\Models\GuestVisit;
use App\Models\Investigator;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GuestBookRoleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const ROLES = [
        'investigator',
        'analis',
        'penyelia',
        'manajer_teknis',
        'admin',
        'supervisor',
        'analyst',
        'lab_analyst',
        'petugas_lab',
    ];

    private const PERMISSIONS = [
        'guest-book.view' => ['display_name' => 'Lihat Buku Tamu', 'action' => 'view'],
        'guest-book.create' => ['display_name' => 'Tambah Buku Tamu', 'action' => 'create'],
        'guest-book.edit' => ['display_name' => 'Edit Buku Tamu', 'action' => 'edit'],
        'guest-book.checkout' => ['display_name' => 'Catat Keluar Buku Tamu', 'action' => 'checkout'],
        'guest-book.delete' => ['display_name' => 'Hapus Buku Tamu', 'action' => 'delete'],
        'guest-book.export' => ['display_name' => 'Export Buku Tamu', 'action' => 'export'],
    ];

    public function test_permission_seeder_grants_every_guest_book_permission_to_every_defined_role(): void
    {
        $existingPermissionIds = Permission::query()
            ->whereIn('name', self::permissionNames())
            ->pluck('id');

        RolePermission::query()
            ->whereIn('role', self::ROLES)
            ->whereIn('permission_id', $existingPermissionIds)
            ->delete();

        $this->seed(PermissionSeeder::class);

        $permissionIds = Permission::query()
            ->whereIn('name', self::permissionNames())
            ->pluck('id');

        $this->assertCount(count(self::PERMISSIONS), $permissionIds);

        foreach (self::ROLES as $role) {
            $this->assertSame(
                count(self::PERMISSIONS),
                RolePermission::query()
                    ->where('role', $role)
                    ->whereIn('permission_id', $permissionIds)
                    ->count(),
                "Role {$role} tidak menerima seluruh permission Buku Tamu."
            );

            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue(
                $user->hasAllPermissions(self::permissionNames()),
                "Permission Buku Tamu untuk role {$role} tidak efektif."
            );
        }
    }

    public function test_migration_upserts_permissions_and_idempotently_grants_hard_coded_and_dynamic_roles(): void
    {
        Permission::query()->whereIn('name', self::permissionNames())->delete();

        $user = User::factory()->create(['role' => 'role_pengguna_dinamis']);
        $markerPermission = Permission::query()->updateOrCreate(
            ['name' => 'test.dynamic-role-marker'],
            [
                'display_name' => 'Penanda Role Dinamis',
                'module' => 'test',
                'action' => 'view',
            ]
        );
        RolePermission::query()->create([
            'role' => 'role_tabel_dinamis',
            'permission_id' => $markerPermission->id,
        ]);

        $this->assertFalse($user->hasPermission('guest-book.checkout'));

        $migration = $this->migration();
        $migration->up();

        foreach (self::PERMISSIONS as $name => $details) {
            $this->assertDatabaseHas('permissions', [
                'name' => $name,
                'display_name' => $details['display_name'],
                'module' => 'guest-book',
                'action' => $details['action'],
            ]);
        }

        $permissionIds = Permission::query()
            ->whereIn('name', self::permissionNames())
            ->pluck('id');
        $roles = [...self::ROLES, 'role_pengguna_dinamis', 'role_tabel_dinamis'];

        foreach ($roles as $role) {
            $this->assertSame(
                count(self::PERMISSIONS),
                RolePermission::query()
                    ->where('role', $role)
                    ->whereIn('permission_id', $permissionIds)
                    ->count(),
                "Role {$role} tidak menerima seluruh permission Buku Tamu."
            );
        }

        $this->assertTrue($user->hasAllPermissions(self::permissionNames()));

        $grantCount = RolePermission::query()
            ->whereIn('role', $roles)
            ->whereIn('permission_id', $permissionIds)
            ->count();
        $permissionIdsBeforeRerun = $permissionIds->sort()->values()->all();

        $migration->up();

        $this->assertSame(
            $grantCount,
            RolePermission::query()
                ->whereIn('role', $roles)
                ->whereIn('permission_id', $permissionIds)
                ->count()
        );
        $this->assertSame(
            $permissionIdsBeforeRerun,
            Permission::query()
                ->whereIn('name', self::permissionNames())
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );
    }

    public function test_non_admin_dynamic_role_can_use_every_guest_book_route_family_and_checkout(): void
    {
        $user = User::factory()->create(['role' => 'role_petugas_buku_tamu']);
        $this->migration()->up();

        $visit = $this->createVisit($user);
        $visitToDelete = $this->createVisit($user);
        $investigator = Investigator::factory()->create();
        $visitWithInvestigator = $this->createVisit($user, $investigator);

        $this->actingAs($user)
            ->get(route('guest-book.index'))
            ->assertOk();
        $this->get(route('guest-book.show', $visit))->assertOk();
        $this->get(route('guest-book.create'))->assertOk();
        $this->get(route('guest-book.edit', $visit))->assertOk();

        $this->post(route('guest-book.store'), $this->validVisitPayload([
            'visitor_name' => 'Tamu Baru',
        ]))
            ->assertRedirect(route('guest-book.index'))
            ->assertSessionHas('success', 'Kunjungan berhasil dicatat.');
        $this->assertDatabaseHas('guest_visits', [
            'visitor_name' => 'Tamu Baru',
            'created_by' => $user->id,
        ]);

        $this->from(route('guest-book.edit', $visit))
            ->put(route('guest-book.update', $visit), $this->validVisitPayload([
                'visitor_name' => 'Tamu Diedit',
            ]))
            ->assertRedirect(route('guest-book.show', $visit))
            ->assertSessionHas('success', 'Data kunjungan berhasil diperbarui.');
        $this->assertSame('Tamu Diedit', $visit->fresh()->visitor_name);

        $this->from(route('guest-book.show', $visitWithInvestigator))
            ->patch(route('guest-book.visitor', $visitWithInvestigator), [
                'same_as_owner' => false,
                'visitor_name' => 'Tamu Diperbarui',
                'visitor_relation' => 'Rekan kerja',
            ])
            ->assertRedirect(route('guest-book.show', $visitWithInvestigator))
            ->assertSessionHas('success', 'Data pihak yang datang berhasil diverifikasi.');
        $this->assertSame('Tamu Diperbarui', $visitWithInvestigator->fresh()->visitor_name);

        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('download')
            ->once()
            ->with(\Mockery::type('string'))
            ->andReturn(response('pdf-content', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadHTML')
            ->once()
            ->with(\Mockery::type('string'))
            ->andReturn($mockPdf);

        $this->get(route('guest-book.monthly-report'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->from(route('guest-book.show', $visit))
            ->patch(route('guest-book.checkout', $visit))
            ->assertRedirect(route('guest-book.show', $visit))
            ->assertSessionHas('success', 'Tamu berhasil dicatat keluar.');

        $visit->refresh();
        $this->assertSame('checked_out', $visit->status);
        $this->assertNotNull($visit->check_out_at);

        $checkedOutAt = $visit->check_out_at->toJSON();
        $this->from(route('guest-book.show', $visit))
            ->patch(route('guest-book.checkout', $visit))
            ->assertRedirect(route('guest-book.show', $visit))
            ->assertSessionHas('error', 'Kunjungan ini sudah checkout sebelumnya.');
        $this->assertSame($checkedOutAt, $visit->fresh()->check_out_at->toJSON());

        $this->delete(route('guest-book.destroy', $visitToDelete))
            ->assertRedirect(route('guest-book.index'))
            ->assertSessionHas('success', 'Data kunjungan berhasil dihapus.');
        $this->assertSoftDeleted($visitToDelete);
    }

    public function test_explicit_user_revocation_survives_migration_rerun_and_remains_effective(): void
    {
        $user = User::factory()->create(['role' => 'role_dengan_pengecualian']);
        $migration = $this->migration();
        $migration->up();

        $user->revokePermission('guest-book.checkout');
        $this->assertFalse($user->hasPermission('guest-book.checkout'));
        $this->assertTrue($user->hasAllPermissions(
            array_values(array_diff(self::permissionNames(), ['guest-book.checkout']))
        ));

        $permission = Permission::query()->where('name', 'guest-book.checkout')->firstOrFail();
        $override = UserPermission::query()
            ->where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->firstOrFail();

        $migration->up();

        $this->assertDatabaseHas('user_permissions', [
            'id' => $override->id,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => false,
        ]);
        $this->assertFalse($user->hasPermission('guest-book.checkout'));

        $visit = $this->createVisit($user);

        $this->actingAs($user)
            ->from(route('guest-book.index'))
            ->patch(route('guest-book.checkout', $visit))
            ->assertRedirect(route('guest-book.index'))
            ->assertSessionHas('error', 'Anda tidak memiliki akses ke halaman ini.');

        $this->assertSame('active', $visit->fresh()->status);
    }

    private static function permissionNames(): array
    {
        return array_keys(self::PERMISSIONS);
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_07_29_000001_grant_all_guest_book_permissions_to_all_roles.php'
        );
    }

    private function createVisit(User $user, ?Investigator $investigator = null): GuestVisit
    {
        return GuestVisit::query()->create([
            'investigator_id' => $investigator?->id,
            'visit_date' => now()->toDateString(),
            'visit_time' => '09:00',
            'purpose' => 'Pelatihan',
            'visitor_name' => 'Tamu Pengujian',
            'visitor_identity' => 'ID-001',
            'visitor_institution' => 'Instansi Pengujian',
            'visitor_phone' => '081234567890',
            'created_by' => $user->id,
        ]);
    }

    private function validVisitPayload(array $overrides = []): array
    {
        return array_merge([
            'visit_date' => now()->toDateString(),
            'visit_time' => '09:00',
            'purpose' => 'Pelatihan',
            'visitor_name' => 'Tamu Pengujian',
            'visitor_identity' => 'ID-001',
            'visitor_institution' => 'Instansi Pengujian',
            'visitor_phone' => '081234567890',
            'nda_accepted' => true,
        ], $overrides);
    }
}
