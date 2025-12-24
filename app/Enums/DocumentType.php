<?php

namespace App\Enums;

enum DocumentType: string
{
    case BA_PENERIMAAN = 'ba_penerimaan';
    case BA_PENYERAHAN = 'ba_penyerahan';
    case LHU = 'lhu';
    case FORM_PREPARATION = 'form_preparation';
    case SAMPLE_RECEIPT = 'sample_receipt';
    case REQUEST_LETTER_RECEIPT = 'request_letter_receipt';
    case HANDOVER_REPORT = 'handover_report';
    case LABEL_SAMPLE = 'label_sample';
    case SETTINGS_PREVIEW = 'settings_preview';

    /**
     * Get human-readable label for the document type
     */
    public function label(): string
    {
        return match ($this) {
            self::BA_PENERIMAAN => 'Berita Acara Penerimaan',
            self::BA_PENYERAHAN => 'Berita Acara Penyerahan',
            self::LHU => 'Laporan Hasil Uji',
            self::FORM_PREPARATION => 'Form Persiapan Sampel',
            self::SAMPLE_RECEIPT => 'Tanda Terima Sampel',
            self::REQUEST_LETTER_RECEIPT => 'Tanda Terima Surat',
            self::HANDOVER_REPORT => 'Berita Acara Serah Terima',
            self::LABEL_SAMPLE => 'Label Sampel',
            self::SETTINGS_PREVIEW => 'Preview Pengaturan',
        };
    }

    /**
     * Get default output format for this document type
     */
    public function defaultFormat(): DocumentFormat
    {
        return match ($this) {
            self::LABEL_SAMPLE => DocumentFormat::HTML,
            self::SETTINGS_PREVIEW => DocumentFormat::PDF,
            default => DocumentFormat::PDF,
        };
    }

    /**
     * Get legacy Blade view path (for fallback)
     */
    public function legacyView(): ?string
    {
        return match ($this) {
            self::BA_PENERIMAAN => 'pdf.berita-acara-penerimaan',
            self::BA_PENYERAHAN => 'pdf.ba-penyerahan',
            self::LHU => 'pdf.laporan-hasil-uji',
            self::FORM_PREPARATION => 'pdf.form-preparation',
            self::SAMPLE_RECEIPT => 'pdf.sample-receipt',
            self::REQUEST_LETTER_RECEIPT => 'pdf.request-letter-receipt',
            self::HANDOVER_REPORT => 'pdf.handover-report',
            self::SETTINGS_PREVIEW => 'pdf.settings-preview',
            default => null,
        };
    }

    /**
     * Get supported formats for this document type
     */
    public function supportedFormats(): array
    {
        return match ($this) {
            self::LHU, self::BA_PENYERAHAN => [
                DocumentFormat::PDF,
                DocumentFormat::HTML,
            ],
            default => [DocumentFormat::PDF],
        };
    }
}
