<?php

namespace App\Enums;

enum DocumentRenderEngine: string
{
    case DOMPDF = 'dompdf';

    public function label(): string
    {
        return match ($this) {
            self::DOMPDF => 'Dompdf',
        };
    }
}
