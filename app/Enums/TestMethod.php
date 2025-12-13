<?php

namespace App\Enums;

enum TestMethod: string
{
    case UV_VIS = 'uv_vis';
    case GC_MS = 'gc_ms';
    case LC_MS = 'lc_ms';

    public function label(): string
    {
        return match($this) {
            self::UV_VIS => 'Identifikasi Spektrofotometri UV-VIS',
            self::GC_MS => 'Identifikasi GC-MS',
            self::LC_MS => 'Identifikasi LC-MS',
        };
    }

    public function shortLabel(): string
    {
        return match($this) {
            self::UV_VIS => 'Spektrofotometri UV-VIS',
            self::GC_MS => 'GC-MS',
            self::LC_MS => 'LC-MS',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function shortOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->shortLabel()])
            ->toArray();
    }
}
