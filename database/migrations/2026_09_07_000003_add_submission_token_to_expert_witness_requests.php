<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_witness_requests', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable()->unique()->after('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('expert_witness_requests', function (Blueprint $table): void {
            $table->dropUnique(['submission_token']);
            $table->dropColumn('submission_token');
        });
    }
};
