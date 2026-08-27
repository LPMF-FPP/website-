<?php

use App\Services\WhatsApp\GowaEvidenceImporter;

function writeGowaEvidenceFixture(string $directory, string $signature, array $payload): string
{
    $path = $directory.'/evidence.json';
    file_put_contents($path, json_encode([
        'schema_version' => 1,
        'payload' => $payload,
        'signature' => $signature,
    ], JSON_THROW_ON_ERROR));

    return $path;
}

it('imports signed canonical evidence and rejects tampering', function (): void {
    $directory = sys_get_temp_dir().'/gowa-evidence-'.bin2hex(random_bytes(4));
    mkdir($directory, 0700, true);
    $keyPair = sodium_crypto_sign_keypair();
    $publicKeyPath = $directory.'/evidence.pub';
    file_put_contents($publicKeyPath, base64_encode(sodium_crypto_sign_publickey($keyPair)));

    $payload = [
        'contract' => 'gowa-evidence-v1',
        'operation_id' => '00000000-0000-4000-8000-000000000000',
        'fencing_token' => 1,
        'sequence' => 1,
        'plane' => 'root',
        'code' => 'mutation_observed',
        'occurred_at' => '2026-08-27T00:00:00+00:00',
        'snapshot_hash' => str_repeat('a', 64),
        'container_identity' => 'sha256:'.str_repeat('b', 64),
    ];
    $importer = new GowaEvidenceImporter($publicKeyPath);
    $signature = sodium_crypto_sign_detached($importer->canonicalJson($payload), sodium_crypto_sign_secretkey($keyPair));
    $path = writeGowaEvidenceFixture($directory, base64_encode($signature), $payload);

    expect($importer->decode($path))->toMatchArray($payload);

    $payload['code'] = 'forged';
    file_put_contents($path, json_encode([
        'schema_version' => 1,
        'payload' => $payload,
        'signature' => base64_encode($signature),
    ], JSON_THROW_ON_ERROR));
    expect(fn () => $importer->decode($path))->toThrow(\RuntimeException::class, 'evidence_signature_invalid');

    unlink($path);
    unlink($publicKeyPath);
    rmdir($directory);
});

it('rejects unsafe evidence paths and malformed payloads', function (): void {
    $importer = new GowaEvidenceImporter;

    expect(fn () => $importer->decode('/path/that/does/not/exist'))->toThrow(\RuntimeException::class, 'evidence_unavailable');
});
