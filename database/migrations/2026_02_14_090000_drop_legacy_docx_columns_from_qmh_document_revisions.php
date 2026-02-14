<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table): void {
            $table->dropColumn([
                'source_docx_path',
                'source_docx_checksum',
                'source_docx_version',
                'export_pdf_from_docx',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table): void {
            $table->string('source_docx_path')->nullable();
            $table->string('source_docx_checksum', 64)->nullable();
            $table->unsignedInteger('source_docx_version')->default(1);
            $table->boolean('export_pdf_from_docx')->default(false);
        });
    }
};
