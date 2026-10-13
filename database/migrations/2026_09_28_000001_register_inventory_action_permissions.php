<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'inventori.receipt.create', 'display_name' => 'Catat Penerimaan Inventori', 'module' => 'inventori', 'action' => 'receipt.create'],
            ['name' => 'inventori.issue.create', 'display_name' => 'Catat Pengeluaran Inventori', 'module' => 'inventori', 'action' => 'issue.create'],
            ['name' => 'inventori.transfer.create', 'display_name' => 'Catat Transfer Inventori', 'module' => 'inventori', 'action' => 'transfer.create'],
            ['name' => 'inventori.stocktake.submit', 'display_name' => 'Ajukan Stok Opname', 'module' => 'inventori', 'action' => 'stocktake.submit'],
            ['name' => 'inventori.stocktake.approve', 'display_name' => 'Setujui Selisih Stok Opname', 'module' => 'inventori', 'action' => 'stocktake.approve'],
            ['name' => 'inventori.material-disposal.create', 'display_name' => 'Catat Disposal Bahan Inventori', 'module' => 'inventori', 'action' => 'material-disposal.create'],
            ['name' => 'inventori.location-access.manage', 'display_name' => 'Kelola Akses Lokasi Inventori', 'module' => 'inventori', 'action' => 'location-access.manage'],
        ];

        DB::table('permissions')->insertOrIgnore(array_map(
            fn (array $permission): array => [...$permission, 'created_at' => now(), 'updated_at' => now()],
            $permissions
        ));
    }

    public function down(): void
    {
        // Permission records may be assigned after deployment; retain shared authorization data.
    }
};
