<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_witness_requests', function (Blueprint $table): void {
            $table->string('investigator_rank')->nullable()->after('investigator_name');
            $table->string('suspect_name')->nullable()->after('investigator_phone');
        });
    }

    public function down(): void
    {
        Schema::table('expert_witness_requests', function (Blueprint $table): void {
            $table->dropColumn(['investigator_rank', 'suspect_name']);
        });
    }
};
