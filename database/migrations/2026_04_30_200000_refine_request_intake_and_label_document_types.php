<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_requests', function (Blueprint $table) {
            if (Schema::hasColumn('test_requests', 'to_office')) {
                $table->dropColumn('to_office');
            }

            if (! Schema::hasColumn('test_requests', 'has_expert_witness_request')) {
                $table->boolean('has_expert_witness_request')->default(false)->after('case_description');
            }

            if (! Schema::hasColumn('test_requests', 'expert_witness_letter_number')) {
                $table->string('expert_witness_letter_number')->nullable()->after('has_expert_witness_request');
            }

            if (! Schema::hasColumn('test_requests', 'expert_witness_letter_date')) {
                $table->date('expert_witness_letter_date')->nullable()->after('expert_witness_letter_number');
            }
        });

        if (Schema::hasTable('documents')) {
            DB::table('test_requests')
                ->whereIn('id', DB::table('documents')
                    ->select('test_request_id')
                    ->where('document_type', 'expert_witness_request')
                    ->whereNotNull('test_request_id'))
                ->update(['has_expert_witness_request' => true]);
        }

        $this->refreshDocumentTypeConstraint();
    }

    public function down(): void
    {
        Schema::table('test_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('test_requests', 'to_office')) {
                $table->string('to_office')->nullable()->after('status');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('test_requests', 'expert_witness_letter_date') ? 'expert_witness_letter_date' : null,
                Schema::hasColumn('test_requests', 'expert_witness_letter_number') ? 'expert_witness_letter_number' : null,
                Schema::hasColumn('test_requests', 'has_expert_witness_request') ? 'has_expert_witness_request' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function refreshDocumentTypeConstraint(): void
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
};
