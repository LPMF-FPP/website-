<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\WhatsApp\GowaReleasePreparer;

final class FakeGowaReleasePreparer implements GowaReleasePreparer
{
    public bool $enabled = true;

    public array $proof = [
        'state' => 'ready',
        'version' => '1.0.0',
        'release_id' => 'release-a',
        'digest' => 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'previous_digest' => 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'catalog_generation' => 'test-generation',
        'container_identity' => 'container-test',
    ];

    public array $dispatched = [];

    public function available(): bool
    {
        return $this->enabled;
    }

    public function status(): array
    {
        return $this->proof;
    }

    public function dispatch(string $version): bool
    {
        $this->dispatched[] = $version;

        return $this->enabled;
    }
}
