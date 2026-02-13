<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('clause');
            $table->enum('doc_type', ['sop', 'ik', 'fr']);
            $table->unsignedInteger('version')->default(1);
            $table->string('storage_disk')->default('local');
            $table->string('source_docx_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['clause', 'doc_type', 'is_active'], 'qmh_templates_lookup_idx');
        });

        DB::statement('ALTER TABLE qmh_templates ADD CONSTRAINT qmh_templates_clause_check CHECK (clause BETWEEN 4 AND 8)');

        Schema::table('qmh_document_revisions', function (Blueprint $table) {
            $table->foreignId('template_id')
                ->nullable()
                ->after('status')
                ->constrained('qmh_templates')
                ->nullOnDelete();
            $table->string('template_name')->nullable()->after('template_id');
            $table->unsignedInteger('template_version')->nullable()->after('template_name');
            $table->string('source_docx_path')->nullable()->after('template_version');
            $table->string('source_docx_checksum', 64)->nullable()->after('source_docx_path');
            $table->unsignedInteger('source_docx_version')->default(1)->after('source_docx_checksum');
            $table->timestamp('last_autosaved_at')->nullable()->after('source_docx_version');
        });
    }

    public function down(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn([
                'template_name',
                'template_version',
                'source_docx_path',
                'source_docx_checksum',
                'source_docx_version',
                'last_autosaved_at',
            ]);
        });

        DB::statement('ALTER TABLE qmh_templates DROP CONSTRAINT IF EXISTS qmh_templates_clause_check');
        Schema::dropIfExists('qmh_templates');
    }
};
