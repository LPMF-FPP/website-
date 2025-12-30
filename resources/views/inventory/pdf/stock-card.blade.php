@php
    use Carbon\Carbon;
    Carbon::setLocale('id');
    $now = $generatedAt ?? now();
    $openingBalance = !empty($stockCard) ? ($stockCard[0]['running_balance'] - $stockCard[0]['change']) : 0;
    $closingBalance = !empty($stockCard) ? end($stockCard)['running_balance'] : 0;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Kartu Stok — {{ $item->name }}</title>
<style>
    /* DOMPDF-safe styles - Optimized for single page */
    @page { 
        size: A4 landscape; 
        margin: 8mm 10mm; 
    }
    body { 
        font-family: DejaVu Sans, Arial, Helvetica, sans-serif; 
        font-size: 7pt; 
        color: #000; 
        line-height: 1.2; 
    }
    
    /* Compact Header */
    .header { 
        width: 100%; 
        margin-bottom: 6px;
        border-bottom: 1.5px solid #1e40af;
        padding-bottom: 4px;
    }
    .header-title { 
        font-size: 12pt; 
        font-weight: bold; 
        color: #1e40af;
        margin: 0;
        display: inline;
    }
    .header-subtitle {
        font-size: 8pt;
        color: #4b5563;
        margin: 0;
        display: inline;
        margin-left: 10px;
    }
    
    /* Compact Item Info Table */
    .info-table {
        width: 100%;
        margin-bottom: 6px;
        border-collapse: collapse;
    }
    .info-table td {
        padding: 2px 4px;
        vertical-align: top;
        font-size: 7pt;
    }
    .info-label {
        font-weight: bold;
        width: 70px;
        color: #374151;
    }
    .info-value {
        color: #000;
    }
    
    /* Compact Balance Summary */
    .balance-summary {
        width: 100%;
        margin-bottom: 6px;
        border-collapse: collapse;
    }
    .balance-summary td {
        width: 25%;
        text-align: center;
        padding: 4px;
        border: 1px solid #d1d5db;
        background-color: #f9fafb;
    }
    .balance-label {
        font-size: 6pt;
        color: #6b7280;
        margin-bottom: 2px;
    }
    .balance-value {
        font-size: 10pt;
        font-weight: bold;
    }
    .balance-opening { color: #2563eb; }
    .balance-in { color: #16a34a; }
    .balance-out { color: #dc2626; }
    .balance-closing { color: #7c3aed; }
    
    /* Compact Main Table */
    .stock-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 6.5pt;
    }
    .stock-table th {
        background-color: #1e40af;
        color: #fff;
        font-weight: bold;
        text-align: left;
        padding: 3px 2px;
        border: 1px solid #1e3a8a;
        font-size: 6pt;
    }
    .stock-table th.text-right {
        text-align: right;
    }
    .stock-table td {
        padding: 2px 2px;
        border: 1px solid #d1d5db;
        vertical-align: middle;
    }
    .stock-table tr:nth-child(even) {
        background-color: #f9fafb;
    }
    .stock-table .text-right {
        text-align: right;
    }
    .stock-table .text-center {
        text-align: center;
    }
    .stock-table .font-mono {
        font-family: DejaVu Sans Mono, monospace;
        font-size: 6pt;
    }
    
    /* Compact Movement type badges */
    .badge {
        display: inline-block;
        padding: 1px 3px;
        font-size: 5.5pt;
        font-weight: bold;
        border-radius: 2px;
    }
    .badge-receipt { background-color: #dcfce7; color: #166534; }
    .badge-issue { background-color: #fee2e2; color: #991b1b; }
    .badge-transfer { background-color: #dbeafe; color: #1e40af; }
    .badge-adjust { background-color: #fef3c7; color: #92400e; }
    .badge-dispose { background-color: #e5e7eb; color: #374151; }
    .badge-return { background-color: #f3e8ff; color: #6b21a8; }
    
    /* Colors */
    .text-green { color: #16a34a; }
    .text-red { color: #dc2626; }
    .text-gray { color: #6b7280; }
    .font-bold { font-weight: bold; }
    
    /* Compact Footer */
    .footer {
        margin-top: 6px;
        font-size: 6pt;
        color: #6b7280;
        border-top: 1px solid #d1d5db;
        padding-top: 3px;
    }
    .footer-left { float: left; }
    .footer-right { float: right; }
</style>
</head>
<body>

    <!-- Compact Header -->
    <div class="header">
        <span class="header-title">KARTU STOK</span>
        <span class="header-subtitle">Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri</span>
    </div>
    
    <!-- Compact Item Information (2 columns inline) -->
    <table class="info-table">
        <tr>
            <td class="info-label">Item:</td>
            <td class="info-value"><strong>{{ $item->name }}</strong> ({{ $item->item_type_label ?? $item->item_type }})</td>
            <td class="info-label">Lot:</td>
            <td class="info-value">{{ $lot->lot_no ?? 'Semua' }}</td>
            <td class="info-label">Lokasi:</td>
            <td class="info-value">{{ $location->name ?? 'Semua' }}</td>
        </tr>
        <tr>
            <td class="info-label">Merek:</td>
            <td class="info-value">{{ $item->brand ?? '-' }} · {{ $item->uom }}</td>
            <td class="info-label">Periode:</td>
            <td class="info-value">
                @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                    {{ $filters['date_from'] ?? '...' }} s/d {{ $filters['date_to'] ?? '...' }}
                @else
                    Semua
                @endif
            </td>
            <td class="info-label">Cetak:</td>
            <td class="info-value">{{ $now->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
    
    <!-- Balance Summary -->
    @php
        $totalIn = 0;
        $totalOut = 0;
        foreach ($stockCard as $row) {
            if ($row['change'] > 0) {
                $totalIn += $row['change'];
            } else {
                $totalOut += abs($row['change']);
            }
        }
    @endphp
    <table class="balance-summary">
        <tr>
            <td>
                <div class="balance-label">SALDO AWAL</div>
                <div class="balance-value balance-opening">{{ number_format($openingBalance, 2) }}</div>
            </td>
            <td>
                <div class="balance-label">TOTAL MASUK</div>
                <div class="balance-value text-green">+ {{ number_format($totalIn, 2) }}</div>
            </td>
            <td>
                <div class="balance-label">TOTAL KELUAR</div>
                <div class="balance-value text-red">- {{ number_format($totalOut, 2) }}</div>
            </td>
            <td>
                <div class="balance-label">SALDO AKHIR</div>
                <div class="balance-value balance-closing">{{ number_format($closingBalance, 2) }}</div>
            </td>
        </tr>
    </table>
    
    <!-- Stock Card Table -->
    @if(!empty($stockCard))
    <table class="stock-table">
        <thead>
            <tr>
                <th style="width: 18px;">No</th>
                <th style="width: 70px;">Tanggal</th>
                <th style="width: 50px;">Tipe</th>
                <th style="width: 65px;">No. Lot</th>
                <th style="width: 75px;">Dari</th>
                <th style="width: 75px;">Ke</th>
                <th style="width: 45px;" class="text-right">Masuk</th>
                <th style="width: 45px;" class="text-right">Keluar</th>
                <th style="width: 50px;" class="text-right">Saldo</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockCard as $index => $row)
                @php
                    $movement = $row['movement'];
                    $change = $row['change'];
                    $balance = $row['running_balance'];
                    
                    $badgeClass = match($movement->movement_type) {
                        'RECEIPT' => 'badge-receipt',
                        'ISSUE' => 'badge-issue',
                        'TRANSFER' => 'badge-transfer',
                        'ADJUST' => 'badge-adjust',
                        'DISPOSE' => 'badge-dispose',
                        'RETURN' => 'badge-return',
                        default => ''
                    };
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $movement->performed_at->format('d/m/y H:i') }}</td>
                    <td>
                        <span class="badge {{ $badgeClass }}">
                            {{ $movement->movement_type }}
                        </span>
                    </td>
                    <td>{{ Str::limit($movement->lot?->lot_no ?? '-', 12) }}</td>
                    <td class="text-gray">{{ Str::limit($movement->fromLocation?->name ?? '-', 12) }}</td>
                    <td class="text-gray">{{ Str::limit($movement->toLocation?->name ?? '-', 12) }}</td>
                    <td class="text-right font-mono {{ $change > 0 ? 'text-green font-bold' : '' }}">
                        {{ $change > 0 ? number_format($change, 2) : '' }}
                    </td>
                    <td class="text-right font-mono {{ $change < 0 ? 'text-red font-bold' : '' }}">
                        {{ $change < 0 ? number_format(abs($change), 2) : '' }}
                    </td>
                    <td class="text-right font-mono font-bold">
                        {{ number_format($balance, 2) }}
                    </td>
                    <td class="text-gray">{{ Str::limit($movement->notes ?? $movement->reason_code ?? '', 25) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div style="text-align: center; padding: 20px; color: #6b7280;">
        <p style="font-size: 10pt;">Tidak ada data mutasi untuk filter yang dipilih.</p>
    </div>
    @endif
    
    <!-- Footer -->
    <div class="footer">
        <span class="footer-left">{{ $item->name }} | {{ $lot->lot_no ?? 'Semua Lot' }}</span>
        <span class="footer-right">{{ $generatedBy->name ?? 'System' }} | {{ $now->format('d/m/Y H:i') }}</span>
    </div>

</body>
</html>