<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Barang Bukti - {{ $label['kode_sampel'] }}</title>
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
        }
        .label-header h1 {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
            line-height: 1;
        }
        .label-header .subtitle {
            font-size: 3.5pt;
            color: #555;
            margin-top: 0.2mm;
            line-height: 1;
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
            width: 12mm;
            height: 12mm;
            display: block;
            margin: 0 auto;
        }
        .qr-text {
            font-size: 3pt;
            color: #666;
            margin-top: 0.3mm;
            word-break: break-all;
            overflow: hidden;
            max-height: 4mm;
        }
        .field {
            margin-bottom: 0.5mm;
            overflow: hidden;
        }
        .field-label {
            font-size: 4pt;
            color: #666;
            text-transform: uppercase;
        }
        .field-value {
            font-size: 5pt;
            font-weight: bold;
            word-break: break-word;
            overflow: hidden;
            line-height: 1.1;
        }
        .field-value.large {
            font-size: 6.5pt;
        }
        .clamp2 {
            max-height: 4.5mm;
            overflow: hidden;
        }
        .label-footer {
            position: absolute;
            bottom: 0.4mm;
            left: 1.2mm;
            right: 1.2mm;
            font-size: 3pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="label-header">
            <h1>Barang Bukti</h1>
            <div class="subtitle">LPMF - Pusdokkes Polri</div>
        </div>
        
        <div class="label-body">
            <div class="label-content">
                <div class="field">
                    <div class="field-label">Resi</div>
                    <div class="field-value">{{ $label['resi'] }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Kode Sampel</div>
                    <div class="field-value large">{{ $label['kode_sampel'] }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Tanggal Terima</div>
                    <div class="field-value">{{ $label['tanggal_terima'] }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Deskripsi Singkat</div>
                    <div class="field-value clamp2">{{ $label['deskripsi_singkat'] }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Satuan Kerja</div>
                    <div class="field-value clamp2">{{ $label['satuan_kerja'] }}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">Satuan</div>
                    <div class="field-value">{{ $label['satuan'] }}</div>
                </div>
            </div>
            
            <div class="label-qr">
                <img src="{{ $label['qr'] }}" alt="QR Code">
                <div class="qr-text">{{ $label['qr_text'] }}</div>
            </div>
        </div>
        
        <div class="label-footer">
            Dicetak: {{ $printDate }}
        </div>
    </div>
</body>
</html>
