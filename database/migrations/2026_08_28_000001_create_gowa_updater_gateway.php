<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->postgresGatewayEnabled()) {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA IF NOT EXISTS updater_gateway;
REVOKE ALL ON SCHEMA updater_gateway FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA updater_gateway FROM PUBLIC;

CREATE TABLE updater_gateway.dispatch_claims (
    operation_id uuid PRIMARY KEY,
    scope text NOT NULL,
    release_id text NOT NULL,
    fencing_token bigint NOT NULL,
    claim_nonce uuid NOT NULL UNIQUE,
    owner text NOT NULL,
    catalog_generation text NOT NULL,
    revocation_generation text NOT NULL,
    claim_payload jsonb NOT NULL,
    payload_hash char(64) NOT NULL,
    claimed_at timestamptz NOT NULL,
    lease_expires_at timestamptz NOT NULL,
    consumed_at timestamptz NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    CONSTRAINT gowa_updater_dispatch_scope_check CHECK (scope = 'gowa'),
    CONSTRAINT gowa_updater_dispatch_hash_check CHECK (payload_hash ~ '^[0-9a-f]{64}$')
);
REVOKE ALL ON TABLE updater_gateway.dispatch_claims FROM PUBLIC;

CREATE OR REPLACE FUNCTION updater_gateway.claim_dispatch(p_operation_id uuid, p_owner text)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, updater_gateway
AS $function$
DECLARE
    v_operation public.gowa_update_operations%ROWTYPE;
    v_scope public.gowa_update_scopes%ROWTYPE;
    v_existing updater_gateway.dispatch_claims%ROWTYPE;
    v_payload jsonb;
    v_nonce uuid;
    v_hash text;
BEGIN
    IF p_operation_id IS NULL OR p_owner IS NULL OR length(btrim(p_owner)) = 0 OR length(p_owner) > 64 THEN
        RAISE EXCEPTION 'claim_rejected' USING ERRCODE = 'P0001';
    END IF;

    SELECT * INTO v_operation
    FROM public.gowa_update_operations
    WHERE id = p_operation_id
    FOR UPDATE;

    SELECT * INTO v_scope
    FROM public.gowa_update_scopes
    WHERE scope = v_operation.scope
    FOR UPDATE;

    IF NOT FOUND OR v_operation.scope <> 'gowa'
        OR v_operation.status NOT IN ('queued', 'preparing', 'updating', 'verifying', 'reconciling')
        OR v_scope.active_operation_id <> v_operation.id
        OR v_operation.fencing_token <> v_scope.current_fence
        OR v_operation.lease_expires_at IS NULL
        OR v_operation.lease_expires_at <= clock_timestamp()
        OR v_operation.requested_digest !~ '^sha256:[0-9a-f]{64}$'
        OR coalesce(v_operation.feature_snapshot->>'catalog_generation', '') = ''
        OR coalesce(v_operation.feature_snapshot->>'revocation_generation', '') = '' THEN
        RAISE EXCEPTION 'claim_rejected' USING ERRCODE = 'P0001';
    END IF;

    SELECT * INTO v_existing
    FROM updater_gateway.dispatch_claims
    WHERE operation_id = v_operation.id
    FOR UPDATE;

    IF FOUND THEN
        IF v_existing.owner <> p_owner
            OR v_existing.release_id <> v_operation.release_id
            OR v_existing.fencing_token <> v_operation.fencing_token
            OR v_existing.catalog_generation <> v_operation.feature_snapshot->>'catalog_generation'
            OR v_existing.payload_hash <> encode(public.digest(v_existing.claim_payload::text, 'sha256'), 'hex') THEN
            RAISE EXCEPTION 'claim_payload_mismatch' USING ERRCODE = 'P0001';
        END IF;

        RETURN jsonb_build_object('replayed', true, 'payload', v_existing.claim_payload, 'payload_hash', v_existing.payload_hash);
    END IF;

    v_nonce := md5(v_operation.id::text || ':' || v_operation.fencing_token::text || ':' || clock_timestamp()::text || ':' || random()::text)::uuid;
    v_payload := jsonb_build_object(
        'operation_id', v_operation.id,
        'scope', v_operation.scope,
        'release_id', v_operation.release_id,
        'digest', v_operation.requested_digest,
        'fencing_token', v_operation.fencing_token,
        'claim_nonce', v_nonce,
        'catalog_generation', v_operation.feature_snapshot->>'catalog_generation',
        'revocation_generation', v_operation.feature_snapshot->>'revocation_generation',
        'lease_expires_at', v_operation.lease_expires_at
    );
    v_hash := encode(public.digest(v_payload::text, 'sha256'), 'hex');

    INSERT INTO updater_gateway.dispatch_claims (
        operation_id, scope, release_id, fencing_token, claim_nonce, owner,
        catalog_generation, revocation_generation, claim_payload, payload_hash,
        claimed_at, lease_expires_at, consumed_at, created_at, updated_at
    ) VALUES (
        v_operation.id, v_operation.scope, v_operation.release_id, v_operation.fencing_token,
        v_nonce, p_owner, v_operation.feature_snapshot->>'catalog_generation',
        v_operation.feature_snapshot->>'revocation_generation', v_payload, v_hash,
        clock_timestamp(), v_operation.lease_expires_at, NULL, clock_timestamp(), clock_timestamp()
    );

    RETURN jsonb_build_object('replayed', false, 'payload', v_payload, 'payload_hash', v_hash);
EXCEPTION
    WHEN unique_violation THEN
        RAISE EXCEPTION 'claim_payload_mismatch' USING ERRCODE = 'P0001';
END
$function$;

CREATE OR REPLACE FUNCTION updater_gateway.consume_dispatch(
    p_operation_id uuid,
    p_claim_nonce uuid DEFAULT NULL,
    p_fencing_token bigint DEFAULT NULL,
    p_release_id text DEFAULT NULL,
    p_payload_hash text DEFAULT NULL
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, updater_gateway
AS $function$
DECLARE
    v_claim updater_gateway.dispatch_claims%ROWTYPE;
    v_operation public.gowa_update_operations%ROWTYPE;
    v_hash text;
BEGIN
    IF session_user <> 'lpmf_gowa_submit' THEN
        RAISE EXCEPTION 'claim_unauthorized' USING ERRCODE = 'P0001';
    END IF;

    SELECT * INTO v_claim
    FROM updater_gateway.dispatch_claims
    WHERE operation_id = p_operation_id
    FOR UPDATE;

    SELECT * INTO v_operation
    FROM public.gowa_update_operations
    WHERE id = p_operation_id
    FOR UPDATE;

    v_hash := encode(public.digest(v_claim.claim_payload::text, 'sha256'), 'hex');
    IF v_claim.operation_id IS NULL OR v_operation.id IS NULL OR v_operation.scope <> 'gowa'
        OR v_claim.consumed_at IS NOT NULL
        OR v_claim.claim_nonce IS DISTINCT FROM coalesce(p_claim_nonce, v_claim.claim_nonce)
        OR v_claim.fencing_token IS DISTINCT FROM coalesce(p_fencing_token, v_claim.fencing_token)
        OR v_claim.release_id IS DISTINCT FROM coalesce(p_release_id, v_claim.release_id)
        OR v_claim.payload_hash IS DISTINCT FROM coalesce(p_payload_hash, v_claim.payload_hash)
        OR v_claim.fencing_token <> (SELECT current_fence FROM public.gowa_update_scopes WHERE scope = v_claim.scope)
        OR v_claim.lease_expires_at <= clock_timestamp()
        OR v_claim.release_id <> v_operation.release_id
        OR v_claim.payload_hash <> v_hash
        OR v_operation.status NOT IN ('queued', 'preparing', 'updating', 'verifying', 'reconciling') THEN
        RAISE EXCEPTION 'claim_rejected' USING ERRCODE = 'P0001';
    END IF;

    UPDATE updater_gateway.dispatch_claims
    SET consumed_at = clock_timestamp(), updated_at = clock_timestamp()
    WHERE operation_id = p_operation_id AND consumed_at IS NULL;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'claim_replay' USING ERRCODE = 'P0001';
    END IF;

    RETURN jsonb_build_object('replayed', false, 'payload', v_claim.claim_payload, 'payload_hash', v_claim.payload_hash);
END
$function$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'lpmf_gowa_updater_owner')
        OR NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'lpmf_gowa_submit') THEN
        RAISE EXCEPTION 'gowa_updater_roles_required' USING ERRCODE = 'P0001';
    END IF;
END
$$;

ALTER TABLE updater_gateway.dispatch_claims OWNER TO lpmf_gowa_updater_owner;
ALTER FUNCTION updater_gateway.claim_dispatch(uuid, text) OWNER TO lpmf_gowa_updater_owner;
ALTER FUNCTION updater_gateway.consume_dispatch(uuid, uuid, bigint, text, text) OWNER TO lpmf_gowa_updater_owner;
GRANT SELECT ON public.gowa_update_operations, public.gowa_update_scopes TO lpmf_gowa_updater_owner;
GRANT SELECT, INSERT, UPDATE ON TABLE updater_gateway.dispatch_claims TO lpmf_gowa_updater_owner;
REVOKE ALL ON FUNCTION updater_gateway.claim_dispatch(uuid, text) FROM PUBLIC;
REVOKE ALL ON FUNCTION updater_gateway.consume_dispatch(uuid, uuid, bigint, text, text) FROM PUBLIC;

DO $$
DECLARE
    v_app_role text := current_user;
BEGIN
    EXECUTE format('GRANT USAGE ON SCHEMA updater_gateway TO %I', v_app_role);
    EXECUTE format('GRANT EXECUTE ON FUNCTION updater_gateway.claim_dispatch(uuid, text) TO %I', v_app_role);
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'lpmf_gowa_submit') THEN
        GRANT USAGE ON SCHEMA updater_gateway TO lpmf_gowa_submit;
        GRANT EXECUTE ON FUNCTION updater_gateway.claim_dispatch(uuid, text) TO lpmf_gowa_submit;
        GRANT EXECUTE ON FUNCTION updater_gateway.consume_dispatch(uuid, uuid, bigint, text, text) TO lpmf_gowa_submit;
    END IF;
END
$$;
SQL);
    }

    public function down(): void
    {
        if (! $this->postgresGatewayEnabled()) {
            return;
        }

        DB::unprepared(<<<'SQL'
REVOKE ALL ON FUNCTION updater_gateway.claim_dispatch(uuid, text) FROM PUBLIC;
REVOKE ALL ON FUNCTION updater_gateway.consume_dispatch(uuid, uuid, bigint, text, text) FROM PUBLIC;
DROP FUNCTION IF EXISTS updater_gateway.consume_dispatch(uuid, uuid, bigint, text, text);
DROP FUNCTION IF EXISTS updater_gateway.claim_dispatch(uuid, text);
DROP SCHEMA IF EXISTS updater_gateway CASCADE;
SQL);
    }

    private function postgresGatewayEnabled(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql'
            && (! app()->environment('testing') || (bool) env('GOWA_UPDATER_RUN_PG_INTEGRATION', false));
    }
};
