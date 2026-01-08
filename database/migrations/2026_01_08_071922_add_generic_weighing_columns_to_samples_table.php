<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->unsignedInteger('weighed_items_count')->nullable()->after('active_substance');
            $table->decimal('weighed_mass_value', 12, 6)->nullable()->after('weighed_items_count');
            $table->string('weighed_mass_unit', 10)->nullable()->after('weighed_mass_value');
            $table->foreignId('weighed_by')->nullable()->after('weighed_mass_unit')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('weighed_at')->nullable()->after('weighed_by');
        });

        DB::statement("
            UPDATE samples
            SET
                weighed_items_count = 1,
                weighed_mass_value = uvvis_weighed_grams,
                weighed_mass_unit = 'g',
                weighed_by = uvvis_weighed_by,
                weighed_at = uvvis_weighed_at
            WHERE uvvis_weighed_grams IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weighed_by');
            $table->dropColumn([
                'weighed_items_count',
                'weighed_mass_value',
                'weighed_mass_unit',
                'weighed_at',
            ]);
        });
    }
};
