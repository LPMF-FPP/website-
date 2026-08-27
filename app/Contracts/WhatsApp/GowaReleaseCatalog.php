<?php

declare(strict_types=1);

namespace App\Contracts\WhatsApp;

interface GowaReleaseCatalog
{
    public function find(string $releaseId): ?array;

    /** @return array<int, array<string, mixed>> */
    public function approved(): array;

    public function generation(): ?string;
}
