<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $this->dropPgDocTypeCheckConstraints();
            DB::statement('ALTER TABLE qmh_documents ALTER COLUMN doc_type TYPE VARCHAR(20) USING doc_type::text');
            DB::statement("ALTER TABLE qmh_documents ADD CONSTRAINT qmh_documents_doc_type_check CHECK (doc_type IN ('sop','ik','formulir','pendukung'))");
            DB::statement('CREATE INDEX IF NOT EXISTS qmh_documents_clause_index ON qmh_documents (clause)');
            DB::statement('CREATE INDEX IF NOT EXISTS qmh_documents_doc_type_clause_index ON qmh_documents (doc_type, clause)');
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE qmh_documents MODIFY COLUMN doc_type ENUM('sop','ik','formulir','pendukung')");
            DB::statement('CREATE INDEX qmh_documents_clause_index ON qmh_documents (clause)');
            DB::statement('CREATE INDEX qmh_documents_doc_type_clause_index ON qmh_documents (doc_type, clause)');
        }

        Schema::table('qmh_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('qmh_documents', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('qmh_documents', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("UPDATE qmh_documents SET doc_type = 'formulir' WHERE doc_type = 'pendukung'");
            $this->dropPgDocTypeCheckConstraints();
            DB::statement('ALTER TABLE qmh_documents ALTER COLUMN doc_type TYPE VARCHAR(20) USING doc_type::text');
            DB::statement("ALTER TABLE qmh_documents ADD CONSTRAINT qmh_documents_doc_type_check CHECK (doc_type IN ('sop','ik','formulir'))");
            DB::statement('DROP INDEX IF EXISTS qmh_documents_clause_index');
            DB::statement('DROP INDEX IF EXISTS qmh_documents_doc_type_clause_index');
        } elseif ($driver === 'mysql') {
            DB::statement("UPDATE qmh_documents SET doc_type = 'formulir' WHERE doc_type = 'pendukung'");
            DB::statement("ALTER TABLE qmh_documents MODIFY COLUMN doc_type ENUM('sop','ik','formulir')");
            DB::statement('DROP INDEX qmh_documents_clause_index ON qmh_documents');
            DB::statement('DROP INDEX qmh_documents_doc_type_clause_index ON qmh_documents');
        }

        Schema::table('qmh_documents', function (Blueprint $table) {
            if (Schema::hasColumn('qmh_documents', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('qmh_documents', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }

    private function dropPgDocTypeCheckConstraints(): void
    {
        $constraints = DB::select(<<<'SQL'
            SELECT c.conname
            FROM pg_constraint c
            INNER JOIN pg_class t ON c.conrelid = t.oid
            INNER JOIN pg_namespace n ON t.relnamespace = n.oid
            WHERE t.relname = 'qmh_documents'
                AND n.nspname = current_schema()
                AND c.contype = 'c'
                AND pg_get_constraintdef(c.oid) ILIKE '%doc_type%'
        SQL);

        foreach ($constraints as $constraint) {
            $name = data_get($constraint, 'conname');
            if (! is_string($name) || $name === '') {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE qmh_documents DROP CONSTRAINT IF EXISTS "%s"', str_replace('"', '""', $name)));
        }
    }
};
