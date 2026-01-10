<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('mode', ['emergency', 'scheduled'])->default('emergency');
            $table->enum('status', ['queued', 'running', 'success', 'failed'])->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('artifact_dir')->nullable();
            $table->string('db_dump_path')->nullable();
            $table->string('storage_archive_path')->nullable();
            $table->string('manifest_path')->nullable();
            $table->unsignedBigInteger('db_size_bytes')->default(0);
            $table->unsignedBigInteger('storage_size_bytes')->default(0);
            $table->string('git_commit', 40)->nullable();
            $table->text('sha256_manifest')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
