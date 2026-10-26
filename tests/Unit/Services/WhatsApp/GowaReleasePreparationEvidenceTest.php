<?php

declare(strict_types=1);

use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Services\WhatsApp\SystemdGowaReleasePreparer;
use Tests\TestCase;

uses(TestCase::class);

it('requires fresh signed preparation evidence and rejects tampering, future time, and expiry', function (): void {
    $root = storage_path('framework/testing/gowa-preparation-'.str()->uuid());
    mkdir($root, 0o700, true);
    $keys = sodium_crypto_sign_keypair();
    file_put_contents($root.'/key.pub', base64_encode(sodium_crypto_sign_publickey($keys)));
    chmod($root.'/key.pub', 0o600);
    config()->set('gowa-updater.preparation_evidence_path', $root.'/preparation.json');
    config()->set('gowa-updater.evidence_public_key_path', $root.'/key.pub');
    $runner = Mockery::mock(GowaUpdateRunner::class);
    $runner->shouldReceive('available')->andReturn(false);
    $preparer = new SystemdGowaReleasePreparer($runner, false);
    $write = function (array $payload) use ($keys, $root): void {
        ksort($payload);
        file_put_contents($root.'/preparation.json', json_encode([
            'schema_version' => 1, 'payload' => $payload,
            'signature' => base64_encode(sodium_crypto_sign_detached(
                json_encode($payload, JSON_UNESCAPED_SLASHES), sodium_crypto_sign_secretkey($keys),
            )),
        ]));
        chmod($root.'/preparation.json', 0o600);
    };
    $payload = [
        'contract' => 'gowa-preparation-v1', 'state' => 'ready', 'version' => 'v9.7.0',
        'container_identity' => 'container-test', 'observed_at' => now()->toIso8601String(),
    ];
    try {
        expect($preparer->status())->toBe(['state' => 'unavailable']);
        $write($payload);
        expect($preparer->status()['state'])->toBe('ready');
        $document = json_decode(file_get_contents($root.'/preparation.json'), true);
        $document['payload']['state'] = 'failed';
        file_put_contents($root.'/preparation.json', json_encode($document));
        expect($preparer->status()['state'])->toBe('unavailable');
        $write(array_replace($payload, ['observed_at' => now()->subMinutes(16)->toIso8601String()]));
        expect($preparer->status()['state'])->toBe('expired');
        $write(array_replace($payload, ['observed_at' => now()->addMinute()->toIso8601String()]));
        expect($preparer->status()['state'])->toBe('expired');
        $write(array_replace($payload, ['contract' => 'not-preparation']));
        expect($preparer->status()['state'])->toBe('unavailable');
        expect($preparer->dispatch('v9.7.0;whoami'))->toBeFalse();
    } finally {
        unlink($root.'/preparation.json');
        unlink($root.'/key.pub');
        rmdir($root);
    }
});
