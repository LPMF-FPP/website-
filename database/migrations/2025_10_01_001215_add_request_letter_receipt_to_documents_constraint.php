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
            // NOTE: SQLite does not support dropping/adding named CHECK constraints via ALTER TABLE.
            // For test environment we skip this migration segment; functional environments should use MySQL/PostgreSQL.
            return;
        }
        // Hapus constraint lama
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_document_type_check');

        // Tambahkan constraint baru dengan nilai request_letter_receipt
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('lab_report','cover_letter','handover_report','sample_receipt','report_receipt','letter_receipt','sample_handover','test_results','qr_code','request_letter_receipt'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return; // See note above about SQLite limitation.
        }
        // Kembalikan ke constraint sebelumnya jika rollback
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_document_type_check');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('lab_report','cover_letter','handover_report','sample_receipt','report_receipt','letter_receipt','sample_handover','test_results','qr_code'))");
    }
};
