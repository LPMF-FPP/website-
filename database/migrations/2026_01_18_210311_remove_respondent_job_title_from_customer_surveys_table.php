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
        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->dropColumn('respondent_job_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->string('respondent_job_title')->nullable()->after('respondent_name');
        });
    }
};
