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

            // render_engine column already added in 2025_02_13_000000_add_render_engine_to_document_templates
            // Skip adding it again to prevent "Duplicate column" error

            $table->longText('content_html')->nullable()->after('storage_path');
            $table->longText('content_css')->nullable()->after('content_html');
            $table->json('editor_project')->nullable()->after('content_css');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            // Only drop columns added in this migration, render_engine is handled separately
            $table->dropColumn(['content_html', 'content_css', 'editor_project']);

            if (Schema::hasColumn('document_templates', 'storage_path')) {
                $table->string('storage_path')->nullable(false)->change();
            }
        });
    }
};
