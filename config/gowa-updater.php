<?php

return [
    'enabled' => (bool) env('GOWA_UPDATER_ENABLED', false),
    'no_socket_gate' => (bool) env('GOWA_UPDATER_NO_SOCKET_GATE', false),
    'catalog_path' => env('GOWA_UPDATER_CATALOG_PATH', '/etc/lpmf/gowa-updater/catalog.json'),
    'submit_helper' => env('GOWA_UPDATER_SUBMIT_HELPER', '/usr/local/sbin/lpmf-gowa-submit'),
    'runner_path' => env('GOWA_UPDATER_RUNNER_PATH', '/usr/local/libexec/lpmf-gowa-runner'),
    'capability_manifest' => env('GOWA_UPDATER_CAPABILITY_MANIFEST', '/etc/lpmf/gowa-updater/capability.json'),
    'rollback_manifest' => env('GOWA_UPDATER_ROLLBACK_MANIFEST', '/etc/lpmf/gowa-updater/rollback-manifest.json'),
    'runtime_evidence_path' => env('GOWA_UPDATER_RUNTIME_EVIDENCE_PATH', '/var/lib/lpmf/gowa-updater/runtime.json'),
    'evidence_root' => env('GOWA_UPDATER_EVIDENCE_ROOT', '/var/lib/lpmf/gowa-updater/evidence'),
    'evidence_public_key_path' => env('GOWA_UPDATER_EVIDENCE_PUBLIC_KEY_PATH', '/etc/lpmf/gowa-updater/evidence.pub'),
    'evidence_retention_days' => (int) env('GOWA_UPDATER_EVIDENCE_RETENTION_DAYS', 30),
    'lease_minutes' => (int) env('GOWA_UPDATER_LEASE_MINUTES', 10),
    'policy_version' => env('GOWA_UPDATER_POLICY_VERSION', '1'),
    'required_capability_version' => env('GOWA_UPDATER_REQUIRED_CAPABILITY_VERSION', '1'),
];
