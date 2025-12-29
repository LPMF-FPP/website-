<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Barang Bukti - {{ $evidenceUnit->sample_code }}</title>
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
            margin-bottom: 2mm;
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
            <h1>Label Barang Bukti</h1>
            <div class="subtitle">LPMF - Pusdokkes Polri</div>
        </div>
        
        <div class="label-body clearfix">
            <div class="label-content">
                <div class="field">
                    <div class="field-label">Resi</div>
                    <div class="field-value">{{ $evidenceUnit->receipt_code ?? '-' }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Kode Sampel</div>
                    <div class="field-value large">{{ $evidenceUnit->sample_code }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Tanggal Terima</div>
                    <div class="field-value">{{ $evidenceUnit->received_at_formatted ?? '-' }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Penyidik</div>
                    <div class="field-value">{{ $evidenceUnit->investigator_name ?? '-' }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Satuan</div>
                    <div class="field-value">{{ $evidenceUnit->investigator_unit ?? '-' }}</div>
                </div>
                
                @if($evidenceUnit->seal_status_received)
                <div class="field">
                    <div class="field-label">Segel</div>
                    <div class="field-value">{{ $evidenceUnit->seal_status_received }}</div>
                </div>
                @endif
            </div>
            
            <div class="label-qr">
                <img src="data:image/png;base64,{{ base64_encode(QrCode::format('png')->size(80)->generate($evidenceUnit->qr_content)) }}" alt="QR">
                <div style="font-size: 5pt; margin-top: 1mm;">{{ $evidenceUnit->qr_content }}</div>
            </div>
        </div>
        
        <div class="label-footer">
            Dicetak: {{ $printDate }}
        </div>
    </div>
</body>
</html>
