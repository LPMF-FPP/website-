<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label Sisa Sampel</title>
    @php
        $totalLabels = $remainingUnits->count();
        // Determine grid layout based on label count
        if ($totalLabels <= 4) {
            $labelHeight = 55; // mm
            $labelWidth = 95;  // mm
            $fontSize = 8;
        } elseif ($totalLabels <= 6) {
            $labelHeight = 43; // mm
            $labelWidth = 95;  // mm
            $fontSize = 7;
        } elseif ($totalLabels <= 8) {
            $labelHeight = 32; // mm
            $labelWidth = 95;  // mm
            $fontSize = 6;
        } elseif ($totalLabels <= 12) {
            $labelHeight = 21; // mm
            $labelWidth = 95;  // mm
            $fontSize = 5.5;
        } else {
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
            color: black;
        }
        .label-container {
            width: 100%;
        }
        .sheet-table {
            width: 100%;
            border-collapse: collapse;
        }
        .label-cell {
            width: 50%;
            vertical-align: top;
            padding: 2mm;
        }
        .label {
            border: 1px solid black;
            padding: 1.5mm;
            height: {{ $labelHeight }}mm;
            width: {{ $labelWidth }}mm;
            position: relative;
            background: white;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid black;
            padding-bottom: 0.4mm;
            margin-bottom: 0.6mm;
            background: white;
            margin: -1.5mm -1.5mm 0.6mm -1.5mm;
            padding: 0.8mm 1mm;
        }
        .label-header h1 {
            font-size: {{ $fontSize }}pt;
            font-weight: bold;
            margin: 0;
        }
        .text-balance {
            text-wrap: balance;
        }
        .text-pretty {
            text-wrap: pretty;
        }
        .header-logo {
            height: {{ min($labelHeight * 0.12, 8) }}mm;
            width: auto;
        }
        .header-table {
            border-collapse: collapse;
            width: 100%;
        }
        .header-logo-cell {
            width: 12%;
            text-align: center;
            vertical-align: middle;
        }
        .header-center-cell {
            width: 76%;
            text-align: center;
            vertical-align: middle;
        }
        .label-body-table {
            width: 100%;
            border-collapse: collapse;
        }
        .label-content-cell {
            width: 68%;
            vertical-align: top;
        }
        .label-qr-cell {
            width: 32%;
            vertical-align: top;
            text-align: center;
            padding-top: 0.5mm;
        }
        .label-header .subtitle {
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            color: dimgray;
            margin-top: 0.3mm;
        }
        .sisa-badge {
            background-color: black;
            color: white;
            padding: 0.3mm 1.5mm;
            border-radius: 1.5mm;
            font-size: {{ max($fontSize - 1, 5) }}pt;
            text-transform: uppercase;
        }
        .field {
            margin-bottom: 0.5mm;
            overflow: hidden;
        }
        .field-label {
            display: inline-block;
            width: 14mm;
            font-size: {{ max($fontSize - 2, 4) }}pt;
            color: dimgray;
            vertical-align: top;
        }
        .field-value {
            display: inline-block;
            width: calc(100% - 15mm);
            font-size: {{ $fontSize }}pt;
            font-weight: bold;
            vertical-align: top;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .field-value.large {
            font-size: {{ $fontSize + 1 }}pt;
        }
        .field-value.qty {
            font-size: {{ $fontSize }}pt;
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
            bottom: 0.6mm;
            left: 2mm;
            right: 2mm;
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            color: dimgray;
            border-top: 1px dotted lightgray;
            padding-top: 0.3mm;
            font-variant-numeric: tabular-nums;
        }
        .qr-img {
            display: block;
            margin: 0 auto;
        }
        .size-qr {
            width: {{ min($labelHeight * 0.35, 16) }}mm;
            height: {{ min($labelHeight * 0.35, 16) }}mm;
        }
        .qr-text {
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            margin-top: 0.5mm;
            text-align: center;
            width: {{ min($labelHeight * 0.35, 16) }}mm;
            margin-left: auto;
            margin-right: auto;
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body>
    {{-- ALL LABELS IN ONE PAGE (no page break) --}}
    <table class="sheet-table" cellspacing="0" cellpadding="0" role="presentation">
        @foreach($remainingUnits->chunk(2) as $row)
            <tr>
                @foreach($row as $unit)
                    <td class="label-cell">
                        <div class="label text-pretty">
                            <div class="label-header">
                                <table class="header-table" role="presentation">
                                    <tr>
                                        <td class="header-logo-cell">
                                            <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                        </td>
                                        <td class="header-center-cell">
                                            <h1 class="text-balance"><span class="sisa-badge">SISA</span></h1>
                                            <div class="subtitle text-pretty">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
                                        </td>
                                        <td class="header-logo-cell">
                                            <img src="{{ public_path('images/logo-pusdokkes-polri.png') }}" class="header-logo" alt="Logo Pusdokkes">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <table class="label-body-table" role="presentation">
                                <tr>
                                    <td class="label-content-cell">
                                        <div class="field">
                                            <span class="field-label text-pretty">Resi:</span>
                                            <span class="field-value clamp2 text-pretty">{{ $unit->evidenceUnit->receipt_code ?? '-' }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label text-pretty">Kode:</span>
                                            <span class="field-value large clamp2 text-pretty">{{ $unit->remaining_code }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label text-pretty">Tgl Serah:</span>
                                            <span class="field-value text-pretty">{{ $unit->delivered_at_formatted ?? '-' }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label text-pretty">Qty Sisa:</span>
                                            <span class="field-value qty text-pretty">{{ $unit->qty_with_uom }}</span>
                                        </div>
                                        
                                        @if($unit->seal_status_delivered)
                                        <div class="field">
                                            <span class="field-label text-pretty">Segel:</span>
                                            <span class="field-value clamp2 text-pretty">{{ $unit->seal_status_delivered }}</span>
                                        </div>
                                        @endif
                                        
                                        @if($unit->handover_doc_no)
                                        <div class="field">
                                            <span class="field-label text-pretty">No. BA:</span>
                                            <span class="field-value clamp2 text-pretty">{{ $unit->handover_doc_no }}</span>
                                        </div>
                                        @endif
                                    </td>
                                    <td class="label-qr-cell">
                                        <img src="{{ $unit->qr_png ?? '' }}" class="qr-img size-qr" alt="QR code for {{ $unit->remaining_code }}">
                                        <div class="qr-text text-pretty">{{ $unit->qr_content }}</div>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="label-footer text-pretty">
                                Dicetak: {{ $printDate }}
                            </div>
                        </div>
                    </td>
                @endforeach
                @if($row->count() === 1)
                    <td style="width:50%; vertical-align:top;"></td>
                @endif
            </tr>
        @endforeach
    </table>
</body>
</html>
