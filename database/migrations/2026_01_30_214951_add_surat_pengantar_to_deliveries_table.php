<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Check if columns exist before adding to be safe (idempotent)
            if (! Schema::hasColumn('deliveries', 'has_surat_pengantar')) {
                $table->boolean('has_surat_pengantar')->default(false)->after('collected_at');
            }
            if (! Schema::hasColumn('deliveries', 'surat_pengantar_number')) {
                $table->string('surat_pengantar_number')->nullable()->after('has_surat_pengantar');
            }
            if (! Schema::hasColumn('deliveries', 'surat_pengantar_date')) {
                $table->date('surat_pengantar_date')->nullable()->after('surat_pengantar_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['has_surat_pengantar', 'surat_pengantar_number', 'surat_pengantar_date']);
        });
    }
};
