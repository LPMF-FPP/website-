<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_documents', function (Blueprint $table) {
            $table->foreignId('parent_sop_id')
                ->nullable()
                ->after('doc_type')
                ->constrained('qmh_documents')
                ->nullOnDelete();

            $table->foreignId('paired_ik_id')
                ->nullable()
                ->after('parent_sop_id')
                ->constrained('qmh_documents')
                ->nullOnDelete();

            $table->index(['clause', 'parent_sop_id'], 'qmh_documents_parent_clause_idx');
            $table->index('paired_ik_id', 'qmh_documents_paired_ik_idx');
        });
    }

    public function down(): void
    {
        Schema::table('qmh_documents', function (Blueprint $table) {
            $table->dropIndex('qmh_documents_parent_clause_idx');
            $table->dropIndex('qmh_documents_paired_ik_idx');
            $table->dropConstrainedForeignId('paired_ik_id');
            $table->dropConstrainedForeignId('parent_sop_id');
        });
    }
};
