<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table): void {
            $table->boolean('export_pdf_from_docx')
                ->default(false)
                ->after('content_css');
        });
    }

    public function down(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table): void {
            $table->dropColumn('export_pdf_from_docx');
        });
    }
};
