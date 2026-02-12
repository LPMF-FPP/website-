<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_documents', function (Blueprint $table) {
            $table->id();
            $table->string('doc_code')->unique();
            $table->string('title');
            $table->unsignedTinyInteger('clause');
            $table->enum('doc_type', ['sop', 'ik', 'formulir']);
            $table->string('owner_label')->default('Laboratorium');
            $table->foreignId('current_revision_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clause', 'doc_type']);
        });

        DB::statement('ALTER TABLE qmh_documents ADD CONSTRAINT qmh_documents_clause_check CHECK (clause BETWEEN 4 AND 8)');

        Schema::create('qmh_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('qmh_documents')->cascadeOnDelete();
            $table->unsignedInteger('edition_number')->default(1);
            $table->unsignedInteger('revision_number')->default(0);
            $table->string('version_label');
            $table->enum('status', ['draft', 'in_review', 'in_approval', 'published', 'rejected', 'obsolete'])->default('draft');
            $table->text('change_summary')->nullable();
            $table->enum('version_bump_mode', ['auto', 'manual'])->default('auto');
            $table->json('editor_json')->nullable();
            $table->longText('content_html')->nullable();
            $table->longText('content_css')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diperiksa_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamp('obsolete_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'edition_number', 'revision_number'], 'qmh_document_revision_unique');
            $table->index(['document_id', 'status']);
            $table->index(['edition_number', 'revision_number']);
        });

        Schema::table('qmh_documents', function (Blueprint $table) {
            $table->foreign('current_revision_id', 'qmh_documents_current_revision_fk')
                ->references('id')
                ->on('qmh_document_revisions')
                ->nullOnDelete();
        });

        Schema::create('qmh_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('qmh_document_revisions')->cascadeOnDelete();
            $table->enum('event_type', ['create_draft', 'submit_review', 'review_return', 'review_pass', 'approve', 'reject', 'publish', 'download', 'lock', 'unlock']);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['revision_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_workflow_events');

        DB::statement('ALTER TABLE qmh_documents DROP CONSTRAINT IF EXISTS qmh_documents_clause_check');

        Schema::table('qmh_documents', function (Blueprint $table) {
            $table->dropForeign('qmh_documents_current_revision_fk');
        });

        Schema::dropIfExists('qmh_document_revisions');
        Schema::dropIfExists('qmh_documents');
    }
};
