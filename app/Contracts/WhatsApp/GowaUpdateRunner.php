<?php

declare(strict_types=1);

namespace App\Contracts\WhatsApp;

interface GowaUpdateRunner
{
    public function available(): bool;

    /** @param array<string, scalar|null> $claim */
    public function dispatch(array $claim): bool;
}
