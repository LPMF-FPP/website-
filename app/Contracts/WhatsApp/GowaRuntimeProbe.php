<?php

declare(strict_types=1);

namespace App\Contracts\WhatsApp;

interface GowaRuntimeProbe
{
    /** @return array<string, mixed> */
    public function current(): array;

    public function isFresh(array $runtime): bool;
}
