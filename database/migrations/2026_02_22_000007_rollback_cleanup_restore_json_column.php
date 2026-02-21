<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('qmh_audits', 'auditors_json')) {
            Schema::table('qmh_audits', function (Blueprint $table) {
                $table->json('auditors_json')->nullable()->after('location');
            });
        }

        DB::table('qmh_audits')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($audits): void {
                foreach ($audits as $audit) {
                    $auditorIds = DB::table('qmh_audit_auditors')
                        ->where('audit_id', $audit->id)
                        ->whereNull('deleted_at')
                        ->pluck('user_id')
                        ->filter(fn ($id) => $id !== null)
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    DB::table('qmh_audits')
                        ->where('id', $audit->id)
                        ->update([
                            'auditors_json' => json_encode($auditorIds),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // intentionally no-op; use dedicated cleanup migration for dropping column
    }
};
