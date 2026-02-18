<?php

declare(strict_types=1);

namespace App\Support;

final class QmhFrV2Gate
{
    public static function normalizedDocType(?string $docType): string
    {
        return match (strtolower(trim((string) $docType))) {
            'fr', 'formulir' => 'fr',
            default => strtolower(trim((string) $docType)),
        };
    }

    public static function isFrType(?string $docType): bool
    {
        return self::normalizedDocType($docType) === 'fr';
    }

    public static function isEnabled(): bool
    {
        return (bool) config('quality.fr_v2.enabled', false);
    }

    public static function isCreateEnabled(?string $docType): bool
    {
        return self::isFrType($docType)
            && self::isEnabled()
            && (bool) config('quality.fr_v2.create_enabled', false);
    }
}
