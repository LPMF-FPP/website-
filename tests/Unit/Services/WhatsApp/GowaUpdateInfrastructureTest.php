<?php

use App\Services\WhatsApp\FileGowaReleaseCatalog;
use App\Services\WhatsApp\FileGowaRuntimeProbe;
use App\Services\WhatsApp\SystemdGowaUpdateRunner;

it('only exposes approved immutable catalog releases', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'gowa-catalog-');
    file_put_contents($path, json_encode([
        'schema_version' => 1,
        'generation' => 'generation-1',
        'revocation_generation' => 'revocation-1',
        'approved_registry' => 'registry.example.test',
        'approved_repository' => 'registry.example.test/gowa',
        'platform' => 'linux/amd64',
        'signature' => ['algorithm' => 'ed25519', 'key_id' => 'fixture-key', 'value' => str_repeat('A', 16)],
        'signature_valid' => true,
        'releases' => [
            ['release_id' => 'good', 'version' => '1.0.0', 'digest' => 'sha256:'.str_repeat('a', 64), 'image' => 'registry.example.test/gowa@sha256:'.str_repeat('a', 64), 'approved' => true, 'revoked' => false, 'revocation_generation' => 'revocation-1'],
            ['release_id' => 'revoked', 'version' => '1.0.1', 'digest' => 'sha256:'.str_repeat('c', 64), 'image' => 'registry.example.test/gowa@sha256:'.str_repeat('c', 64), 'approved' => true, 'revoked' => true, 'revocation_generation' => 'revocation-1'],
        ],
    ], JSON_THROW_ON_ERROR));

    $catalog = new FileGowaReleaseCatalog($path);

    expect($catalog->find('good'))->not->toBeNull()
        ->and($catalog->find('tag'))->toBeNull()
        ->and($catalog->find('revoked'))->toBeNull()
        ->and($catalog->generation())->toBe('generation-1');

    unlink($path);
});

it('fails closed when the privileged runner gate is not enabled', function (): void {
    $runner = new SystemdGowaUpdateRunner(false, false, '/path/that/is/not/installed', '/path/that/is/not/installed', '/path/that/is/not/installed');

    expect($runner->available())->toBeFalse()
        ->and($runner->dispatch(['operation_id' => '00000000-0000-4000-8000-000000000000']))->toBeFalse();
});

it('rejects a catalog without signed immutable fields', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'gowa-catalog-invalid-');
    file_put_contents($path, json_encode(['schema_version' => 1, 'signature_valid' => true, 'generation' => 'generation-1', 'releases' => []], JSON_THROW_ON_ERROR));

    $catalog = new FileGowaReleaseCatalog($path);

    expect($catalog->generation())->toBeNull()->and($catalog->approved())->toBe([]);
    unlink($path);
});

it('marks missing or stale runtime evidence as not fresh', function (): void {
    $probe = new FileGowaRuntimeProbe('/path/that/is/not/installed');

    expect($probe->current())->toBe([])
        ->and($probe->isFresh([]))->toBeFalse();
});
