<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view' => 'Lihat Pemusnahan Sampel', 'execute' => 'Eksekusi Pemusnahan Sampel'] as $action => $label) {
            DB::table('permissions')->insertOrIgnore([
                'name' => 'sample-disposal.'.$action, 'module' => 'sample-disposal', 'action' => $action,
                'display_name' => $label, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve permission records that may have been explicitly assigned after deployment.
    }
};
