<?php

use Illuminate\Support\Str;

function gowaUpdaterGatewayMigration(): string
{
    return file_get_contents(base_path('database/migrations/2026_08_28_000001_create_gowa_updater_gateway.php')) ?: '';
}

it('keeps unauthorized root claims behind the explicit submit role grant', function (): void {
    $sql = gowaUpdaterGatewayMigration();

    expect($sql)->toContain("RAISE EXCEPTION 'claim_unauthorized'")
        ->and($sql)->toContain('REVOKE ALL ON FUNCTION updater_gateway.consume_dispatch')
        ->and($sql)->toContain('GRANT EXECUTE ON FUNCTION updater_gateway.consume_dispatch')
        ->and($sql)->not->toContain('GRANT EXECUTE ON FUNCTION updater_gateway.consume_dispatch(uuid, uuid, bigint, text, text) TO PUBLIC');
});

it('binds replay and payload validation to the durable claim nonce', function (): void {
    $sql = gowaUpdaterGatewayMigration();

    expect($sql)->toContain('claim_nonce uuid NOT NULL UNIQUE')
        ->and($sql)->toContain('v_claim.consumed_at IS NOT NULL')
        ->and($sql)->toContain('v_claim.claim_nonce IS DISTINCT FROM coalesce(p_claim_nonce, v_claim.claim_nonce)')
        ->and($sql)->toContain('v_claim.payload_hash IS DISTINCT FROM coalesce(p_payload_hash, v_claim.payload_hash)');
});

it('requires the current fence and an unexpired lease before root consumption', function (): void {
    $sql = gowaUpdaterGatewayMigration();

    expect($sql)->toContain('v_claim.fencing_token <> (SELECT current_fence')
        ->and($sql)->toContain('v_claim.lease_expires_at <= clock_timestamp()')
        ->and($sql)->toContain('v_operation.status NOT IN');
});

it('revalidates the release and revocation generations captured by the application', function (): void {
    $sql = gowaUpdaterGatewayMigration();

    expect($sql)->toContain("coalesce(v_operation.feature_snapshot->>'revocation_generation', '') = ''")
        ->and($sql)->toContain("v_operation.feature_snapshot->>'revocation_generation'")
        ->and($sql)->toContain('v_claim.release_id <> v_operation.release_id');
});

it('binds root consumption to the operation release and canonical payload hash', function (): void {
    $sql = gowaUpdaterGatewayMigration();

    expect($sql)->toContain('v_claim.release_id <> v_operation.release_id')
        ->and($sql)->toContain('v_claim.payload_hash <> v_hash')
        ->and($sql)->toContain("'payload_hash', v_claim.payload_hash");
});

it('does not transport credentials or arbitrary command input across the helper boundary', function (): void {
    $helper = file_get_contents(base_path('ops/gowa-updater/lpmf-gowa-submit')) ?: '';
    $runner = file_get_contents(base_path('ops/gowa-updater/lpmf-gowa-runner')) ?: '';

    expect($helper)->toContain('claim_dispatch')
        ->and($helper)->toContain('operation_id')
        ->and($helper)->not->toMatch('/DB_PASSWORD|GOWA_PASSWORD|Authorization|docker\.sock/i')
        ->and($runner)->toContain('consume_dispatch')
        ->and($runner)->toContain('claim_nonce')
        ->and($runner)->not->toMatch('/DB_PASSWORD|GOWA_PASSWORD|Authorization/i')
        ->and(Str::contains($helper, 'systemctl start --no-block "$unit"'))->toBeTrue();
});
