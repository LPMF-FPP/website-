<?php

it('reports the runner contract while refusing execution without installed gates', function (): void {
    $runner = getcwd().'/ops/gowa-updater/lpmf-gowa-runner';
    $capabilities = shell_exec('bash '.escapeshellarg($runner).' --capabilities');
    expect(json_decode((string) $capabilities, true))->toMatchArray([
        'contract' => 'reconcile-first-v1',
        'fully_implemented' => true,
        'production_ready' => false,
        'capability_version' => '1',
    ]);

    $status = 0;
    exec('GOWA_UPDATER_ENABLED=1 GOWA_UPDATER_NO_SOCKET_GATE=1 bash '.escapeshellarg($runner).' 00000000-0000-4000-8000-000000000000', result_code: $status);
    expect($status)->toBe(78);
});

it('keeps example installation disabled and rejects placeholder catalog data', function (): void {
    $installer = getcwd().'/ops/gowa-updater/install';
    $status = 0;
    exec('bash '.escapeshellarg($installer).' '.escapeshellarg(getcwd().'/ops/gowa-updater/catalog.example.json').' '.escapeshellarg(getcwd().'/ops/gowa-updater/compose-envelope.example.json'), result_code: $status);
    expect($status)->toBe(78);
});
