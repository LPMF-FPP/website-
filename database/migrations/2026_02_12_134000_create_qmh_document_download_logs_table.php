<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_document_download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('qmh_documents')->cascadeOnDelete();
            $table->foreignId('revision_id')->constrained('qmh_document_revisions')->cascadeOnDelete();
            $table->unsignedInteger('edition_number');
            $table->unsignedInteger('revision_number');
            $table->enum('copy_type', ['controlled', 'uncontrolled']);
            $table->foreignId('downloaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('downloaded_at')->useCurrent();
            $table->text('reason')->nullable();
            $table->string('distribution_target')->nullable();
            $table->string('watermark_text', 64);
            $table->string('file_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'downloaded_at']);
            $table->index(['revision_id', 'copy_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_document_download_logs');
    }
};
