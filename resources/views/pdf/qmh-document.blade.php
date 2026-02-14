@php
    /** @var \App\Models\QmhDocumentRevision $revision */
    /** @var \App\Models\QmhDocument $document */
    $document = $revision->document;

    $isPreview = (bool) ($isPreview ?? false);
    $logoPath = public_path('images/logo-pusdokkes-polri.png');
    $logoSrc = $isPreview ? asset('images/logo-pusdokkes-polri.png') : $logoPath;

    $docTypeLabel = match ((string) ($document->doc_type ?? '')) {
        'sop' => 'PROSEDUR',
        'ik' => 'INSTRUKSI KERJA',
        'fr' => 'FORMULIR',
        default => 'DOKUMEN',
    };

    $effectiveDate = $revision->effective_date?->format('d-m-Y') ?? '-';
    $statusLabel = strtoupper((string) ($revision->status ?? ''));
    $versionLabel = (string) ($revision->version_label ?? ('E'.$revision->edition_number.'-R'.$revision->revision_number));

    // Rendered body comes from editor (Tiptap) or fallback.
    $bodyHtml = $contentHtml ?? ($revision->content_html ?: '<p>Konten dokumen belum tersedia.</p>');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document?->doc_code ?? 'QMH' }} - {{ $versionLabel }}</title>
    <style>
        @page {
            margin: 125px 40px 85px 40px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #111827;
        }

        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 52px;
            letter-spacing: 6px;
            color: rgba(100, 116, 139, 0.18);
            z-index: -1;
            white-space: nowrap;
        }

        header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 95px;
        }

        footer {
            position: fixed;
            bottom: -55px;
            left: 0;
            right: 0;
            height: 50px;
            font-size: 9px;
            color: #374151;
        }

        .doc-header {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-header td {
            border: 1px solid #111827;
            vertical-align: middle;
            padding: 6px 8px;
        }

        .doc-header .logo-cell {
            width: 28%;
            text-align: center;
            padding: 8px;
        }

        .doc-header .logo {
            width: 60px;
            height: auto;
            margin: 0 auto 4px auto;
            display: block;
        }

        .doc-header .org {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .doc-header .type {
            width: 16%;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .doc-header .meta {
            width: 56%;
            padding: 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            border: 1px solid #111827;
            padding: 5px 6px;
            font-size: 10px;
        }

        .meta-label {
            width: 34%;
            font-weight: 700;
        }

        .meta-value {
            width: 66%;
        }

        .doc-title {
            margin: 10px 0 0 0;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        main {
            font-size: 11px;
        }

        main table {
            width: 100%;
            border-collapse: collapse;
        }

        main th,
        main td {
            border: 1px solid #111827;
            padding: 4px 6px;
            vertical-align: top;
        }

        .footer-line {
            border-top: 1px solid #9ca3af;
            padding-top: 6px;
        }

        .footer-left {
            float: left;
            width: 75%;
        }

        .footer-right {
            float: right;
            width: 25%;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="watermark">{{ $watermarkText ?? '' }}</div>

    <header>
        <table class="doc-header">
            <tr>
                <td class="logo-cell">
                    <img class="logo" src="{{ $logoSrc }}" alt="Logo Pusdokkes Polri">
                    <div class="org">Laboratorium Pengujian Mutu Farmapol<br>Pusdokkes Polri</div>
                </td>
                <td class="type">{{ $docTypeLabel }}</td>
                <td class="meta">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">No. Dokumen</td>
                            <td class="meta-value">{{ $document?->doc_code ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Edisi/Revisi</td>
                            <td class="meta-value">{{ $versionLabel }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Tgl. Efektif</td>
                            <td class="meta-value">{{ $effectiveDate }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Status</td>
                            <td class="meta-value">{{ $statusLabel }}</td>
                        </tr>
                    </table>
                    <div class="doc-title">{{ $document?->title ?? 'Dokumen QMH' }}</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <div class="footer-line">
            <div class="footer-left">
                Isi Dokumen ini tidak diperkenankan untuk disalin atau digandakan tanpa persetujuan dari Kepala Farmasi Kepolisian Pusdokkes Polri
            </div>
            <div class="footer-right">
                <span class="page-number"></span>
            </div>
        </div>
        <script type="text/php">
            if (isset($pdf)) {
                $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
                $pdf->page_text(520, 805, '{PAGE_NUM}/{PAGE_COUNT}', $font, 9, [55, 65, 81]);
            }
        </script>
    </footer>

    <main>
        {!! $bodyHtml !!}
    </main>
</body>
</html>
