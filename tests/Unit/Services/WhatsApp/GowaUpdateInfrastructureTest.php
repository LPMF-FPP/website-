<?php

use App\Services\WhatsApp\FileGowaReleaseCatalog;
use App\Services\WhatsApp\FileGowaRuntimeProbe;
use App\Services\WhatsApp\SystemdGowaUpdateRunner;

/** @return array{catalog: array<string, mixed>, keyPair: string, publicKeyPath: string, catalogPath: string} */
function signedGowaCatalogFixture(array $overrides = []): array
{
    $keyPair = sodium_crypto_sign_keypair();
    $publicKeyPath = tempnam(sys_get_temp_dir(), 'gowa-catalog-key-');
    $catalogPath = tempnam(sys_get_temp_dir(), 'gowa-catalog-');
    $catalog = array_replace_recursive([
        'schema_version' => 1,
        'signature_valid' => true,
        'generation' => 'generation-1',
        'revocation_generation' => 'revocation-1',
        'approved_registry' => 'registry.example.test',
        'approved_repository' => 'registry.example.test/gowa',
        'platform' => 'linux/amd64',
        'signature' => ['algorithm' => 'ed25519', 'key_id' => 'fixture-key', 'value' => ''],
        'releases' => [[
            'release_id' => 'gowa-v9-2-2',
            'version' => 'v9.2.2',
            'digest' => 'sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc',
            'image' => 'registry.example.test/gowa@sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc',
            'approved' => true,
            'revoked' => false,
            'revocation_generation' => 'revocation-1',
        ]],
    ], $overrides);
    $catalogVerifier = new FileGowaReleaseCatalog('', $publicKeyPath, false);
    $catalog['signature']['value'] = base64_encode(sodium_crypto_sign_detached(
        $catalogVerifier->canonicalPayload($catalog),
        sodium_crypto_sign_secretkey($keyPair),
    ));
    file_put_contents($publicKeyPath, base64_encode(sodium_crypto_sign_publickey($keyPair)));
    file_put_contents($catalogPath, json_encode($catalog, JSON_THROW_ON_ERROR));

    return compact('catalog', 'keyPair', 'publicKeyPath', 'catalogPath');
}

function removeGowaCatalogFixture(array $fixture): void
{
    unlink($fixture['publicKeyPath']);
    unlink($fixture['catalogPath']);
}

it('only exposes approved immutable catalog releases', function (): void {
    $fixture = signedGowaCatalogFixture();
    $catalog = new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath'], false);

    expect($catalog->find('gowa-v9-2-2'))->not->toBeNull()
        ->and($catalog->find('tag'))->toBeNull()
        ->and($catalog->find('revoked'))->toBeNull()
        ->and($catalog->generation())->toBe('generation-1');

    removeGowaCatalogFixture($fixture);
});

it('accepts a catalog signed by an ephemeral Ed25519 key', function (): void {
    $fixture = signedGowaCatalogFixture();
    $catalog = new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath'], false);

    expect($catalog->find('gowa-v9-2-2')['digest'])->toBe('sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc');
    removeGowaCatalogFixture($fixture);
});

it('rejects tampering, a wrong key, revoked releases, and mutable tags', function (): void {
    $fixture = signedGowaCatalogFixture();
    $tampered = $fixture['catalog'];
    $tampered['releases'][0]['version'] = 'v9.2.3';
    file_put_contents($fixture['catalogPath'], json_encode($tampered, JSON_THROW_ON_ERROR));
    $catalog = new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath'], false);
    expect($catalog->approved())->toBe([]);

    $wrongKey = sodium_crypto_sign_keypair();
    file_put_contents($fixture['publicKeyPath'], base64_encode(sodium_crypto_sign_publickey($wrongKey)));
    expect((new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath'], false))->approved())->toBe([]);

    $revoked = signedGowaCatalogFixture(['releases' => [[
        'revoked' => true,
        'release_id' => 'gowa-v9-2-2',
        'version' => 'v9.2.2',
        'digest' => 'sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc',
        'image' => 'registry.example.test/gowa@sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc',
        'approved' => true,
        'revocation_generation' => 'revocation-1',
    ]]]);
    expect((new FileGowaReleaseCatalog($revoked['catalogPath'], $revoked['publicKeyPath'], false))->approved())->toBe([]);

    $mutable = signedGowaCatalogFixture(['releases' => [[
        'release_id' => 'gowa-v9-2-2',
        'version' => 'v9.2.2',
        'digest' => 'sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc',
        'image' => 'registry.example.test/gowa:v9.2.2',
        'approved' => true,
        'revoked' => false,
        'revocation_generation' => 'revocation-1',
    ]]]);
    expect((new FileGowaReleaseCatalog($mutable['catalogPath'], $mutable['publicKeyPath'], false))->generation())->toBeNull();

    removeGowaCatalogFixture($fixture);
    removeGowaCatalogFixture($revoked);
    removeGowaCatalogFixture($mutable);
});

it('rejects duplicate and unknown catalog fields', function (): void {
    $fixture = signedGowaCatalogFixture();
    $raw = str_replace('"schema_version":1,', '"schema_version":1,"schema_version":1,', file_get_contents($fixture['catalogPath']));
    file_put_contents($fixture['catalogPath'], $raw);
    expect((new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath'], false))->approved())->toBe([]);

    $unknown = $fixture['catalog'];
    $unknown['unexpected'] = true;
    file_put_contents($fixture['catalogPath'], json_encode($unknown, JSON_THROW_ON_ERROR));
    expect((new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath'], false))->approved())->toBe([]);
    removeGowaCatalogFixture($fixture);
});

it('rejects missing or invalid signatures and non-root trust keys', function (): void {
    $fixture = signedGowaCatalogFixture();
    $missing = $fixture['catalog'];
    unset($missing['signature']);
    file_put_contents($fixture['catalogPath'], json_encode($missing, JSON_THROW_ON_ERROR));
    expect((new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath'], false))->approved())->toBe([]);

    $invalid = $fixture['catalog'];
    $invalid['signature']['value'] = 'not-a-signature';
    file_put_contents($fixture['catalogPath'], json_encode($invalid, JSON_THROW_ON_ERROR));
    expect((new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath'], false))->approved())->toBe([]);

    file_put_contents($fixture['catalogPath'], json_encode($fixture['catalog'], JSON_THROW_ON_ERROR));
    expect((new FileGowaReleaseCatalog($fixture['catalogPath'], $fixture['publicKeyPath']))->approved())->toBe([]);
    removeGowaCatalogFixture($fixture);
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
