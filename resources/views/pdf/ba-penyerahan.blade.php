@php
  use Carbon\Carbon;
  use Illuminate\Support\Str;
  Carbon::setLocale('id');
  $req = $request;
  $inv = $req->investigator;
  $samples = $req->samples ?? collect();
  $today = isset($generatedAt) ? Carbon::parse($generatedAt) : now();

  // Derived fields to match the displayed structure
  $firstSample = $samples->first();
  $mainSampleCode = $firstSample->sample_code ?? $firstSample->sample_name ?? '-';
  // Build combined sample codes string (unique, comma-separated)
  $sampleCodes = collect($samples)->map(function($s){
      return $s->sample_code ?? $s->sample_name ?? null;
  })->filter()->unique()->values();
  $allSampleCodesStr = $sampleCodes->isNotEmpty() ? $sampleCodes->join(', ') : $mainSampleCode;
  $invName = trim(($inv?->rank).' '.($inv?->name));
  $invNrp = $inv?->nrp ?? null;
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
  // Look into metadata bag if present (handle array/stdClass/JSON string)
  $metaRaw = $req->metadata ?? [];
  if (is_string($metaRaw)) {
    $decoded = json_decode($metaRaw, true);
    $meta = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
  } elseif (is_array($metaRaw)) {
    $meta = $metaRaw;
  } elseif ($metaRaw instanceof \Illuminate\Support\Collection) {
    $meta = $metaRaw->toArray();
  } else {
    $meta = (array)$metaRaw;
  }
  // Build combined LHU numbers per sample (unique, comma-separated). Fallback: derive from sample_code as FLHUXXX.
  $computeFLHUFromSampleCode = function($code){
    if (!$code || !is_string($code)) return null;
    $c = strtoupper($code);
    // Rule: ambil deretan angka pertama (1-3 digit) setelah awalan 'W' jika ada,
    // lalu pad menjadi 3 digit. Jika tidak ada 'W', ambil deretan angka pertama (1-3 digit)
    // yang bukan kandidat tahun (4 digit).
    if (preg_match('/W.*?(\d{1,3})(?!\d)/i', $c, $m)) {
      return 'FLHU'.str_pad($m[1], 3, '0', STR_PAD_LEFT);
    }
    if (preg_match('/(\d{1,3})(?!\d)/', $c, $m)) {
      return 'FLHU'.str_pad($m[1], 3, '0', STR_PAD_LEFT);
    }
    return null;
  };
  $perSampleLhus = collect($samples)->map(function($s) use ($computeFLHUFromSampleCode){
    $cand = $s->lhu_number ?? $s->flhu_number ?? null;
    if (!$cand) {
      $mraw = $s->metadata ?? null;
      if (is_string($mraw)) { $dec = json_decode($mraw, true); $metaS = (json_last_error()===JSON_ERROR_NONE && is_array($dec)) ? $dec : []; }
      elseif (is_array($mraw)) { $metaS = $mraw; }
      elseif ($mraw instanceof \Illuminate\Support\Collection) { $metaS = $mraw->toArray(); }
      else { $metaS = (array)$mraw; }
      $cand = $metaS['report_number'] ?? $metaS['lhu_number'] ?? $metaS['flhu_number'] ?? null;
    }
    if (!$cand) {
      $cand = $computeFLHUFromSampleCode($s->sample_code ?? $s->sample_name ?? '');
    }
    return $cand ? strtoupper($cand) : null;
  })->filter()->unique()->values();
  $allLhuNumbersStr = $perSampleLhus->isNotEmpty() ? $perSampleLhus->join(', ') : $lhuNumber;
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
  // Try to derive LHU number from generated folder: storage/app/public/investigators/{nrp-name-slug or nrp}/{REQ}/generated/(laporan_hasil_uji|laporan_hasil_uji_html)
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
        // Prefer strict token like FLHU### or FLHU-###; fallback to generic
        $lhuFromFile = null;
        if (preg_match('/(?i)(?:^|[_\-])(?:F?LHU)[_\-]?(\d{1,})\b/', $base, $m)) {
          $digits = $m[1];
          $lhuFromFile = 'FLHU'.str_pad($digits, 3, '0', STR_PAD_LEFT);
        } elseif (preg_match('/(?i)laporan[\-_]hasil[\-_]uji[\-_]([A-Za-z0-9\-]+)/', $base, $m)) {
          $lhuFromFile = $m[1];
        }
        if (!empty($lhuFromFile)) { $lhuNumber = strtoupper(str_replace([' ','_'], ['','-'], $lhuFromFile)); break; }
      }
    }
  } catch (\Throwable $e) {
    // Ignore folder parse errors; keep previous fallbacks
  }
  // As another fallback, try to read from samples and their processes' metadata
  if ($lhuNumber === '—' || empty($lhuNumber)) {
    foreach ($samples as $s) {
      $cand = $s->lhu_number ?? $s->flhu_number ?? $s->report_number ?? null;
      if (!$cand) {
        $mraw = $s->metadata ?? null;
        if (is_string($mraw)) { $dec = json_decode($mraw, true); $metaS = (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : []; }
        elseif (is_array($mraw)) { $metaS = $mraw; }
        elseif ($mraw instanceof \Illuminate\Support\Collection) { $metaS = $mraw->toArray(); }
        else { $metaS = (array)$mraw; }
        $cand = $metaS['report_number'] ?? $metaS['lab_report_no'] ?? $metaS['lhu_number'] ?? $cand;
      }
      // Probe common process relations for metadata
      $procObjs = [ $s->process ?? null, $s->test_process ?? null, $s->latest_process ?? null, $s->interpretation_process ?? null, $s->sample_test_process ?? null ];
      foreach ($procObjs as $p) {
        if (!$p) continue;
        $pm = $p->metadata ?? [];
        if (is_string($pm)) { $pd = json_decode($pm, true); $pmArr = (json_last_error() === JSON_ERROR_NONE && is_array($pd)) ? $pd : []; }
        elseif (is_array($pm)) { $pmArr = $pm; }
        elseif ($pm instanceof \Illuminate\Support\Collection) { $pmArr = $pm->toArray(); }
        else { $pmArr = (array)$pm; }
        $cand = $cand ?? $p->report_number ?? ($pmArr['report_number'] ?? $pmArr['lab_report_no'] ?? $pmArr['lhu_number'] ?? null);
        if ($cand) break;
      }
      if ($cand) { $lhuNumber = $cand; break; }
    }
  }
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
  $calcSisa = function($s) use ($formatQuantity, $appendUnit) {
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
          $s->packaging_type ?? $s->quantity_unit
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
  .instansi,.lab{ font-weight:700; text-transform:uppercase; margin:0; }
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

  /* ===== Tanda tangan proporsional ===== */
  .signatures{ width:100%; border-collapse:separate; border-spacing:10px 0; margin-top:6px; }
  .sigcell{ width:50%; }
  .sigbox{ border:1px solid #000; padding:10px; min-height:88px; display:flex; flex-direction:column; justify-content:space-between; }
  .sigtitle{ font-weight:700; margin-bottom:6px; }

  /* Footer rapat */
  .footer{ position:fixed; bottom:8mm; left:12mm; right:12mm; font-size:9pt; display:flex; justify-content:space-between; border-top:1px solid #000; padding-top:4px; }
  .small{ font-size:9pt; } .muted{ opacity:.9; }
</style>
</head>
<body>

  <div class="header">
    @if(file_exists(public_path('images/logo-tribrata-polri.png')))
      <img class="logo logo-left" src="{{ public_path('images/logo-tribrata-polri.png') }}" alt="">
    @endif
    <div class="center">
      <div class="instansi">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</div>
      <div class="lab">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</div>
      <div class="meta">Jl. Cipinang Baru Raya No.3B, Jakarta Timur 13240 • Telp/Fax: 021-4700921 • Email: labmutufarmapol@gmail.com</div>
    </div>
    @if(file_exists(public_path('images/logo-pusdokkes-polri.png')))
      <img class="logo logo-right" src="{{ public_path('images/logo-pusdokkes-polri.png') }}" alt="">
    @endif
  </div>

  <h1 class="title">Berita Acara Penyerahan</h1>

  <table class="meta-table">
    <tr><td class="label">Nomor Permintaan (Lab)</td><td class="sep">:</td><td class="value nowrap"><strong>{{ $req->request_number }}</strong></td></tr>
    <tr><td class="label">Nomor BA</td><td class="sep">:</td><td class="value">{{ $baNumber }}</td></tr>
    <tr><td class="label">Pelanggan</td><td class="sep">:</td><td class="value">{{ $invName ?: '—' }} @if($invNrp) — NRP/NIP: {{ $invNrp }} @endif</td></tr>
    <tr><td class="label">Unit/Satuan</td><td class="sep">:</td><td class="value">{{ $inv?->jurisdiction ?? $req->unit ?? '—' }}</td></tr>
    <tr><td class="label">Nama Tersangka</td><td class="sep">:</td><td class="value">{{ $req->suspect_name ?? '—' }}</td></tr>
  <tr><td class="label">Kode Sampel</td><td class="sep">:</td><td class="value">{{ $allSampleCodesStr }}</td></tr>
  <tr><td class="label">Nomor LHU</td><td class="sep">:</td><td class="value">{{ $allLhuNumbersStr }}</td></tr>
    <tr><td class="label">Dasar Permohonan</td><td class="sep">:</td><td class="value">{{ $basisText }}</td></tr>
    <tr><td class="label">Rujukan Dokumen Asal</td><td class="sep">:</td><td class="value"><strong>Lihat FR/LPMF/7.8.1, FR/LPMF/7.8.2, dan Tanda Terima terkait</strong></td></tr>
  </table>

  <div class="section">
    <h2>Daftar Sampel (Ringkas)</h2>
    <table class="tbl-sampel">
      <colgroup><col style="width:22px"><col></colgroup>
      <thead><tr><th class="col-no">No</th><th>Sampel — Uji</th></tr></thead>
      <tbody>
        @forelse($samples as $i => $s)
          @php
            $methods = $s->test_methods;
            if (is_string($methods)) { $methods = json_decode($methods, true) ?? []; }
            $map = ['uv_vis'=>'Identifikasi Spektrofotometri UV-VIS','gc_ms'=>'Identifikasi GC-MS','lc_ms'=>'Identifikasi LC-MS'];
            $methodsStr = collect($methods)->map(fn($m)=>$map[$m] ?? $m)->join('; ');
            $code = $s->sample_code ?? $s->sample_name ?? '—';
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
    <h2>Sisa Sampel Diserahkan</h2>
    <table>
      <thead><tr><th>Kode Sampel</th><th>Sisa</th></tr></thead>
      <tbody>
        @forelse($samples as $s)
          @php $code = $s->sample_code ?? $s->sample_name ?? '—'; @endphp
          <tr><td>{{ $code }}</td><td>{{ $calcSisa($s) }}</td></tr>
        @empty
          <tr><td colspan="2" style="text-align:center;font-style:italic;">Tidak ada data sisa sampel.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="section small muted">
    Demikian Berita Acara ini dibuat dengan sebenar-benarnya untuk dapat dipergunakan sebagaimana mestinya.
  </div>

  <table class="signatures">
    <tr>
      <td class="sigcell">
        <div class="sigbox">
          <div class="sigtitle">Yang Menyerahkan</div>
          <div>Staf Laboratorium Farmapol Pusdokkes Polri</div>
          <div style="height:36px;"></div>
        </div>
      </td>
      <td class="sigcell">
        <div class="sigbox">
          <div class="sigtitle">Yang Menerima</div>
          <div>{{ $invName ?: '—' }}</div>
          <div class="small muted">NRP {{ $invNrp ?? '—' }}</div>
        </div>
      </td>
    </tr>
  </table>

  <div class="footer">
    <div class="small">Kode Dokumen: BA-Penyerahan/{{ $req->request_number }}</div>
    <div class="small">Dicetak pada: {{ $today->translatedFormat('d F Y H:i') }} WIB</div>
    <div class="small">Halaman 1 dari 1</div>
  </div>

</body>
</html>

