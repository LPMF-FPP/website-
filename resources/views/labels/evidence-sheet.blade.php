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
        .label {
            border: 1px solid #333;
            padding: 3mm;
            height: 46mm;
            width: 92mm;
            position: relative;
            background: #fff;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .label-header {
            /* Border moved to table */
            padding-bottom: 0;
            margin-bottom: 0;
        }
        .label-header h1 {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            margin: 0;
        }
        .label-header .subtitle {
            font-size: 5pt;
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
    @foreach($rows->chunk(5) as $chunkIndex => $chunk)
        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            @foreach($chunk as $row)
                <tr>
                    {{-- Left Column: Evidence Label --}}
                    <td style="width:50%; vertical-align:top; padding-right:4mm; padding-bottom:2mm;">
                        @if($row['left'])
                            <div class="label">
                                <div class="label-header">
                                    <table width="100%" style="border-bottom: 1px solid #333; margin-bottom: 1.5mm; padding-bottom: 1mm;">
                                        <tr>
                                            <td style="width: 15%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-tribrata-polri.png') }}" style="height: 10mm; width: auto;">
                                            </td>
                                            <td style="width: 70%; text-align: center; vertical-align: middle;">
                                                <h1>Barang Bukti</h1>
                                                <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
                                            </td>
                                            <td style="width: 15%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-pusdokkes-polri.png') }}" style="height: 10mm; width: auto;">
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="label-body">
                                    <div class="label-content">
                                        <div class="field">
                                            <div class="field-label">Resi</div>
                                            <div class="field-value">{{ $row['left']['resi'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Kode Sampel</div>
                                            <div class="field-value large">{{ $row['left']['kode_sampel'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Tanggal Terima</div>
                                            <div class="field-value">{{ $row['left']['tanggal_terima'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Deskripsi Singkat</div>
                                            <div class="field-value clamp2">{{ $row['left']['deskripsi_singkat'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Satuan Kerja</div>
                                            <div class="field-value clamp2">{{ $row['left']['satuan_kerja'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Satuan</div>
                                            <div class="field-value">{{ $row['left']['satuan'] }}</div>
                                        </div>

                                        @if($row['left']['jenis'] && $row['left']['jenis'] !== '-')
                                        <div class="field">
                                            <div class="field-label">Jenis</div>
                                            <div class="field-value small">{{ $row['left']['jenis'] }}</div>
                                        </div>
                                        @endif
                                    </div>

                                    <div class="label-qr">
                                        <img src="{{ $row['left']['qr'] }}" alt="QR Code">
                                        <div class="qr-text">{{ $row['left']['qr_text'] }}</div>
                                    </div>
                                </div>

                                <div class="label-footer">
                                    Dicetak: {{ $printDate }}
                                </div>
                            </div>
                        @endif
                    </td>

                    {{-- Right Column: Case Label --}}
                    <td style="width:50%; vertical-align:top; padding-right:4mm; padding-bottom:2mm;">
                        @if($row['right'])
                            <div class="label">
                                <div class="label-header">
                                    <table width="100%" style="border-bottom: 1px solid #333; margin-bottom: 1.5mm; padding-bottom: 1mm;">
                                        <tr>
                                            <td style="width: 15%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-tribrata-polri.png') }}" style="height: 10mm; width: auto;">
                                            </td>
                                            <td style="width: 70%; text-align: center; vertical-align: middle;">
                                                <h1>Barang Bukti</h1>
                                                <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
                                            </td>
                                            <td style="width: 15%; text-align: center; vertical-align: middle;">
                                                <img src="{{ public_path('images/logo-pusdokkes-polri.png') }}" style="height: 10mm; width: auto;">
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="label-body">
                                    <div class="label-content" style="width: 100%;">
                                        <div class="field">
                                            <div class="field-label">Asal Instansi</div>
                                            <div class="field-value clamp2">{{ $row['right']['satuan_kerja'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Nama Tersangka</div>
                                            <div class="field-value">{{ $row['right']['nama_tsk'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Nomor Sampel</div>
                                            <div class="field-value clamp2 small">{{ $row['right']['daftar_kode_sampel'] }}</div>
                                        </div>

                                        <div class="field">
                                            <div class="field-label">Nomor Surat</div>
                                            <div class="field-value">{{ $row['right']['nomor_surat'] }}</div>
                                        </div>
                                    </div>
                                    {{-- Optional: QR Code for Case Label (User didn't specify, but space allows) --}}
                                    {{-- 
                                    <div class="label-qr">
                                         <img src="..." alt="QR Code">
                                    </div> 
                                    --}}
                                </div>

                                <div class="label-footer">
                                    Dicetak: {{ $printDate }}
                                </div>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
        
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>