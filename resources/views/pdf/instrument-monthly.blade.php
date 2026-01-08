@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $printedAt = isset($generatedAt) ? Carbon::parse($generatedAt) : now();
    $monthLabel = $month->translatedFormat('F Y');
    $assetLabel = $asset ? $asset->asset_code . ' - ' . ($asset->instrument->name ?? 'Unknown') : 'Semua Instrumen';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Log Penggunaan Instrumen — {{ $monthLabel }}</title>
<style>
  @page { size: A4 landscape; margin: 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; line-height: 1.28; margin:0; }

  .header { position: relative; margin:0 0 12px; padding-bottom:8px; border-bottom:1px solid #000; }
  .center { text-align:center; line-height:1.18; }
  .instansi, .lab { font-weight:700; text-transform:uppercase; margin:0; }
  .meta { font-size: 8.8pt; margin:1px 0 0; }

  h1.title { text-align:center; font-size:14pt; margin:12px 0 4px; text-transform:uppercase; }
  .subtitle { text-align:center; font-size:11pt; font-weight:400; margin: 0 0 12px; }

  table { border-collapse: collapse; width:100%; }

  .info-table { margin-bottom:12px; }
  .info-table td { padding:2px 0; }
  .info-table .label { font-weight:700; width:150px; }

  .list-table { font-size:8.5pt; margin-top: 6px; }
  .list-table th, .list-table td { border:1px solid #000; padding:4px 5px; vertical-align:top; }
  .list-table th { text-align:center; background:#f0f0f0; font-weight:700; }
  .list-table td { text-align:left; }
  .list-table td.center { text-align:center; }
  .list-table tr:nth-child(even) { background:#fafafa; }

  .footer { margin-top:16px; font-size:8.5pt; color:#666; text-align:right; }
  .empty { text-align:center; padding:20px; color:#666; font-style:italic; }
</style>
</head>
<body>

  <div class="header">
    <div class="center">
      <div class="instansi">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</div>
      <div class="lab">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</div>
      <div class="meta">Log Penggunaan Instrumen</div>
    </div>
  </div>

  <h1 class="title">Log Penggunaan Instrumen Bulanan</h1>
  <div class="subtitle">Periode: {{ $monthLabel }}</div>

  <table class="info-table">
    <tr>
      <td class="label">Instrumen:</td>
      <td>{{ $assetLabel }}</td>
    </tr>
    @if($asset)
    <tr>
      <td class="label">Serial Number:</td>
      <td>{{ $asset->serial_number ?? '-' }}</td>
    </tr>
    <tr>
      <td class="label">Lokasi:</td>
      <td>{{ $asset->location ?? '-' }}</td>
    </tr>
    @endif
    <tr>
      <td class="label">Dicetak pada:</td>
      <td>{{ $printedAt->translatedFormat('d F Y H:i') }}</td>
    </tr>
  </table>

  @if($logs->isEmpty())
    <div class="empty">Tidak ada data untuk periode ini.</div>
  @else
  <table class="list-table">
    <thead>
      <tr>
        <th style="width:4%;">No</th>
        <th style="width:12%;">Tanggal/Waktu</th>
        @if(!$asset)
        <th style="width:14%;">Instrumen</th>
        <th style="width:10%;">Kode Aset</th>
        @endif
        <th style="width:12%;">No. Permintaan</th>
        <th style="width:10%;">Kode Sampel</th>
        <th style="width:10%;">Metode</th>
        <th style="width:8%;">Tipe</th>
        <th style="width:12%;">Dilakukan Oleh</th>
        <th style="width:10%;">Catatan</th>
      </tr>
    </thead>
    <tbody>
      @foreach($logs as $index => $log)
      <tr>
        <td class="center">{{ $index + 1 }}</td>
        <td>{{ $log->logged_at->format('d/m/Y H:i') }}</td>
        @if(!$asset)
        <td>{{ $log->instrumentAsset->instrument->name ?? '-' }}</td>
        <td>{{ $log->instrumentAsset->asset_code ?? '-' }}</td>
        @endif
        <td>{{ $log->testRequest->receipt_number ?? $log->testRequest->request_number ?? '-' }}</td>
        <td>{{ $log->sample->short_description ?? $log->sample->id ?? '-' }}</td>
        <td class="center">{{ strtoupper(str_replace('_', '-', $log->method_code)) }}</td>
        <td class="center">{{ $log->usage_type?->value ?? '-' }}</td>
        <td>{{ $log->performer->name ?? '-' }}</td>
        <td>{{ $log->notes ?? '-' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="footer">
    Total data: {{ $logs->count() }} record(s) &bull; 
    Dicetak: {{ $printedAt->format('d/m/Y H:i') }}
  </div>

</body>
</html>
