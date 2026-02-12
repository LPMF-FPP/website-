<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_document_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->unique()->constrained('qmh_document_revisions')->cascadeOnDelete();
            $table->foreignId('locked_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('locked_at');
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('expires_at');
            $table->foreignId('force_unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('force_unlocked_reason')->nullable();
            $table->timestamps();

            $table->index(['revision_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_document_locks');
    }
};
