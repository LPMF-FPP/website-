<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_requests', function (Blueprint $table) {
            // Ubah kolom menjadi nullable
            $table->string('case_number')->nullable()->change();
            $table->text('case_description')->nullable()->change();
            $table->date('incident_date')->nullable()->change();
            $table->text('incident_location')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('test_requests', function (Blueprint $table) {
            // Kembalikan ke not null jika rollback
            $table->string('case_number')->nullable(false)->change();
            $table->text('case_description')->nullable(false)->change();
            $table->date('incident_date')->nullable(false)->change();
            $table->text('incident_location')->nullable(false)->change();
        });
    }
};
