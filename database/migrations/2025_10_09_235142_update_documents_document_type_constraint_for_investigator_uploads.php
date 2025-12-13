<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // Add new constraint with investigator document types
        $allowedTypes = implode("','", [
            'lab_report', 'cover_letter', 'handover_report',
            'sample_receipt', 'report_receipt', 'letter_receipt',
            'sample_handover', 'test_results', 'qr_code',
            'request_letter_receipt',
            // Add new types for investigator uploads
            'request_letter', 'sample_photo', 'evidence_photo',
            'test_result', 'lhu', 'ba_penyerahan', 'ba_penerimaan', 'other'
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
        
        // Revert to previous constraint
        DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_document_type_check');
        
        $allowedTypes = implode("','", [
            'lab_report', 'cover_letter', 'handover_report',
            'sample_receipt', 'report_receipt', 'letter_receipt',
            'sample_handover', 'test_results', 'qr_code',
            'request_letter_receipt'
        ]);
        
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('{$allowedTypes}'))");
    }
};
