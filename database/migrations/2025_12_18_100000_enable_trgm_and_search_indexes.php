<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transactions for this migration because CREATE INDEX CONCURRENTLY
     * cannot run inside a transaction block.
     */
    public function withoutTransactions()
    {
        return true;
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->createIndex('people', 'people_name_trgm_idx', 'name gin_trgm_ops', true);
        $this->createIndex('documents', 'documents_title_trgm_idx', 'title gin_trgm_ops', true);
        $this->createIndex('documents', 'documents_ba_no_trgm_idx', 'ba_no gin_trgm_ops', true);
        $this->createIndex('documents', 'documents_lp_no_trgm_idx', 'lp_no gin_trgm_ops', true);

        $this->createIndex('documents', 'documents_doc_type_idx', 'doc_type');
        $this->createIndex('documents', 'documents_doc_date_idx', 'doc_date');
        $this->createIndex('documents', 'documents_created_at_idx', 'created_at');

        $this->createIndex('case_people', 'case_people_person_id_idx', 'person_id');
        $this->createIndex('case_people', 'case_people_case_id_idx', 'case_id');
        $this->createIndex('cases', 'cases_created_at_idx', 'created_at');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->dropIndex('cases_created_at_idx');
        $this->dropIndex('case_people_case_id_idx');
        $this->dropIndex('case_people_person_id_idx');
        $this->dropIndex('documents_created_at_idx');
        $this->dropIndex('documents_doc_date_idx');
        $this->dropIndex('documents_doc_type_idx');
        $this->dropIndex('documents_lp_no_trgm_idx');
        $this->dropIndex('documents_ba_no_trgm_idx');
        $this->dropIndex('documents_title_trgm_idx');
        $this->dropIndex('people_name_trgm_idx');
    }

    private function createIndex(string $table, string $index, string $columnDefinition, bool $gin = false): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $using = $gin ? ' USING gin' : '';
        DB::statement(sprintf(
            'CREATE INDEX IF NOT EXISTS %s ON %s%s (%s)',
            $index,
            $table,
            $using,
            $columnDefinition
        ));
    }

    private function dropIndex(string $index): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', $index));
    }
};
