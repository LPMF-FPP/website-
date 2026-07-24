<?php

declare(strict_types=1);

namespace App\Enums;

enum GuestVisitPurpose: string
{
    case PermohonanPengujian = 'Permohonan Pengujian';
    case PengambilanHasilPengujian = 'Pengambilan Hasil Pengujian';
    case AuditMutu = 'Audit Mutu';
    case Inspeksi = 'Inspeksi';
    case Pelatihan = 'Pelatihan';
    case KunjunganStudiBanding = 'Kunjungan (Studi Banding)';
    case ServiceMesin = 'Service Mesin';
    case Magang = 'Magang';
    case Lainnya = 'Lainnya';

    /** @return list<string> */
    public static function casePurposes(): array
    {
        return [
            self::PermohonanPengujian->value,
            self::PengambilanHasilPengujian->value,
        ];
    }

    /** @return list<string> */
    public static function nonCasePurposes(): array
    {
        return [
            self::AuditMutu->value,
            self::Inspeksi->value,
            self::Pelatihan->value,
            self::KunjunganStudiBanding->value,
            self::ServiceMesin->value,
            self::Magang->value,
            self::Lainnya->value,
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [...self::casePurposes(), ...self::nonCasePurposes()];
    }
}
