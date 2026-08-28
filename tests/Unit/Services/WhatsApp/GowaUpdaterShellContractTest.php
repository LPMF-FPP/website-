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

it('publishes an isolated maintenance worker and exact sudo contract', function (): void {
    $worker = file_get_contents(getcwd().'/ops/gowa-updater/lpmf-gowa-maintenance.service');
    $sudoers = file_get_contents(getcwd().'/ops/gowa-updater/sudoers.example');
    $installer = file_get_contents(getcwd().'/ops/gowa-updater/install');
    $gateway = file_get_contents(getcwd().'/ops/gowa-updater/gateway.sql');

    expect($worker)->toContain('User=lpmf-gowa-maintenance')
        ->and($worker)->toContain('Group=lpmf-gowa-maintenance')
        ->and($worker)->toContain('SupplementaryGroups=')
        ->and($worker)->toContain('--queue=gowa-maintenance --tries=1')
        ->and($worker)->not->toContain('/var/run/docker.sock')
        ->and($sudoers)->toContain('lpmf-gowa-maintenance ALL=(root) NOPASSWD: LPMF_GOWA_SUBMIT')
        ->and($sudoers)->toContain('env_reset, !setenv, env_keep -= *')
        ->and($installer)->toContain('systemd-analyze verify')
        ->and($installer)->toContain('visudo -cf')
        ->and($installer)->toContain('gateway.sql')
        ->and($installer)->toContain('capability.json')
        ->and($installer)->toContain('rollback-manifest.json')
        ->and($installer)->toContain('authority.json')
        ->and($installer)->toContain('preflight.pass')
        ->and($installer)->toContain('catalog.pub')
        ->and($installer)->toContain('evidence.pub')
        ->and($installer)->toContain('$(id -u)" != 0')
        ->and($gateway)->toContain('REVOKE ALL ON SCHEMA updater_gateway FROM PUBLIC')
        ->and($gateway)->toContain('GRANT EXECUTE ON FUNCTION updater_gateway.claim_dispatch')
        ->and($gateway)->toContain('GRANT EXECUTE ON FUNCTION updater_gateway.consume_dispatch')
        ->and($gateway)->toContain('gateway_privileges_rejected')
        ->and($gateway)->toContain('CREATE OR REPLACE FUNCTION updater_gateway.assert_installation')
        ->and($gateway)->toContain('p_owner_role name, p_app_role name')
        ->and($installer)->toContain('stat -c')
        ->and($installer)->toContain('id -u lpmf-gowa-maintenance')
        ->and($installer)->toContain('getent group lpmf-gowa-maintenance');
});

it('keeps production readiness explicitly disabled in the shipped capability artifact', function (): void {
    $capability = json_decode(file_get_contents(getcwd().'/ops/gowa-updater/capability.example.json'), true, 32, JSON_THROW_ON_ERROR);

    expect($capability)->toMatchArray([
        'fully_implemented' => true,
        'production_ready' => false,
        'contract' => 'reconcile-first-v1',
        'capability_version' => '1',
    ]);
});
