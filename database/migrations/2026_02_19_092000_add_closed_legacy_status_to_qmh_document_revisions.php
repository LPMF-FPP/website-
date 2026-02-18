<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
DO $$
DECLARE
    constraint_name text;
BEGIN
    FOR constraint_name IN
        SELECT con.conname
        FROM pg_constraint con
        WHERE con.conrelid = 'qmh_document_revisions'::regclass
            AND con.contype = 'c'
            AND pg_get_constraintdef(con.oid) ILIKE '%status%'
    LOOP
        EXECUTE format('ALTER TABLE qmh_document_revisions DROP CONSTRAINT %I', constraint_name);
    END LOOP;

    ALTER TABLE qmh_document_revisions
        ADD CONSTRAINT qmh_document_revisions_status_check
        CHECK (status::text = ANY (ARRAY[
            'draft'::text,
            'in_review'::text,
            'in_approval'::text,
            'published'::text,
            'rejected'::text,
            'obsolete'::text,
            'closed_legacy'::text
        ]));
END
$$;
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DO $$
DECLARE
    constraint_name text;
BEGIN
    FOR constraint_name IN
        SELECT con.conname
        FROM pg_constraint con
        WHERE con.conrelid = 'qmh_document_revisions'::regclass
            AND con.contype = 'c'
            AND pg_get_constraintdef(con.oid) ILIKE '%status%'
    LOOP
        EXECUTE format('ALTER TABLE qmh_document_revisions DROP CONSTRAINT %I', constraint_name);
    END LOOP;

    ALTER TABLE qmh_document_revisions
        ADD CONSTRAINT qmh_document_revisions_status_check
        CHECK (status::text = ANY (ARRAY[
            'draft'::text,
            'in_review'::text,
            'in_approval'::text,
            'published'::text,
            'rejected'::text,
            'obsolete'::text
        ]));
END
$$;
SQL);
    }
};
