<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table): void {
            if (! Schema::hasColumn('qmh_document_revisions', 'source_pdf_disk')) {
                $table->string('source_pdf_disk', 64)->nullable()->after('source_docx_version');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'source_pdf_path')) {
                $table->string('source_pdf_path')->nullable()->after('source_pdf_disk');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'source_pdf_sha256')) {
                $table->string('source_pdf_sha256', 64)->nullable()->after('source_pdf_path');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'source_pdf_mime')) {
                $table->string('source_pdf_mime', 64)->nullable()->after('source_pdf_sha256');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'source_pdf_size')) {
                $table->unsignedBigInteger('source_pdf_size')->nullable()->after('source_pdf_mime');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'source_pdf_page_count')) {
                $table->unsignedInteger('source_pdf_page_count')->nullable()->after('source_pdf_size');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'source_pdf_uploaded_at')) {
                $table->timestamp('source_pdf_uploaded_at')->nullable()->after('source_pdf_page_count');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'layout_checker_status')) {
                $table->string('layout_checker_status', 32)->nullable()->after('source_pdf_uploaded_at');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'layout_checker_payload')) {
                $table->json('layout_checker_payload')->nullable()->after('layout_checker_status');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'layout_checker_checked_at')) {
                $table->timestamp('layout_checker_checked_at')->nullable()->after('layout_checker_payload');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'attestation_actor')) {
                $table->string('attestation_actor', 255)->nullable()->after('layout_checker_checked_at');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'attestation_reason')) {
                $table->text('attestation_reason')->nullable()->after('attestation_actor');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'attestation_incident_ref')) {
                $table->string('attestation_incident_ref', 255)->nullable()->after('attestation_reason');
            }

            if (! Schema::hasColumn('qmh_document_revisions', 'attestation_recorded_at')) {
                $table->timestamp('attestation_recorded_at')->nullable()->after('attestation_incident_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('qmh_document_revisions', function (Blueprint $table): void {
            $columns = [
                'source_pdf_disk',
                'source_pdf_path',
                'source_pdf_sha256',
                'source_pdf_mime',
                'source_pdf_size',
                'source_pdf_page_count',
                'source_pdf_uploaded_at',
                'layout_checker_status',
                'layout_checker_payload',
                'layout_checker_checked_at',
                'attestation_actor',
                'attestation_reason',
                'attestation_incident_ref',
                'attestation_recorded_at',
            ];

            $existing = array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn('qmh_document_revisions', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
