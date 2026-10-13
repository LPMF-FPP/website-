<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\User;
use App\Services\InventoryReadinessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventoryOperationalPreflight extends Command
{
    protected $signature = 'inventory:operational-preflight';

    protected $description = 'Read-only operational blockers without assigning grants or signing owner evidence';

    public function handle(InventoryReadinessService $readiness): int
    {
        $blockers = [];
        $missingCodes = InventoryItem::query()->whereNull('item_code')->count();
        if ($missingCodes > 0) {
            $blockers[] = 'Item legacy tanpa kode: '.$missingCodes.'; perlukan mapping item_id,item_code yang disetujui.';
        }
        foreach (InventoryItem::ITEM_TYPES as $type => $_label) {
            $policy = InventoryLotTrackingPolicy::query()->where('item_type', $type)->first();
            if (! $policy || ! in_array($policy->lot_tracking_mode, ['lot_tracked', 'untracked'], true)
                || trim((string) $policy->evidence_reference) === '' || ! $policy->decided_by_user_id || ! $policy->decided_at) {
                $blockers[] = 'Policy lot belum disetujui: '.$type;
            }
        }
        $required = ['inventori.location-access.manage', 'inventori.readiness.inventory-admin.accept',
            'inventori.readiness.system-owner.accept', 'inventori.mutation.activate', 'inventori.mutation.disable',
            'sample-disposal.view', 'sample-disposal.execute'];
        foreach ($required as $permission) {
            $granted = User::query()->where('is_active', true)->get()->contains(fn ($user) => $user->hasPermission($permission));
            if (! $granted) {
                $blockers[] = 'Belum ada pemegang izin aktif: '.$permission;
            }
        }
        foreach ($readiness->missingCategories() as $category) {
            $blockers[] = 'Bukti atau dua sign-off independen belum lengkap: '.$category;
        }
        $pending = DB::table('sample_disposal_file_operations')->whereIn('state', ['pending', 'copied'])->count();
        if ($pending > 0) {
            $blockers[] = 'Operasi file perlu rekonsiliasi: '.$pending;
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            $privileged = DB::selectOne("select r.rolsuper, exists(select 1 from pg_tables t where t.schemaname = current_schema() and t.tablename like 'inventory_%' and pg_has_role(current_user, t.tableowner, 'USAGE')) as owns_inventory from pg_roles r where r.rolname = current_user");
            if ($privileged->rolsuper || $privileged->owns_inventory) {
                $blockers[] = 'Koneksi database memiliki hak owner/superuser inventori; pisahkan role runtime dan migration sebelum operasi produksi.';
            }
        }
        foreach ($blockers as $blocker) {
            $this->warn($blocker);
        }
        $this->info('Read-only: tidak ada grant, mapping, policy, sign-off, atau data yang diubah. Penetapan pemilik tidak dapat diinferensikan dari pemeriksaan ini.');

        return $blockers === [] ? self::SUCCESS : self::FAILURE;
    }
}
