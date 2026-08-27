<?php

use App\Services\WhatsApp\FileGowaReleaseCatalog;
use App\Services\WhatsApp\FileGowaRuntimeProbe;
use App\Services\WhatsApp\SystemdGowaUpdateRunner;

it('only exposes approved immutable catalog releases', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'gowa-catalog-');
    file_put_contents($path, json_encode([
        'generation' => 'generation-1',
        'signature_valid' => true,
        'releases' => [
            ['release_id' => 'good', 'version' => '1.0.0', 'digest' => 'sha256:'.str_repeat('a', 64), 'image' => 'repo@sha256:'.str_repeat('a', 64), 'approved' => true, 'revoked' => false],
            ['release_id' => 'tag', 'version' => 'latest', 'digest' => 'sha256:'.str_repeat('b', 64), 'image' => 'repo:latest', 'approved' => true, 'revoked' => false],
            ['release_id' => 'revoked', 'version' => '1.0.1', 'digest' => 'sha256:'.str_repeat('c', 64), 'image' => 'repo@sha256:'.str_repeat('c', 64), 'approved' => true, 'revoked' => true],
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
        ->and($runner->dispatch('00000000-0000-4000-8000-000000000000'))->toBeFalse();
});

it('marks missing or stale runtime evidence as not fresh', function (): void {
    $probe = new FileGowaRuntimeProbe('/path/that/is/not/installed');

    expect($probe->current())->toBe([])
        ->and($probe->isFresh([]))->toBeFalse();
});
