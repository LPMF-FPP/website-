<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Uji {{ $reportNumber }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #111827; }
        h1 { font-size: 24px; margin-bottom: 8px; }
        h2 { font-size: 18px; margin-top: 32px; margin-bottom: 8px; }
        p { margin: 4px 0; line-height: 1.4; }
        .meta { font-size: 12px; color: #6b7280; margin-bottom: 24px; }
        .section { margin-top: 24px; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .grid-item { padding: 12px; background: #f9fafb; border-radius: 6px; }
        .label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; margin-bottom: 4px; }
        .value { font-size: 14px; font-weight: 600; color: #1f2937; }
    </style>
</head>
<body>
    <h1>Laporan Hasil Uji</h1>
    <div class="meta">
        Nomor Laporan: <strong>{{ $reportNumber }}</strong><br>
        Dicetak pada: {{ $generatedAt->format('d/m/Y H:i') }} WIB
    </div>

    <div class="section">
        <h2>Informasi Sampel</h2>
        <div class="grid">
            <div class="grid-item">
                <div class="label">Nama Sampel</div>
                <div class="value">{{ $sampleProcess->sample?->sample_name ?? '-' }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Kode Sampel</div>
                <div class="value">{{ $sampleProcess->sample?->sample_code ?? '-' }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Nomor Permintaan</div>
                <div class="value">{{ $sampleProcess->sample?->testRequest?->request_number ?? '-' }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Pelaksana</div>
                <div class="value">{{ $sampleProcess->analyst?->display_name_with_title ?? 'Belum ditentukan' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Rincian Pengujian</h2>
        <div class="grid">
            <div class="grid-item">
                <div class="label">Instrumen Pengujian</div>
                <div class="value">{{ $instrument }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Hasil Uji</div>
                <div class="value">{{ $testResultLabel }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Zat Aktif Terdeteksi</div>
                <div class="value">{{ $detectedSubstance }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Tanggal Mulai</div>
                <div class="value">{{ optional($sampleProcess->started_at)->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
            <div class="grid-item">
                <div class="label">Tanggal Selesai</div>
                <div class="value">{{ optional($sampleProcess->completed_at)->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Catatan Interpretasi</h2>
        <p>{{ $sampleProcess->notes ?? 'Tidak ada catatan tambahan.' }}</p>
    </div>
</body>
</html>
