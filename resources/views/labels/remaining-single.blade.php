<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Sisa - {{ $remainingUnit->remaining_code }}</title>
    <style>
        @page {
            margin: 0;
            size: 75mm 38mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 5pt;
            line-height: 1.1;
        }
        .label {
            width: 75mm;
            height: 38mm;
            padding: 1mm;
            position: relative;
            background: #fff;
            overflow: hidden;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 0.4mm;
            margin-bottom: 0.4mm;
            background: #fffde7;
            margin: -1mm -1mm 0.4mm -1mm;
            padding: 0.6mm;
        }
        .label-header h1 {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1;
        }
        .label-header .subtitle {
            font-size: 3.5pt;
            color: #555;
            line-height: 1;
        }
        .sisa-badge {
            display: inline-block;
            background: #ffc107;
            color: #333;
            padding: 0.3mm 1mm;
            font-size: 4.5pt;
            font-weight: bold;
            border-radius: 1.5mm;
            margin-left: 0.5mm;
        }
        .label-body {
            overflow: hidden;
        }
        .label-content {
            float: left;
            width: 62%;
        }
        .label-qr {
            float: right;
            width: 34%;
            text-align: center;
        }
        .label-qr img {
            width: 12mm;
            height: 12mm;
        }
        .field {
            margin-bottom: 0.5mm;
        }
        .field-label {
            font-size: 4pt;
            color: #666;
            text-transform: uppercase;
        }
        .field-value {
            font-size: 5pt;
            font-weight: bold;
        }
        .field-value.large {
            font-size: 6.5pt;
            color: #c00;
        }
        .field-value.qty {
            font-size: 6pt;
            color: #060;
        }
        .label-footer {
            position: absolute;
            bottom: 0.4mm;
            left: 1.2mm;
            right: 1.2mm;
            font-size: 3pt;
            color: #999;
            border-top: 1px dotted #ccc;
            padding-top: 0.3mm;
        }
        .clearfix::after {
            content: "";
            display: block;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="label-header">
            <h1><span class="sisa-badge">SISA</span></h1>
            <div class="subtitle">LPMF - Pusdokkes Polri</div>
        </div>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 65%; vertical-align: top;">
                    <div class="field">
                        <span class="field-label">Resi:</span>
                        <span class="field-value">{{ $remainingUnit->evidenceUnit->receipt_code ?? '-' }}</span>
                    </div>
                    
                    <div class="field">
                        <span class="field-label">Kode:</span>
                        <span class="field-value large">{{ $remainingUnit->remaining_code }}</span>
                    </div>
                    
                    <div class="field">
                        <span class="field-label">Tgl Serah:</span>
                        <span class="field-value">{{ $remainingUnit->delivered_at_formatted ?? '-' }}</span>
                    </div>
                    
                    <div class="field">
                        <span class="field-label">Qty Sisa:</span>
                        <span class="field-value qty">{{ $remainingUnit->qty_with_uom }}</span>
                    </div>
                    
                    @if($remainingUnit->seal_status_delivered)
                    <div class="field">
                        <span class="field-label">Segel:</span>
                        <span class="field-value">{{ $remainingUnit->seal_status_delivered }}</span>
                    </div>
                    @endif
                    
                    @if($remainingUnit->handover_doc_no)
                    <div class="field">
                        <span class="field-label">No. BA:</span>
                        <span class="field-value">{{ $remainingUnit->handover_doc_no }}</span>
                    </div>
                    @endif
                </td>
                <td style="width: 35%; vertical-align: top; text-align: center; padding-top: 0.3mm;">
                    <img src="{{ $remainingUnit->qr_png ?? '' }}" style="width:12mm;height:12mm;display:block;margin:0 auto;" alt="QR">
                    <div style="font-size: 3pt; margin-top: 0.3mm; text-align: center; width: 12mm; margin-left: auto; margin-right: auto;">{{ $remainingUnit->qr_content }}</div>
                </td>
            </tr>
        </table>
        
        <div class="label-footer">
            Dicetak: {{ $printDate }}
        </div>
    </div>
</body>
</html>
