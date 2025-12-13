<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        
        // Drop old constraint
        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');

        // Add new constraint with all document types including form types
        $allowedTypes = implode("','", [
            // Original types
            'lab_report', 'cover_letter', 'handover_report',
            'sample_receipt', 'report_receipt', 'letter_receipt',
            'sample_handover', 'test_results', 'qr_code',
            'request_letter_receipt',
            // Investigator upload types
            'request_letter', 'sample_photo', 'evidence_photo',
            'test_result', 'lhu', 'ba_penyerahan', 'ba_penerimaan', 'other',
            // Form types (generated from sample test processes)
            'form_preparation', 'instrument_uv_vis', 'instrument_gc_ms', 
            'instrument_lc_ms', 'instrument_result',
            // Lab report types (HTML and PDF versions)
            'laporan_hasil_uji', 'laporan_hasil_uji_html'
        ]);
        
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('{$allowedTypes}'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        
        // Revert to previous constraint (without form types)
        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');
        
        $allowedTypes = implode("','", [
            'lab_report', 'cover_letter', 'handover_report',
            'sample_receipt', 'report_receipt', 'letter_receipt',
            'sample_handover', 'test_results', 'qr_code',
            'request_letter_receipt',
            'request_letter', 'sample_photo', 'evidence_photo',
            'test_result', 'lhu', 'ba_penyerahan', 'ba_penerimaan', 'other'
        ]);
        
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('{$allowedTypes}'))");
    }
};
