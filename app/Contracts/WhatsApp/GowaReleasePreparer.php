<?php

declare(strict_types=1);

namespace App\Contracts\WhatsApp;

interface GowaReleasePreparer
{
    public function available(): bool;

    public function status(): array;

    public function dispatch(string $version): bool;
}
