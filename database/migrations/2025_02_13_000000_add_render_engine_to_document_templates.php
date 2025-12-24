<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_templates')) {
            return;
        }

        if (!Schema::hasColumn('document_templates', 'render_engine')) {
            Schema::table('document_templates', function (Blueprint $table) {
                $table->string('render_engine')->default('dompdf')->after('storage_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('document_templates') && Schema::hasColumn('document_templates', 'render_engine')) {
            Schema::table('document_templates', function (Blueprint $table) {
                $table->dropColumn('render_engine');
            });
        }
    }
};
