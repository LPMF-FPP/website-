<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table): void {
            $table->uuid('review_id')->nullable()->unique();
            $table->json('manifest_snapshot')->nullable();
            $table->string('execution_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table): void {
            $table->dropUnique(['review_id']);
            $table->dropColumn(['review_id', 'manifest_snapshot', 'execution_fingerprint']);
        });
    }
};
