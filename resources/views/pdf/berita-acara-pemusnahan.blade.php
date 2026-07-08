@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $executedAt = $disposal->executed_at;
    $printedAt = now();

    $leftLogoPath = public_path('images/logo-tribrata-polri.png');
    $rightLogoPath = public_path('images/logo-pusdokkes-polri.png');
    $rightLogoSrc = file_exists($rightLogoPath) ? $rightLogoPath : null;
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

    $witnessSigners = collect($disposal->witness_entries_for_display)
        ->map(fn (array $entry) => pdf_build_signer(
            null,
            fallbackName: $entry['name'] ?? '-',
            fallbackIdentity: $entry['identity'] ?? null,
            fallbackRole: $entry['role'] ?? '-'
        ))
        ->values();
    $witnessSummary = $witnessSigners->map(fn (array $signer) => $signer['name'].' ('.$signer['identity'].')')->implode('; ');
    $executorPerson = $disposal->executedBy;
    $executorNumber = $executorPerson?->nrp ?: $executorPerson?->nip;
    $executorNumberLabel = $executorPerson?->nrp ? 'NRP.' : ($executorPerson?->nip ? 'NIP.' : null);
    $executorIdentityFallback = $disposal->executed_by_identity
        ?: trim($executorNumberLabel && $executorNumber ? $executorNumberLabel.' '.$executorNumber : '');
    $executorSigner = pdf_build_signer(
        null,
        fallbackName: $disposal->executed_by_name ?: ($executorPerson?->name ?? '-'),
        fallbackIdentity: $executorIdentityFallback,
        fallbackRole: $disposal->executed_by_role ?: ($executorPerson?->rank ?: 'ANALIS')
    );
    $approverSigner = pdf_build_signer(
        null,
        fallbackName: $disposal->approver_name,
        fallbackIdentity: $disposal->approver_identity,
        fallbackRole: $disposal->approver_role
    );
    $documentationPhotos = collect($disposal->documentation_photos_for_display)
        ->filter(fn (array $photo) => $photo['exists'] && is_file($photo['absolute_path']))
        ->values();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Berita Acara Pemusnahan — {{ $disposal->batch_number }}</title>
<style>
  @page { size: A4; margin: 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.28; margin:0; padding-bottom: 10mm; }

  .header { position: relative; margin:0 0 6px; min-height:52px; padding:0 72px; border-bottom:1px solid #000; padding-bottom:4px; }
  .logo { height:52px; position:absolute; top:0; }
  .logo-left{left:0;} .logo-right{right:0;}
  .center { text-align:center; line-height:1.18; }
  .instansi { font-weight:700; font-size:14pt; text-transform:uppercase; margin:0; }
  .lab { font-weight:700; font-size:12.5pt; text-transform:uppercase; margin:0; }
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
  .col-no { width: 6%; text-align: center; }
  .col-lhu { width: 18%; }
  .col-lp { width: 22%; }
  .col-tersangka { width: 24%; }
  .col-bukti { width: 30%; }
  .col-dok { width: 16%; }
  .doc-grid { width:100%; margin-top:10px; }
  .doc-grid td { width:50%; border:none; padding:6px; vertical-align:top; }
  .doc-card { border:1px solid #bbb; padding:6px; }
  .doc-image { width:100%; max-height:220px; object-fit:contain; display:block; }
  .doc-caption { margin-top:4px; font-size:8pt; text-align:center; color:#444; }

  .sign-table { width:100%; margin-top:10px; border:0; border-collapse:separate; }
  .sign-table td { width:33%; vertical-align:top; border:0; }
  .sigcell { padding:6px 8px; }
  .sigtitle { text-align:center; font-weight:700; margin-bottom:0; }
  .sigspacer { height:60px; }
  .signame { text-align:center; text-decoration: underline; font-weight:700; }
  .sigidentity { text-align:center; font-size:9pt; margin-top:4px; }

  .footer { margin-top: 16px; font-size:9pt; color:#555; }
</style>
</head>
<body>

  <div class="header">
    @if(file_exists($leftLogoPath))
      <img class="logo logo-left" src="{{ $leftLogoPath }}" alt="Logo Polri">
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

  <h1 class="title">Berita Acara Pemusnahan Sisa Sampel Uji</h1>
  <div class="subtitle">Nomor Batch: <b>{{ $disposal->batch_number }}</b></div>

  <p>
    Pada hari ini, <b>{{ $executedAt->translatedFormat('l, d F Y') }}</b> pukul <b>{{ $executedAt->format('H:i') }} WIB</b>,
    telah dilaksanakan pemusnahan sisa sampel uji di Laboratorium Pengujian Mutu Farmasi Kepolisian,
    Pusat Kedokteran dan Kesehatan Polri, dengan rincian sebagai berikut:
  </p>

  <table class="meta-table">
    <tr><td class="label">Nomor Batch Pemusnahan</td><td class="sep">:</td><td class="value nowrap"><strong>{{ $disposal->batch_number }}</strong></td></tr>
    <tr><td class="label">Tanggal Pelaksanaan</td><td class="sep">:</td><td class="value">{{ $executedAt->translatedFormat('d F Y, H:i') }} WIB</td></tr>
    <tr><td class="label">Metode Pemusnahan</td><td class="sep">:</td><td class="value">{{ $disposal->method->label() }}</td></tr>
    <tr><td class="label">Jumlah Sampel</td><td class="sep">:</td><td class="value"><strong>{{ $disposal->samples->count() }}</strong> sampel</td></tr>
    <tr><td class="label">Pelaksana</td><td class="sep">:</td><td class="value">{{ $disposal->executed_by_name ?: ($disposal->executedBy?->display_name_with_title ?? $disposal->executedBy?->name ?? '-') }}</td></tr>
    <tr><td class="label">Saksi</td><td class="sep">:</td><td class="value">{{ $witnessSummary !== '' ? $witnessSummary : '-' }}</td></tr>
    @if($disposal->notes)
    <tr><td class="label">Catatan</td><td class="sep">:</td><td class="value">{{ $disposal->notes }}</td></tr>
    @endif
  </table>

  <div class="section-title">Daftar Sampel yang Dimusnahkan</div>
  <table class="list-table">
    <thead>
      <tr>
        <th class="col-no">No</th>
        <th class="col-lhu">No. LHU</th>
        <th class="col-lp">No. LP / Tgl</th>
        <th class="col-tersangka">Tersangka</th>
        <th class="col-bukti">Bukti Sisa (Asal)</th>
        <th class="col-dok">Dokumentasi</th>
      </tr>
    </thead>
    <tbody>
      @foreach($disposal->samples as $i => $sample)
      @php
          $lhuProcess = $sample->testProcesses->where('stage', 'interpretation')->whereNotNull('completed_at')->first();
          $lhuNumber = $lhuProcess?->metadata['lhu_number'] ?? '-';
          $testRequest = $sample->testRequest;
          $investigator = $testRequest?->investigator;
          $caseNumber = $testRequest?->case_number ?? '-';
          $caseDate = $testRequest?->case_date ? Carbon::parse($testRequest->case_date)->format('d/m/Y') : '';
      @endphp
      <tr>
        <td class="col-no">{{ $i + 1 }}</td>
        <td class="col-lhu">{{ $lhuNumber }}</td>
        <td class="col-lp">{{ $caseNumber }}@if($caseDate)<br><small>{{ $caseDate }}</small>@endif</td>
        <td class="col-tersangka">{{ $testRequest?->suspect_name ?? '-' }}</td>
        <td class="col-bukti">{{ $sample->short_description ?? $sample->sample_form }} ({{ $sample->sample_code }})</td>
        <td class="col-dok">{{ $documentationPhotos->isNotEmpty() ? 'Terlampir' : '-' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  @if($documentationPhotos->isNotEmpty())
    <div class="section-title" style="margin-top:12px;">Dokumentasi Pemusnahan</div>
    <table class="doc-grid">
      @foreach($documentationPhotos->chunk(2) as $photoRow)
        <tr>
          @foreach($photoRow as $photo)
            <td>
              <div class="doc-card">
                <img class="doc-image" src="{{ $photo['absolute_path'] }}" alt="Dokumentasi pemusnahan {{ $loop->parent->iteration }}-{{ $loop->iteration }}">
                <div class="doc-caption">{{ $photo['original_name'] }}</div>
              </div>
            </td>
          @endforeach
          @if($photoRow->count() === 1)
            <td></td>
          @endif
        </tr>
      @endforeach
    </table>
  @endif

  <p style="margin-top:12px;">Demikian Berita Acara ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

  <table class="sign-table">
    <tr>
      <td class="sigcell">
        <div class="sigtitle">Saksi,</div>
        @foreach($witnessSigners as $witnessSigner)
          <div class="sigspacer"></div>
          <div class="signame">{{ $witnessSigner['name'] }}</div>
          <div class="sigidentity">{{ $witnessSigner['identity'] }}</div>
        @endforeach
      </td>
      <td class="sigcell">
        <div class="sigtitle">Pelaksana,</div>
        <div class="sigspacer"></div>
        <div class="signame">{{ $executorSigner['name'] }}</div>
        <div class="sigidentity">{{ $executorSigner['identity'] }}</div>
      </td>
      <td class="sigcell">
        <div class="sigtitle">Mengetahui,<br>Kepala Farmapol</div>
        <div class="sigspacer"></div>
        <div class="signame">{{ $approverSigner['name'] }}</div>
        <div class="sigidentity">{{ $approverSigner['identity'] }}</div>
      </td>
    </tr>
  </table>

  <div class="footer">
    Dicetak pada: {{ $printedAt->translatedFormat('d F Y, H:i') }} WIB
  </div>

</body>
</html>
