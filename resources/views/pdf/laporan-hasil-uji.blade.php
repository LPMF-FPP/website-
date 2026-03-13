@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $proc = $process;
    $samp = $proc->sample ?? null;
    $req  = $samp?->testRequest;
    $inv  = $req?->investigator;

    $today = isset($generatedAt) ? Carbon::parse($generatedAt) : now();

    // Nomor LHU
    $metaRaw = $proc->metadata ?? [];
    if (is_string($metaRaw)) {
        $decoded = json_decode($metaRaw, true);
        $meta = is_array($decoded) ? $decoded : [];
    } elseif (is_array($metaRaw)) {
        $meta = $metaRaw;
    } elseif (is_object($metaRaw) && method_exists($metaRaw, 'toArray')) {
        $meta = $metaRaw->toArray();
    } elseif (is_object($metaRaw)) {
        $meta = (array) $metaRaw;
    } else {
        $meta = [];
    }
    $noLHU = $noLHU
          ?? ($meta['report_number'] ?? $meta['lab_report_no'] ?? $meta['lhu_number'] ?? $meta['report_no'] ?? '—');

    // Nomor Resi
    $receiptNumber = $req?->receipt_number
        ?? ($meta['receipt_number'] ?? $meta['receipt_no'] ?? $meta['resi'] ?? null)
        ?? $req?->request_number
        ?? '—';
    if ($receiptNumber === '') {
        $receiptNumber = '—';
    }

    // Audit dokumen
    $docGeneratedAt = now();
    if (isset($generatedAt)) {
        try { $docGeneratedAt = Carbon::parse($generatedAt); }
        catch (\Throwable $e) { $docGeneratedAt = now(); }
    }

    $procMetaRaw = $proc?->metadata ?? [];
    if (is_string($procMetaRaw)) {
        $decoded = json_decode($procMetaRaw, true);
        $procMeta = is_array($decoded) ? $decoded : [];
    } elseif ($procMetaRaw instanceof \Illuminate\Support\Collection) {
        $procMeta = $procMetaRaw->toArray();
    } elseif (is_array($procMetaRaw)) {
        $procMeta = $procMetaRaw;
    } elseif (is_object($procMetaRaw) && method_exists($procMetaRaw, 'toArray')) {
        $procMeta = $procMetaRaw->toArray();
    } elseif (is_object($procMetaRaw)) {
        $procMeta = (array) $procMetaRaw;
    } else {
        $procMeta = [];
    }

    $normalizeString = function ($value) {
        if (is_string($value)) {
            $value = trim($value);
            return $value !== '' ? $value : null;
        }
        if (is_scalar($value)) {
            $value = trim((string) $value);
            return $value !== '' ? $value : null;
        }
        return null;
    };

    $docGeneratedBy = $normalizeString($generatedBy ?? null);
    if (!$docGeneratedBy) {
        try {
            $user = auth()->user();
            $docGeneratedBy = $normalizeString($user?->name ?? $user?->username ?? $user?->email ?? null);
        } catch (\Throwable $e) {
            $docGeneratedBy = null;
        }
    }
    if (!$docGeneratedBy) {
        $docGeneratedBy = $normalizeString(
            $procMeta['generated_by']
            ?? $procMeta['printed_by']
            ?? $procMeta['created_by']
            ?? $procMeta['user_name']
            ?? null
        );
    }
    $docGeneratedBy = $docGeneratedBy ?: 'System';

    $docGeneratedUnit = $normalizeString($generatedUnit ?? null);
    if (!$docGeneratedUnit) {
        try {
            $user = $user ?? auth()->user();
            $docGeneratedUnit = $normalizeString($user?->unit ?? $user?->department ?? null);
            if (!$docGeneratedUnit) {
                $docGeneratedUnit = $normalizeString($user?->role?->name ?? null);
            }
        } catch (\Throwable $e) {
            $docGeneratedUnit = null;
        }
    }
    if (!$docGeneratedUnit) {
        $docGeneratedUnit = $normalizeString(
            $procMeta['generated_by_unit']
            ?? $procMeta['generated_unit']
            ?? $procMeta['printed_unit']
            ?? null
        );
    }

  // Metode/Instrumen & Hasil uji
  $methodMap = [
    'gc_ms'  => 'GC-MS (Gas Chromatography–Mass Spectrometry)',
    'uv_vis' => 'UV-VIS (Ultraviolet–Visible Spectrophotometry)',
    'lc_ms'  => 'LC-MS (Liquid Chromatography–Mass Spectrometry)',
  ];
  $normalizeMethods = function ($rawMethods) {
    if (is_string($rawMethods)) {
      $decoded = json_decode($rawMethods, true);
      $rawMethods = is_array($decoded) ? $decoded : [$rawMethods];
    }

    if (!is_array($rawMethods)) {
      return [];
    }

    return array_values(array_filter(array_map(function ($method) {
      if (is_string($method)) {
        $method = trim($method);
        return $method !== '' ? $method : null;
      }

      return is_scalar($method) ? trim((string) $method) : null;
    }, $rawMethods)));
  };
  $formatMethodLabel = function ($method) use ($methodMap) {
    if ($method === null || $method === '') {
      return null;
    }

    return $methodMap[$method] ?? \Illuminate\Support\Str::of((string) $method)->replace('_', ' ')->title()->toString();
  };
  $methodKey = $proc->method ?? $proc->test_method ?? $meta['test_method'] ?? $meta['method'] ?? null;
  $sampleMethodLabels = collect($normalizeMethods($samp?->test_methods ?? []))
    ->map(fn ($method) => $formatMethodLabel($method))
    ->filter()
    ->unique()
    ->values();
  $fallbackMethodLbl = collect([$formatMethodLabel($methodKey)])
    ->filter()
    ->merge($sampleMethodLabels)
    ->unique()
    ->values()
    ->join(', ');
  $fallbackMethodLbl = $fallbackMethodLbl !== '' ? $fallbackMethodLbl : null;

  $rows = [];
  $mainInstr = $meta['instrument'] ?? $meta['instrument_pengujian'] ?? $fallbackMethodLbl;
  $mainRes   = $meta['test_result'] ?? null;
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

    // Batch & Exp. Date (Exp hanya Bulan Tahun)
    $batchNo = $samp?->batch_no ?? $samp?->batch_number ?? $samp?->batch ?? '—';
    $expRaw  = $samp?->exp_date ?? $samp?->expiry_date ?? $samp?->expiration_date ?? null;
    $expDate = '—';
    if ($expRaw) {
        try { 
            $expDate = Carbon::parse($expRaw)->translatedFormat('F Y'); 
        }
        catch (\Throwable $e) { $expDate = $expRaw; }
    }

    // KAFARMAPOL
    $cfg = config('lab', []);
    $headTitle   = $cfg['head_title'] ?? 'KAFARMAPOL';
    $headName    = $cfg['head_name']  ?? 'KUSWARDANI, S.Si., Apt., M.Farm';
    $headRankNrp = ($cfg['head_rank'] ?? 'KOMBES POL.').' NRP. '.($cfg['head_nrp'] ?? '70040687');
    $signRel     = $cfg['head_signature'] ?? 'images/ttd-kafarmapol.png'; 
    $signPath    = public_path($signRel);
    $hasSign     = file_exists($signPath);

    // Images
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

  .doc-audit { font-size:9pt; color:#555; margin-top:6px; }

  /* Paraf & TTD */
  .signrow td { vertical-align:top; }
  .lcol { width:55%; padding-right:8px; }
  .rcol { width:45%; text-align:center; padding-left:8px; }

  .paraf th, .paraf td { border:1px solid #000; padding:10px 6px; }
  .paraf th { background:#f1f1f1; text-align:left; }
  .boxh { height:58px; }

  .headtitle { font-weight:700; margin-bottom:52px; }
  .headname  { text-decoration:underline; font-weight:700; }
  .small { font-size:9pt; color:#333; }

  .watermark {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    font-size: 100pt;
    color: rgba(0, 0, 0, 0.08);
    z-index: -1000;
    pointer-events: none;
    font-weight: bold;
    text-transform: uppercase;
    width: 100%;
    text-align: center;
  }
</style>
</head>
<body>
<div class="watermark avoid">RAHASIA</div>
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
  <div class="avoid" style="margin-top:5px; font-weight:700">Informasi & Sampel</div>
  <table class="kv avoid">
    <tr><th>Nomor Resi</th><td><b>{{ $receiptNumber }}</b></td></tr>
    <tr><th>Alamat</th><td>{{ $inv?->jurisdiction ?: '—' }}</td></tr>
    <tr><th>Deskripsi</th><td>{{ $samp?->physical_identification ?? '—' }}</td></tr>
    <tr><th>Jumlah Sampel</th><td>{{ ($samp?->package_quantity ?? $samp?->quantity ?? 1) }} {{ $samp?->unit ?? 'Unit' }}</td></tr>
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
    <div class="small">Hasil uji hanya berlaku untuk sampel yang diterima oleh Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri.</div>
  </div>

  <div class="doc-audit avoid">
    Dokumen ini dibuat secara elektronis pada {{ $docGeneratedAt->translatedFormat('d F Y') }}
    pukul {{ $docGeneratedAt->format('H:i') }} WIB oleh {{ $docGeneratedBy }}@if(!empty($docGeneratedUnit)) — {{ $docGeneratedUnit }}@endif
  </div>

  <!-- KIRI: TTD KAFARMAPOL | KANAN: PARAF VERIFIKATOR -->
  <table class="avoid" style="margin-top:6px;">
    <tr class="signrow">
      <!-- LEFT: KAFARMAPOL -->
      <td class="lcol" style="text-align:center;">
        
        <!-- REVISI: TANGGAL BOLD & FONT SIZE SAMA DENGAN HEADTITLE (10pt Default Bold) -->
        <!-- Menggunakan &nbsp; untuk spasi kosong tanggal -->
        <div style="font-weight:700; margin-bottom: 2px;">
            Jakarta, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $today->translatedFormat('F Y') }}
        </div>
        
        <div class="headtitle">{{ $headTitle }}</div>
        
        <div style="height:60px; margin: 2px 0;">
          @if($signBase64)
            <img src="{{ $signBase64 }}" style="height:58px">
          @endif
        </div>
        <div class="headname">{{ $headName }}</div>
        <div class="small">{{ $headRankNrp }}</div>
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
