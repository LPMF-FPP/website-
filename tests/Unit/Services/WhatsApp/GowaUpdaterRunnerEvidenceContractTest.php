<?php

use App\Services\WhatsApp\GowaEvidenceImporter;

function gowaRunnerFixture(string $dockerMode = 'forward-success'): array
{
    $root = sys_get_temp_dir().'/gowa-runner-'.bin2hex(random_bytes(5));
    $paths = [
        'root' => $root,
        'requestRoot' => $root.'/requests',
        'evidenceRoot' => $root.'/evidence',
        'workingDirectory' => $root.'/project',
        'composeFile' => $root.'/project/compose.yml',
        'overrideFile' => $root.'/project/override.json',
        'manifest' => $root.'/capability.json',
        'catalog' => $root.'/catalog.json',
        'envelope' => $root.'/envelope.json',
        'authority' => $root.'/authority.json',
        'rollbackManifest' => $root.'/rollback-manifest.json',
        'gate' => $root.'/preflight.pass',
        'enabledMarker' => $root.'/enabled',
        'signingKey' => $root.'/evidence.key',
        'publicKey' => $root.'/evidence.pub',
        'docker' => $root.'/docker',
        'dockerState' => $root.'/docker-state',
        'psql' => $root.'/psql',
        'lock' => $root.'/update.lock',
    ];
    mkdir($paths['requestRoot'], 0700, true);
    mkdir($paths['evidenceRoot'], 0700, true);
    mkdir($paths['workingDirectory'], 0700, true);

    $operationId = '00000000-0000-4000-8000-000000000000';
    $claimNonce = '00000000-0000-4000-8000-000000000001';
    $digest = 'sha256:'.str_repeat('a', 64);
    $previousDigest = 'sha256:'.str_repeat('b', 64);
    $image = 'registry.example.test/gowa@'.$digest;
    $previousImage = 'registry.example.test/gowa@'.$previousDigest;
    $requestHash = str_repeat('c', 64);
    $operationRequest = $paths['requestRoot'].'/'.$operationId;
    mkdir($operationRequest, 0700, true);
    file_put_contents($operationRequest.'/request.pending', json_encode([
        'operation_id' => $operationId,
        'action' => 'update',
        'scope' => 'gowa',
        'fence' => 1,
        'claim_nonce' => $claimNonce,
        'payload_hash' => $requestHash,
        'release_id' => 'release-current',
    ], JSON_THROW_ON_ERROR));

    $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
    $keyPair = sodium_crypto_sign_seed_keypair($seed);
    $public = sodium_crypto_sign_publickey($keyPair);
    file_put_contents($paths['signingKey'], "-----BEGIN PRIVATE KEY-----\n".chunk_split(base64_encode("\x30\x2e\x02\x01\x00\x30\x05\x06\x03\x2b\x65\x70\x04\x22\x04\x20".$seed), 64, "\n")."-----END PRIVATE KEY-----\n");
    file_put_contents($paths['publicKey'], base64_encode($public));
    chmod($paths['publicKey'], 0600);
    chmod($paths['signingKey'], 0600);

    file_put_contents($paths['manifest'], json_encode([
        'contract' => 'reconcile-first-v1',
        'fully_implemented' => true,
        'production_ready' => true,
        'capability_version' => '1',
    ], JSON_THROW_ON_ERROR));
    $catalog = [
        'schema_version' => 1,
        'signature_valid' => true,
        'generation' => 'generation-1',
        'revocation_generation' => 'revocation-1',
        'approved_registry' => 'registry.example.test',
        'approved_repository' => 'registry.example.test/gowa',
        'platform' => 'linux/amd64',
        'signature' => ['algorithm' => 'ed25519', 'key_id' => 'catalog-test-key', 'value' => ''],
        'releases' => [[
            'release_id' => 'release-current',
            'approved' => true,
            'revoked' => false,
            'digest' => $digest,
            'image' => $image,
            'revocation_generation' => 'revocation-1',
        ]],
    ];
    $catalogKeyPair = sodium_crypto_sign_keypair();
    $catalogPayload = $catalog;
    unset($catalogPayload['signature'], $catalogPayload['signature_valid']);
    $catalogSort = function (array $value) use (&$catalogSort): array {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $catalogSort($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    };
    $catalog['signature']['value'] = base64_encode(sodium_crypto_sign_detached(
        json_encode($catalogSort($catalogPayload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        sodium_crypto_sign_secretkey($catalogKeyPair),
    ));
    file_put_contents($paths['catalog'], json_encode($catalog, JSON_THROW_ON_ERROR));
    file_put_contents($paths['root'].'/catalog.pub', base64_encode(sodium_crypto_sign_publickey($catalogKeyPair)));
    chmod($paths['root'].'/catalog.pub', 0600);
    file_put_contents($paths['envelope'], json_encode([
        'project' => 'go-whatsapp-web-multidevice',
        'service' => 'whatsapp_go',
        'working_directory' => $paths['workingDirectory'],
        'compose_files' => [$paths['composeFile']],
        'image_override' => $paths['overrideFile'],
        'network_mode' => 'host',
        'restart_policy' => 'unless-stopped',
        'required_socket_absent' => true,
    ], JSON_THROW_ON_ERROR));
    file_put_contents($paths['composeFile'], "services:\n  whatsapp_go:\n    image: placeholder\n");
    file_put_contents($paths['overrideFile'], json_encode(['services' => ['whatsapp_go' => ['image' => $image]]], JSON_THROW_ON_ERROR));
    file_put_contents($paths['dockerState'], 'previous');
    file_put_contents($paths['authority'], json_encode([
        'operation_id' => $operationId,
        'fence' => 1,
        'revoked' => false,
    ], JSON_THROW_ON_ERROR));
    file_put_contents($paths['rollbackManifest'], json_encode([
        'schema_version' => 1,
        'previous_image' => $previousImage,
        'project' => 'go-whatsapp-web-multidevice',
        'service' => 'whatsapp_go',
        'working_directory' => $paths['workingDirectory'],
        'compose_files' => [$paths['composeFile']],
        'image_override' => $paths['overrideFile'],
        'network_mode' => 'host',
        'restart_policy' => 'unless-stopped',
        'mounts' => [],
        'fixed_config_hash' => 'd2c28428f03bb3573f2e617cd97b373544352eff796e585f9e0896c5b0c90e57',
    ], JSON_THROW_ON_ERROR));
    file_put_contents($paths['gate'], "pass\n");
    file_put_contents($paths['enabledMarker'], "enabled\n");

    $docker = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
mode="${FAKE_DOCKER_MODE}"
state_file="${FAKE_DOCKER_STATE:?}"
joined=" $* "
if [[ "$1" == "compose" && "$joined" == *" config --format json "* ]]; then
  image="registry.example.test/gowa@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
  if [[ "$joined" == *"rollback-compose.json"* ]]; then
    image="registry.example.test/gowa@sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
  fi
  printf '%s\n' "{\"services\":{\"whatsapp_go\":{\"image\":\"$image\",\"network_mode\":\"host\",\"restart\":\"unless-stopped\"}}}"
  exit 0
fi
if [[ "$1" == "compose" && "$joined" == *" pull whatsapp_go "* ]]; then
  if [[ "$joined" == *"rollback-compose.json"* ]]; then
    [[ "$mode" != "rollback-fail" ]]
  else
    [[ "$mode" == "forward-success" ]]
  fi
  status=$?
  [[ "$status" -eq 0 && "$joined" != *"rollback-compose.json"* ]] && printf '%s' target > "$state_file"
  exit "$status"
fi
if [[ "$1" == "compose" && "$joined" == *" up --no-deps --wait whatsapp_go "* ]]; then
  if [[ "$joined" == *"rollback-compose.json"* ]]; then
    [[ "$mode" != "rollback-fail" ]]
  else
    [[ "$mode" == "forward-success" ]]
  fi
  status=$?
  [[ "$status" -eq 0 && "$joined" == *"rollback-compose.json"* ]] && printf '%s' rollback > "$state_file"
  exit "$status"
fi
if [[ "$1" == "compose" && "$joined" == *" ps --format json whatsapp_go "* ]]; then
  if [[ "$joined" == *"rollback-compose.json"* ]]; then
    [[ "$mode" != "rollback-fail" ]] || exit 1
    printf '%s\n' '[{"ID":"bbbbbbbbbbbb"}]'
  else
    state="$(<"$state_file")"
    if [[ "$state" == target ]]; then
      [[ "$mode" == "forward-success" ]] || exit 1
      printf '%s\n' '[{"ID":"aaaaaaaaaaaa"}]'
    else
      printf '%s\n' '[{"ID":"bbbbbbbbbbbb"}]'
    fi
  fi
  exit 0
fi
if [[ "$1" == "inspect" ]]; then
  image="registry.example.test/gowa@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
  id="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
  if [[ "$2" == "bbbbbbbbbbbb" ]]; then
    image="registry.example.test/gowa@sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
    id="bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
  fi
  printf '%s\n' "[{\"Id\":\"$id\",\"Config\":{\"Labels\":{\"com.docker.compose.project\":\"go-whatsapp-web-multidevice\",\"com.docker.compose.service\":\"whatsapp_go\"},\"Image\":\"$image\"},\"HostConfig\":{\"NetworkMode\":\"host\",\"RestartPolicy\":{\"Name\":\"unless-stopped\"}},\"Mounts\":[]}]"
  exit 0
fi
exit 1
BASH;
    file_put_contents($paths['docker'], $docker);
    chmod($paths['docker'], 0700);

    $psql = <<<'BASH'
#!/usr/bin/env bash
printf '%s\n' '{"replayed":false,"payload":{"operation_id":"00000000-0000-4000-8000-000000000000","claim_nonce":"00000000-0000-4000-8000-000000000001","release_id":"release-current","digest":"sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","catalog_generation":"generation-1","revocation_generation":"revocation-1","fencing_token":1},"payload_hash":"cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc"}'
BASH;
    file_put_contents($paths['psql'], $psql);
    chmod($paths['psql'], 0700);

    return $paths + compact('operationId', 'keyPair', 'image', 'previousImage', 'requestHash', 'dockerMode') + ['publicKey' => $paths['publicKey']];
}

function removeGowaRunnerFixture(array $fixture): void
{
    exec('rm -rf '.escapeshellarg($fixture['root']));
}

function runGowaRunner(array $fixture): array
{
    $runner = getcwd().'/ops/gowa-updater/lpmf-gowa-runner';
    $env = [
        'GOWA_UPDATER_ENABLED' => '1',
        'GOWA_UPDATER_NO_SOCKET_GATE' => '1',
        'GOWA_UPDATER_CAPABILITY_MANIFEST' => $fixture['manifest'],
        'GOWA_UPDATER_CATALOG' => $fixture['catalog'],
        'GOWA_UPDATER_CATALOG_PUBLIC_KEY' => $fixture['root'].'/catalog.pub',
        'GOWA_UPDATER_ENVELOPE' => $fixture['envelope'],
        'GOWA_UPDATER_REQUEST_ROOT' => $fixture['requestRoot'],
        'GOWA_UPDATER_EVIDENCE_ROOT' => $fixture['evidenceRoot'],
        'GOWA_UPDATER_AUTHORITY_PATH' => $fixture['authority'],
        'GOWA_UPDATER_LOCK_PATH' => $fixture['lock'],
        'GOWA_UPDATER_PREFLIGHT_GATE' => $fixture['gate'],
        'GOWA_UPDATER_ENABLED_MARKER' => $fixture['enabledMarker'],
        'GOWA_UPDATER_EVIDENCE_SIGNING_KEY' => $fixture['signingKey'],
        'GOWA_UPDATER_ROLLBACK_MANIFEST' => $fixture['rollbackManifest'],
        'GOWA_UPDATER_DOCKER_BIN' => $fixture['docker'],
        'GOWA_UPDATER_PSQL_BIN' => $fixture['psql'],
        'FAKE_DOCKER_MODE' => $fixture['dockerMode'],
        'FAKE_DOCKER_STATE' => $fixture['dockerState'],
    ];
    $command = implode(' ', array_map(static fn (string $key, string $value): string => $key.'='.escapeshellarg($value), array_keys($env), $env));
    $status = 0;
    exec($command.' bash '.escapeshellarg($runner).' '.escapeshellarg($fixture['operationId']), result_code: $status);

    $evidence = array_filter(
        glob($fixture['evidenceRoot'].'/*/*/*.json') ?: [],
        static fn (string $path): bool => basename(dirname($path)) === '1',
    );

    return ['status' => $status, 'evidence' => array_values($evidence)];
}

it('produces runner evidence that the Laravel importer accepts', function (): void {
    $fixture = gowaRunnerFixture();

    try {
        $result = runGowaRunner($fixture);
        expect($result['status'])->toBe(0)->and($result['evidence'])->toHaveCount(3);

        $payloads = array_map(fn (string $path): array => (new GowaEvidenceImporter($fixture['publicKey'], false))->decode($path), $result['evidence']);
        expect(array_column($payloads, 'sequence'))->toBe([1, 2, 3])
            ->and(array_column($payloads, 'code'))->toBe(['mutation_prepared', 'mutation_started', 'mutation_observed'])
            ->and($payloads[2])->toMatchArray([
                'contract' => 'gowa-evidence-v1',
                'operation_id' => $fixture['operationId'],
                'fencing_token' => 1,
                'plane' => 'root',
                'code' => 'mutation_observed',
            ]);
        $rotatedManifest = json_decode(file_get_contents($fixture['rollbackManifest']), true, 32, JSON_THROW_ON_ERROR);
        expect($rotatedManifest['previous_image'])->toBe($fixture['image'])
            ->and($rotatedManifest['fixed_config_hash'])->toBe('d2c28428f03bb3573f2e617cd97b373544352eff796e585f9e0896c5b0c90e57');
    } finally {
        removeGowaRunnerFixture($fixture);
    }
});

it('reports a verified rollback through the same evidence contract', function (): void {
    $fixture = gowaRunnerFixture('forward-fail-rollback-success');

    try {
        $result = runGowaRunner($fixture);
        expect($result['status'])->toBe(70)->and($result['evidence'])->toHaveCount(3);

        $document = json_decode(file_get_contents($result['evidence'][2]), true, 32, JSON_THROW_ON_ERROR);
        expect($document['payload']['code'])->toBe('rollback_observed')
            ->and($document['payload']['container_identity'])->toStartWith('bbbbbbbbbbbb');
    } finally {
        removeGowaRunnerFixture($fixture);
    }
});

it('reports degraded rollback when the previous release cannot be verified', function (): void {
    $fixture = gowaRunnerFixture('rollback-fail');

    try {
        $result = runGowaRunner($fixture);
        expect($result['status'])->toBe(70)->and($result['evidence'])->toHaveCount(3);

        $document = json_decode(file_get_contents($result['evidence'][2]), true, 32, JSON_THROW_ON_ERROR);
        expect($document['payload'])->toMatchArray([
            'code' => 'rollback_degraded',
            'container_identity' => 'unknown',
        ]);
    } finally {
        removeGowaRunnerFixture($fixture);
    }
});

it('rejects a rollback manifest that does not match the current runtime', function (): void {
    $fixture = gowaRunnerFixture();

    try {
        $manifest = json_decode(file_get_contents($fixture['rollbackManifest']), true, 32, JSON_THROW_ON_ERROR);
        $manifest['previous_image'] = 'registry.example.test/gowa@sha256:'.str_repeat('d', 64);
        file_put_contents($fixture['rollbackManifest'], json_encode($manifest, JSON_THROW_ON_ERROR));

        expect(runGowaRunner($fixture)['status'])->toBe(78)
            ->and(glob($fixture['evidenceRoot'].'/'.$fixture['operationId'].'/*/*.json') ?: [])->toBe([])
            ->and(is_file($fixture['requestRoot'].'/'.$fixture['operationId'].'/request.consumed'))->toBeFalse();
    } finally {
        removeGowaRunnerFixture($fixture);
    }
});

it('rejects a rollback manifest when the resolved fixed configuration hash drifts', function (): void {
    $fixture = gowaRunnerFixture();

    try {
        $manifest = json_decode(file_get_contents($fixture['rollbackManifest']), true, 32, JSON_THROW_ON_ERROR);
        $manifest['fixed_config_hash'] = str_repeat('e', 64);
        file_put_contents($fixture['rollbackManifest'], json_encode($manifest, JSON_THROW_ON_ERROR));

        expect(runGowaRunner($fixture)['status'])->toBe(78)
            ->and(glob($fixture['evidenceRoot'].'/'.$fixture['operationId'].'/*/*.json') ?: [])->toBe([])
            ->and(is_file($fixture['requestRoot'].'/'.$fixture['operationId'].'/request.consumed'))->toBeFalse();
    } finally {
        removeGowaRunnerFixture($fixture);
    }
});
