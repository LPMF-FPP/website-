<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Branding</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #111;
        }
        .header {
            border-bottom: 2px solid <?php echo e($branding['primary_color'] ?? '#000'); ?>;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            text-transform: uppercase;
            color: <?php echo e($branding['primary_color'] ?? '#000'); ?>;
        }
        .section {
            margin-bottom: 18px;
        }
        .section h2 {
            font-size: 14px;
            margin-bottom: 6px;
            color: <?php echo e($branding['secondary_color'] ?? '#333'); ?>;
        }
        .footer {
            border-top: 1px solid #ddd;
            padding-top: 10px;
            text-align: center;
            font-size: 10px;
            color: #555;
            position: fixed;
            bottom: 40px;
            left: 40px;
            right: 40px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo e($branding['org_name'] ?? 'Laboratorium'); ?></h1>
        <p><?php echo e($pdf['header']['address'] ?? 'Alamat belum diatur'); ?></p>
        <p><?php echo e($pdf['header']['contact'] ?? ''); ?></p>
    </div>

    <div class="section">
        <h2>Profil Branding</h2>
        <p><strong>Kode Laboratorium:</strong> <?php echo e($branding['lab_code'] ?? '-'); ?></p>
        <p><strong>Warna Primer:</strong> <?php echo e($branding['primary_color'] ?? '#000000'); ?></p>
        <p><strong>Warna Sekunder:</strong> <?php echo e($branding['secondary_color'] ?? '#777777'); ?></p>
    </div>

    <div class="section">
        <h2>Konfigurasi PDF</h2>
        <p><strong>Header:</strong> <?php echo e(($pdf['header']['show'] ?? false) ? 'Aktif' : 'Nonaktif'); ?></p>
        <p><strong>Footer:</strong> <?php echo e(($pdf['footer']['show'] ?? false) ? 'Aktif' : 'Nonaktif'); ?></p>
        <p><strong>Tanda Tangan Otomatis:</strong> <?php echo e(($pdf['signature']['enabled'] ?? false) ? 'Ya' : 'Tidak'); ?></p>
        <p><strong>QR Code:</strong> <?php echo e(($pdf['qr']['enabled'] ?? false) ? 'Ya' : 'Tidak'); ?></p>
    </div>

    <div class="section">
        <h2>Contoh Konten</h2>
        <p>Dokumen ini merupakan contoh tampilan PDF berdasarkan pengaturan branding saat ini.</p>
        <p>Tanggal: <?php echo e(now()->format('d F Y H:i')); ?></p>
    </div>

    <div class="footer">
        <?php echo e($pdf['footer']['text'] ?? 'Rahasia - Hanya untuk keperluan resmi'); ?>

    </div>
</body>
</html>
<?php /**PATH /home/lpmf-dev/website-/resources/views/pdf/settings-preview.blade.php ENDPATH**/ ?>