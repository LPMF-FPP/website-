<?php

namespace App\Enums;

enum EnvironmentLocationType: string
{
    case ROOM = 'room';
    case FRIDGE = 'fridge';
    case FREEZER = 'freezer';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ROOM => 'Ruangan',
            self::FRIDGE => 'Kulkas',
            self::FREEZER => 'Freezer',
            self::OTHER => 'Lainnya',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
