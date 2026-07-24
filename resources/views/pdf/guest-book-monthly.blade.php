@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $printedAt = isset($generatedAt) ? Carbon::parse($generatedAt) : now();
    $monthLabel = $month->translatedFormat('F Y');
    $letterheadOrgName = settings('branding.org_name', 'PUSAT KEDOKTERAN DAN KESEHATAN POLRI');
    $letterheadLabName = settings('branding.lab_name', 'LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN');
    $letterheadAddress = settings('branding.address', 'Jl. Cipinang Baru Raya No. 3B, Jakarta Timur 13240');
    $letterheadPhone = settings('branding.phone', '021-4700921');
    $letterheadEmail = settings('branding.email', 'labmutufarmapol@gmail.com');
    $letterheadWebsite = settings('branding.website');
    $letterheadContactParts = [];
    if ($letterheadPhone) { $letterheadContactParts[] = 'Telp: '.$letterheadPhone; }
    if ($letterheadEmail) { $letterheadContactParts[] = 'Email: '.$letterheadEmail; }
    if ($letterheadWebsite) { $letterheadContactParts[] = 'Website: '.$letterheadWebsite; }
    $letterheadContactLine = implode(' • ', $letterheadContactParts);

    $isPreview = $isPreview ?? false;
    $leftLogoPath = public_path('images/logo-tribrata-polri.png');
    $rightLogoPath = public_path('images/logo-pusdokkes-polri.png');
    $leftLogoSrc = $isPreview ? asset('images/logo-tribrata-polri.png') : $leftLogoPath;
    $rightLogoSrc = file_exists($rightLogoPath) ? ($isPreview ? asset('images/logo-pusdokkes-polri.png') : $rightLogoPath) : null;

    $totalVisits = $visits->count();
    $totalActive = $visits->where('status', 'active')->count();
    $totalCheckedOut = $visits->where('status', 'checked_out')->count();
    $purposeCounts = $visits->groupBy('purpose')->map->count()->sortDesc();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Rekap Buku Tamu — {{ $monthLabel }}</title>
<style>
  @page { size: A4; margin: 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.28; margin:0; }

  .header { position: relative; margin:0 0 6px; min-height:52px; padding:0 72px; border-bottom:1px solid #000; padding-bottom:4px; }
  .logo { height:52px; position:absolute; top:0; }
  .logo-left{left:0;} .logo-right{right:0;}
  .center { text-align:center; line-height:1.18; }
  .instansi { font-weight:700; font-size:14pt; text-transform:uppercase; margin:0; }
  .lab { font-weight:700; font-size:12.5pt; text-transform:uppercase; margin:0; }
  .meta { font-size: 8.8pt; margin:1px 0 0; }

  h1.title { text-align:center; font-size:14.5pt; margin:4px 0 4px; text-transform:uppercase; }
  .subtitle { text-align:center; font-size:11pt; font-weight:400; margin: 0 0 10px; }

  table { border-collapse: collapse; width:100%; }

  .summary-table { margin-bottom:10px; }
  .summary-table td { padding:2px 0; }
  .summary-table .label { font-weight:700; width:120px; }

  .list-table { font-size:8.5pt; margin-top: 6px; }
  .list-table th, .list-table td { border:1px solid #000; padding:3px 5px; vertical-align:top; }
  .list-table th { text-align:center; background:#f0f0f0; font-weight:700; font-size:8pt; }
  .list-table td { overflow-wrap:anywhere; word-break:break-word; }
  .list-table td.center { text-align:center; }
  .list-table tr:nth-child(even) { background:#fafafa; }

  .footer { margin-top:16px; font-size:8pt; color:#555; text-align:right; }
  .empty { text-align:center; padding:20px; color:#666; font-style:italic; }

  .chart-label { font-size:8pt; margin:0 0 2px; }
  .chart-bar-bg { background:#eee; height:12px; margin-bottom:4px; position:relative; }
  .chart-bar-fill { background:#1d4ed8; height:12px; position:absolute; left:0; top:0; }
</style>
</head>
<body>

  <div class="header">
    @if(file_exists($leftLogoPath))
      <img class="logo logo-left" src="{{ $leftLogoSrc }}" alt="Logo Polri">
    @endif
    <div class="center">
      <div class="instansi">{{ $letterheadOrgName }}</div>
      <div class="lab">{{ $letterheadLabName }}</div>
      <div class="meta">{{ $letterheadAddress }}{{ $letterheadAddress && $letterheadContactLine ? ' • ' : '' }}{{ $letterheadContactLine }}</div>
    </div>
    @if($rightLogoSrc)
      <img class="logo logo-right" src="{{ $rightLogoSrc }}" alt="Logo Pusdokkes">
    @endif
  </div>

  <h1 class="title">Rekapitulasi Buku Tamu</h1>
  <div class="subtitle">Periode: {{ $monthLabel }}</div>

  <table class="summary-table">
    <tr>
      <td class="label">Periode</td>
      <td>: {{ $monthLabel }}</td>
    </tr>
    <tr>
      <td class="label">Total Kunjungan</td>
      <td>: {{ $totalVisits }} kunjungan</td>
    </tr>
    <tr>
      <td class="label">Masih Aktif</td>
      <td>: {{ $totalActive }} tamu</td>
    </tr>
    <tr>
      <td class="label">Selesai</td>
      <td>: {{ $totalCheckedOut }} tamu</td>
    </tr>
    <tr>
      <td class="label">Dicetak pada</td>
      <td>: {{ $printedAt->translatedFormat('d F Y H:i') }}</td>
    </tr>
  </table>

  @if($purposeCounts->isNotEmpty())
    <p style="font-weight:700; margin:8px 0 4px;">Rincian per Keperluan</p>
    <table class="list-table">
      <thead>
        <tr>
          <th style="width:5%;">No</th>
          <th style="width:45%;">Keperluan</th>
          <th style="width:12%;">Jumlah</th>
          <th>Proporsi</th>
        </tr>
      </thead>
      <tbody>
        @php $maxCount = $purposeCounts->max(); @endphp
        @foreach($purposeCounts as $purpose => $count)
        <tr>
          <td class="center">{{ $loop->iteration }}</td>
          <td>{{ $purpose }}</td>
          <td class="center">{{ $count }}</td>
          <td>
            @php $pct = $maxCount > 0 ? round(($count / $maxCount) * 100) : 0; @endphp
            <div class="chart-label">{{ round(($count / $totalVisits) * 100) }}% ({{ $count }}/{{ $totalVisits }})</div>
            <div class="chart-bar-bg">
              <div class="chart-bar-fill" style="width:{{ $pct }}%;"></div>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  @if($visits->isNotEmpty())
    <p style="font-weight:700; margin:10px 0 4px;">Daftar Kunjungan</p>
    <table class="list-table">
      <thead>
        <tr>
          <th style="width:3%;">No</th>
          <th style="width:11%;">Tanggal</th>
          <th style="width:20%;">Nama Tamu</th>
          <th style="width:15%;">Keperluan</th>
          <th style="width:16%;">Instansi / Pemilik Kasus</th>
          <th style="width:9%;">Status</th>
          <th style="width:13%;">Check-in</th>
          <th style="width:13%;">Check-out</th>
        </tr>
      </thead>
      <tbody>
        @foreach($visits as $i => $visit)
        <tr>
          <td class="center">{{ $i + 1 }}</td>
          <td>{{ $visit->visit_date->format('d/m/Y') }}</td>
          <td>{{ $visit->visitor_name ?? $visit->investigator?->name ?? '—' }}</td>
          <td>
            {{ $visit->purpose }}
            @if($visit->purpose_detail)
              <br><small>({{ $visit->purpose_detail }})</small>
            @endif
          </td>
          <td>
            @if($visit->investigator)
              {{ $visit->investigator->jurisdiction ?? $visit->investigator->institution ?? '—' }}
            @else
              {{ $visit->visitor_institution ?? '—' }}
            @endif
          </td>
          <td class="center">{{ $visit->isActive() ? 'Aktif' : 'Keluar' }}</td>
          <td>{{ \Carbon\Carbon::parse($visit->visit_date)->format('d/m') }} {{ substr($visit->visit_time, 0, 5) }}</td>
          <td>{{ $visit->check_out_at?->format('d/m H:i') ?? '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <div class="empty">Tidak ada data kunjungan untuk periode ini.</div>
  @endif

  <div class="footer">
    Dokumen ini dicetak otomatis oleh sistem LPMF LIMS.<br>
    Dicetak pada: {{ $printedAt->translatedFormat('d F Y, H:i') }} WIB
  </div>

</body>
</html>
