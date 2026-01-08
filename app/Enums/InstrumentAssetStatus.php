<?php

namespace App\Enums;

enum InstrumentAssetStatus: string
{
    case ACTIVE = 'active';
    case MAINTENANCE = 'maintenance';
    case OUT_OF_SERVICE = 'out_of_service';
    case CALIBRATION_DUE = 'calibration_due';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::MAINTENANCE => 'Dalam Perawatan',
            self::OUT_OF_SERVICE => 'Tidak Beroperasi',
            self::CALIBRATION_DUE => 'Perlu Kalibrasi',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::ACTIVE;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
