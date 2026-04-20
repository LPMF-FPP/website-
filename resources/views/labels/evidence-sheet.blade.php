<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Barang Bukti</title>
    @php
        $pageWidth = 165; // mm
        $evidenceLabelWidth = 71; // mm
        $caseLabelWidth = 69; // mm
        $labelHeight = 35; // mm
        $gapWidth = 5.0; // mm
        $rowGap = 5.0; // mm
        $sheetLeftOffset = 3; // mm
        $fontSize = 7; // pt
    @endphp
    <style>
        @page {
            size: 165mm 210mm;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: {{ $fontSize }}pt;
            line-height: 1.15;
            color: #000;
        }
        .page-break {
            page-break-after: always;
            break-after: page;
        }
        .sheet {
            padding-top: 3mm;
            padding-left: {{ $sheetLeftOffset }}mm;
            width: {{ $pageWidth - $sheetLeftOffset }}mm;
            text-align: left;
        }
        .sheet-row {
            display: block;
            width: {{ $evidenceLabelWidth + $caseLabelWidth + $gapWidth }}mm;
            page-break-inside: avoid;
            margin-left: 0;
            margin-right: 0;
            font-size: 0;
            white-space: nowrap;
        }
        .sheet-row + .sheet-row {
            margin-top: {{ $rowGap }}mm;
        }
        .sheet-cell {
            display: inline-block;
            width: {{ $evidenceLabelWidth }}mm;
            height: {{ $labelHeight }}mm;
            vertical-align: top;
            padding: 0;
            font-size: {{ $fontSize }}pt;
        }
        .sheet-cell.case-cell {
            width: {{ $caseLabelWidth }}mm;
            padding-left: 0;
        }
        .sheet.layout-evidence-grid .sheet-row {
            width: {{ ($evidenceLabelWidth * 2) + $gapWidth }}mm;
        }
        .sheet.layout-evidence-grid .sheet-cell.case-cell {
            width: {{ $evidenceLabelWidth }}mm;
        }
        .sheet-gap {
            display: inline-block;
            width: {{ $gapWidth }}mm;
            height: {{ $labelHeight }}mm;
            vertical-align: top;
        }
        .label {
            width: {{ $evidenceLabelWidth }}mm;
            height: {{ $labelHeight }}mm;
            border: 0.25mm solid #333;
            padding: 0.7mm;
            position: relative;
            background: #fff;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .label-header {
            margin-bottom: 0.6mm;
        }
        .label.case-label {
            width: {{ $caseLabelWidth }}mm;
            padding-left: 0.8mm;
            padding-right: 0.8mm;
            padding-top: 0.5mm;
            padding-bottom: 0.5mm;
        }
        .label.evidence-label {
            padding-left: 0.8mm;
            padding-right: 0.8mm;
            padding-top: 0.5mm;
            padding-bottom: 0.5mm;
        }
        .label.case-label .header-table {
            margin-bottom: 0.25mm;
            padding-bottom: 0.1mm;
        }
        .label.evidence-label .header-table {
            margin-bottom: 0.25mm;
            padding-bottom: 0.1mm;
        }
        .label.case-label .header-logo {
            height: 3.4mm;
        }
        .label.evidence-label .header-logo {
            height: 3.4mm;
        }
        .label.case-label .label-header h1 {
            font-size: 6.5pt;
        }
        .label.evidence-label .label-header h1 {
            font-size: 6.5pt;
        }
        .label.case-label .label-header .subtitle {
            font-size: 3.8pt;
            margin-top: 0;
        }
        .label.evidence-label .label-header .subtitle {
            font-size: 3.8pt;
            margin-top: 0;
        }
        .label.case-label .label-body {
            margin-top: 0;
        }
        .label.evidence-label .label-body {
            margin-top: 0;
        }
        .label.case-label .label-content {
            padding-right: 0;
        }
        .label.evidence-label .label-content {
            padding-right: 0;
        }
        .label.case-label .field {
            margin-bottom: 0.15mm;
        }
        .label.evidence-label .field {
            margin-bottom: 0.15mm;
        }
        .label.case-label .field-label {
            font-size: 4.8pt;
        }
        .label.evidence-label .field-label {
            font-size: 4.8pt;
        }
        .label.case-label .field-value {
            font-size: 5.6pt;
            line-height: 1;
        }
        .label.evidence-label .field-value {
            font-size: 5.6pt;
            line-height: 1;
        }
        .label.case-label .field-value.small {
            font-size: 4.4pt;
        }
        .label.evidence-label .field-value.small {
            font-size: 4.4pt;
        }
        .label.case-label .label-footer {
            left: 1.2mm;
            right: 1.2mm;
            bottom: 0.35mm;
            font-size: 4.3pt;
            padding-top: 0.1mm;
        }
        .label.evidence-label .label-footer {
            left: 1.2mm;
            right: 1.2mm;
            bottom: 0.35mm;
            font-size: 4.3pt;
            padding-top: 0.1mm;
        }
        .label.case-label.no-footer .label-footer {
            display: none;
        }
        .label-header h1 {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2pt;
            margin: 0;
            line-height: 1;
        }
        .label-header .subtitle {
            font-size: 5pt;
            color: #555;
            margin-top: 0.2mm;
            line-height: 1;
        }
        .header-logo {
            height: 5mm;
            width: auto;
        }
        .header-table {
            border-bottom: 1px solid #333;
            margin-bottom: 0.6mm;
            padding-bottom: 0.2mm;
            width: 100%;
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
            padding-right: 1mm;
        }
        .label-qr {
            display: table-cell;
            width: 32%;
            vertical-align: top;
            text-align: center;
        }
        .label-qr img {
            width: 14mm;
            height: 14mm;
            display: block;
            margin: 0 auto;
        }
        .qr-text {
            font-size: 5pt;
            color: #666;
            margin-top: 0.3mm;
            word-break: break-all;
            overflow: hidden;
            max-height: 3mm;
        }
        .field {
            margin-bottom: 0.4mm;
            overflow: hidden;
        }
        .field-label {
            font-size: 6pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.1pt;
        }
        .field-value {
            font-size: 7pt;
            font-weight: bold;
            word-break: break-word;
            overflow: hidden;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .field-value.large {
            font-size: 8pt;
        }
        .field-value.small {
            font-size: 5.5pt;
            font-weight: normal;
        }
        .clamp2 {
            max-height: 8mm;
            overflow: hidden;
        }
        .label-footer {
            position: absolute;
            bottom: 0.6mm;
            left: 1.5mm;
            right: 1.5mm;
            font-size: 5pt;
            color: #888;
            border-top: 1px dotted #ccc;
            padding-top: 0.2mm;
        }

        /* Checklist Styles - Compact format for 165x210mm */
        .checklist-container {
            padding: 5mm;
        }
        .checklist-header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 2mm;
            margin-bottom: 3mm;
        }
        .checklist-title {
            margin: 0;
            font-size: 10pt;
            text-transform: uppercase;
            font-weight: bold;
        }
        .checklist-info {
            width: 100%;
            margin-bottom: 3mm;
            font-size: 8pt;
        }
        .checklist-info td {
            vertical-align: top;
            padding-bottom: 1mm;
        }
        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        .checklist-table th, .checklist-table td {
            border: 1px solid #000;
            padding: 1.5mm;
        }
        .checklist-table th {
            background-color: #f0f0f0;
            text-align: center;
        }
        .checkbox {
            width: 3mm;
            height: 3mm;
            border: 1px solid #000;
            display: inline-block;
        }
    </style>
</head>
<body>
    @foreach($pages as $page)
        <div class="sheet layout-{{ $page['layout'] ?? 'mixed' }}">
            @foreach($page['rows'] as $row)
                <div class="sheet-row">
                    <div class="sheet-cell">
                        @if($row && $row['left'])
                            <div class="label evidence-label">
                                <div class="label-header">
                                    <table class="header-table">
                                        <tr>
                                            <td style="width: 12%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                            </td>
                                            <td style="width: 76%; text-align: center; vertical-align: middle;">
                                                <h1>Barang Bukti</h1>
                                                <div class="subtitle">Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri</div>
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
                    </div>
                    <div class="sheet-gap"></div>
                    <div class="sheet-cell case-cell">
                        @if($row && $row['right'])
                            @if(($page['layout'] ?? 'mixed') === 'mixed')
                                <div class="label case-label {{ empty($row['right']['print_footer']) ? 'no-footer' : '' }}">
                                    <div class="label-header">
                                        <table class="header-table">
                                            <tr>
                                                <td style="width: 12%; text-align: center; vertical-align: middle;">
                                                    <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                                </td>
                                                <td style="width: 76%; text-align: center; vertical-align: middle;">
                                                    <h1>LPMF</h1>
                                                    <div class="subtitle">Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri</div>
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

                                    @if(! empty($row['right']['print_footer']))
                                        <div class="label-footer">
                                            Dicetak: {{ $printDate }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="label evidence-label">
                                    <div class="label-header">
                                        <table class="header-table">
                                            <tr>
                                                <td style="width: 12%; text-align: center; vertical-align: middle;">
                                                    <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                                </td>
                                                <td style="width: 76%; text-align: center; vertical-align: middle;">
                                                    <h1>Barang Bukti</h1>
                                                    <div class="subtitle">Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri</div>
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
                                                <div class="field-value">{{ $row['right']['resi'] }}</div>
                                            </div>

                                            <div class="field">
                                                <div class="field-label">Kode Sampel</div>
                                                <div class="field-value large">{{ $row['right']['kode_sampel'] }}</div>
                                            </div>

                                            <div class="field">
                                                <div class="field-label">Tanggal Terima</div>
                                                <div class="field-value">{{ $row['right']['tanggal_terima'] }}</div>
                                            </div>

                                            <div class="field">
                                                <div class="field-label">Deskripsi Singkat</div>
                                                <div class="field-value clamp2">{{ $row['right']['deskripsi_singkat'] }}</div>
                                            </div>
                                        </div>

                                        <div class="label-qr">
                                            <img src="{{ $row['right']['qr'] }}" alt="QR Code">
                                            <div class="qr-text">{{ $row['right']['qr_text'] }}</div>
                                        </div>
                                    </div>

                                    <div class="label-footer">
                                        Dicetak: {{ $printDate }}
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

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
