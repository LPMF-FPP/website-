<?php

namespace App\Enums;

enum ReadingSource: string
{
    case MANUAL = 'manual';
    case IMPORT = 'import';
    case IOT = 'iot';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Input Manual',
            self::IMPORT => 'Import File',
            self::IOT => 'Sensor IoT',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
