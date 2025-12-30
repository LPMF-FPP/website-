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
            float: left;
            border: 1px solid #333;
            padding: 3mm; /* Reduced from 4mm */
            height: 48mm;
            width: 90mm;
            margin-right: 5mm;
            margin-bottom: 3mm;
            position: relative;
            background: #fff;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 1mm; /* Reduced from 2mm */
            margin-bottom: 1mm; /* Reduced from 2mm */
            background: #fffde7;
            margin: -3mm -3mm 2mm -3mm; /* Match new padding */
            padding: 2mm;
        }
        /* ... */
        .field {
            margin-bottom: 1mm; /* Reduced from 1.5mm */
        }
        /* ... */
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
    @php
        $labelsPerPage = 10;
        $chunks = $remainingUnits->chunk($labelsPerPage);
    @endphp

    @foreach($chunks as $chunkIndex => $chunk)
        <div class="label-container">
            @foreach($chunk as $unit)
                <div class="label">
                    <div class="label-header">
                        <h1>Label Sisa <span class="sisa-badge">SISA</span></h1>
                        <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 65%; vertical-align: top;">
                                <div class="field">
                                    <span class="field-label">Resi:</span>
                                    <span class="field-value">{{ $unit->evidenceUnit->receipt_code ?? '-' }}</span>
                                </div>
                                
                                <div class="field">
                                    <span class="field-label">Kode:</span>
                                    <span class="field-value large">{{ $unit->remaining_code }}</span>
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
                                    <span class="field-value">{{ $unit->seal_status_delivered }}</span>
                                </div>
                                @endif
                                
                                @if($unit->handover_doc_no)
                                <div class="field">
                                    <span class="field-label">No. BA:</span>
                                    <span class="field-value">{{ $unit->handover_doc_no }}</span>
                                </div>
                                @endif
                            </td>
                            <td style="width: 35%; vertical-align: top; text-align: center; padding-top: 2mm;">
                                <div style="width: 25mm; height: 25mm; margin: 0 auto;">{!! QrCode::size(100)->generate($unit->qr_content) !!}</div>
                                <div style="font-size: 6pt; margin-top: 1mm;">{{ $unit->qr_content }}</div>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="label-footer">
                        Dicetak: {{ $printDate }}
                    </div>
                </div>
            @endforeach
        </div>
        
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
