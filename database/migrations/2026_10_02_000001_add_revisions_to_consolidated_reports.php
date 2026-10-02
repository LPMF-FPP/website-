<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consolidated_reports', function (Blueprint $table): void {
            $table->foreignId('revision_of_id')
                ->nullable()
                ->constrained('consolidated_reports')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->text('revision_reason')->nullable();
            $table->index(['revision_of_id', 'revision_number'], 'consolidated_reports_revision_chain_index');
        });

        $duplicates = DB::table('consolidated_reports')
            ->select('period_type', 'period_start', 'period_end')
            ->whereNull('revision_of_id')
            ->whereNull('deleted_at')
            ->groupBy('period_type', 'period_start', 'period_end')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($duplicates) {
            throw new \RuntimeException('Duplicate active base reports must be reconciled before enabling revisions.');
        }

        Schema::table('consolidated_reports', function (Blueprint $table): void {
            $table->dropUnique('unique_period');
        });

        DB::statement(
            'CREATE UNIQUE INDEX consolidated_reports_unique_base_period '
            .'ON consolidated_reports (period_type, period_start, period_end) '
            .'WHERE revision_of_id IS NULL AND deleted_at IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX consolidated_reports_unique_revision_number '
            .'ON consolidated_reports (revision_of_id, revision_number) '
            .'WHERE revision_of_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        if (DB::table('consolidated_reports')->whereNotNull('revision_of_id')->exists()) {
            throw new \RuntimeException('Cannot remove report revision columns while revision records exist.');
        }

        DB::statement('DROP INDEX IF EXISTS consolidated_reports_unique_base_period');
        DB::statement('DROP INDEX IF EXISTS consolidated_reports_unique_revision_number');

        Schema::table('consolidated_reports', function (Blueprint $table): void {
            $table->dropIndex('consolidated_reports_revision_chain_index');
            $table->dropConstrainedForeignId('revision_of_id');
            $table->dropColumn(['revision_number', 'revision_reason']);
            $table->unique(['period_type', 'period_start', 'period_end', 'deleted_at'], 'unique_period');
        });
    }
};
