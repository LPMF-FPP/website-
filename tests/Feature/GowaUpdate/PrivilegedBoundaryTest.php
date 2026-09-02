<?php

it('rejects malformed runner invocations before any mutation', function (): void {
    $runner = base_path('ops/gowa-updater/lpmf-gowa-runner');
    $status = 0;
    exec('bash '.escapeshellarg($runner).' invalid 2>/dev/null', $output, $status);

    expect($status)->toBe(64);
});

it('keeps the submit helper disabled until root-owned gates are present', function (): void {
    $submit = base_path('ops/gowa-updater/lpmf-gowa-submit');
    $status = 0;
    exec('GOWA_UPDATER_ENABLED=0 GOWA_UPDATER_NO_SOCKET_GATE=0 bash '.escapeshellarg($submit).' 00000000-0000-4000-8000-000000000000 2>/dev/null', $output, $status);

    expect($status)->toBe(78);
});

it('creates one deterministic pending request before starting the unit', function (): void {
    $root = sys_get_temp_dir().'/gowa-submit-'.bin2hex(random_bytes(4));
    $bin = $root.'/bin';
    $manifest = $root.'/capability.json';
    mkdir($bin, 0700, true);
    file_put_contents($manifest, '{"fully_implemented":true,"production_ready":true,"contract":"reconcile-first-v1","capability_version":"1"}');
    file_put_contents($root.'/enabled', "enabled\n");
    file_put_contents($root.'/preflight.pass', "pass\n");
    file_put_contents($bin.'/systemctl', "#!/usr/bin/env bash\nprintf '%s' \"\$*\" > \"$root/systemctl.args\"\n");
    chmod($bin.'/systemctl', 0700);
    file_put_contents($bin.'/psql', "#!/usr/bin/env bash\nprintf '%s\\n' '{\"replayed\":false,\"payload\":{\"operation_id\":\"00000000-0000-4000-8000-000000000000\",\"scope\":\"gowa\",\"claim_nonce\":\"00000000-0000-4000-8000-000000000001\",\"fencing_token\":1,\"release_id\":\"release-a\",\"digest\":\"sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\",\"catalog_generation\":\"generation-1\",\"revocation_generation\":\"revocation-1\"},\"payload_hash\":\"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\"}'\n");
    chmod($bin.'/psql', 0700);
    $operationDirectory = $root.'/requests/00000000-0000-4000-8000-000000000000';
    mkdir($operationDirectory, 0700, true);
    file_put_contents($operationDirectory.'/request.json', json_encode([
        'operation_id' => '00000000-0000-4000-8000-000000000000',
        'action' => 'update',
        'release_id' => 'release-a',
        'fence' => 1,
    ], JSON_THROW_ON_ERROR));

    $command = sprintf(
        'PATH=%s:/usr/bin:/bin GOWA_UPDATER_CAPABILITY_MANIFEST=%s GOWA_UPDATER_ENABLED_MARKER=%s GOWA_UPDATER_PREFLIGHT_GATE=%s GOWA_UPDATER_AUTHORITY_PATH=%s GOWA_UPDATER_REQUEST_ROOT=%s bash %s 00000000-0000-4000-8000-000000000000',
        escapeshellarg($bin), escapeshellarg($manifest), escapeshellarg($root.'/enabled'), escapeshellarg($root.'/preflight.pass'), escapeshellarg($root.'/authority.json'), escapeshellarg($root.'/requests'), escapeshellarg(base_path('ops/gowa-updater/lpmf-gowa-submit'))
    );
    exec($command, $output, $status);

    expect($status)->toBe(0)
        ->and(is_file($root.'/requests/00000000-0000-4000-8000-000000000000/request.pending'))->toBeTrue()
        ->and(json_decode(file_get_contents($root.'/authority.json'), true, 32, JSON_THROW_ON_ERROR))->toMatchArray(['operation_id' => '00000000-0000-4000-8000-000000000000', 'fence' => 1, 'revoked' => false])
        ->and(file_get_contents($root.'/systemctl.args'))->toContain('lpmf-gowa-update@00000000-0000-4000-8000-000000000000.service');

    unlink($bin.'/systemctl');
    unlink($bin.'/psql');
    unlink($manifest);
    unlink($root.'/enabled');
    unlink($root.'/preflight.pass');
    unlink($root.'/authority.json');
    unlink($root.'/systemctl.args');
    unlink($root.'/requests/00000000-0000-4000-8000-000000000000/request.pending');
    rmdir($root.'/requests/00000000-0000-4000-8000-000000000000');
    rmdir($root.'/requests');
    rmdir($bin);
    rmdir($root);
});
