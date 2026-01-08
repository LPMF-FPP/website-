@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $printedAt = isset($generatedAt) ? Carbon::parse($generatedAt) : now();
    $monthLabel = $month->translatedFormat('F Y');
    $locationName = $location ? $location->name : 'Semua Lokasi';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Log Suhu Lingkungan — {{ $monthLabel }}</title>
<style>
  @page { size: A4; margin: 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.28; margin:0; }

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

  .list-table { font-size:9pt; margin-top: 6px; }
  .list-table th, .list-table td { border:1px solid #000; padding:4px 6px; vertical-align:top; }
  .list-table th { text-align:center; background:#f0f0f0; font-weight:700; }
  .list-table td { text-align:left; }
  .list-table td.center { text-align:center; }
  .list-table tr:nth-child(even) { background:#fafafa; }

  .footer { margin-top:16px; font-size:8.5pt; color:#666; text-align:right; }
  .empty { text-align:center; padding:20px; color:#666; font-style:italic; }
  .out-of-range { color:#c00; font-weight:700; }
  .correction { color:#0066cc; font-style:italic; }
</style>
</head>
<body>

  <div class="header">
    <div class="center">
      <div class="instansi">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</div>
      <div class="lab">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</div>
      <div class="meta">Log Monitoring Lingkungan</div>
    </div>
  </div>

  <h1 class="title">Log Suhu & Kelembaban Bulanan</h1>
  <div class="subtitle">Periode: {{ $monthLabel }}</div>

  <table class="info-table">
    <tr>
      <td class="label">Lokasi:</td>
      <td>{{ $locationName }}</td>
    </tr>
    <tr>
      <td class="label">Dicetak pada:</td>
      <td>{{ $printedAt->translatedFormat('d F Y H:i') }}</td>
    </tr>
  </table>

  @if($readings->isEmpty())
    <div class="empty">Tidak ada data untuk periode ini.</div>
  @else
  <table class="list-table">
    <thead>
      <tr>
        <th style="width:5%;">No</th>
        <th style="width:20%;">Tanggal/Waktu</th>
        @if(!$location)
        <th style="width:18%;">Lokasi</th>
        @endif
        <th style="width:12%;">Suhu (°C)</th>
        <th style="width:12%;">Kelembaban (%)</th>
        <th style="width:18%;">Diinput Oleh</th>
        <th style="width:15%;">Catatan</th>
      </tr>
    </thead>
    <tbody>
      @foreach($readings as $index => $reading)
      <tr @if($reading->correction_of_id) class="correction" @endif>
        <td class="center">{{ $index + 1 }}</td>
        <td>{{ $reading->measured_at->format('d/m/Y H:i') }}</td>
        @if(!$location)
        <td>{{ $reading->location_name ?? $reading->location->name ?? '-' }}</td>
        @endif
        <td class="center">{{ $reading->temperature_c }}</td>
        <td class="center">{{ $reading->humidity_rh ?? '-' }}</td>
        <td>{{ $reading->enteredByUser->name ?? $reading->enteredBy?->name ?? '-' }}</td>
        <td>
          @if($reading->correction_of_id)
            <span class="correction">[Koreksi] </span>
          @endif
          {{ $reading->notes ?? '-' }}
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="footer">
    Total data: {{ $readings->count() }} record(s) &bull; 
    Dicetak: {{ $printedAt->format('d/m/Y H:i') }}
  </div>

</body>
</html>
