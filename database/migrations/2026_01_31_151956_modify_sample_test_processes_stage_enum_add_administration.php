<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the constraint if it exists (PostgreSQL specific)
        DB::statement('ALTER TABLE sample_test_processes DROP CONSTRAINT IF EXISTS sample_test_processes_stage_check');

        // Add the constraint with the new 'administration' value
        DB::statement("ALTER TABLE sample_test_processes ADD CONSTRAINT sample_test_processes_stage_check CHECK (stage::text = ANY (ARRAY['preparation'::character varying, 'instrumentation'::character varying, 'interpretation'::character varying, 'administration'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original allowed values
        DB::statement('ALTER TABLE sample_test_processes DROP CONSTRAINT IF EXISTS sample_test_processes_stage_check');
        DB::statement("ALTER TABLE sample_test_processes ADD CONSTRAINT sample_test_processes_stage_check CHECK (stage::text = ANY (ARRAY['preparation'::character varying, 'instrumentation'::character varying, 'interpretation'::character varying]::text[]))");
    }
};
