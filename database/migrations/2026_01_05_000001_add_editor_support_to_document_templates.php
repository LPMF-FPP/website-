<?php

use App\Enums\DocumentRenderEngine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            if (Schema::hasColumn('document_templates', 'storage_path')) {
                $table->string('storage_path')->nullable()->change();
            }

            // Add render_engine column if not exists
            if (!Schema::hasColumn('document_templates', 'render_engine')) {
                $table->string('render_engine')
                    ->default(DocumentRenderEngine::DOMPDF->value)
                    ->after('storage_path');
            }

            // Add content columns if not exist
            if (!Schema::hasColumn('document_templates', 'content_html')) {
                $table->longText('content_html')->nullable()->after('storage_path');
            }
            if (!Schema::hasColumn('document_templates', 'content_css')) {
                $table->longText('content_css')->nullable()->after('content_html');
            }
            if (!Schema::hasColumn('document_templates', 'editor_project')) {
                $table->json('editor_project')->nullable()->after('content_css');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            // Drop columns added in this migration
            $columnsToDrop = [];
            
            if (Schema::hasColumn('document_templates', 'render_engine')) {
                $columnsToDrop[] = 'render_engine';
            }
            if (Schema::hasColumn('document_templates', 'content_html')) {
                $columnsToDrop[] = 'content_html';
            }
            if (Schema::hasColumn('document_templates', 'content_css')) {
                $columnsToDrop[] = 'content_css';
            }
            if (Schema::hasColumn('document_templates', 'editor_project')) {
                $columnsToDrop[] = 'editor_project';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

            if (Schema::hasColumn('document_templates', 'storage_path')) {
                $table->string('storage_path')->nullable(false)->change();
            }
        });
    }
};
