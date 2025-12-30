<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Barang Bukti</title>
    <style>
        @page {
            margin: 8mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.25;
        }
        .page-break {
            page-break-after: always;
        }
        .label-container {
            width: 100%;
        }
        .label-row {
            width: 100%;
            overflow: hidden;
            margin-bottom: 2mm;
        }
        .label {
            float: left;
            border: 1px solid #333;
            padding: 3mm;
            height: 46mm;
            width: 92mm;
            margin-right: 4mm;
            margin-bottom: 2mm;
            position: relative;
            background: #fff;
            overflow: hidden;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 1.5mm;
            margin-bottom: 1.5mm;
        }
        .label-header h1 {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            margin: 0;
        }
        .label-header .subtitle {
            font-size: 6pt;
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
            width: 65%;
            vertical-align: top;
            padding-right: 2mm;
        }
        .label-qr {
            display: table-cell;
            width: 35%;
            vertical-align: top;
            text-align: center;
        }
        .label-qr img {
            width: 22mm;
            height: 22mm;
            display: block;
            margin: 0 auto;
        }
        .qr-text {
            font-size: 5pt;
            color: #666;
            margin-top: 1mm;
            word-break: break-all;
            overflow: hidden;
            max-height: 8mm;
        }
        .field {
            margin-bottom: 1mm;
            overflow: hidden;
        }
        .field-label {
            font-size: 6pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.2pt;
        }
        .field-value {
            font-size: 7.5pt;
            font-weight: bold;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-word;
            overflow: hidden;
            max-height: 8mm;
        }
        .field-value.large {
            font-size: 10pt;
        }
        .field-value.small {
            font-size: 7pt;
            font-weight: normal;
        }
        /* Clamp text to max 2 lines */
        .clamp2 {
            max-height: 6mm;
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
            font-size: 5pt;
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
        $chunks = $labels->chunk($labelsPerPage);
    @endphp

    @foreach($chunks as $chunkIndex => $chunk)
        <div class="label-container">
            @foreach($chunk as $label)
                <div class="label">
                    <div class="label-header">
                        <h1>Label Barang Bukti</h1>
                        <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
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
                                <div class="field-label">Penyidik</div>
                                <div class="field-value clamp2">{{ $label['penyidik'] }}</div>
                            </div>
                            
                            <div class="field">
                                <div class="field-label">Satuan Kerja</div>
                                <div class="field-value clamp2">{{ $label['satuan_kerja'] }}</div>
                            </div>
                            
                            <div class="field">
                                <div class="field-label">Satuan</div>
                                <div class="field-value">{{ $label['satuan'] }}</div>
                            </div>
                            
                            @if($label['jenis'] && $label['jenis'] !== '-')
                            <div class="field">
                                <div class="field-label">Jenis</div>
                                <div class="field-value small">{{ $label['jenis'] }}</div>
                            </div>
                            @endif
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
            @endforeach
        </div>
        
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
