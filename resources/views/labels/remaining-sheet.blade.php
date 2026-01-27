<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label Sisa Sampel</title>
    @php
        $totalLabels = $remainingUnits->count();
        // Determine grid layout based on label count
        if ($totalLabels <= 4) {
            $labelHeight = 42; // mm
            $labelWidth = 95;  // mm
            $fontSize = 8;
        } elseif ($totalLabels <= 6) {
            $labelHeight = 35; // mm
            $labelWidth = 95;  // mm
            $fontSize = 7;
        } elseif ($totalLabels <= 8) {
            $labelHeight = 28; // mm
            $labelWidth = 95;  // mm
            $fontSize = 6;
        }
    @endphp
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm; /* Increased from 5mm for cutting margin */
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: {{ $fontSize }}pt;
            line-height: 1.2;
            color: black;
        }
        .label-container {
            width: 100%;
        }
        .sheet-table {
            width: 100%;
            border-collapse: collapse;
        }
        .label-cell {
            width: 50%;
            vertical-align: top;
            padding: 2mm;
        }
        .label {
            border: 1px solid black;
            padding: 1.5mm;
            height: {{ $labelHeight }}mm;
            width: {{ $labelWidth }}mm;
            position: relative;
            background: white;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid black;
            padding-bottom: 0.4mm;
            margin-bottom: 0.6mm;
            background: white;
            margin: -1.5mm -1.5mm 0.6mm -1.5mm;
            padding: 0.8mm 1mm;
        }
        .label-header h1 {
            font-size: {{ $fontSize }}pt;
            font-weight: bold;
            margin: 0;
        }
        .text-balance {
            text-wrap: balance;
        }
        .text-pretty {
            text-wrap: pretty;
        }
        .header-logo {
            height: {{ min($labelHeight * 0.12, 8) }}mm;
            width: auto;
        }
        .header-table {
            border-collapse: collapse;
            width: 100%;
        }
        .header-logo-cell {
            width: 12%;
            text-align: center;
            vertical-align: middle;
        }
        .header-center-cell {
            width: 76%;
            text-align: center;
            vertical-align: middle;
        }
        .label-body-table {
            width: 100%;
            border-collapse: collapse;
        }
        .label-content-cell {
            width: 68%;
            vertical-align: top;
        }
        .label-qr-cell {
            width: 32%;
            vertical-align: top;
            text-align: center;
            padding-top: 0.5mm;
        }
        .label-header .subtitle {
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            color: dimgray;
            margin-top: 0.3mm;
        }
        .sisa-badge {
            background-color: black;
            color: white;
            padding: 0.3mm 1.5mm;
            border-radius: 1.5mm;
            font-size: {{ max($fontSize - 1, 5) }}pt;
            text-transform: uppercase;
        }
        .field {
            margin-bottom: 0.5mm;
            overflow: hidden;
        }
        .field-label {
            display: inline-block;
            width: 14mm;
            font-size: {{ max($fontSize - 2, 4) }}pt;
            color: dimgray;
            vertical-align: top;
        }
        .field-value {
            display: inline-block;
            width: calc(100% - 15mm);
            font-size: {{ $fontSize }}pt;
            font-weight: bold;
            vertical-align: top;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .field-value.large {
            font-size: {{ $fontSize + 1 }}pt;
        }
        .field-value.qty {
            font-size: {{ $fontSize }}pt;
        }
        .clamp2 {
            max-height: {{ $fontSize * 0.6 }}mm;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .label-footer {
            position: absolute;
            bottom: 0.6mm;
            left: 2mm;
            right: 2mm;
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            color: dimgray;
            border-top: 1px dotted lightgray;
            padding-top: 0.3mm;
            font-variant-numeric: tabular-nums;
        }
        .qr-img {
            display: block;
            margin: 0 auto;
        }
        .size-qr {
            width: {{ min($labelHeight * 0.35, 16) }}mm;
            height: {{ min($labelHeight * 0.35, 16) }}mm;
        }
        .qr-text {
            font-size: {{ max($fontSize - 2.5, 3.5) }}pt;
            margin-top: 0.5mm;
            text-align: center;
            width: {{ min($labelHeight * 0.35, 16) }}mm;
            margin-left: auto;
            margin-right: auto;
            font-variant-numeric: tabular-nums;
        }

        /* =========================
           PRINT OVERRIDES (DomPDF Compatible - Global Scope)
           ========================= */
        @page {
            /* Custom size matches Controller: 16.2cm x 20.5cm */
            size: 16.2cm 20.5cm;
            margin-top: 2mm;
            margin-bottom: 0mm;
            margin-left: 5mm;
            margin-right: 5mm;
        }

        body {
            line-height: 1.1;
        }

        .sheet-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        /* Adjust cell padding to create horizontal gap if needed, 
           but Label 121 usually has labels touching or small gap. 
           We use padding inside label or cell. */
        .label-cell {
            padding: 1mm !important; 
            vertical-align: top;
            /* 2 columns in 15.2cm printable width (16.2 - 1). 
               Each cell ~7.6cm. Label is 7.7cm. Might be tight. 
               Let's trust the table layout. */
        }

        .label {
            /* Fixed dimension for Label 121 */
            width: 77mm !important;
            height: 38mm !important;
            
            padding: 1mm !important;
            border: 0.25mm solid #333 !important;
            page-break-inside: avoid;
            break-inside: avoid;
            background: white;
            overflow: hidden;
        }

            .header-table {
                margin-bottom: 0.8mm !important;
                padding-bottom: 0.4mm !important;
                border-bottom: none !important; /* Removed black line */
            }

            .header-logo {
                height: 7mm !important; /* Increased from 6mm */
                width: auto !important;
            }

            .label-header h1 {
                margin: 0 !important;
                line-height: 1.05 !important;
                letter-spacing: 0.1pt !important;
                font-size: 9pt !important; /* Increased from 8pt */
                margin-bottom: 1mm !important;
            }

            .label-header .subtitle {
                display: none !important;
            }

            .sisa-badge {
                font-size: 7pt !important; /* Increased */
                padding: 0.5mm 2mm !important;
            }

            .field {
                margin-bottom: 0.8mm !important;
            }

            .field-label {
                line-height: 1.1 !important;
                font-size: 6pt !important; /* Increased from 5pt */
            }

            .field-value {
                line-height: 1.1 !important;
                font-size: 8pt !important; /* Increased from 7pt */
            }

            .qr-img {
                width: 15mm !important; /* Increased from 14mm */
                height: 15mm !important;
            }

            .qr-text {
                font-size: 5pt !important;
            }

            .label-footer {
                bottom: 1mm !important;
                padding-top: 0.3mm !important;
                line-height: 1.05 !important;
                font-size: 5pt !important; /* Increased */
                /* Border top remains (dotted) from original style unless overridden. 
                   We are NOT overriding border-top here, so it inherits the original '1px dotted lightgray'. */
            }

        .header-logo {
            height: 5mm !important;
            width: auto !important;
        }

        .label-header h1 {
            margin: 0 !important;
            font-size: 7pt !important;
            line-height: 1 !important;
        }

        .label-header .subtitle {
            margin-top: 0.1mm !important;
            font-size: 4pt !important;
            line-height: 1 !important;
        }

        .sisa-badge {
            font-size: 6pt !important;
            padding: 0.2mm 1mm !important;
        }

        .field {
            margin-bottom: 0.4mm !important;
        }

        .field-label {
            width: 12mm !important;
            font-size: 5pt !important;
            line-height: 1 !important;
        }

        .field-value {
            font-size: 6pt !important;
            line-height: 1 !important;
            width: calc(100% - 13mm) !important;
        }
        
        .field-value.large {
            font-size: 7pt !important;
        }

        .qr-img {
            width: 12mm !important;
            height: 12mm !important;
        }

        .qr-text {
            font-size: 4pt !important;
            margin-top: 0.2mm !important;
            max-height: 3mm !important;
        }

        .label-footer {
            bottom: 0.5mm !important;
            padding-top: 0.2mm !important;
            font-size: 4pt !important;
        }

        .page-break {
            page-break-after: always;
            break-after: page;
        }
    </style>
</head>
<body>
    {{-- ALL LABELS IN ONE PAGE (no page break) --}}
    <table class="sheet-table" cellspacing="0" cellpadding="0" role="presentation">
        @foreach($remainingUnits->chunk(2) as $row)
            <tr>
                @foreach($row as $unit)
                    <td class="label-cell">
                        <div class="label text-pretty">
                            <div class="label-header">
                                <table class="header-table" role="presentation">
                                    <tr>
                                        <td class="header-logo-cell">
                                            <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                        </td>
                                        <td class="header-center-cell">
                                            <h1 class="text-balance"><span class="sisa-badge">SISA</span></h1>
                                            <div class="subtitle text-pretty">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
                                        </td>
                                        <td class="header-logo-cell">
                                            <img src="{{ public_path('images/logo-pusdokkes-polri.png') }}" class="header-logo" alt="Logo Pusdokkes">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <table class="label-body-table" role="presentation">
                                <tr>
                                    <td class="label-content-cell">
                                        <div class="field">
                                            <span class="field-label text-pretty">Resi:</span>
                                            <span class="field-value clamp2 text-pretty">{{ $unit->evidenceUnit->receipt_code ?? '-' }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label text-pretty">Kode:</span>
                                            <span class="field-value large clamp2 text-pretty">{{ $unit->remaining_code }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label text-pretty">Tgl Serah:</span>
                                            <span class="field-value text-pretty">{{ $unit->delivered_at_formatted ?? '-' }}</span>
                                        </div>
                                        
                                        <div class="field">
                                            <span class="field-label text-pretty">Qty Sisa:</span>
                                            <span class="field-value qty text-pretty">{{ $unit->qty_with_uom }}</span>
                                        </div>
                                        
                                        @if($unit->seal_status_delivered)
                                        <div class="field">
                                            <span class="field-label text-pretty">Segel:</span>
                                            <span class="field-value clamp2 text-pretty">{{ $unit->seal_status_delivered }}</span>
                                        </div>
                                        @endif
                                        
                                        @if($unit->handover_doc_no)
                                        <div class="field">
                                            <span class="field-label text-pretty">No. BA:</span>
                                            <span class="field-value clamp2 text-pretty">{{ $unit->handover_doc_no }}</span>
                                        </div>
                                        @endif
                                    </td>
                                    <td class="label-qr-cell">
                                        <img src="{{ $unit->qr_png ?? '' }}" class="qr-img size-qr" alt="QR code for {{ $unit->remaining_code }}">
                                        <div class="qr-text text-pretty">{{ $unit->qr_content }}</div>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="label-footer text-pretty">
                                Dicetak: {{ $printDate }}
                            </div>
                        </div>
                    </td>
                @endforeach
                @if($row->count() === 1)
                    <td style="width:50%; vertical-align:top;"></td>
                @endif
            </tr>
        @endforeach
    </table>
</body>
</html>