<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Sisa Sampel</title>
    <style>
        @page {
            margin: 10mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
        }
        .page-break {
            page-break-after: always;
        }
        .label-container {
            width: 100%;
        }
        .label {
            border: 1px solid #333;
            padding: 3mm; /* Reduced from 4mm */
            height: 48mm;
            width: 90mm;
            position: relative;
            background: #fff;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 1mm;
            margin-bottom: 1mm;
            background: #fffde7;
            margin: -3mm -3mm 1mm -3mm; /* Adjusted margin */
            padding: 1.5mm;
        }
        .label-header h1 {
            font-size: 8pt;
            font-weight: bold;
            margin: 0;
        }
        .label-header .subtitle {
            font-size: 5pt;
            color: #555;
            margin-top: 0.5mm;
        }
        .sisa-badge {
            background-color: #333;
            color: #fff;
            padding: 0.5mm 2mm;
            border-radius: 2mm;
            font-size: 7pt;
            letter-spacing: 1px;
        }
        .field {
            margin-bottom: 0.8mm; /* Reduced */
            overflow: hidden;
        }
        .field-label {
            display: inline-block;
            width: 16mm;
            font-size: 6pt;
            color: #666;
            vertical-align: top;
        }
        .field-value {
            display: inline-block;
            width: calc(100% - 17mm);
            font-size: 7.5pt;
            font-weight: bold;
            vertical-align: top;
            line-height: 1.1;
        }
        .field-value.large {
            font-size: 9pt;
        }
        .field-value.qty {
            font-size: 8pt;
            color: #d32f2f;
        }
        /* Clamp text to max 2 lines */
        .clamp2 {
            max-height: 5.5mm;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .label-footer {
            position: absolute;
            bottom: 1.5mm;
            left: 3mm;
            right: 3mm;
            font-size: 6pt;
            color: #888;
            border-top: 1px dotted #ccc;
            padding-top: 0.5mm;
        }
        .clearfix::after {
            content: "";
            display: block;
            clear: both;
        }
    </style>
</head>
<body>
    @foreach($remainingUnits->chunk(10) as $chunkIndex => $chunk)
        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            @foreach($chunk->chunk(2) as $row)
                <tr>
                    @foreach($row as $unit)
                        <td style="width:50%; vertical-align:top; padding-right:5mm; padding-bottom:3mm;">
                            <div class="label">
                                <div class="label-header">
                                    <h1><span class="sisa-badge">SISA</span></h1>
                                    <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
                                </div>
                                
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="width: 70%; vertical-align: top;">
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
                                        <td style="width: 30%; vertical-align: top; text-align: center; padding-top: 1mm;">
                                            <img src="{{ $unit->qr_png ?? '' }}" style="width:20mm;height:20mm;display:block;margin:0 auto;" alt="QR">
                                            <div style="font-size: 5pt; margin-top: 1mm;">{{ $unit->qr_content }}</div>
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
        
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>