<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            // Add doc_type column (BA|LHU) if not exists
            if (!Schema::hasColumn('document_templates', 'doc_type')) {
                $table->enum('doc_type', ['BA', 'LHU'])
                    ->nullable()
                    ->after('id')
                    ->comment('Document type: BA (Berita Acara) or LHU (Laporan Hasil Uji)');
            }
            
            // Add status column (draft/issued/obsolete)
            if (!Schema::hasColumn('document_templates', 'status')) {
                $table->enum('status', ['draft', 'issued', 'obsolete'])
                    ->default('draft')
                    ->after('is_active')
                    ->comment('Template status');
            }
            
            // Add issued_at timestamp
            if (!Schema::hasColumn('document_templates', 'issued_at')) {
                $table->timestamp('issued_at')
                    ->nullable()
                    ->after('updated_at')
                    ->comment('When the template was issued/published');
            }
            
            // Add GrapesJS specific columns for components and styles
            // Note: content_html, content_css, editor_project already added by 2026_01_05_000001_add_editor_support
            if (!Schema::hasColumn('document_templates', 'gjs_components')) {
                $table->json('gjs_components')
                    ->nullable()
                    ->after('content_css')
                    ->comment('GrapesJS components structure');
            }
            
            if (!Schema::hasColumn('document_templates', 'gjs_styles')) {
                $table->json('gjs_styles')
                    ->nullable()
                    ->after('gjs_components')
                    ->comment('GrapesJS styles structure');
            }
        });
        
        // Create indexes - use raw SQL to avoid doctrine issues
        DB::statement('CREATE INDEX IF NOT EXISTS idx_doc_type_status ON document_templates (doc_type, status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_doc_type_is_active ON document_templates (doc_type, is_active)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes
        DB::statement('DROP INDEX IF EXISTS idx_doc_type_status');
        DB::statement('DROP INDEX IF EXISTS idx_doc_type_is_active');
        
        Schema::table('document_templates', function (Blueprint $table) {
            // Drop columns
            $columns = ['doc_type', 'status', 'issued_at', 'gjs_components', 'gjs_styles'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('document_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
