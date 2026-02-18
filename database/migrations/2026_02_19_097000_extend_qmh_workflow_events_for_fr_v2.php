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
        WHERE con.conrelid = 'qmh_workflow_events'::regclass
            AND con.contype = 'c'
            AND pg_get_constraintdef(con.oid) ILIKE '%event_type%'
    LOOP
        EXECUTE format('ALTER TABLE qmh_workflow_events DROP CONSTRAINT %I', constraint_name);
    END LOOP;

    ALTER TABLE qmh_workflow_events
        ADD CONSTRAINT qmh_workflow_events_event_type_check
        CHECK (event_type::text = ANY (ARRAY[
            'create_draft'::text,
            'submit_review'::text,
            'review_return'::text,
            'review_pass'::text,
            'approve'::text,
            'reject'::text,
            'publish'::text,
            'download'::text,
            'lock'::text,
            'unlock'::text,
            'checker_pass'::text,
            'checker_fail'::text,
            'checker_unavailable'::text,
            'attestation_fallback'::text,
            'close_legacy'::text,
            'duplicate_to_v2'::text,
            'cutover_idempotent_replay'::text
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
        WHERE con.conrelid = 'qmh_workflow_events'::regclass
            AND con.contype = 'c'
            AND pg_get_constraintdef(con.oid) ILIKE '%event_type%'
    LOOP
        EXECUTE format('ALTER TABLE qmh_workflow_events DROP CONSTRAINT %I', constraint_name);
    END LOOP;

    ALTER TABLE qmh_workflow_events
        ADD CONSTRAINT qmh_workflow_events_event_type_check
        CHECK (event_type::text = ANY (ARRAY[
            'create_draft'::text,
            'submit_review'::text,
            'review_return'::text,
            'review_pass'::text,
            'approve'::text,
            'reject'::text,
            'publish'::text,
            'download'::text,
            'lock'::text,
            'unlock'::text
        ]));
END
$$;
SQL);
    }
};
