<?php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $receivedAt = $request->received_at ? Carbon::parse($request->received_at) : now();
    $printedAt  = isset($generatedAt) ? Carbon::parse($generatedAt) : now();

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

    $testsSummary = $request->samples->map(fn($s) => $formatMethods($s->test_methods))->filter()->unique()->join('; ');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Berita Acara Penerimaan — <?php echo e($request->request_number); ?></title>
<style>
  /* DOMPDF-SAFE CSS (NO grid/flex) */
  @page { size: A4; margin: 16mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; line-height: 1.4; }

  .meta { font-size: 9pt; color:#000; }
  .title { text-align:center; font-size:16pt; font-weight:bold; text-transform:uppercase; margin: 10px 0 8px; }

  table { border-collapse: collapse; width:100%; }
  .header-table td { vertical-align:middle; }
  .header-center { text-align:center; line-height:1.2; }
  .instansi { font-weight:bold; text-transform:uppercase; font-size:12pt; }
  .lab { font-weight:bold; text-transform:uppercase; font-size:11pt; }

  .kv-table { margin: 10px 0; }
  .kv-table td { padding:4px 6px; }
  .kv-label { white-space:nowrap; width: 200px; }
  .kv-sep   { width: 12px; }
  .kv-value { width: auto; }

  .section-title { font-size:12pt; font-weight:bold; margin: 12px 0 6px; }

  .list-table { font-size:10pt; table-layout: fixed; margin-top: 6px; }
  .list-table th, .list-table td { border:1px solid #000; padding:6px 8px; vertical-align:top; }
  .list-table th { text-align:center; background:#f0f0f0; }
  .col-name  { width: 46%; }
  .col-tests { width: 34%; }
  .col-act   { width: 20%; }

  .sign-table { width:100%; margin-top:22px; border:0; border-collapse:separate; }
  .sign-table td { width:50%; vertical-align:top; border:0; }
  .sigcell { padding:6px 8px; }
  .sigtitle { text-align:center; font-weight:bold; margin-bottom:56px; }
  .signame { text-align:center; text-decoration: underline; font-weight:bold; }

  .footer { margin-top: 18px; font-size:9pt; color:#555; }
</style>
</head>
<body>

  <table class="header-table">
    <tr>
      <td style="width:80px; text-align:left;">
        <?php if(file_exists(public_path('images/logo-tribrata-polri.png'))): ?>
          <img src="<?php echo e(public_path('images/logo-tribrata-polri.png')); ?>" alt="Logo Polri" style="height:60px;">
        <?php endif; ?>
      </td>
      <td class="header-center">
        <div class="instansi">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</div>
        <div class="lab">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</div>
        <div class="meta">Jl. Cipinang Baru Raya No. 3B, Jakarta Timur 13240 | Telp/Fax: 021-4700921 | Email: labmutufarmapol@gmail.com</div>
      </td>
      <td style="width:80px; text-align:right;">
        <?php if(file_exists(public_path('images/logo-pusdokkes-polri.png'))): ?>
          <img src="<?php echo e(public_path('images/logo-pusdokkes-polri.png')); ?>" alt="Logo Pusdokkes" style="height:60px;">
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <div class="title">BERITA ACARA PENERIMAAN SAMPEL</div>

  <p>
    Pada hari ini, <b><?php echo e($receivedAt->translatedFormat('l, d F Y')); ?></b>,
    telah diterima sampel untuk keperluan pengujian di Laboratorium Pengujian Mutu Farmasi Kepolisian,
    Pusat Kedokteran dan Kesehatan Polri, dengan rincian sebagai berikut:
  </p>

  <table class="kv-table">
    <tr><td class="kv-label">Nomor Permintaan</td><td class="kv-sep">:</td><td class="kv-value"><b><?php echo e($request->request_number); ?></b></td></tr>
    <tr><td class="kv-label">Nomor Surat Permintaan</td><td class="kv-sep">:</td><td class="kv-value"><?php echo e($request->case_number ?? '-'); ?></td></tr>
    <tr><td class="kv-label">Ditujukan Kepada</td><td class="kv-sep">:</td><td class="kv-value"><?php echo e($request->to_office ?? 'Kepala Sub Satker Farmapol Pusdokkes Polri'); ?></td></tr>
    <tr><td class="kv-label">Penyerah Sampel</td><td class="kv-sep">:</td><td class="kv-value"><?php echo e(trim(($request->investigator->rank ?? '').' '.($request->investigator->name ?? ''))); ?> (NRP: <?php echo e($request->investigator->nrp ?? '-'); ?>)</td></tr>
    <tr><td class="kv-label">Unit/Satuan</td><td class="kv-sep">:</td><td class="kv-value"><?php echo e($request->investigator->jurisdiction ?? '-'); ?></td></tr>
    <tr><td class="kv-label">Jumlah Sampel</td><td class="kv-sep">:</td><td class="kv-value"><b><?php echo e($request->samples->count()); ?></b> sampel</td></tr>
    <tr><td class="kv-label">Jenis Pengujian</td><td class="kv-sep">:</td><td class="kv-value"><?php echo e($testsSummary ?: '-'); ?></td></tr>
  </table>

  <div class="section-title">Daftar Sampel yang Diterima</div>
  <table class="list-table">
    <thead>
      <tr>
        <th class="col-name">Nama Sampel</th>
        <th class="col-tests">Jenis Pengujian</th>
        <th class="col-act">Zat Aktif</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $request->samples; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $sample): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="col-name"><b><?php echo e($sample->sample_name); ?></b></td>
        <td class="col-tests"><?php echo e($formatMethods($sample->test_methods)); ?></td>
        <td class="col-act"><?php echo e($sample->active_substance ?? '-'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>

  <p style="margin-top:12px;">Demikian Berita Acara ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

  <table class="sign-table">
    <tr>
      <td class="sigcell">
        <div class="sigtitle">Yang Menyerahkan</div>
        <div class="signame"><?php echo e(trim(($request->investigator->rank ?? '').' '.($request->investigator->name ?? ''))); ?></div>
      </td>
      <td class="sigcell">
        <div class="sigtitle">Yang Menerima</div>
        <div class="signame">Staff Laboratorium Farmapol Pusdokkes Polri</div>
      </td>
    </tr>
  </table>

  <div class="footer">
    Dokumen ini dibuat secara elektronis pada <?php echo e($printedAt->translatedFormat('l, d F Y H:i')); ?> WIB
  </div>

</body>
</html>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/pdf/berita-acara-penerimaan.blade.php ENDPATH**/ ?>