<?php

namespace Tests\Feature\Seeders;

use App\Models\RolePermission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionSeederQmhTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_seeder_assigns_qmh_permissions_to_expected_roles(): void
    {
        $this->seed(PermissionSeeder::class);

        $adminQmhCreate = RolePermission::query()
            ->where('role', 'admin')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.create'))
            ->exists();

        $supervisorQmhCreate = RolePermission::query()
            ->where('role', 'supervisor')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.create'))
            ->exists();

        $manajerTeknisQmhCreate = RolePermission::query()
            ->where('role', 'manajer_teknis')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.create'))
            ->exists();

        $investigatorQmhCreate = RolePermission::query()
            ->where('role', 'investigator')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.create'))
            ->exists();

        $adminQmhReport = RolePermission::query()
            ->where('role', 'admin')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.report'))
            ->exists();

        $supervisorQmhReport = RolePermission::query()
            ->where('role', 'supervisor')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.report'))
            ->exists();

        $manajerTeknisQmhReport = RolePermission::query()
            ->where('role', 'manajer_teknis')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.report'))
            ->exists();

        $investigatorQmhReport = RolePermission::query()
            ->where('role', 'investigator')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.report'))
            ->exists();

        $adminQmhTemplateManage = RolePermission::query()
            ->where('role', 'admin')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.template.manage'))
            ->exists();

        $supervisorQmhTemplateManage = RolePermission::query()
            ->where('role', 'supervisor')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.template.manage'))
            ->exists();

        $manajerTeknisQmhTemplateManage = RolePermission::query()
            ->where('role', 'manajer_teknis')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.template.manage'))
            ->exists();

        $investigatorQmhTemplateManage = RolePermission::query()
            ->where('role', 'investigator')
            ->whereHas('permission', fn ($query) => $query->where('name', 'qmh.template.manage'))
            ->exists();

        $this->assertTrue($adminQmhCreate);
        $this->assertTrue($supervisorQmhCreate);
        $this->assertTrue($manajerTeknisQmhCreate);
        $this->assertFalse($investigatorQmhCreate);

        $this->assertTrue($adminQmhReport);
        $this->assertTrue($supervisorQmhReport);
        $this->assertTrue($manajerTeknisQmhReport);
        $this->assertFalse($investigatorQmhReport);

        $this->assertTrue($adminQmhTemplateManage);
        $this->assertTrue($supervisorQmhTemplateManage);
        $this->assertTrue($manajerTeknisQmhTemplateManage);
        $this->assertFalse($investigatorQmhTemplateManage);
    }
}
