<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Sisa Sampel</title>
    @php
        $totalLabels = $remainingUnits->count();
        // Determine grid layout based on label count
        if ($totalLabels <= 4) {
            $labelHeight = 65; // mm
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
        }
        .label-container {
            width: 100%;
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
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 0.8mm;
            margin-bottom: 0.8mm;
            background: #fffde7;
            margin: -1.5mm -1.5mm 0.8mm -1.5mm;
            padding: 1mm;
        }
        .label-header h1 {
            font-size: {{ $fontSize }}pt;
            font-weight: bold;
            margin: 0;
        }
        .label-header .subtitle {
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            color: #555;
            margin-top: 0.3mm;
        }
        .sisa-badge {
            background-color: #333;
            color: #fff;
            padding: 0.3mm 1.5mm;
            border-radius: 1.5mm;
            font-size: {{ max($fontSize - 1, 5) }}pt;
            letter-spacing: 0.5px;
        }
        .field {
            margin-bottom: 0.5mm;
            overflow: hidden;
        }
        .field-label {
            display: inline-block;
            width: 14mm;
            font-size: {{ max($fontSize - 2, 4) }}pt;
            color: #666;
            vertical-align: top;
        }
        .field-value {
            display: inline-block;
            width: calc(100% - 15mm);
            font-size: {{ $fontSize }}pt;
            font-weight: bold;
            vertical-align: top;
            line-height: 1.1;
        }
        .field-value.large {
            font-size: {{ $fontSize + 1 }}pt;
        }
        .field-value.qty {
            font-size: {{ $fontSize }}pt;
            color: #d32f2f;
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
        .qr-img {
            width: {{ min($labelHeight * 0.35, 16) }}mm;
            height: {{ min($labelHeight * 0.35, 16) }}mm;
            display: block;
            margin: 0 auto;
        }
        .qr-text {
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            margin-top: 0.5mm;
            text-align: center;
            width: {{ min($labelHeight * 0.35, 16) }}mm;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>
<body>
    {{-- ALL LABELS IN ONE PAGE (no page break) --}}
    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        @foreach($remainingUnits->chunk(2) as $row)
            <tr>
                @foreach($row as $unit)
                    <td style="width:50%; vertical-align:top; padding: 3mm;">
                        <div class="label">
                            <div class="label-header">
                                <h1><span class="sisa-badge">SISA</span></h1>
                                <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
                            </div>
                            
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 68%; vertical-align: top;">
                                        <div class="field">
                                            <span class="field-label">Resi:</span>
                                            <span class="field-value clamp2">{{ $unit->evidenceUnit->receipt_code ?? '-' }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label">Kode:</span>
                                            <span class="field-value large clamp2">{{ $unit->remaining_code }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label">Tgl Serah:</span>
                                            <span class="field-value">{{ $unit->delivered_at_formatted ?? '-' }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label">Qty Sisa:</span>
                                            <span class="field-value qty">{{ $unit->qty_with_uom }}</span>
                                        </div>
                                        
                                        @if($unit->seal_status_delivered)
                                        <div class="field">
                                            <span class="field-label">Segel:</span>
                                            <span class="field-value clamp2">{{ $unit->seal_status_delivered }}</span>
                                        </div>
                                        @endif
                                        
                                        @if($unit->handover_doc_no)
                                        <div class="field">
                                            <span class="field-label">No. BA:</span>
                                            <span class="field-value clamp2">{{ $unit->handover_doc_no }}</span>
                                        </div>
                                        @endif
                                    </td>
                                    <td style="width: 32%; vertical-align: top; text-align: center; padding-top: 0.5mm;">
                                        <img src="{{ $unit->qr_png ?? '' }}" class="qr-img" alt="QR">
                                        <div class="qr-text">{{ $unit->qr_content }}</div>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="label-footer">
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
