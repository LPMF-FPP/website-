<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_request_id')->constrained();
            $table->enum('document_type', [
                'lab_report', 'cover_letter', 'handover_report',
                'sample_receipt', 'report_receipt', 'letter_receipt',
                'sample_handover', 'test_results', 'qr_code'
            ]);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
