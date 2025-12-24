<?php

namespace App\Support;

use Illuminate\Support\Str;

class DocumentTypes
{
    /**
     * Known document type labels for UI display.
     */
    private const LABELS = [
        'sample_receipt' => 'Tanda Terima Sampel',
        'request_letter' => 'Surat Permintaan Pengujian',
        'request_letter_receipt' => 'Tanda Terima Surat Permintaan',
        'handover_report' => 'Berita Acara Serah Terima',
        'ba_penerimaan' => 'Berita Acara Penerimaan Sampel',
        'ba_penyerahan' => 'Berita Acara Penyerahan Sampel',
        'laporan_hasil_uji' => 'Laporan Hasil Uji (LHU)',
        'lab_report' => 'Laporan Lab',
        'cover_letter' => 'Surat Pengantar',
        'sample_photo' => 'Foto Sampel',
        'evidence_photo' => 'Foto Barang Bukti',
        'form_preparation' => 'Form Persiapan',
        'instrument_uv_vis' => 'Hasil Instrumen UV-VIS',
        'instrument_gc_ms' => 'Hasil Instrumen GC-MS',
        'instrument_lc_ms' => 'Hasil Instrumen LC-MS',
        'instrument_result' => 'Hasil Instrumen',
        'report_receipt' => 'Tanda Terima Laporan',
        'letter_receipt' => 'Tanda Terima Surat',
        'sample_handover' => 'Serah Terima Sampel',
        'test_results' => 'Hasil Pengujian',
        'qr_code' => 'QR Code',
    ];

    public static function label(?string $type): string
    {
        if (!$type) {
            return 'Dokumen';
        }

        return self::LABELS[$type] ?? Str::of($type)->replace('_', ' ')->headline();
    }

    /**
     * Map a list of type keys to value/label option arrays.
     *
     * @param iterable<string> $types
     * @return array<int, array{value: string, label: string}>
     */
    public static function mapOptions(iterable $types): array
    {
        $options = [];

        foreach ($types as $type) {
            if (!$type) {
                continue;
            }

            $options[$type] = [
                'value' => $type,
                'label' => self::label($type),
            ];
        }

        ksort($options);

        return array_values($options);
    }
}
