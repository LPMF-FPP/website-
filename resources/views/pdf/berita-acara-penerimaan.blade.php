@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $receivedAt = $request->received_at ? Carbon::parse($request->received_at) : now();
    $printedAt  = isset($generatedAt) ? Carbon::parse($generatedAt) : now();

    // Use receipt number from database (generated when request was created)
    // If not available (old records), generate one for display only
    $receiptNumber = $request->receipt_number;
    if (!$receiptNumber) {
        $numberingService = app(\App\Services\NumberingService::class);
        $receiptNumber = $numberingService->preview('tracking', [
            'investigator_id' => $request->investigator_id,
            'now' => $receivedAt instanceof \Carbon\CarbonImmutable ? $receivedAt : \Carbon\CarbonImmutable::parse($receivedAt),
        ]);
    }

    $methodMap = [
        'uv_vis' => 'Identifikasi Spektrofotometri UV-VIS',
        'gc_ms'  => 'Identifikasi GC-MS',
        'lc_ms'  => 'Identifikasi LC-MS',
    ];

    $formatMethods = function($methods) use ($methodMap) {
        if (is_string($methods)) { $arr = json_decode($methods, true) ?? []; }
        else { $arr = $methods ?? []; }
        return collect($arr)->map(fn($m) => $methodMap[$m] ?? $m)->unique()->join('; ');
    };

    $testsSummary = $request->samples->map(fn($s) => $formatMethods($s->test_methods ?? []))->filter()->unique()->join('; ');

    $getQty = function($sample) {
        $keys = [
            'package_quantity',
            'quantity_delivered',
            'delivered_quantity',
            'jumlah_yang_diserahkan',
            'jumlah_diserahkan',
            'submitted_quantity',
            'quantity_submitted',
            'qty',
            'quantity',
        ];
        foreach ($keys as $key) {
            $value = data_get($sample, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    };

    $getUnit = function($sample) {
        $keys = ['unit', 'satuan', 'pack_unit', 'packaging_unit'];
        foreach ($keys as $key) {
            $value = data_get($sample, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    };

    $formatQtyUnit = function($sample) use ($getQty, $getUnit) {
        $qty = $getQty($sample);
        $unit = $getUnit($sample);

        $hasQty = $qty !== null && $qty !== '';
        $hasUnit = $unit !== null && $unit !== '';

        if ($hasQty && $hasUnit) {
            return trim($qty.' '.$unit);
        }
        if ($hasQty) {
            return $qty;
        }
        if ($hasUnit) {
            return $unit;
        }
        return '—';
    };

    $isPreview = $isPreview ?? false;
    $leftLogoPath = public_path('images/logo-tribrata-polri.png');
    $rightLogoPath = public_path('images/logo-pusdokkes-polri.png');
    $leftLogoSrc = $isPreview ? asset('images/logo-tribrata-polri.png') : $leftLogoPath;
    $rightLogoSrc = $isPreview ? asset('images/logo-pusdokkes-polri.png') : $rightLogoPath;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Berita Acara Penerimaan — {{ $request->request_number }}</title>
<style>
  @page { size: A4; margin: 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.28; margin:0; padding-bottom: 10mm; }

  .header { position: relative; margin:0 0 6px; min-height:52px; padding:0 72px; border-bottom:1px solid #000; padding-bottom:4px; }
  .logo { height:52px; position:absolute; top:0; }
  .logo-left{left:0;} .logo-right{right:0;}
  .center { text-align:center; line-height:1.18; }
  .instansi, .lab { font-weight:700; text-transform:uppercase; margin:0; }
  .meta { font-size: 8.8pt; margin:1px 0 0; }

  h1.title { text-align:center; font-size:14.5pt; margin:4px 0 4px; text-transform:uppercase; }
  .subtitle { text-align:center; font-size:11pt; font-weight:700; margin: 0 0 8px; }

  table { border-collapse: collapse; width:100%; }

  .meta-table{ width:100%; border-collapse:collapse; table-layout:auto; margin:8px 0 10px; }
  .meta-table td{ padding:1px 2px; border:none; vertical-align:top; }
  .meta-table td.label{ width:34%; white-space:nowrap; }
  .meta-table td.sep{ width:1%; text-align:center; padding:0; }
  .meta-table td.value{ width:65%; white-space:normal; word-break:break-word; }
  .meta-table .nowrap{ white-space:nowrap; }

  .section-title { font-size:10pt; font-weight:700; margin: 6px 0 4px; }

  .list-table { font-size:8pt; table-layout: fixed; margin-top: 4px; }
  .list-table th, .list-table td { border:1px solid #000; padding:3px 5px; vertical-align:top; }
  .list-table th { text-align:center; background:#f0f0f0; white-space: normal !important; overflow-wrap:anywhere; word-break:break-word; hyphens:auto; line-height:1.2; }
  .list-table td { overflow-wrap:anywhere; word-break:break-word; hyphens:auto; }
  .col-name  { width: 30%; }
  .col-qty   { width: 20%; text-align:center; }
  .col-tests { width: 32%; }
  .col-act   { width: 18%; }

  .sign-table { width:100%; margin-top:10px; border:0; border-collapse:separate; }
  .sign-table td { width:50%; vertical-align:top; border:0; }
  .sigcell { padding:6px 8px; }
  .sigtitle { text-align:center; font-weight:700; margin-bottom:75px; }
  .signame { text-align:center; text-decoration: underline; font-weight:700; }

  .footer { margin-top: 16px; font-size:9pt; color:#555; }
</style>
</head>
<body>

  <div class="header">
    @if(file_exists($leftLogoPath))
      <img class="logo logo-left" src="{{ $leftLogoSrc }}" alt="Logo Polri">
    @endif
    <div class="center">
      <div class="instansi">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</div>
      <div class="lab">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</div>
      <div class="meta">Jl. Cipinang Baru Raya No. 3B, Jakarta Timur 13240 • Telp/Fax: 021-4700921 • Email: labmutufarmapol@gmail.com</div>
    </div>
    @if(file_exists($rightLogoPath))
      <img class="logo logo-right" src="{{ $rightLogoSrc }}" alt="Logo Pusdokkes">
    @endif
  </div>

  <h1 class="title">Berita Acara Penerimaan Sampel</h1>
  <div class="subtitle">Nomor Permintaan: <b>{{ $request->request_number }}</b></div>

  <p>
    Pada hari ini, <b>{{ $receivedAt->translatedFormat('l, d F Y') }}</b>,
    telah diterima sampel untuk keperluan pengujian di Laboratorium Pengujian Mutu Farmasi Kepolisian,
    Pusat Kedokteran dan Kesehatan Polri, dengan rincian sebagai berikut:
  </p>

  <table class="meta-table">
    <tr><td class="label">Nomor Resi</td><td class="sep">:</td><td class="value nowrap"><strong>{{ $receiptNumber }}</strong></td></tr>
    <tr><td class="label">Nomor Surat Permintaan</td><td class="sep">:</td><td class="value">{{ $request->case_number ?? '-' }}</td></tr>
    <tr><td class="label">Ditujukan Kepada</td><td class="sep">:</td><td class="value">{{ $request->to_office ?? 'Kepala Sub Satker Farmapol Pusdokkes Polri' }}</td></tr>
    <tr><td class="label">Penyerah Sampel</td><td class="sep">:</td><td class="value">{{ trim(($request->investigator->rank ?? '').' '.($request->investigator->name ?? '')) }} (NRP: {{ $request->investigator->nrp ?? '-' }})</td></tr>
    <tr><td class="label">Unit/Satuan</td><td class="sep">:</td><td class="value">{{ $request->investigator->jurisdiction ?? '-' }}</td></tr>
    <tr><td class="label">Jumlah Sampel</td><td class="sep">:</td><td class="value"><strong>{{ $request->samples->count() }}</strong> sampel</td></tr>
    <tr><td class="label">Jenis Pengujian</td><td class="sep">:</td><td class="value">{{ $testsSummary ?: '-' }}</td></tr>
  </table>

  <div class="section-title">Daftar Sampel yang Diterima</div>
  <table class="list-table">
    <thead>
      <tr>
        <th class="col-name">Deskripsi Singkat</th>
        <th class="col-qty">Jumlah Sampel yang Diterima</th>
        <th class="col-tests">Jenis Pengujian</th>
        <th class="col-act">Zat Aktif</th>
      </tr>
    </thead>
    <tbody>
      @foreach($request->samples as $i => $sample)
      <tr>
        <td class="col-name"><b>{{ $sample->short_description ?? '—' }}</b></td>
        <td class="col-qty">{{ $formatQtyUnit($sample) }}</td>
        <td class="col-tests">{{ $formatMethods($sample->test_methods ?? []) }}</td>
        <td class="col-act">{{ $sample->active_substance ?? '-' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <p style="margin-top:12px;">Demikian Berita Acara ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

  <table class="sign-table">
    <tr>
      <td class="sigcell">
        <div class="sigtitle">Yang Menyerahkan</div>
        <div class="signame">{{ trim(($request->investigator->rank ?? '').' '.($request->investigator->name ?? '')) }}</div>
      </td>
      <td class="sigcell">
        <div class="sigtitle">Yang Menerima</div>
        <div class="signame">Staff Laboratorium Farmapol Pusdokkes Polri</div>
      </td>
    </tr>
  </table>

  <div class="footer">
    Dokumen ini dibuat secara elektronis pada {{ $printedAt->translatedFormat('l, d F Y H:i') }} WIB
  </div>

</body>
</html>
