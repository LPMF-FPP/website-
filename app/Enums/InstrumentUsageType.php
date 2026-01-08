<?php

namespace App\Enums;

enum InstrumentUsageType: string
{
    case PREP = 'PREP';
    case RUN = 'RUN';

    public function label(): string
    {
        return match ($this) {
            self::PREP => 'Preparasi',
            self::RUN => 'Pengujian',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
