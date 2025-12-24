@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $proc = $process;
    $samp = $proc->sample ?? null;
    $req  = $samp?->testRequest;
    $inv  = $req?->investigator;

    $today = isset($generatedAt) ? Carbon::parse($generatedAt) : now();

    // Nomor LHU (prioritas: dari controller, lalu dari metadata, lalu fallback)
    $meta = $proc->metadata ?? [];
    $noLHU = $noLHU
          ?? ($meta['report_number'] ?? $meta['lab_report_no'] ?? $meta['lhu_number'] ?? $meta['report_no'] ?? '—');

  // Metode/Instrumen & Hasil uji: gunakan metadata (utama + multi_interpretations)
  $methodMap = [
    'gc_ms'  => 'GC-MS (Gas Chromatography–Mass Spectrometry)',
    'uv_vis' => 'UV-VIS (Ultraviolet–Visible Spectrophotometry)',
    'lc_ms'  => 'LC-MS (Liquid Chromatography–Mass Spectrometry)',
  ];
  $methodKey = $proc->method ?? $proc->test_method ?? null;
  $fallbackMethodLbl = $methodKey ? ($methodMap[$methodKey] ?? $methodKey) : null;

  $rows = [];
  $mainInstr = $meta['instrument'] ?? $meta['instrument_pengujian'] ?? $fallbackMethodLbl;
  $mainRes   = $meta['test_result'] ?? null; // 'positive' | 'negative' | null
  $mainDet   = $meta['detected_substance'] ?? $meta['detection'] ?? $meta['hasil'] ?? ($forcedActiveSubstance ?? ($samp->active_substance ?? '—'));
  $sign      = $mainRes === 'positive' ? '(+)' : ($mainRes === 'negative' ? '(-)' : '');
  $rows[]    = [
    'instrument' => $mainInstr ?? '—',
    'resultText' => trim(($sign ? $sign.' ' : '').$mainDet),
  ];

  if (!empty($meta['multi_interpretations']) && is_array($meta['multi_interpretations'])) {
    foreach ($meta['multi_interpretations'] as $mi) {
      if (!is_array($mi)) continue;
      $instr = $mi['instrument'] ?? $fallbackMethodLbl ?? '—';
      $res   = $mi['test_result'] ?? null;
      $det   = $mi['detected_substance'] ?? ($forcedActiveSubstance ?? ($samp->active_substance ?? '—'));
      $sgn   = $res === 'positive' ? '(+)' : ($res === 'negative' ? '(-)' : '');
      $rows[] = [ 'instrument' => $instr, 'resultText' => trim(($sgn ? $sgn.' ' : '').$det) ];
    }
  }

    // Tanggal terima
    $tglTerima = $req?->received_at
        ? Carbon::parse($req->received_at)->translatedFormat('d F Y')
        : ($req?->created_at?->translatedFormat('d F Y') ?? '—');

    // Batch & Exp. Date
    $batchNo = $samp?->batch_no ?? $samp?->batch_number ?? $samp?->batch ?? '—';
    $expRaw  = $samp?->exp_date ?? $samp?->expiry_date ?? $samp?->expiration_date ?? null;
    $expDate = '—';
    if ($expRaw) {
        try { $expDate = Carbon::parse($expRaw)->translatedFormat('d F Y'); }
        catch (\Throwable $e) { $expDate = $expRaw; }
    }

    // KAFARMAPOL (configurable)
    $cfg = config('lab', []);
    $headTitle   = $cfg['head_title'] ?? 'KAFARMAPOL';
    $headName    = $cfg['head_name']  ?? 'KUSWARDANI, S.Si., Apt., M.Farm';
    $headRankNrp = ($cfg['head_rank'] ?? 'KOMBES POL.').' NRP. '.($cfg['head_nrp'] ?? '70040687');
    $signRel     = $cfg['head_signature'] ?? 'images/ttd-kafarmapol.png'; // public/
    $signPath    = public_path($signRel);
    $hasSign     = file_exists($signPath);

    // Pre-encode all images to base64 once at the top to avoid multiple file reads
    $leftLogoPath = public_path('images/logo-tribrata-polri.png');
    $leftLogoBase64 = file_exists($leftLogoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($leftLogoPath)) : '';
    
    $rightLogoPath = public_path('images/logo-pusdokkes-polri.png');
    $rightLogoBase64 = file_exists($rightLogoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($rightLogoPath)) : '';
    
    $signBase64 = '';
    if ($hasSign) {
        $signBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($signPath));
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Hasil Uji — {{ $noLHU }}</title>
<style>
  /* 1 halaman A4 */
  @page { size: A4; margin: 10mm 10mm 11mm 10mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 10pt; color:#000; line-height:1.32; }

  table { border-collapse: collapse; width:100%; }
  .hr { border-top:1px solid #222; margin:6px 0 8px; height:1px; }
  .muted { color:#444; }
  .avoid { page-break-inside: avoid; }

  /* Header */
  .hdr td { vertical-align:middle; }
  .hdr .c { text-align:center; line-height:1.15; }
  .instansi { font-weight:700; font-size:12.5pt; text-transform:uppercase; }
  .lab      { font-weight:700; font-size:11pt;  text-transform:uppercase; }
  .addr     { font-size:8.8pt; }

  .title-row td { padding-top:4px; }
  .ttl { font-weight:700; font-size:12.5pt; }
  .meta-ttl { text-align:right; font-size:10pt; }

  /* KV */
  .kv { margin-top:5px; }
  .kv th, .kv td { border:1px solid #000; padding:4px 5px; }
  .kv th { background:#f1f1f1; text-align:left; width:36%; }
  .kv td { width:64%; }

  /* Hasil */
  .res { margin-top:7px; }
  .res th, .res td { border:1px solid #000; padding:5px 6px; }
  .res th { background:#f1f1f1; text-align:center; }
  .res .c1 { width:34%; }
  .res .c2 { width:26%; text-align:center; }
  .res .c3 { width:40%; }

  /* Paraf & TTD */
  .signrow td { vertical-align:top; }
  .lcol { width:55%; padding-right:8px; }
  .rcol { width:45%; text-align:center; padding-left:8px; }

  .paraf th, .paraf td { border:1px solid #000; padding:10px 6px; }
  .paraf th { background:#f1f1f1; text-align:left; }
  .boxh { height:58px; } /* tinggi area paraf */

  .headtitle { font-weight:700; margin-bottom:52px; } /* ruang ttd */
  .headname  { text-decoration:underline; font-weight:700; }
  .small { font-size:9pt; color:#333; }
</style>
</head>
<body>

  <!-- HEADER -->
  <table class="hdr avoid">
    <tr>
      <td style="width:78px">
        @if($leftLogoBase64)
          <img src="{{ $leftLogoBase64 }}" style="height:54px">
        @endif
      </td>
      <td class="c">
        <div class="instansi">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</div>
        <div class="lab">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</div>
        <div class="addr">Jl. Cipinang Baru Raya No. 3B, Jakarta Timur 13240 • Telp/Fax: 021-4700921 • Email: labmutufarmapol@gmail.com</div>
      </td>
      <td style="width:78px; text-align:right">
        @if($rightLogoBase64)
          <img src="{{ $rightLogoBase64 }}" style="height:54px">
        @endif
      </td>
    </tr>
  </table>
  <div class="hr"></div>

  <!-- TITLE + META -->
  <table class="title-row avoid">
    <tr>
      <td class="ttl">LAPORAN HASIL UJI</td>
      <td class="meta-ttl">
        Nomor: <b>{{ $noLHU }}</b><br>
        Halaman: <b>1/1</b>
      </td>
    </tr>
  </table>

  <!-- INFORMASI -->
  <div class="avoid" style="margin-top:5px; font-weight:700">Informasi Pelanggan & Sampel</div>
  <table class="kv avoid">
    <tr><th>Nama Pelanggan</th><td>{{ trim(($inv?->rank).' '.($inv?->name)) }}</td></tr>
    <tr><th>Alamat Pelanggan</th><td>{{ $inv?->jurisdiction }}</td></tr>
    <tr><th>Nama Sampel</th><td>{{ $samp?->sample_name }}</td></tr>
    <tr><th>Jumlah Sampel</th><td>{{ ($samp?->package_quantity ?? $samp?->quantity ?? 1) }} {{ $samp?->packaging_type ?? 'Unit' }}</td></tr>
    <tr><th>No Batch</th><td>{{ $batchNo }}</td></tr>
    <tr><th>Exp. Date</th><td>{{ $expDate }}</td></tr>
    <tr><th>Tanggal Penerimaan Sampel</th><td>{{ $tglTerima }}</td></tr>
    <tr><th>Kode Sampel</th><td>{{ $samp?->sample_code ?? '—' }}</td></tr>
  </table>

  <!-- HASIL -->
  <div class="avoid" style="margin-top:8px; font-weight:700">Hasil Pengujian</div>
  <table class="res avoid">
    <thead>
      <tr>
        <th class="c1">Parameter Uji</th>
        <th class="c2">Hasil</th>
        <th class="c3">Metode Uji</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td>Identifikasi</td>
          <td>{{ $r['resultText'] }}</td>
          <td>{{ $r['instrument'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="avoid" style="margin-top:6px">
    <div class="muted small"><em>Referensi: Farmakope Indonesia Suplemen I Edisi VI Tahun 2022</em></div>
    <div class="small">Hasil uji hanya berlaku untuk sampel yang diterima oleh laboratorium.</div>
  </div>

  <!-- KIRI: TTD KAFARMAPOL | KANAN: PARAF VERIFIKATOR -->
  <table class="avoid" style="margin-top:6px;">
    <tr class="signrow">
      <!-- LEFT: KAFARMAPOL -->
      <td class="lcol" style="text-align:center;">
        <div class="headtitle">{{ $headTitle }}</div>
        <div style="height:60px; margin: 2px 0;">
          @if($signBase64)
            <img src="{{ $signBase64 }}" style="height:58px">
          @endif
        </div>
        <div class="headname">{{ $headName }}</div>
        <div class="small">{{ $headRankNrp }}</div>
        <div class="small" style="margin-top:6px;">Jakarta, {{ $today->translatedFormat('d F Y') }}</div>
      </td>

      <!-- RIGHT: Paraf verifikator -->
      <td class="rcol">
        <table class="paraf" style="width:100%;">
          <tr><th colspan="3">Paraf verifikator</th></tr>
          <tr>
            <td>1. Teknis<div class="boxh"></div></td>
            <td>2. Mutu<div class="boxh"></div></td>
            <td>3. Administrasi<div class="boxh"></div></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

</body>
</html>
