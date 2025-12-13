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

        // Add new constraint with all document types
        $allowedTypes = implode("','", [
            'lab_report', 'cover_letter', 'handover_report',
            'sample_receipt', 'report_receipt', 'letter_receipt',
            'sample_handover', 'test_results', 'qr_code',
            'request_letter_receipt',
            'request_letter', 'sample_photo', 'evidence_photo',
            'test_result', 'lhu', 'ba_penyerahan', 'ba_penerimaan', 'other',
            'form_preparation', 'instrument_uv_vis', 'instrument_gc_ms', 
            'instrument_lc_ms', 'instrument_result',
            'laporan_hasil_uji', 'laporan_hasil_uji_html',
            'ba_penyerahan_html'
        ]);
        
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('{$allowedTypes}'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to revert - constraint includes all types
    }
};
