<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Barang Bukti - {{ $label['kode_sampel'] }}</title>
    <style>
        @page {
            margin: 0;
            size: 74mm 52mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 5.5pt;
            line-height: 1.2;
        }
        .label {
            width: 74mm;
            height: 52mm;
            padding: 2mm;
            position: relative;
            background: #fff;
            overflow: hidden;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 1mm;
            margin-bottom: 1mm;
        }
        .label-header h1 {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .label-header .subtitle {
            font-size: 4pt;
            color: #555;
            margin-top: 0.5mm;
        }
        .label-body {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .label-content {
            display: table-cell;
            width: 62%;
            vertical-align: top;
            padding-right: 2mm;
        }
        .label-qr {
            display: table-cell;
            width: 38%;
            vertical-align: top;
            text-align: center;
        }
        .label-qr img {
            width: 15mm;
            height: 15mm;
            display: block;
            margin: 0 auto;
        }
        .qr-text {
            font-size: 3.5pt;
            color: #666;
            margin-top: 1mm;
            word-break: break-all;
            overflow: hidden;
            max-height: 6mm;
        }
        .field {
            margin-bottom: 0.8mm;
            overflow: hidden;
        }
        .field-label {
            font-size: 4.5pt;
            color: #666;
            text-transform: uppercase;
        }
        .field-value {
            font-size: 5.5pt;
            font-weight: bold;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-word;
            overflow: hidden;
            max-height: 6mm;
        }
        .field-value.large {
            font-size: 7pt;
        }
        .clamp2 {
            max-height: 5mm;
            overflow: hidden;
        }
        .label-footer {
            position: absolute;
            bottom: 1mm;
            left: 2.5mm;
            right: 2.5mm;
            font-size: 3.5pt;
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