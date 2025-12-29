<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Label Barang Bukti</title>
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
        .label-row {
            width: 100%;
            overflow: hidden;
            margin-bottom: 3mm;
        }
        .label {
            float: left;
            border: 1px solid #333;
            padding: 4mm;
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
            padding-bottom: 2mm;
            margin-bottom: 2mm;
        }
        .label-header h1 {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }
        .label-header .subtitle {
            font-size: 7pt;
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
        .label-qr svg {
            width: 25mm;
            height: 25mm;
        }
        .field {
            margin-bottom: 1.5mm;
        }
        .field-label {
            font-size: 7pt;
            color: #666;
            text-transform: uppercase;
        }
        .field-value {
            font-size: 8pt;
            font-weight: bold;
            word-break: break-word;
        }
        .field-value.large {
            font-size: 11pt;
        }
        .field-value.desc {
            font-weight: normal;
            font-size: 7.5pt;
            max-height: 10mm;
            overflow: hidden;
        }
        .label-footer {
            position: absolute;
            bottom: 2mm;
            left: 4mm;
            right: 4mm;
            font-size: 6pt;
            color: #888;
            border-top: 1px dotted #ccc;
            padding-top: 1mm;
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
        $chunks = $evidenceUnits->chunk($labelsPerPage);
    @endphp

    @foreach($chunks as $chunkIndex => $chunk)
        <div class="label-container">
            @foreach($chunk as $unit)
                <div class="label">
                    <div class="label-header">
                        <h1>Label Barang Bukti</h1>
                        <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
                    </div>
                    
                    <div class="label-body clearfix">
                        <div class="label-content">
                            <div class="field">
                                <div class="field-label">Resi</div>
                                <div class="field-value">{{ $unit->receipt_code ?? '-' }}</div>
                            </div>
                            
                            <div class="field">
                                <div class="field-label">Kode Sampel</div>
                                <div class="field-value large">{{ $unit->sample_code }}</div>
                            </div>
                            
                            <div class="field">
                                <div class="field-label">Tanggal Terima</div>
                                <div class="field-value">{{ $unit->received_at_formatted ?? '-' }}</div>
                            </div>
                            
                            <div class="field">
                                <div class="field-label">Penyidik</div>
                                <div class="field-value">{{ $unit->investigator_name ?? '-' }}</div>
                            </div>
                            
                            <div class="field">
                                <div class="field-label">Satuan</div>
                                <div class="field-value">{{ $unit->investigator_unit ?? '-' }}</div>
                            </div>
                            
                            @if($unit->sample_type)
                            <div class="field">
                                <div class="field-label">Jenis</div>
                                <div class="field-value">{{ $unit->sample_type }}</div>
                            </div>
                            @endif
                            
                            @if($unit->seal_status_received)
                            <div class="field">
                                <div class="field-label">Segel</div>
                                <div class="field-value">{{ $unit->seal_status_received }}</div>
                            </div>
                            @endif
                        </div>
                        
                        <div class="label-qr">
                            <img src="data:image/png;base64,{{ base64_encode(QrCode::format('png')->size(100)->generate($unit->qr_content)) }}" alt="QR">
                            <div style="font-size: 6pt; margin-top: 1mm;">{{ $unit->qr_content }}</div>
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
