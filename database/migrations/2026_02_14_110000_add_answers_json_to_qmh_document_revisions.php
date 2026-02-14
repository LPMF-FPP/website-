<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table) {
            $table->jsonb('answers_json')->nullable()->after('editor_json');
        });
    }

    public function down(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table) {
            $table->dropColumn('answers_json');
        });
    }
};
