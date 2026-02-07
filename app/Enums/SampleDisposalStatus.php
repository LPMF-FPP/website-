<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleDisposalStatus: string
{
    case PENDING = 'pending';
    case ELIGIBLE = 'eligible';
    case DISPOSED = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Belum Siap',
            self::ELIGIBLE => 'Siap Musnah',
            self::DISPOSED => 'Sudah Dimusnahkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::ELIGIBLE => 'yellow',
            self::DISPOSED => 'green',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-gray-100 text-gray-800',
            self::ELIGIBLE => 'bg-yellow-100 text-yellow-800',
            self::DISPOSED => 'bg-green-100 text-green-800',
        };
    }
}
