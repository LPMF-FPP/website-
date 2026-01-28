<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Barang Bukti</title>
    @php
        $totalLabels = $rows->count();
        // Determine grid layout based on label count
        if ($totalLabels <= 3) {
            $cols = 2;
            $maxRows = 3;
            $labelHeight = 85; // mm
            $labelWidth = 95;  // mm
            $fontSize = 8;
        } elseif ($totalLabels <= 6) {
            $cols = 2;
            $maxRows = 6;
            $labelHeight = 43; // mm
            $labelWidth = 95;  // mm
            $fontSize = 7;
        } elseif ($totalLabels <= 8) {
            $cols = 2;
            $maxRows = 8;
            $labelHeight = 32; // mm
            $labelWidth = 95;  // mm
            $fontSize = 6;
        } elseif ($totalLabels <= 12) {
            $cols = 2;
            $maxRows = 12;
            $labelHeight = 21; // mm
            $labelWidth = 95;  // mm
            $fontSize = 5.5;
        } else {
            $cols = 2;
            $maxRows = 15;
            $labelHeight = 17; // mm
            $labelWidth = 95;  // mm
            $fontSize = 5;
        }
    @endphp
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm; /* Increased from 5mm for cutting margin */
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: {{ $fontSize }}pt;
            line-height: 1.2;
        }
        .page-break {
            page-break-after: always;
        }
        .labels-page {
            width: 100%;
            height: auto;
        }
        .label {
            border: 1px solid #333;
            padding: 1.5mm;
            height: {{ $labelHeight }}mm;
            width: {{ $labelWidth }}mm;
            position: relative;
            background: #fff;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .label-header {
            padding-bottom: 0;
            margin-bottom: 0;
        }
        .label-header h1 {
            font-size: {{ $fontSize + 1 }}pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2pt;
            margin: 0;
        }
        .label-header .subtitle {
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            color: #555;
            margin-top: 0.3mm;
        }
        .label-body {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .label-content {
            display: table-cell;
            width: 68%;
            vertical-align: top;
            padding-right: 1.5mm;
        }
        .label-qr {
            display: table-cell;
            width: 32%;
            vertical-align: top;
            text-align: center;
        }
        .label-qr img {
            width: {{ min($labelHeight * 0.35, 18) }}mm;
            height: {{ min($labelHeight * 0.35, 18) }}mm;
            display: block;
            margin: 0 auto;
        }
        .qr-text {
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            color: #666;
            margin-top: 0.5mm;
            word-break: break-all;
            overflow: hidden;
            max-height: 6mm;
        }
        .field {
            margin-bottom: 0.5mm;
            overflow: hidden;
        }
        .field-label {
            font-size: {{ max($fontSize - 2, 4) }}pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.1pt;
        }
        .field-value {
            font-size: {{ $fontSize }}pt;
            font-weight: bold;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-word;
            overflow: hidden;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .field-value.large {
            font-size: {{ $fontSize + 1 }}pt;
        }
        .field-value.small {
            font-size: {{ max($fontSize - 1, 4.5) }}pt;
            font-weight: normal;
        }
        .text-balance {
            text-wrap: balance;
        }
        .text-pretty {
            text-wrap: pretty;
        }
        .clamp2 {
            max-height: {{ $fontSize * 0.6 }}mm;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .label-footer {
            position: absolute;
            bottom: 1mm;
            left: 2mm;
            right: 2mm;
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            color: #888;
            border-top: 1px dotted #ccc;
            padding-top: 0.3mm;
        }
        
        /* Logo sizing based on label height */
        .header-logo {
            height: {{ min($labelHeight * 0.12, 8) }}mm;
            width: auto;
        }
        .header-table {
            border-bottom: 1px solid #333;
            margin-bottom: 1mm;
            padding-bottom: 0.5mm;
        }
        
        /* Checklist Styles - A4 format */
        .checklist-container {
            padding: 10mm;
        }
        .checklist-header {
            text-align: center;
            border-bottom: 1.5px solid #000;
            padding-bottom: 3mm;
            margin-bottom: 5mm;
        }
        .checklist-title {
            margin: 0;
            font-size: 12pt;
            text-transform: uppercase;
            font-weight: bold;
        }
        .checklist-info {
            width: 100%;
            margin-bottom: 5mm;
            font-size: 10pt;
        }
        .checklist-info td {
            vertical-align: top;
            padding-bottom: 2mm;
        }
        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        .checklist-table th, .checklist-table td {
            border: 1px solid #000;
            padding: 2mm;
        }
        .checklist-table th {
            background-color: #f0f0f0;
            text-align: center;
        }
        .checkbox {
            width: 4mm;
            height: 4mm;
            border: 1px solid #000;
            display: inline-block;
        }

        /* =========================
           PRINT OVERRIDES (DomPDF Compatible - Global Scope)
           Label 121 Format (16.2cm x 20.5cm)
           ========================= */
        @page {
            size: 16.2cm 20.5cm;
            margin-top: 2mm;
            margin-bottom: 0mm;
            margin-left: 5mm;
            margin-right: 5mm;
        }

        body {
            line-height: 1.1;
        }
        
        .label {
            /* Fixed dimension for Label 121 */
            width: 72mm !important; /* Reduced from 75mm */
            height: 38mm !important;
            padding: 1mm !important;
            border: 0.25mm solid #333 !important;
        }

        .header-table {
            margin-bottom: 0.5mm !important;
            padding-bottom: 0.2mm !important;
            border-bottom: none !important; /* Removed line */
        }

        .header-logo {
            height: 5mm !important; /* Reduced */
            width: auto !important;
        }

        .label-header h1 {
            font-size: 8pt !important; /* Reduced */
            margin: 0 !important;
            line-height: 1 !important;
            margin-bottom: 0.2mm !important; /* Reduced */
        }

        .label-header .subtitle {
            display: block !important;
            font-size: 5pt !important;
            margin-top: 0.1mm !important;
            line-height: 1 !important;
        }

        .field {
            margin-bottom: 0.4mm !important; /* Tightened */
        }

        .field-label {
            font-size: 6pt !important;
            line-height: 1.1 !important;
        }

        .field-value {
            font-size: 7pt !important; /* Reduced */
            line-height: 1.1 !important;
        }
        
        .field-value.large {
            font-size: 8pt !important;
        }

        .label-qr img {
            width: 14mm !important;
            height: 14mm !important;
        }

        .qr-text {
            font-size: 5pt !important;
            margin-top: 0.2mm !important;
            max-height: 3mm !important;
        }

        .label-footer {
            bottom: 1mm !important;
            padding-top: 0.3mm !important;
            font-size: 5pt !important;
        }
        
        /* Adjust checklist page top margin compensation */
        .checklist-container {
            padding: 5mm 10mm !important;
            margin-top: 15mm !important; /* Compensate for small page margin */
        }
    </style>
</head>
<body>
    {{-- HALAMAN 1: SEMUA LABEL (tanpa page break) --}}
    <div class="labels-page">
        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            @foreach($rows as $row)
                <tr>
                    {{-- Left Column: Evidence Label --}}
                    <td style="width:50%; vertical-align:top; padding: 1mm;">
                        @if($row['left'])
                            <div class="label text-pretty">
                                <div class="label-header">
                                    <table width="100%" class="header-table">
                                        <tr>
                                            <td style="width: 12%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                            </td>
                                            <td style="width: 76%; text-align: center; vertical-align: middle;">
                                                <h1>Barang Bukti</h1>
                                                <div class="subtitle text-pretty">Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri</div>
                                            </td>
                                            <td style="width: 12%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-pusdokkes-polri.png') }}" class="header-logo" alt="Logo Pusdokkes">
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="label-body">
                                    <div class="label-content">
                                        <div class="field">
                                            <div class="field-label">Resi</div>
                                            <div class="field-value">{{ $row['left']['resi'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Kode Sampel</div>
                                            <div class="field-value large">{{ $row['left']['kode_sampel'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Tanggal Terima</div>
                                            <div class="field-value">{{ $row['left']['tanggal_terima'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Deskripsi Singkat</div>
                                            <div class="field-value clamp2">{{ $row['left']['deskripsi_singkat'] }}</div>
                                        </div>
                                    </div>

                                    <div class="label-qr">
                                        <img src="{{ $row['left']['qr'] }}" alt="QR Code">
                                        <div class="qr-text">{{ $row['left']['qr_text'] }}</div>
                                    </div>
                                </div>

                                <div class="label-footer">
                                    Dicetak: {{ $printDate }}
                                </div>
                            </div>
                        @endif
                    </td>

                    {{-- Right Column: Case Label --}}
                    <td style="width:50%; vertical-align:top; padding: 1mm;">
                        @if($row['right'])
                            <div class="label text-pretty">
                                <div class="label-header">
                                    <table width="100%" class="header-table">
                                        <tr>
                                            <td style="width: 12%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                            </td>
                                            <td style="width: 76%; text-align: center; vertical-align: middle;">
                                                <h1 class="text-balance">LPMF</h1>
                                                <div class="subtitle text-pretty">Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri</div>
                                            </td>
                                            <td style="width: 12%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-pusdokkes-polri.png') }}" class="header-logo" alt="Logo Pusdokkes">
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="label-body">
                                    <div class="label-content" style="width: 100%;">
                                        <div class="field">
                                            <div class="field-label">Asal Instansi</div>
                                            <div class="field-value clamp2">{{ $row['right']['satuan_kerja'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Nama Tersangka</div>
                                            <div class="field-value">{{ $row['right']['nama_tsk'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Nomor Sampel</div>
                                            <div class="field-value clamp2 small">{{ $row['right']['daftar_kode_sampel'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Nomor Surat</div>
                                            <div class="field-value">{{ $row['right']['nomor_surat'] }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="label-footer">
                                    Dicetak: {{ $printDate }}
                                </div>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    {{-- PAGE BREAK sebelum checklist --}}
    <div class="page-break"></div>

    {{-- HALAMAN 2: CHECKLIST --}}
    <div class="checklist-container">
        <div class="checklist-header">
            <h1 class="checklist-title">Checklist Kelengkapan Dokumen</h1>
            <div style="margin-top: 2mm; font-size: 9pt;">Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri</div>
        </div>

        <table class="checklist-info">
            <tr>
                <td style="width: 20%; font-weight: bold;">Nomor Resi</td>
                <td style="width: 2%;">:</td>
                <td>{{ $request->receipt_number ?? '-' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Asal Instansi</td>
                <td style="width: 2%;">:</td>
                <td>{{ $request->investigator->jurisdiction ?? '-' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Nama Tersangka</td>
                <td style="width: 2%;">:</td>
                <td>{{ $request->suspect_name ?? '-' }}</td>
            </tr>
        </table>

        <table class="checklist-table">
            <thead>
                <tr>
                    <th style="width: 8%;">No</th>
                    <th style="text-align: left;">Nama Dokumen</th>
                    <th style="width: 15%;">Ceklis</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $checklistItems = [
                        'BA Penerimaan',
                        'Laporan Hasil Uji',
                        'Lampiran Hasil Uji',
                        'BA Penyerahan',
                        'Sisa Sampel',
                        'Sprin Saksi Ahli',
                        'Surat Pengantar'
                    ];
                @endphp
                @foreach($checklistItems as $idx => $item)
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>{{ $item }}</td>
                    <td style="text-align: center;">
                        <div class="checkbox"></div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div style="margin-top: 15mm; text-align: right; font-size: 10pt;">
            <div style="margin-bottom: 20mm;">Petugas,</div>
            <div>(.......................................)</div>
        </div>
    </div>
</body>
</html>
