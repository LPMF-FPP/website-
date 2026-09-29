<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_reopenings', function (Blueprint $table): void {
            $table->dropForeign(['sample_id']);
            $table->unsignedBigInteger('sample_id')->nullable()->change();
            $table->json('sample_snapshot')->nullable();
            $table->foreign('sample_id')->references('id')->on('samples')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('delivery_reopenings')->whereNull('sample_id')->exists()) {
            throw new RuntimeException('Cannot restore required sample references while audited sample-removal snapshots exist.');
        }

        Schema::table('delivery_reopenings', function (Blueprint $table): void {
            $table->dropForeign(['sample_id']);
            $table->dropColumn('sample_snapshot');
            $table->unsignedBigInteger('sample_id')->nullable(false)->change();
            $table->foreign('sample_id')->references('id')->on('samples')->restrictOnDelete();
        });
    }
};
