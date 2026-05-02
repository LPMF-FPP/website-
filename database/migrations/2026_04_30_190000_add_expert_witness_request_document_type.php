<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');

        $allowedTypes = implode("','", [
            'lab_report',
            'cover_letter',
            'handover_report',
            'sample_receipt',
            'report_receipt',
            'letter_receipt',
            'sample_handover',
            'test_results',
            'qr_code',
            'request_letter_receipt',
            'request_letter',
            'expert_witness_request',
            'sample_photo',
            'evidence_photo',
            'test_result',
            'lhu',
            'ba_penyerahan',
            'ba_penerimaan',
            'ba_penerimaan_html',
            'other',
            'form_preparation',
            'instrument_uv_vis',
            'instrument_gc_ms',
            'instrument_lc_ms',
            'instrument_result',
            'laporan_hasil_uji',
            'laporan_hasil_uji_html',
            'ba_penyerahan_html',
            'label_evidence',
            'label_remaining',
            'sample_label',
            'label_sample',
            'remaining_label',
            'environment_monthly_log',
            'instrument_monthly_log',
            'uvvis_weighing_monthly_log',
        ]);

        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('{$allowedTypes}'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');

        $allowedTypes = implode("','", [
            'lab_report',
            'cover_letter',
            'handover_report',
            'sample_receipt',
            'report_receipt',
            'letter_receipt',
            'sample_handover',
            'test_results',
            'qr_code',
            'request_letter_receipt',
            'request_letter',
            'sample_photo',
            'evidence_photo',
            'test_result',
            'lhu',
            'ba_penyerahan',
            'ba_penerimaan',
            'ba_penerimaan_html',
            'other',
            'form_preparation',
            'instrument_uv_vis',
            'instrument_gc_ms',
            'instrument_lc_ms',
            'instrument_result',
            'laporan_hasil_uji',
            'laporan_hasil_uji_html',
            'ba_penyerahan_html',
            'label_evidence',
            'label_remaining',
            'sample_label',
            'label_sample',
            'remaining_label',
            'environment_monthly_log',
            'instrument_monthly_log',
            'uvvis_weighing_monthly_log',
        ]);

        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('{$allowedTypes}'))");
    }
};
