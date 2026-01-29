<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label Sisa Sampel</title>
    @php
        $labelWidth = 75; // mm
        $labelHeight = 38; // mm
        $fontSize = 7; // pt
    @endphp
    <style>
        @page {
            size: 165mm 210mm;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: {{ $fontSize }}pt;
            line-height: 1.15;
            color: #000;
        }
        .page-break {
            page-break-after: always;
            break-after: page;
        }
        .sheet {
            padding-top: 5mm;
            padding-left: 7mm;
        }
        .grid-table {
            border-collapse: collapse;
            table-layout: fixed;
        }
        .cell {
            width: {{ $labelWidth }}mm;
            height: {{ $labelHeight }}mm;
            vertical-align: top;
            padding: 0;
        }
        .gap-x {
            width: 2.5mm;
        }
        .gap-y td {
            height: 2mm;
        }
        .label {
            width: {{ $labelWidth }}mm;
            height: {{ $labelHeight }}mm;
            border: 0.25mm solid #333;
            padding: 1mm;
            position: relative;
            background: #fff;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .label-header {
            text-align: center;
            border-bottom: 1px solid #333;
            margin-bottom: 0.6mm;
            padding-bottom: 0.3mm;
        }
        .label-header h1 {
            font-size: 8pt;
            font-weight: bold;
            margin: 0;
            line-height: 1;
        }
        .header-logo {
            height: 5mm;
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
        .label-header .subtitle {
            font-size: 5pt;
            color: #555;
            margin-top: 0.2mm;
            line-height: 1;
        }
        .sisa-badge {
            background-color: #000;
            color: #fff;
            padding: 0.3mm 1.2mm;
            border-radius: 1.5mm;
            font-size: 6pt;
            text-transform: uppercase;
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
            padding-top: 0.2mm;
        }
        .field {
            margin-bottom: 0.4mm;
            overflow: hidden;
        }
        .field-label {
            display: inline-block;
            width: 12mm;
            font-size: 6pt;
            color: #666;
            vertical-align: top;
        }
        .field-value {
            display: inline-block;
            width: calc(100% - 13mm);
            font-size: 7pt;
            font-weight: bold;
            vertical-align: top;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .field-value.large {
            font-size: 8pt;
        }
        .field-value.qty {
            font-size: 7pt;
        }
        .clamp2 {
            max-height: 8mm;
            overflow: hidden;
        }
        .label-footer {
            position: absolute;
            bottom: 0.6mm;
            left: 1.5mm;
            right: 1.5mm;
            font-size: 5pt;
            color: #666;
            border-top: 1px dotted #ccc;
            padding-top: 0.2mm;
            font-variant-numeric: tabular-nums;
        }
        .qr-img {
            display: block;
            margin: 0 auto;
        }
        .size-qr {
            width: 14mm;
            height: 14mm;
        }
        .qr-text {
            font-size: 5pt;
            margin-top: 0.3mm;
            text-align: center;
            width: 14mm;
            margin-left: auto;
            margin-right: auto;
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body>
    {{-- LABEL SHEET 2x5 --}}
    @php
        $pages = $remainingUnits->chunk(10);
        if ($pages->isEmpty()) {
            $pages = collect([collect()]);
        }
    @endphp

    @foreach($pages as $page)
        <div class="sheet">
            <table class="grid-table" cellspacing="0" cellpadding="0" role="presentation">
                @for($r = 0; $r < 5; $r++)
                    @php
                        $left = $page->get($r * 2);
                        $right = $page->get($r * 2 + 1);
                    @endphp
                    <tr>
                        <td class="cell">
                            @if($left)
                                <div class="label">
                                    <div class="label-header">
                                        <table class="header-table" role="presentation">
                                            <tr>
                                                <td class="header-logo-cell">
                                                    <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                                </td>
                                                <td class="header-center-cell">
                                                    <h1><span class="sisa-badge">SISA</span></h1>
                                                    <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
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
                                                    <span class="field-label">Resi:</span>
                                                    <span class="field-value clamp2">{{ $left->evidenceUnit->receipt_code ?? '-' }}</span>
                                                </div>

                                                <div class="field">
                                                    <span class="field-label">Kode:</span>
                                                    <span class="field-value large clamp2">{{ $left->remaining_code }}</span>
                                                </div>

                                                <div class="field">
                                                    <span class="field-label">Tgl Serah:</span>
                                                    <span class="field-value">{{ $left->delivered_at_formatted ?? '-' }}</span>
                                                </div>

                                                <div class="field">
                                                    <span class="field-label">Qty Sisa:</span>
                                                    <span class="field-value qty">{{ $left->qty_with_uom }}</span>
                                                </div>

                                                @if($left->seal_status_delivered)
                                                <div class="field">
                                                    <span class="field-label">Segel:</span>
                                                    <span class="field-value clamp2">{{ $left->seal_status_delivered }}</span>
                                                </div>
                                                @endif

                                                @if($left->handover_doc_no)
                                                <div class="field">
                                                    <span class="field-label">No. BA:</span>
                                                    <span class="field-value clamp2">{{ $left->handover_doc_no }}</span>
                                                </div>
                                                @endif
                                            </td>
                                            <td class="label-qr-cell">
                                                <img src="{{ $left->qr_png ?? '' }}" class="qr-img size-qr" alt="QR code for {{ $left->remaining_code }}">
                                                <div class="qr-text">{{ $left->qr_content }}</div>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="label-footer">
                                        Dicetak: {{ $printDate }}
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td class="gap-x"></td>
                        <td class="cell">
                            @if($right)
                                <div class="label">
                                    <div class="label-header">
                                        <table class="header-table" role="presentation">
                                            <tr>
                                                <td class="header-logo-cell">
                                                    <img src="{{ public_path('images/logo-tribrata-polri.png') }}" class="header-logo" alt="Logo Polri">
                                                </td>
                                                <td class="header-center-cell">
                                                    <h1><span class="sisa-badge">SISA</span></h1>
                                                    <div class="subtitle">LPMF - Laboratorium Farmapol Pusdokkes Polri</div>
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
                                                    <span class="field-label">Resi:</span>
                                                    <span class="field-value clamp2">{{ $right->evidenceUnit->receipt_code ?? '-' }}</span>
                                                </div>

                                                <div class="field">
                                                    <span class="field-label">Kode:</span>
                                                    <span class="field-value large clamp2">{{ $right->remaining_code }}</span>
                                                </div>

                                                <div class="field">
                                                    <span class="field-label">Tgl Serah:</span>
                                                    <span class="field-value">{{ $right->delivered_at_formatted ?? '-' }}</span>
                                                </div>

                                                <div class="field">
                                                    <span class="field-label">Qty Sisa:</span>
                                                    <span class="field-value qty">{{ $right->qty_with_uom }}</span>
                                                </div>

                                                @if($right->seal_status_delivered)
                                                <div class="field">
                                                    <span class="field-label">Segel:</span>
                                                    <span class="field-value clamp2">{{ $right->seal_status_delivered }}</span>
                                                </div>
                                                @endif

                                                @if($right->handover_doc_no)
                                                <div class="field">
                                                    <span class="field-label">No. BA:</span>
                                                    <span class="field-value clamp2">{{ $right->handover_doc_no }}</span>
                                                </div>
                                                @endif
                                            </td>
                                            <td class="label-qr-cell">
                                                <img src="{{ $right->qr_png ?? '' }}" class="qr-img size-qr" alt="QR code for {{ $right->remaining_code }}">
                                                <div class="qr-text">{{ $right->qr_content }}</div>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="label-footer">
                                        Dicetak: {{ $printDate }}
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @if($r < 4)
                        <tr class="gap-y"><td colspan="3"></td></tr>
                    @endif
                @endfor
            </table>
        </div>

        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
