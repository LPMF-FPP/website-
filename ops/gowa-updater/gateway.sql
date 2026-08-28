-- Installed and executed only by the root updater installer.
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA IF NOT EXISTS updater_gateway;
REVOKE ALL ON SCHEMA updater_gateway FROM PUBLIC;
CREATE TABLE IF NOT EXISTS updater_gateway.dispatch_claims (
    operation_id uuid PRIMARY KEY,
    scope text NOT NULL,
    release_id text NOT NULL,
    fencing_token bigint NOT NULL,
    claim_nonce uuid UNIQUE NOT NULL,
    owner text NOT NULL,
    catalog_generation text NOT NULL,
    revocation_generation text NOT NULL,
    claim_payload jsonb NOT NULL,
    payload_hash text NOT NULL,
    claimed_at timestamptz NOT NULL,
    lease_expires_at timestamptz NOT NULL,
    consumed_at timestamptz,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL
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
    v_claim updater_gateway.dispatch_claims%ROWTYPE;
    v_payload jsonb;
    v_nonce uuid;
    v_hash text;
BEGIN
    IF p_operation_id IS NULL OR p_owner IS DISTINCT FROM 'gowa-maintenance' THEN
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

    SELECT * INTO v_claim
    FROM updater_gateway.dispatch_claims
    WHERE operation_id = v_operation.id
    FOR UPDATE;

    IF FOUND THEN
        IF v_claim.owner <> p_owner
            OR v_claim.release_id <> v_operation.release_id
            OR v_claim.fencing_token <> v_operation.fencing_token
            OR v_claim.catalog_generation <> v_operation.feature_snapshot->>'catalog_generation'
            OR v_claim.payload_hash <> encode(public.digest(v_claim.claim_payload::text, 'sha256'), 'hex') THEN
            RAISE EXCEPTION 'claim_payload_mismatch' USING ERRCODE = 'P0001';
        END IF;

        RETURN jsonb_build_object('replayed', true, 'payload', v_claim.claim_payload, 'payload_hash', v_claim.payload_hash);
    END IF;

    v_nonce := gen_random_uuid();
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
        claimed_at, lease_expires_at, created_at, updated_at
    ) VALUES (
        v_operation.id, v_operation.scope, v_operation.release_id, v_operation.fencing_token,
        v_nonce, p_owner, v_operation.feature_snapshot->>'catalog_generation',
        v_operation.feature_snapshot->>'revocation_generation', v_payload, v_hash,
        clock_timestamp(), v_operation.lease_expires_at, clock_timestamp(), clock_timestamp()
    );

    RETURN jsonb_build_object('replayed', false, 'payload', v_payload, 'payload_hash', v_hash);
EXCEPTION
    WHEN unique_violation THEN
        RAISE EXCEPTION 'claim_payload_mismatch' USING ERRCODE = 'P0001';
END
$function$;

CREATE OR REPLACE FUNCTION updater_gateway.consume_dispatch(
    p_operation_id uuid,
    p_claim_nonce uuid,
    p_fencing_token bigint,
    p_release_id text,
    p_payload_hash text
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

    SELECT * INTO v_claim FROM updater_gateway.dispatch_claims WHERE operation_id = p_operation_id FOR UPDATE;
    SELECT * INTO v_operation FROM public.gowa_update_operations WHERE id = p_operation_id FOR UPDATE;
    v_hash := encode(public.digest(v_claim.claim_payload::text, 'sha256'), 'hex');

    IF v_claim.operation_id IS NULL OR v_operation.id IS NULL
        OR v_claim.consumed_at IS NOT NULL
        OR v_claim.claim_nonce <> p_claim_nonce
        OR v_claim.fencing_token <> p_fencing_token
        OR v_claim.release_id <> p_release_id
        OR v_claim.payload_hash <> p_payload_hash
        OR v_claim.payload_hash <> v_hash
        OR v_claim.fencing_token <> (SELECT current_fence FROM public.gowa_update_scopes WHERE scope = v_claim.scope)
        OR v_claim.lease_expires_at <= clock_timestamp()
        OR v_operation.scope <> 'gowa'
        OR v_operation.release_id <> v_claim.release_id
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

REVOKE ALL ON FUNCTION updater_gateway.claim_dispatch(uuid, text) FROM PUBLIC;
REVOKE ALL ON FUNCTION updater_gateway.consume_dispatch(uuid, uuid, bigint, text, text) FROM PUBLIC;
GRANT USAGE ON SCHEMA updater_gateway TO lpmf_gowa_submit;
GRANT EXECUTE ON FUNCTION updater_gateway.claim_dispatch(uuid, text) TO lpmf_gowa_submit;
GRANT EXECUTE ON FUNCTION updater_gateway.consume_dispatch(uuid, uuid, bigint, text, text) TO lpmf_gowa_submit;

CREATE OR REPLACE FUNCTION updater_gateway.assert_installation(p_submit_role name, p_owner_role name, p_app_role name)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, updater_gateway
AS $function$
BEGIN
    IF EXISTS (
            SELECT 1
            FROM pg_namespace n
            CROSS JOIN LATERAL aclexplode(COALESCE(n.nspacl, acldefault('n', n.nspowner))) acl
            WHERE n.nspname = 'updater_gateway'
              AND acl.grantee = 0
              AND acl.privilege_type IN ('USAGE', 'CREATE')
        )
        OR has_schema_privilege(p_submit_role, 'updater_gateway', 'CREATE')
              OR NOT has_function_privilege(p_submit_role, 'updater_gateway.claim_dispatch(uuid,text)', 'EXECUTE')
        OR NOT has_function_privilege(p_submit_role, 'updater_gateway.consume_dispatch(uuid,uuid,bigint,text,text)', 'EXECUTE')
              OR pg_get_userbyid((SELECT nspowner FROM pg_namespace WHERE nspname = 'updater_gateway')) <> p_owner_role::text
              OR pg_get_userbyid((SELECT relowner FROM pg_class WHERE oid = 'updater_gateway.dispatch_claims'::regclass)) <> p_owner_role::text
              OR pg_get_userbyid((SELECT proowner FROM pg_proc WHERE pronamespace = 'updater_gateway'::regnamespace AND proname = 'claim_dispatch' AND oidvectortypes(proargtypes) = 'uuid, text')) <> p_owner_role::text
              OR pg_get_userbyid((SELECT proowner FROM pg_proc WHERE pronamespace = 'updater_gateway'::regnamespace AND proname = 'consume_dispatch' AND oidvectortypes(proargtypes) = 'uuid, uuid, bigint, text, text')) <> p_owner_role::text
              OR has_function_privilege(p_app_role, 'updater_gateway.claim_dispatch(uuid,text)', 'EXECUTE')
              OR has_function_privilege(p_app_role, 'updater_gateway.consume_dispatch(uuid,uuid,bigint,text,text)', 'EXECUTE') THEN
        RAISE EXCEPTION 'gateway_privileges_rejected';
    END IF;
END
$function$;
REVOKE ALL ON FUNCTION updater_gateway.assert_installation(name, name, name) FROM PUBLIC;
