<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove 'psikotropika' from sample_category enum
     */
    public function up(): void
    {
        // First, update any existing records with 'psikotropika' to 'other'
        DB::table('samples')
            ->where('sample_category', 'psikotropika')
            ->update(['sample_category' => 'other']);

        // Alter the enum to remove 'psikotropika'
        // PostgreSQL syntax for altering enum
        DB::statement('ALTER TABLE samples DROP CONSTRAINT IF EXISTS samples_sample_category_check');
        DB::statement("ALTER TABLE samples ADD CONSTRAINT samples_sample_category_check CHECK (sample_category::text = ANY (ARRAY['narkotika'::text, 'prekursor'::text, 'zat_adiktif'::text, 'obat_keras'::text, 'other'::text]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add 'psikotropika' to enum
        DB::statement('ALTER TABLE samples DROP CONSTRAINT IF EXISTS samples_sample_category_check');
        DB::statement("ALTER TABLE samples ADD CONSTRAINT samples_sample_category_check CHECK (sample_category::text = ANY (ARRAY['narkotika'::text, 'psikotropika'::text, 'prekursor'::text, 'zat_adiktif'::text, 'obat_keras'::text, 'other'::text]))");
    }
};
