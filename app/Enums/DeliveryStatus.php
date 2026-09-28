<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case PENDING = 'penyerahan_pending';
    case READY = 'penyerahan_ready';
    case COLLECTED = 'hasil_diambil';
    case UNCOLLECTED = 'hasil_belum_diambil';

    case REOPENED = 'penyerahan_dibuka_kembali';

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING => $newStatus === self::READY || $newStatus === self::REOPENED,
            self::READY => $newStatus === self::COLLECTED || $newStatus === self::UNCOLLECTED || $newStatus === self::REOPENED,
            self::UNCOLLECTED => $newStatus === self::COLLECTED || $newStatus === self::REOPENED,
            self::REOPENED => $newStatus === self::PENDING,
            default => false
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Penyerahan',
            self::READY => 'Siap Diserahkan',
            self::COLLECTED => 'Hasil Sudah Diambil',
            self::UNCOLLECTED => 'Hasil Belum Diambil',
            self::REOPENED => 'Penyerahan Dibuka Kembali',
        };
    }
}
