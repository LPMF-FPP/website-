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

            $table->string('render_engine')
                ->default(DocumentRenderEngine::DOMPDF->value)
                ->after('format')
                ->index();

            $table->longText('content_html')->nullable()->after('storage_path');
            $table->longText('content_css')->nullable()->after('content_html');
            $table->json('editor_project')->nullable()->after('content_css');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn(['render_engine', 'content_html', 'content_css', 'editor_project']);

            if (Schema::hasColumn('document_templates', 'storage_path')) {
                $table->string('storage_path')->nullable(false)->change();
            }
        });
    }
};
