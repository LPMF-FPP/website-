@php
  use Carbon\Carbon;
  use Illuminate\Support\Str;
  Carbon::setLocale('id');
  $req = $request;
  $delivery = $delivery ?? null;
  $inv = $req->investigator;
  $samples = $req->samples ?? collect();
  $today = isset($generatedAt) ? Carbon::parse($generatedAt) : now();
  $isPreview = $isPreview ?? false;
  $leftLogoPath = public_path('images/logo-tribrata-polri.png');
  $rightLogoPath = public_path('images/logo-pusdokkes-polri.png');
  $leftLogoSrc = $isPreview ? asset('images/logo-tribrata-polri.png') : $leftLogoPath;
  $rightLogoSrc = file_exists($rightLogoPath) ? ($isPreview ? asset('images/logo-pusdokkes-polri.png') : $rightLogoPath) : null;
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

  $toArray = function ($value) {
    if ($value instanceof \Illuminate\Support\Collection) return $value->toArray();
    if (is_string($value)) {
      $decoded = json_decode($value, true);
      return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
    }
    if (is_array($value)) return $value;
    if (is_object($value) && method_exists($value, 'toArray')) return $value->toArray();
    if (is_object($value)) return (array) $value;
    return [];
  };

  // Derived fields to match the displayed structure
  $firstSample = $samples->first();
  $mainSampleCode = $firstSample->sample_code ?? $firstSample->short_description ?? '-';
  // Build combined sample codes string (unique, comma-separated)
  $sampleCodes = collect($samples)->map(function($s){
      return $s->sample_code ?? $s->short_description ?? null;
  })->filter()->unique()->values();
  $allSampleCodesStr = $sampleCodes->isNotEmpty() ? $sampleCodes->join(', ') : $mainSampleCode;
  $handoverStaffSigner = pdf_build_signer($delivery?->deliveredBy ?? $req->user, fallbackRole: 'PETUGAS LABORATORIUM');
  $receiverSigner = pdf_build_signer($inv);

  // Normalize request metadata early (used by BA number and LHU fallbacks)
  $meta = $toArray($req->metadata ?? []);

  // BA Penyerahan number - try from settings/numbering service
  $numberingService = app(\App\Services\NumberingService::class);
  $baPenyerahanNumber = '—';
  try {
    // Try to get from request metadata first
    $baPenyerahanNumber = $meta['ba_penyerahan_number'] ?? null;
    if (!$baPenyerahanNumber) {
      // Generate using numbering service with pattern: LPMF/BA/{SEQ:3}/Rah/{YYYY}
      $baPenyerahanNumber = $numberingService->preview('ba_penyerahan', [
        'investigator_id' => $req->investigator_id ?? null,
      ]);
    }
  } catch (\Throwable $e) {
    $baPenyerahanNumber = '—';
  }
  if ($baPenyerahanNumber && $baPenyerahanNumber !== '—') {
    $baPenyerahanNumber = strtoupper((string) $baPenyerahanNumber);
    if (str_contains($baPenyerahanNumber, '/')) {
      $baPenyerahanNumber = preg_replace('/\s*\/\s*/', '/', $baPenyerahanNumber) ?? $baPenyerahanNumber;
      $baPenyerahanNumber = preg_replace('/\/{2,}/', '/', $baPenyerahanNumber) ?? $baPenyerahanNumber;
      $baPenyerahanNumber = trim($baPenyerahanNumber, '/');
    } elseif (preg_match('/^(BA-ST)-(\d+)-([IVXLCDM]+)-(\d{4})-([A-Z0-9]+)$/', $baPenyerahanNumber, $m)) {
      $baPenyerahanNumber = sprintf('%s/%s/%s/%s/%s', $m[1], str_pad($m[2], 3, '0', STR_PAD_LEFT), $m[3], $m[4], $m[5]);
    }
  }
  // Robust fallbacks from DB relations/fields
  $baNumber = $req->ba_number
    ?? ($req->ba->number ?? null)
    ?? ($req->handover->ba_number ?? null)
    ?? ($req->delivery->ba_number ?? null)
    ?? '—';
  $lhuNumber = $req->lhu_number
    ?? $req->flhu_number
    ?? $req->lhuCode
    ?? $req->lhu_code
    ?? $req->final_report_number
    ?? $req->report_number
    ?? ($req->lhu->number ?? null)
    ?? ($req->lab_report->number ?? null)
    ?? ($firstSample->lhu_number ?? null)
    ?? ($firstSample->flhu_number ?? null)
    ?? '—';
  $basisText = $req->request_basis
    ?? $req->basis
    ?? $req->purpose
    ?? $req->request_purpose
    ?? $req->application_reason
    ?? $req->dasar_permohonan
    ?? $req->case_number
    ?? $req->surat_permintaan_no
    ?? $req->surat_permintaan
    ?? $req->notes
    ?? '—';
  // Build combined LHU numbers per sample (unique, comma-separated). Fallback: derive from sample_code using active LHU format.
  $renderLHUFromSequence = function($seq) use ($numberingService, $req) {
    $seq = is_numeric($seq) ? (int) $seq : 0;
    if ($seq <= 0) return null;
    try {
      return $numberingService->preview('lhu', [
        'investigator_id' => $req->investigator_id ?? null,
        'request_short' => $req->request_number ?? null,
        'doc_code' => 'LHU',
      ], $seq);
    } catch (\Throwable $e) {
      return null;
    }
  };
  $computeLHUFromSampleCode = function($code) use ($renderLHUFromSequence){
    if (!$code || !is_string($code)) return null;
    $c = strtoupper($code);
    // Rule: ambil deretan angka 1-3 digit setelah prefix W (W001, W-001, W_001, W 001)
    if (preg_match('/\bW[\s\-_]*0*(\d{1,4})\b/i', $c, $m)) {
      return $renderLHUFromSequence($m[1]);
    }
    // Fallback: ambil 1-3 digit terakhir
    if (preg_match('/(\d{1,4})(?!\d)/', $c, $m)) {
      return $renderLHUFromSequence($m[1]);
    }
    return null;
  };
  $perSampleLhus = collect($samples)->map(function($s) use ($computeLHUFromSampleCode, $toArray){
    $cand = $s->lhu_number ?? $s->flhu_number ?? null;
    if (!$cand) {
      $metaS = $toArray($s->metadata ?? null);
      $cand = $metaS['report_number'] ?? $metaS['lab_report_no'] ?? $metaS['lhu_number'] ?? $metaS['flhu_number'] ?? null;
    }
    if (!$cand) {
      $procObjs = [ $s->process ?? null, $s->test_process ?? null, $s->latest_process ?? null, $s->interpretation_process ?? null, $s->sample_test_process ?? null ];
      foreach ($procObjs as $p) {
        if (!$p) continue;
        $pmArr = $toArray($p->metadata ?? null);
        $cand = $p->report_number
          ?? $p->lhu_number
          ?? ($pmArr['report_number'] ?? $pmArr['lab_report_no'] ?? $pmArr['lhu_number'] ?? null);
        if ($cand) break;
      }
    }
    if (!$cand && !empty($s->testProcesses)) {
      foreach ($s->testProcesses as $p) {
        if (!$p) continue;
        $pmArr = $toArray($p->metadata ?? null);
        $cand = $p->report_number
          ?? $p->lhu_number
          ?? ($pmArr['report_number'] ?? $pmArr['lab_report_no'] ?? $pmArr['lhu_number'] ?? null);
        if ($cand) break;
      }
    }
    if (!$cand) {
      $cand = $computeLHUFromSampleCode($s->sample_code ?? $s->short_description ?? '');
    }
    return $cand ? strtoupper($cand) : null;
  })->filter()->values();
  if ($lhuNumber === '—' || empty($lhuNumber)) {
      $lhuNumber = $meta['report_number']
        ?? $meta['lab_report_no']
        ?? $meta['lhu_number']
        ?? $meta['report_no']
        ?? $meta['noLHU']
        ?? $meta['no_lhu']
        ?? $lhuNumber;
  }
  if ($basisText === '—' || empty($basisText)) {
      $basisText = $meta['request_basis']
        ?? $meta['dasar_permohonan']
        ?? $meta['case_number']
        ?? $meta['surat_permintaan_no']
        ?? $meta['surat_permintaan']
        ?? $basisText;
  }
  // As another fallback, try to read from samples and their processes' metadata
  if ($lhuNumber === '—' || empty($lhuNumber)) {
    foreach ($samples as $s) {
      $cand = $s->lhu_number ?? $s->flhu_number ?? $s->report_number ?? null;
      if (!$cand) {
        $metaS = $toArray($s->metadata ?? null);
        $cand = $metaS['report_number'] ?? $metaS['lab_report_no'] ?? $metaS['lhu_number'] ?? $cand;
      }
      // Probe common process relations for metadata
      $procObjs = [ $s->process ?? null, $s->test_process ?? null, $s->latest_process ?? null, $s->interpretation_process ?? null, $s->sample_test_process ?? null ];
      foreach ($procObjs as $p) {
        if (!$p) continue;
        $pmArr = $toArray($p->metadata ?? null);
        $cand = $cand ?? $p->report_number ?? ($pmArr['report_number'] ?? $pmArr['lab_report_no'] ?? $pmArr['lhu_number'] ?? null);
        if ($cand) break;
      }
      if (!$cand && !empty($s->testProcesses)) {
        foreach ($s->testProcesses as $p) {
          if (!$p) continue;
          $pmArr = $toArray($p->metadata ?? null);
          $cand = $cand ?? $p->report_number ?? ($pmArr['report_number'] ?? $pmArr['lab_report_no'] ?? $pmArr['lhu_number'] ?? null);
          if ($cand) break;
        }
      }
      if ($cand) { $lhuNumber = $cand; break; }
    }
  }
  // Try to derive LHU number from generated folder: storage/app/public/investigators/{nrp-name-slug or nrp}/{REQ}/generated/(laporan_hasil_uji|laporan_hasil_uji_html)
  if ($perSampleLhus->isEmpty() && ($lhuNumber === '—' || empty($lhuNumber))) {
    try {
      $invSlug = isset($invName) && strlen(trim($invName)) ? Str::slug(trim($inv?->name ?? '')) : null;
      $invKey = $invNrp ? trim($invNrp).($invSlug ? ('-'. $invSlug) : '') : null;
      $candidateDirs = [];
      if ($invKey) {
        $basePath = storage_path('app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'investigators'.DIRECTORY_SEPARATOR.$invKey.DIRECTORY_SEPARATOR.($req->request_number).DIRECTORY_SEPARATOR.'generated');
        $candidateDirs[] = $basePath.DIRECTORY_SEPARATOR.'laporan_hasil_uji';
        $candidateDirs[] = $basePath.DIRECTORY_SEPARATOR.'laporan_hasil_uji_html';
      }
      if ($invNrp) {
        $basePath = storage_path('app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'investigators'.DIRECTORY_SEPARATOR.trim($invNrp).DIRECTORY_SEPARATOR.($req->request_number).DIRECTORY_SEPARATOR.'generated');
        $candidateDirs[] = $basePath.DIRECTORY_SEPARATOR.'laporan_hasil_uji';
        $candidateDirs[] = $basePath.DIRECTORY_SEPARATOR.'laporan_hasil_uji_html';
      }
      foreach ($candidateDirs as $lhuDir) {
        if (!is_dir($lhuDir)) continue;
        $candidates = array_merge(
          glob($lhuDir.DIRECTORY_SEPARATOR.'Laporan_Hasil_Uji_*.*') ?: [],
          glob($lhuDir.DIRECTORY_SEPARATOR.'laporan_hasil_uji_*.*') ?: [],
          glob($lhuDir.DIRECTORY_SEPARATOR.'laporan-hasil-uji*.*') ?: [],
          glob($lhuDir.DIRECTORY_SEPARATOR.'LAPORAN_HASIL_UJI_*.*') ?: [],
          glob($lhuDir.DIRECTORY_SEPARATOR.'*laporan*hasil*uji*.*') ?: []
        );
        if (!empty($candidates)) {
          usort($candidates, function($a,$b){ return filemtime($b) <=> filemtime($a); });
          $latest = $candidates[0];
          $base = pathinfo($latest, PATHINFO_FILENAME);
          // Prefer strict token like LHU-LPMF-### or LHU-LPMF-###; fallback to generic
          $lhuFromFile = null;
          if (preg_match('/(?i)LHU[_\-]LPMF[_\-](\d{1,})\b/', $base, $m)) {
            $digits = $m[1];
            $lhuFromFile = 'LHU-LPMF-'.str_pad($digits, 3, '0', STR_PAD_LEFT);
          } elseif (preg_match('/(?i)(?:^|[_\-])(?:F?LHU)[_\-]?(\d{1,})\b/', $base, $m)) {
            $digits = $m[1];
            $lhuFromFile = 'LHU-LPMF-'.str_pad($digits, 3, '0', STR_PAD_LEFT);
          } elseif (preg_match('/(?i)laporan[\-_]hasil[\-_]uji[\-_]([A-Za-z0-9\-]+)/', $base, $m)) {
            $lhuFromFile = $m[1];
          }
          if (!empty($lhuFromFile)) { $lhuNumber = strtoupper(str_replace([' ','_'], ['','-'], $lhuFromFile)); break; }
        }
      }
    } catch (\Throwable $e) {
      // Ignore folder parse errors; keep previous fallbacks
    }
  }
  if ($lhuNumber && $lhuNumber !== '—') {
    $lhuNumber = strtoupper($lhuNumber);
  }
  $allLhuNumbersStr = $perSampleLhus->isNotEmpty() ? $perSampleLhus->join(', ') : ($lhuNumber ?: '—');
  // Helpers untuk menyamakan perhitungan Sisa dengan halaman Delivery
  $formatQuantity = static function ($value): ?string {
      if ($value === null || $value === '') {
          return null;
      }
      if (!is_numeric($value)) {
          return trim((string) $value) ?: null;
      }
      $number = (float) $value;
      $formatted = number_format($number, 2, '.', '');
      $formatted = rtrim(rtrim($formatted, '0'), '.');
      return $formatted === '' ? null : $formatted;
  };
  $appendUnit = static function (?string $quantity, ?string $unit): ?string {
      if ($quantity === null) return null;
      $unit = $unit ? trim($unit) : '';
      return $unit !== '' ? $quantity . ' ' . $unit : $quantity;
  };
  $remainingUnitsBySampleId = collect($req->evidenceUnits ?? [])
      ->filter(fn($evidenceUnit) => isset($evidenceUnit->sample_id))
      ->keyBy('sample_id')
      ->map(fn($evidenceUnit) => collect($evidenceUnit->remainingUnits ?? [])->sortBy('id')->values());
  $calcDelivered = function($s) use ($formatQuantity, $appendUnit) {
      return $appendUnit(
          $formatQuantity($s->package_quantity ?? null),
          $s->unit ?? $s->quantity_unit
      ) ?? '-';
  };
  $calcTesting = function($s) use ($formatQuantity, $appendUnit) {
      return $appendUnit(
          $formatQuantity($s->quantity ?? null),
          $s->quantity_unit ?? $s->unit
      ) ?? '-';
  };
  $calcSisa = function($s) use ($formatQuantity, $appendUnit, $remainingUnitsBySampleId) {
      $remainingUnits = $remainingUnitsBySampleId->get($s->id, collect());
      if ($remainingUnits->count() > 1) {
          $remainingUnit = $remainingUnits->first();
          $display = $appendUnit(
              $formatQuantity($remainingUnits->sum(fn($unit) => (float) ($unit->qty_remaining ?? 0))),
              $remainingUnit->uom ?? ($s->unit ?? $s->quantity_unit)
          );
          return $display ?? '0';
      }

      $deliveredQty = $s->package_quantity;
      $testingQty   = $s->quantity;
      if ($deliveredQty !== null && !is_numeric($deliveredQty)) { $deliveredQty = null; }
      if ($testingQty !== null && !is_numeric($testingQty)) { $testingQty = null; }
      $leftoverQty = null;
      if ($deliveredQty !== null) {
          if ($testingQty !== null) {
              $diff = (float)$deliveredQty - (float)$testingQty;
              $leftoverQty = $diff > 0 ? $diff : 0.0;
          } else {
              $leftoverQty = (float)$deliveredQty;
          }
      }
      $display = $appendUnit(
          $formatQuantity($leftoverQty),
          $s->unit ?? $s->quantity_unit
      );
      return $display ?? '0';
  };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Berita Acara Penyerahan — {{ $req->request_number }}</title>
<style>
  @page { size: A4; margin: 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color:#000; margin:0; line-height:1.28; padding-bottom: 28mm; }
  .header { position: relative; margin:0 0 6px; min-height:52px; padding:0 72px; border-bottom:1px solid #000; padding-bottom:4px; }
  .logo { height:52px; position:absolute; top:0; }
  .logo-left{left:0;} .logo-right{right:0;}
  .center{ text-align:center; line-height:1.18; }
  .instansi{ font-weight:700; font-size:14pt; text-transform:uppercase; margin:0; }
  .lab{ font-weight:700; font-size:12.5pt; text-transform:uppercase; margin:0; }
  .meta{ font-size:8.8pt; margin:1px 0 0; }
  h1.title{ text-align:center; font-size:14.5pt; margin:4px 0 6px; text-transform:uppercase; }

  /* ===== META TABLE: rapat tanpa gap ===== */
  .meta-table{ width:100%; border-collapse:collapse; table-layout:auto; margin:0; }
  .meta-table td{ padding:1px 2px; border:none; vertical-align:top; }
  .meta-table td.label{ width:34%; white-space:nowrap; }
  .meta-table td.sep{ width:1%; text-align:center; padding:0; }
  .meta-table td.value{ width:65%; white-space:normal; word-break:break-word; }
  .meta-table .nowrap{ white-space:nowrap; }

  /* ===== Tabel umum ===== */
  table{ width:100%; border-collapse:collapse; font-size:9.8pt; }
  th,td{ border:1px solid #000; padding:2px 3px; vertical-align:top; }
  th{ text-align:left; }

  /* ===== Daftar Sampel: kolom No kecil, konten fleksibel ===== */
  .tbl-sampel{ table-layout:auto; }
  .tbl-sampel .col-no{ width:22px; text-align:center; }
  .tbl-sampel td:last-child{ word-break:break-word; hyphens:auto; }
  .tbl-rekon{ table-layout:fixed; }
  .tbl-rekon th,.tbl-rekon td{ word-break:break-word; }
  .tbl-rekon .col-code{ width:34%; }
  .tbl-rekon .col-qty{ width:22%; }

  /* ===== Tanda tangan proporsional ===== */
  .signatures{ width:100%; border-collapse:separate; border-spacing:10px 0; margin-top:6px; }
  .sigcell{ width:50%; }
  .sigbox{ border:1px solid #000; padding:10px; min-height:88px; }
  .sigtitle{ font-weight:700; margin-bottom:6px; }
  .sigspacer { height:36px; }
  .signame { text-align:center; text-decoration: underline; font-weight:700; }
  .sigidentity { text-align:center; font-size:9pt; margin-top:4px; }

  /* Footer rapat */
  .footer{ position:fixed; bottom:8mm; left:12mm; right:12mm; font-size:9pt; display:flex; justify-content:space-between; border-top:1px solid #000; padding-top:4px; }
  .small{ font-size:9pt; } .muted{ opacity:.9; }
</style>
</head>
<body>

  <div class="header">
    @if(file_exists($leftLogoPath))
      <img class="logo logo-left" src="{{ $leftLogoSrc }}" alt="">
    @endif
    <div class="center">
      <div class="instansi">{{ $letterheadOrgName }}</div>
      <div class="lab">{{ $letterheadLabName }}</div>
       <div class="meta">{{ $letterheadAddress }}{{ $letterheadAddress && $letterheadContactLine ? ' • ' : '' }}{{ $letterheadContactLine }}</div>
    </div>
    @if(file_exists($rightLogoPath))
      <img class="logo logo-right" src="{{ $rightLogoSrc }}" alt="">
    @endif
  </div>

  <h1 class="title">Berita Acara Penyerahan</h1>
  <div style="text-align:center; margin:4px 0 8px; font-weight:700; font-size:11pt;">{{ $baPenyerahanNumber }}</div>

  <table class="meta-table">
    <tr><td class="label">Nomor Resi</td><td class="sep">:</td><td class="value nowrap"><strong>{{ $req->receipt_number ?? $req->request_number }}</strong></td></tr>
    <tr><td class="label">Pelanggan</td><td class="sep">:</td><td class="value">{{ trim(($inv?->rank).' '.($inv?->name)) ?: '—' }} @if($inv?->nrp ?? $inv?->nip) — NRP/NIP: {{ $inv?->nrp ?? $inv?->nip }} @endif</td></tr>
    <tr><td class="label">Unit/Satuan</td><td class="sep">:</td><td class="value">{{ $inv?->jurisdiction ?? $req->unit ?? '—' }}</td></tr>
    <tr><td class="label">Nama Tersangka</td><td class="sep">:</td><td class="value">{{ $req->suspect_name ?? '—' }}</td></tr>
  <tr><td class="label">Kode Sampel</td><td class="sep">:</td><td class="value">{{ $allSampleCodesStr }}</td></tr>
  <tr><td class="label">Nomor LHU</td><td class="sep">:</td><td class="value">{{ $allLhuNumbersStr }}</td></tr>
    <tr><td class="label">Dasar Permohonan</td><td class="sep">:</td><td class="value">{{ $basisText }}</td></tr>
  </table>

  <div class="section">
    <h2>Daftar Sampel (Ringkas)</h2>
    <table class="tbl-sampel">
      <colgroup><col style="width:22px"><col></colgroup>
      <thead><tr><th class="col-no">No</th><th>Sampel — Uji</th></tr></thead>
      <tbody>
        @forelse($samples as $i => $s)
          @php
            $methods = $s->test_methods ?? [];
            if (is_string($methods)) { $methods = json_decode($methods, true) ?? []; }
            $map = ['uv_vis'=>'Identifikasi Spektrofotometri UV-VIS','gc_ms'=>'Identifikasi GC-MS','lc_ms'=>'Identifikasi LC-MS'];
            $methodsStr = collect($methods)->map(fn($m)=>$map[$m] ?? $m)->join('; ');
            $code = $s->sample_code ?? $s->short_description ?? '—';
          @endphp
          <tr><td class="col-no">{{ $i+1 }}</td><td><strong>{{ $code }}</strong> — Uji: {{ $methodsStr }}</td></tr>
        @empty
          <tr><td colspan="2" style="text-align:center;font-style:italic;">Tidak ada sampel yang terdata.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="small muted">Detail lengkap tiap sampel tersedia di dokumen asal/lampiran LHU.</div>
  </div>



  <div class="section">
    <h2>Rekonsiliasi Sampel</h2>
    <table class="tbl-rekon">
      <thead>
        <tr>
          <th class="col-code">Kode Sampel</th>
          <th class="col-qty">Jumlah Diserahkan</th>
          <th class="col-qty">Digunakan untuk Pengujian</th>
          <th class="col-qty">Sisa Diserahkan</th>
        </tr>
      </thead>
      <tbody>
        @forelse($samples as $s)
          @php $code = $s->sample_code ?? $s->short_description ?? '—'; @endphp
          <tr>
            <td>{{ $code }}</td>
            <td>{{ $calcDelivered($s) }}</td>
            <td>{{ $calcTesting($s) }}</td>
            <td>{{ $calcSisa($s) }}</td>
          </tr>
        @empty
          <tr><td colspan="4" style="text-align:center;font-style:italic;">Tidak ada data rekonsiliasi sampel.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="small muted">Sisa diserahkan dihitung dari jumlah sampel yang diserahkan dikurangi jumlah yang digunakan untuk pengujian, atau berdasarkan penyesuaian fisik akhir saat penyerahan.</div>
  </div>

  <div class="section small muted">
    Demikian Berita Acara ini dibuat dengan sebenar-benarnya untuk dapat dipergunakan sebagaimana mestinya.
  </div>

  <table class="signatures">
    <tr>
      <td class="sigcell">
        <div class="sigbox">
          <div class="sigtitle">Yang Menyerahkan</div>
          <div class="sigspacer"></div>
          <div class="signame">{{ $handoverStaffSigner['name'] }}</div>
          <div class="sigidentity">{{ $handoverStaffSigner['identity'] }}</div>
        </div>
      </td>
      <td class="sigcell">
        <div class="sigbox">
          <div class="sigtitle">Yang Menerima</div>
          <div class="sigspacer"></div>
          <div class="signame">{{ $receiverSigner['name'] }}</div>
          <div class="sigidentity">{{ $receiverSigner['identity'] }}</div>
        </div>
      </td>
    </tr>
  </table>

  <div class="footer">
    <div class="small">Kode Dokumen: {{ $baPenyerahanNumber }}</div>
    <div class="small">Dicetak pada: {{ $today->translatedFormat('d F Y H:i') }} WIB</div>
    <div class="small">Halaman 1 dari 1</div>
  </div>

</body>
</html>
