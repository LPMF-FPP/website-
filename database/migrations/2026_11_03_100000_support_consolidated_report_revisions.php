<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consolidated_reports', 'revision_of_id')) {
            Schema::table('consolidated_reports', function (Blueprint $table): void {
                $table->foreignId('revision_of_id')->nullable()->constrained('consolidated_reports')->restrictOnDelete();
                $table->unsignedSmallInteger('revision_number')->default(1);
                $table->text('revision_reason')->nullable();
            });
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS consolidated_reports_unique_base_period ON consolidated_reports (period_type, period_start, period_end) WHERE revision_of_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS consolidated_reports_unique_revision_number ON consolidated_reports (revision_of_id, revision_number) WHERE revision_of_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        // Revisions are historical records; rollback must not discard their linkage.
    }
};
