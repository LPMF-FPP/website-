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
    $updateService = file_get_contents(getcwd().'/ops/gowa-updater/lpmf-gowa-update@.service');
    $sudoers = file_get_contents(getcwd().'/ops/gowa-updater/sudoers.example');
    $installer = file_get_contents(getcwd().'/ops/gowa-updater/install');
    $gateway = file_get_contents(getcwd().'/ops/gowa-updater/gateway.sql');
    $releaseMaintenance = file_get_contents(getcwd().'/ops/gowa-updater/lpmf-gowa-release-maintenance');

    expect($worker)->toContain('User=lpmf-gowa-maintenance')
        ->and($worker)->toContain('Group=lpmf-gowa-maintenance')
        ->and($worker)->toContain('SupplementaryGroups=')
        ->and($worker)->toContain('--queue=gowa-maintenance --tries=1')
        ->and($worker)->toContain('RuntimeDirectory=lpmf/gowa-updater')
        ->and($worker)->toContain('RuntimeDirectoryMode=0750')
        ->and($worker)->toContain('RuntimeDirectoryPreserve=yes')
        ->and($worker)->toContain('NoNewPrivileges=no')
        ->and($worker)->toContain('RestrictSUIDSGID=yes')
        ->and($worker)->toContain('CapabilityBoundingSet=CAP_CHOWN CAP_DAC_READ_SEARCH CAP_SETUID CAP_SETGID')
        ->and($worker)->toContain('/var/lib/lpmf/gowa-updater')
        ->and($worker)->toContain('/etc/lpmf/gowa-updater /var/backups/lpmf/gowa')
        ->and($worker)->toContain('/run/lpmf/gowa-updater')
        ->and($worker)->toContain('CapabilityBoundingSet=')
        ->and($worker)->not->toContain('/var/run/docker.sock')
        ->and($updateService)->toContain('SupplementaryGroups=lpmf-admin')
        ->and($updateService)->toContain('www-data')
        ->and($updateService)->toContain('ProtectHome=read-only')
        ->and($updateService)->toContain('CapabilityBoundingSet=')
        ->and($updateService)->toContain('NoNewPrivileges=yes')
        ->and($updateService)->toContain('/etc/lpmf/gowa-updater')
        ->and($sudoers)->toContain('NOPASSWD: LPMF_GOWA_SUBMIT, LPMF_GOWA_PREPARE')
        ->and($sudoers)->toContain('--prepare-capabilities')
        ->and($sudoers)->toContain('--prepare-latest')
        ->and($sudoers)->toContain('env_reset, !setenv, env_keep -= *')
        ->and($installer)->toContain('systemd-analyze verify')
        ->and($installer)->toContain('visudo -cf')
        ->and($installer)->toContain('gateway.sql')
        ->and($installer)->toContain('capability.json')
        ->and($installer)->toContain('rollback-manifest.json')
        ->and($installer)->toContain('An explicit current-production rollback manifest is required')
        ->and($installer)->toContain('authority.json')
        ->and($installer)->toContain('preflight.pass')
        ->and($installer)->toContain('catalog.pub')
        ->and($installer)->toContain('evidence.pub')
        ->and($installer)->toContain('RestrictSUIDSGID=yes')
        ->and($installer)->toContain('CapabilityBoundingSet=CAP_CHOWN CAP_DAC_READ_SEARCH CAP_SETUID CAP_SETGID')
        ->and($installer)->toContain('/etc/lpmf/gowa-updater /var/backups/lpmf/gowa')
        ->and($installer)->toContain('$(id -u)" != 0')
        ->and($installer)->toContain('setfacl -m u:root:r--')
        ->and($installer)->toContain('root:www-data:640')
        ->and($installer)->toContain('preparation-capability.json')
        ->and($gateway)->toContain('REVOKE ALL ON SCHEMA updater_gateway FROM PUBLIC')
        ->and($gateway)->toContain('GRANT EXECUTE ON FUNCTION updater_gateway.claim_dispatch')
        ->and($gateway)->toContain('GRANT EXECUTE ON FUNCTION updater_gateway.consume_dispatch')
        ->and($gateway)->toContain('gateway_privileges_rejected')
        ->and($gateway)->toContain('CREATE OR REPLACE FUNCTION updater_gateway.assert_installation')
        ->and($gateway)->toContain('p_owner_role name, p_app_role name')
        ->and($installer)->toContain('stat -c')
        ->and($installer)->toContain('setpriv --reuid 0 --regid 0 --groups lpmf-admin --bounding-set=-all')
        ->and($installer)->toContain('id -u lpmf-gowa-maintenance')
        ->and($installer)->toContain('getent group lpmf-gowa-maintenance');
    expect($installer)->toContain('preparation-capability.json')
        ->and($installer)->toContain('www-data ALL=(root) NOPASSWD: /usr/local/sbin/lpmf-gowa-release-maintenance --prepare-capabilities')
        ->and($installer)->toContain('lpmf-gowa-runtime-probe.timer')
        ->and($releaseMaintenance)->toContain('--prepare-latest')
        ->and($releaseMaintenance)->toContain('--enable-preparation');
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

it('prepares the latest immutable release without replacing the running container', function (): void {
    $maintenance = file_get_contents(getcwd().'/ops/gowa-updater/lpmf-gowa-release-maintenance');
    $prepareStart = strpos($maintenance, 'prepare_latest() {');
    $prepareEnd = strpos($maintenance, 'preparation_capabilities() {');
    $prepareSource = substr($maintenance, $prepareStart, $prepareEnd - $prepareStart);

    expect($prepareStart)->not->toBeFalse()
        ->and($prepareEnd)->not->toBeFalse()
        ->and($prepareSource)->toContain('docker pull "$image"')
        ->and($prepareSource)->toContain('verify_current_catalog')
        ->and($prepareSource)->toContain('verify_runtime_baseline')
        ->and($prepareSource)->toContain('/var/backups/lpmf/gowa/prepare-${version}-')
        ->and($prepareSource)->toContain('rollback_manifest')
        ->and($prepareSource)->not->toContain('compose up')
        ->and($prepareSource)->not->toContain('docker stop')
        ->and($prepareSource)->not->toContain('docker rm');

    expect($maintenance)->toContain('database-name')
        ->and($maintenance)->toContain('root:root:600')
        ->and($maintenance)->toContain('--arg helper_hash')
        ->and($maintenance)->toContain('catalog_tmp:-')
        ->and($maintenance)->toContain('preparation_capabilities');
});

it('upgrades updater artifacts with a backup and verifies that the GOWA image is unchanged', function (): void {
    $upgrade = file_get_contents(getcwd().'/ops/gowa-updater/upgrade-preparation');

    expect($upgrade)->toContain('backup="/var/backups/lpmf/gowa/preparation-upgrade-')
        ->and($upgrade)->toContain('rollback_on_failure')
        ->and($upgrade)->toContain('systemctl enable --now lpmf-gowa-runtime-probe.timer')
        ->and($upgrade)->toContain('GOWA container identity or image changed during updater maintenance')
        ->and($upgrade)->toContain('chmod 0600 "$database_name_file"')
        ->and($upgrade)->toContain('install this reviewed bootstrap as a root-owned mode-0750 file first')
        ->and($upgrade)->toContain('gowa-updater:preflight')
        ->and($upgrade)->not->toContain('docker compose up')
        ->and($upgrade)->not->toContain('docker stop')
        ->and($upgrade)->not->toContain('docker rm');
});

it('ships a signed runtime probe and periodic systemd timer', function (): void {
    $probe = file_get_contents(getcwd().'/ops/gowa-updater/lpmf-gowa-runtime-probe');
    $service = file_get_contents(getcwd().'/ops/gowa-updater/lpmf-gowa-runtime-probe.service');
    $timer = file_get_contents(getcwd().'/ops/gowa-updater/lpmf-gowa-runtime-probe.timer');

    expect($probe)->toContain('openssl pkeyutl -sign -rawin')
        ->and($probe)->toContain('/var/run/docker.sock')
        ->and($service)->toContain('ExecStart=/usr/local/libexec/lpmf-gowa-runtime-probe')
        ->and($service)->toContain('/var/run/docker.sock')
        ->and($timer)->toContain('OnUnitActiveSec=30s');
});

it('prepares the latest immutable release without starting a container replacement', function (): void {
    $maintenance = file_get_contents(getcwd().'/ops/gowa-updater/lpmf-gowa-release-maintenance');
    $prepareStart = strpos($maintenance, 'prepare_latest() {');
    $prepareEnd = strpos($maintenance, 'preparation_capabilities() {');
    $preparationBlock = substr($maintenance, $prepareStart, $prepareEnd - $prepareStart);

    expect($prepareStart)->not->toBeFalse()
        ->and($prepareEnd)->not->toBeFalse()
        ->and($preparationBlock)->toContain('docker pull "$image"')
        ->and($preparationBlock)->toContain('verify_current_catalog')
        ->and($preparationBlock)->toContain('verify_runtime_baseline')
        ->and($preparationBlock)->toContain('rollback_manifest')
        ->and($preparationBlock)->not->toContain('compose up')
        ->and($preparationBlock)->not->toContain('docker stop')
        ->and($preparationBlock)->not->toContain('docker rm');
});
