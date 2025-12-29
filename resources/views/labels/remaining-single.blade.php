<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Sisa - {{ $remainingUnit->remaining_code }}</title>
    <style>
        @page {
            margin: 0;
            size: 100mm 50mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.3;
        }
        .label {
            width: 100mm;
            height: 50mm;
            padding: 3mm;
            position: relative;
            background: #fff;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 2mm;
            margin-bottom: 1mm;
            background: #fffde7;
            margin: -3mm -3mm 2mm -3mm;
            padding: 1.5mm;
        }
        .label-header h1 {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .label-header .subtitle {
            font-size: 6pt;
            color: #555;
        }
        .sisa-badge {
            display: inline-block;
            background: #ffc107;
            color: #333;
            padding: 0.5mm 2mm;
            font-size: 6pt;
            font-weight: bold;
            border-radius: 2mm;
            margin-left: 1mm;
        }
        .label-body {
            overflow: hidden;
        }
        .label-content {
            float: left;
            width: 60%;
        }
        .label-qr {
            float: right;
            width: 35%;
            text-align: center;
        }
        .label-qr img {
            width: 22mm;
            height: 22mm;
        }
        .field {
            margin-bottom: 1mm;
        }
        .field-label {
            font-size: 6pt;
            color: #666;
            text-transform: uppercase;
        }
        .field-value {
            font-size: 7pt;
            font-weight: bold;
        }
        .field-value.large {
            font-size: 10pt;
            color: #c00;
        }
        .field-value.qty {
            font-size: 9pt;
            color: #060;
        }
        .label-footer {
            position: absolute;
            bottom: 1mm;
            left: 3mm;
            right: 3mm;
            font-size: 5pt;
            color: #999;
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
            <h1>Label Sisa <span class="sisa-badge">SISA</span></h1>
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
                <td style="width: 35%; vertical-align: top; text-align: center; padding-top: 1mm;">
                    <img src="data:image/png;base64,{{ base64_encode(QrCode::format('png')->size(80)->generate($remainingUnit->qr_content)) }}" alt="QR" style="width: 20mm; height: 20mm;">
                    <div style="font-size: 5pt; margin-top: 1mm; text-align: center; width: 20mm; margin-left: auto; margin-right: auto;">{{ $remainingUnit->qr_content }}</div>
                </td>
            </tr>
        </table>
        
        <div class="label-footer">
            Dicetak: {{ $printDate }}
        </div>
    </div>
</body>
</html>
