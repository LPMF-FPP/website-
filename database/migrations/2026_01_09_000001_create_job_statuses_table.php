<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('progress_current')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_statuses');
    }
};
