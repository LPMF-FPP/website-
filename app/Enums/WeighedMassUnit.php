<?php

namespace App\Enums;

enum WeighedMassUnit: string
{
    case UG = 'ug';
    case MG = 'mg';
    case G = 'g';

    public function label(): string
    {
        return match ($this) {
            self::UG => 'Mikrogram (μg)',
            self::MG => 'Miligram (mg)',
            self::G => 'Gram (g)',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::UG => 'μg',
            self::MG => 'mg',
            self::G => 'g',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
