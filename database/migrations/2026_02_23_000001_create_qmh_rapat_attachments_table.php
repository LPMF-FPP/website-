<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_rapat_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('qmh_rapats')->cascadeOnDelete();
            $table->string('file_disk', 50)->default('local');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rapat_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_rapat_attachments');
    }
};
