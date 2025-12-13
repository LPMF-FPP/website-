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
        Schema::table('test_requests', function (Blueprint $table) {
            // Make these fields nullable to match controller validation
            $table->text('suspect_address')->nullable()->change();
            $table->string('case_number')->nullable()->change();
            $table->text('case_description')->nullable()->change();
            $table->date('incident_date')->nullable()->change();
            $table->text('incident_location')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_requests', function (Blueprint $table) {
            $table->text('suspect_address')->nullable(false)->change();
            $table->string('case_number')->nullable(false)->change();
            $table->text('case_description')->nullable(false)->change();
            $table->date('incident_date')->nullable(false)->change();
            $table->text('incident_location')->nullable(false)->change();
        });
    }
};
