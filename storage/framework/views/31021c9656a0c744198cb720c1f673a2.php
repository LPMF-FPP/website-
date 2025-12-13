<?php
    use Carbon\Carbon;
    Carbon::setLocale('id');
    $now = isset($generatedAt) ? \Carbon\Carbon::parse($generatedAt) : now();
    $sample = $process->sample ?? null;
    $req    = $sample?->testRequest;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Formulir Preparasi — <?php echo e($sample->sample_name ?? 'Sampel'); ?></title>
<style>
  /* DOMPDF-safe styles */
  @page { size: A4; margin: 16mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11pt; color:#000; line-height:1.45; }
  h1 { font-size: 16pt; margin: 0 0 10px; }
  h2 { font-size: 13pt; margin: 14px 0 6px; }
  .muted { color:#444; }
  .hr { border-top: 1px solid #000; height: 1px; margin: 8px 0 10px; }
  ol { margin: 6px 0 0 18px; }
  ol li { margin: 4px 0; }
  ul { margin: 6px 0 0 18px; }
  .meta { font-size: 10pt; margin: 8px 0 12px; }
  .right { text-align: right; }
  .sig { width: 100%; margin-top: 28px; }
  .sig td { width: 50%; vertical-align: top; }
  .sigcell { padding: 6px 8px; }
  .sigtitle { font-weight: bold; text-align: center; margin-bottom: 56px; }
  .signame { text-align: center; text-decoration: underline; font-weight: bold; }
</style>
</head>
<body>

  <h1><strong>Preparasi Sampel Tablet untuk Analisis GC&ndash;MS</strong></h1>

  <div class="meta">
    <div><strong>Sampel:</strong> <?php echo e($sample->sample_name ?? '-'); ?></div>
    <div><strong>Kode Sampel:</strong> <?php echo e($sample->sample_code ?? ($sample->id ?? '-')); ?></div>
    <div><strong>Nomor Permintaan:</strong> <?php echo e($req->request_number ?? '-'); ?></div>
    <div><strong>Tanggal:</strong> <?php echo e($now->translatedFormat('l, d F Y')); ?></div>
  </div>

  <h2><strong>Tujuan</strong></h2>
  <p>Menyiapkan sampel tablet untuk analisis menggunakan instrumen <strong>Gas Chromatography&ndash;Mass Spectrometry (GC&ndash;MS).</strong></p>

  <div class="hr"></div>

  <h2><strong>Prosedur</strong></h2>
  <ol>
    <li><strong>Ambil</strong> satu (1) tablet sampel.</li>
    <li><strong>Gerus</strong> tablet hingga menjadi serbuk halus secara merata.</li>
    <li><strong>Masukkan</strong> serbuk ke dalam labu ukur 10 mL, kemudian <strong>tambahkan 5 mL metanol grade LC&ndash;MS</strong> sebagai pelarut.</li>
    <li><strong>Sonikasi</strong> campuran tersebut menggunakan <strong>ultrasonik selama 15 menit</strong> untuk membantu proses ekstraksi.</li>
    <li><strong>Tambahkan pelarut hingga tanda batas pada labu ukur 10 mL</strong>, kemudian <strong>homogenkan</strong> (tanpa pemindahan labu).</li>
    <li><strong>Pindahkan</strong> seluruh larutan ke dalam tabung sentrifuge yang sesuai.</li>
    <li><strong>Sentrifugasi</strong> pada <strong>15.000 rpm selama 5 menit.</strong></li>
    <li><strong>Ambil filtrat</strong> jernih sebanyak &plusmn;2&ndash;3 mL.</li>
    <li><strong>Saring</strong> filtrat tersebut menggunakan <strong>mikrofilter (0.45 &micro;m atau sesuai kebutuhan).</strong></li>
    <li><strong>Masukkan</strong> hasil filtrasi ke dalam <strong>vial GC</strong> yang bersih dan kering.</li>
    <li><strong>Injeksi</strong> sampel ke instrumen <strong>GC&ndash;MS</strong> untuk analisis.</li>
  </ol>

  <div class="hr"></div>

  <h2><strong>Catatan Tambahan</strong></h2>
  <ul>
    <li>Gunakan <strong>pelarut grade GC atau LC&ndash;MS</strong> untuk menghindari kontaminasi.</li>
    <li>Pastikan seluruh peralatan bersih dan bebas residu.</li>
    <li>Lakukan <strong>blank run</strong> sebelum injeksi sampel bila perlu.</li>
    <li>Simpan sisa ekstrak di suhu 4 &deg;C bila analisis tidak segera dilakukan.</li>
  </ul>

  <table class="sig">
    <tr>
      <td class="sigcell"></td>
      <td class="sigcell">
        <div class="sigtitle">Analis</div>
        <div class="signame"><?php echo e($process->analyst_name ?? '__________________________'); ?></div>
      </td>
    </tr>
  </table>

  <p class="muted right">Dibuat pada <?php echo e($now->translatedFormat('l, d F Y H:i')); ?> WIB</p>

</body>
</html>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/pdf/form-preparation.blade.php ENDPATH**/ ?>