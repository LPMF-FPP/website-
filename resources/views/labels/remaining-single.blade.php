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
            line-height: 1;
            overflow: hidden;
        }
        .label {
            width: 75mm;
            height: 36mm;
            position: relative;
            background: #fff;
            overflow: hidden;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid #333;
            background: #fffde7;
            height: 5.2mm;
            padding-top: 0.45mm;
        }
        .label-header h1 {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1;
        }
        .label-header .subtitle {
            font-size: 3.2pt;
            color: #555;
            line-height: 1;
        }
        .sisa-badge {
            display: inline-block;
            background: #ffc107;
            color: #333;
            padding: 0.2mm 0.8mm;
            font-size: 4.2pt;
            font-weight: bold;
            border-radius: 1.5mm;
            margin-left: 0.5mm;
        }
        .field {
            position: absolute;
            left: 1.2mm;
            width: 47mm;
            height: 3.7mm;
            overflow: hidden;
            white-space: nowrap;
        }
        .field-label {
            display: inline-block;
            width: 13mm;
            font-size: 4.4pt;
            color: #666;
            text-transform: uppercase;
            vertical-align: baseline;
        }
        .field-value {
            display: inline-block;
            width: 33mm;
            font-size: 5.3pt;
            font-weight: bold;
            line-height: 1;
            vertical-align: baseline;
            overflow: hidden;
            white-space: nowrap;
        }
        .field-value.large {
            font-size: 5pt;
            color: #c00;
        }
        .field-value.qty {
            font-size: 5.8pt;
            color: #060;
        }
        .row-resi { top: 6.4mm; }
        .row-kode { top: 10.4mm; }
        .row-date { top: 14.4mm; }
        .row-qty { top: 18.4mm; }
        .row-seal { top: 22.4mm; }
        .row-ba { top: 26.4mm; }
        .qr-box {
            position: absolute;
            top: 6.4mm;
            right: 1.2mm;
            width: 22mm;
            text-align: center;
        }
        .qr-box img {
            width: 13mm;
            height: 13mm;
        }
        .label-footer {
            position: absolute;
            bottom: 0.8mm;
            left: 1mm;
            right: 1mm;
            font-size: 2.8pt;
            color: #999;
            border-top: 1px dotted #ccc;
            padding-top: 0.2mm;
        }
        .qr-text {
            font-size: 3.1pt;
            margin-top: 0.2mm;
            text-align: center;
            width: 13mm;
            max-height: 4.6mm;
            margin-left: auto;
            margin-right: auto;
            overflow: hidden;
            word-break: break-all;
            line-height: 1;
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="label-header">
            <h1><span class="sisa-badge">SISA</span></h1>
            <div class="subtitle">LPMF - Pusdokkes Polri</div>
        </div>
        
        <div class="field row-resi">
            <span class="field-label">Resi</span>
            <span class="field-value">{{ $remainingUnit->evidenceUnit->receipt_code ?? '-' }}</span>
        </div>
        <div class="field row-kode">
            <span class="field-label">Kode</span>
            <span class="field-value large">{{ $remainingUnit->remaining_code }}</span>
        </div>
        <div class="field row-date">
            <span class="field-label">Tgl Serah</span>
            <span class="field-value">{{ $remainingUnit->delivered_at_formatted ?? '-' }}</span>
        </div>
        <div class="field row-qty">
            <span class="field-label">Qty Sisa</span>
            <span class="field-value qty">{{ $remainingUnit->qty_with_uom }}</span>
        </div>
        @if($remainingUnit->seal_status_delivered)
            <div class="field row-seal">
                <span class="field-label">Segel</span>
                <span class="field-value">{{ $remainingUnit->seal_status_delivered }}</span>
            </div>
        @endif
        @if($remainingUnit->handover_doc_no)
            <div class="field row-ba">
                <span class="field-label">No. BA</span>
                <span class="field-value">{{ $remainingUnit->handover_doc_no }}</span>
            </div>
        @endif

        <div class="qr-box">
            <img src="{{ $remainingUnit->qr_png ?? '' }}" alt="QR">
            <div class="qr-text">{{ $remainingUnit->qr_content }}</div>
        </div>
        
        <div class="label-footer">
            Dicetak: {{ $printDate }}
        </div>
    </div>
</body>
</html>
