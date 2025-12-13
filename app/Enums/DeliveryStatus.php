<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case PENDING = 'penyerahan_pending';
    case READY = 'penyerahan_ready';
    case COLLECTED = 'hasil_diambil';
    case UNCOLLECTED = 'hasil_belum_diambil';

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::PENDING => $newStatus === self::READY,
            self::READY => $newStatus === self::COLLECTED || $newStatus === self::UNCOLLECTED,
            self::UNCOLLECTED => $newStatus === self::COLLECTED,
            default => false
        };
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Menunggu Penyerahan',
            self::READY => 'Siap Diserahkan',
            self::COLLECTED => 'Hasil Sudah Diambil',
            self::UNCOLLECTED => 'Hasil Belum Diambil'
        };
    }
}
