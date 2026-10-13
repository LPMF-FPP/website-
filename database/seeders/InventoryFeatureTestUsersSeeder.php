<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class InventoryFeatureTestUsersSeeder extends Seeder
{
    public const OPERATOR_EMAIL = 'inventory.operator@example.test';

    public const OPERATOR_PASSWORD = 'InventoryTest!2026';

    public const APPROVER_EMAIL = 'inventory.approver@example.test';

    public const APPROVER_PASSWORD = 'InventoryApprove!2026';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Akun dummy inventori hanya boleh dibuat pada local/testing.');
        }

        $this->call(PermissionSeeder::class);
        $operator = $this->account(self::OPERATOR_EMAIL, 'Demo Operator Inventori', self::OPERATOR_PASSWORD);
        $this->removeUnrelatedRoleGrants($operator);
        foreach ([
            'inventori.view', 'inventori.create', 'inventori.edit', 'inventori.receipt.create',
            'inventori.issue.create', 'inventori.transfer.create', 'inventori.stocktake.submit',
            'inventori.material-disposal.create',
            'inventori.location-access.manage', 'inventori.mutation.activate', 'inventori.mutation.disable',
            'inventori.readiness.inventory-admin.accept', 'sample-disposal.view', 'sample-disposal.execute',
        ] as $permission) {
            $operator->grantPermission($permission);
        }
        $operator->revokePermission('inventori.stocktake.approve');

        $approver = $this->account(self::APPROVER_EMAIL, 'Demo Approver Inventori', self::APPROVER_PASSWORD);
        $this->removeUnrelatedRoleGrants($approver);
        foreach ([
            'inventori.view', 'inventori.stocktake.approve', 'inventori.readiness.system-owner.accept',
        ] as $permission) {
            $approver->grantPermission($permission);
        }

        $this->command?->info('Akun operator dummy: '.self::OPERATOR_EMAIL.' / '.self::OPERATOR_PASSWORD);
        $this->command?->info('Akun approver independen dummy: '.self::APPROVER_EMAIL.' / '.self::APPROVER_PASSWORD);
        $this->command?->warn('Kredensial ini hanya untuk lingkungan local/testing; jangan digunakan pada produksi.');
    }

    private function account(string $email, string $name, string $password): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'analis',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    private function removeUnrelatedRoleGrants(User $user): void
    {
        RolePermission::query()->where('role', $user->role)->with('permission')->get()
            ->each(function (RolePermission $assignment) use ($user): void {
                $module = $assignment->permission?->module;
                if ($module !== 'inventori' && $module !== 'sample-disposal' && $assignment->permission) {
                    $user->revokePermission($assignment->permission->name);
                }
            });
    }
}
