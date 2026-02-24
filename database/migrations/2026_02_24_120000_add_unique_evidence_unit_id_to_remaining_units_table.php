<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('remaining_units_dedup_backups')) {
            Schema::create('remaining_units_dedup_backups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('remaining_unit_id');
                $table->unsignedBigInteger('evidence_unit_id');
                $table->json('snapshot');
                $table->string('dedup_batch', 64);
                $table->timestamps();

                $table->index('remaining_unit_id');
                $table->index('evidence_unit_id');
                $table->index('dedup_batch');
            });
        }

        $dedupBatch = 'remaining_units_unique_'.now()->format('YmdHis');

        $duplicateEvidenceUnitIds = DB::table('remaining_units')
            ->select('evidence_unit_id')
            ->groupBy('evidence_unit_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('evidence_unit_id');

        foreach ($duplicateEvidenceUnitIds as $evidenceUnitId) {
            $idsToDelete = DB::table('remaining_units')
                ->where('evidence_unit_id', $evidenceUnitId)
                ->orderByDesc('id')
                ->pluck('id')
                ->slice(1)
                ->all();

            if (! empty($idsToDelete)) {
                $recordsToDelete = DB::table('remaining_units')
                    ->whereIn('id', $idsToDelete)
                    ->get();

                $backupRows = $recordsToDelete->map(function ($record) use ($dedupBatch) {
                    return [
                        'remaining_unit_id' => $record->id,
                        'evidence_unit_id' => $record->evidence_unit_id,
                        'snapshot' => json_encode((array) $record, JSON_UNESCAPED_UNICODE),
                        'dedup_batch' => $dedupBatch,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();

                if (! empty($backupRows)) {
                    DB::table('remaining_units_dedup_backups')->insert($backupRows);
                }

                DB::table('remaining_units')
                    ->whereIn('id', $idsToDelete)
                    ->delete();
            }
        }

        Schema::table('remaining_units', function (Blueprint $table) {
            $table->unique('evidence_unit_id', 'remaining_units_evidence_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::table('remaining_units', function (Blueprint $table) {
            $table->dropUnique('remaining_units_evidence_unit_unique');
        });

        Schema::dropIfExists('remaining_units_dedup_backups');
    }
};
