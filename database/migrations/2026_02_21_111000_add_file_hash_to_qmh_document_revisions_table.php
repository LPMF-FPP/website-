<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table) {
            if (! Schema::hasColumn('qmh_document_revisions', 'file_hash')) {
                $table->string('file_hash', 64)->nullable()->after('source_pdf_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table) {
            if (Schema::hasColumn('qmh_document_revisions', 'file_hash')) {
                $table->dropColumn('file_hash');
            }
        });
    }
};
