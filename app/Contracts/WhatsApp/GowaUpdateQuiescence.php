<?php

declare(strict_types=1);

namespace App\Contracts\WhatsApp;

interface GowaUpdateQuiescence
{
    /** @return array{quiescent: bool, systemd: bool, lock: bool, request: bool, evidence: bool} */
    public function quiescence(string $operationId): array;
}
