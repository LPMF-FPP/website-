<?php

use App\Models\GowaUpdateOperation;

it('does not expose secret-bearing snapshots in the operation projection', function (): void {
    $operation = new GowaUpdateOperation([
        'id' => (string) str()->uuid(),
        'scope' => 'gowa',
        'release_id' => 'release-a',
        'requested_version' => '1.0.0',
        'requested_digest' => 'sha256:'.str_repeat('a', 64),
        'status' => 'degraded',
        'failure_message_key' => 'gowa_update.reconciliation_failed',
        'feature_snapshot' => ['password' => 'must-not-appear'],
    ]);

    expect(json_encode($operation->safeProjection(), JSON_THROW_ON_ERROR))
        ->not->toContain('must-not-appear')
        ->not->toContain('password');
});
