<?php

declare(strict_types=1);

namespace App\Contracts\WhatsApp;

interface GowaReleasePreparationRunner
{
    public function available(): bool;

    /** @return array{release_id: string, version: string, digest: string, catalog_generation: string} */
    public function prepareLatest(): array;
}
