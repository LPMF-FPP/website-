<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleDisposalMethod: string
{
    case BAKAR = 'bakar';
    case KUBUR = 'kubur';
    case HANCUR = 'hancur';
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::BAKAR => 'Dibakar',
            self::KUBUR => 'Dikubur',
            self::HANCUR => 'Dihancurkan',
            self::LAINNYA => 'Metode Lain',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BAKAR => 'Pemusnahan dengan cara dibakar hingga habis',
            self::KUBUR => 'Pemusnahan dengan cara dikubur di lokasi yang ditentukan',
            self::HANCUR => 'Pemusnahan dengan cara dihancurkan secara fisik/kimia',
            self::LAINNYA => 'Metode pemusnahan lainnya sesuai ketentuan',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method) => [$method->value => $method->label()])
            ->all();
    }
}
