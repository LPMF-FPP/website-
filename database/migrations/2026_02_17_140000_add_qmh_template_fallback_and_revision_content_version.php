<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table) {
            if (! Schema::hasColumn('qmh_document_revisions', 'content_version')) {
                $table->unsignedBigInteger('content_version')->default(1)->after('last_autosaved_at');
            }
        });

        Schema::table('qmh_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('qmh_templates', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_active');
            }
        });

        Schema::create('qmh_template_fallback_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('qmh_documents')->cascadeOnDelete();
            $table->foreignId('revision_id')->nullable()->constrained('qmh_document_revisions')->nullOnDelete();
            $table->unsignedTinyInteger('requested_clause');
            $table->enum('requested_doc_type', ['sop', 'ik', 'fr']);
            $table->string('requested_layout_profile')->nullable();
            $table->unsignedTinyInteger('fallback_clause')->default(4);
            $table->foreignId('fallback_template_id')->nullable()->constrained('qmh_templates')->nullOnDelete();
            $table->enum('status', ['requested', 'approved', 'rejected', 'expired'])->default('requested');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'status'], 'qmh_template_fallback_doc_status_idx');
            $table->index(['requested_doc_type', 'requested_clause', 'requested_layout_profile'], 'qmh_template_fallback_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_template_fallback_requests');

        Schema::table('qmh_templates', function (Blueprint $table) {
            if (Schema::hasColumn('qmh_templates', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });

        Schema::table('qmh_document_revisions', function (Blueprint $table) {
            if (Schema::hasColumn('qmh_document_revisions', 'content_version')) {
                $table->dropColumn('content_version');
            }
        });
    }
};
