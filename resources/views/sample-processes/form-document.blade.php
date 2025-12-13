@php(
    $title = $stage->value === 'preparation' ? 'Formulir Preparasi Sampel' : 'Formulir Pengujian Instrumen'
)
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 6mm 8mm; }
        body {
            margin: 0;
            padding: 16px 20px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            line-height: 1.35;
            color: #111827;
        }
        header, section { break-inside: avoid; }
        header { margin-bottom: 12px; }
        .section { margin-bottom: 12px; }
        h1 { font-size: 1.55rem; margin-bottom: 4px; }
        h2 { font-size: 1.05rem; margin-bottom: 6px; }
        h3 { font-size: 1rem; margin-bottom: 6px; }
        .info-grid { gap: 10px; }
        .info-grid .font-medium { font-size: 0.9rem; }
        .notes { font-size: 0.8rem; color: #6b7280; }
        .checklist-grid { display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 14px; }
        .checklist-list { list-style: none; margin: 0; padding: 0; column-gap: 16px; }
        .checklist-columns { columns: 2; }
        .checklist-item { break-inside: avoid; display: flex; gap: 8px; align-items: flex-start; margin-bottom: 4px; font-size: 0.88rem; }
        .checklist-box { width: 12px; height: 12px; border: 1px solid #9ca3af; margin-top: 2px; flex-shrink: 0; }
        .signature-section { margin-top: 16px; }
        .signature-single { max-width: 200px; }
        .signature-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .signature-line { height: 55px; border-bottom: 1px solid #9ca3af; }
        @media print {
            body { font-size: 0.85rem; line-height: 1.3; padding: 12px 16px; }
            .checklist-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .checklist-item { font-size: 0.85rem; margin-bottom: 3px; }
            .signature-line { height: 50px; }
        }
    </style>
</head>
<body class="bg-white text-gray-900">
    <header class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-semibold text-primary-900">{{ $title }}</h1>
            <p class="text-xs text-gray-600">Dicetak: {{ now()->format('d M Y H:i') }}</p>
        </div>
        <div class="shrink-0">
            <img src="{{ asset('images/logo-pusdokkes-polri.png') }}" alt="Logo Pusdokkes Polri" class="h-14 w-auto">
        </div>
    </header>

    <section class="section">
        <h2 class="font-medium text-primary-900">Informasi Sampel</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 info-grid text-xs">
            <div>
                <div class="text-gray-500">Nomor Permintaan</div>
                <div class="font-medium text-gray-900">{{ $process->sample->testRequest->request_number ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Nama Sampel</div>
                <div class="font-medium text-gray-900">{{ $process->sample->sample_name }}</div>
            </div>
            <div>
                <div class="text-gray-500">
                    {{ $stage->value === 'preparation' ? 'Zat Aktif' : 'Pemohon' }}
                </div>
                <div class="font-medium text-gray-900">
                    @if($stage->value === 'preparation')
                        {{ $process->sample->active_substance ?? '-' }}
                    @else
                        {{ $process->sample->testRequest->investigator->name ?? '-' }}
                    @endif
                </div>
            </div>
            <div>
                <div class="text-gray-500">Analis</div>
                <div class="font-medium text-gray-900">{{ $process->analyst->display_name_with_title ?? '-' }}</div>
            </div>
        </div>
    </section>

    @if($stage->value === 'preparation')
        <section class="section">
            <h2 class="font-medium text-primary-900">Checklist Preparasi GC-MS</h2>
            <p class="notes mb-2">
                Disusun berdasarkan dokumen Checklist Preparasi Sampel Tablet untuk Analisis GC-MS.
            </p>

            <div class="checklist-grid text-xs">
                <div>
                    <h3 class="font-medium text-primary-800">Checklist Prosedur</h3>
                    <ul class="checklist-list checklist-columns">
                        @foreach([
                            'Ambil satu (1) tablet sampel.',
                            'Gerus tablet hingga menjadi serbuk halus secara merata.',
                            'Masukkan serbuk ke dalam labu ukur 10 mL lalu tambahkan 5 mL metanol grade LC-MS sebagai pelarut.',
                            'Sonikasi campuran menggunakan ultrasonik selama 15 menit untuk membantu proses ekstraksi.',
                            'Tambahkan pelarut hingga tanda batas pada labu ukur 10 mL, kemudian homogenkan (tanpa memindahkan labu).',
                            'Pindahkan seluruh larutan ke dalam tabung sentrifuge yang sesuai.',
                            'Sentrifugasi pada 15.000 rpm selama 5 menit.',
                            'Ambil filtrat jernih sebanyak ~2-3 mL.',
                            'Saring filtrat menggunakan mikrofilter (0.45 &micro;m atau sesuai kebutuhan).',
                            'Masukkan hasil filtrasi ke dalam vial GC yang bersih dan kering.',
                            'Injeksi sampel ke instrumen GC-MS untuk analisis.',
                        ] as $item)
                            <li class="checklist-item">
                                <span class="checklist-box"></span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h3 class="font-medium text-primary-800">Catatan Tambahan</h3>
                    <ul class="checklist-list">
                        @foreach([
                            'Gunakan pelarut grade GC atau LC-MS untuk menghindari kontaminasi.',
                            'Pastikan seluruh peralatan bersih dan bebas residu.',
                            'Lakukan blank run sebelum injeksi sampel bila diperlukan.',
                            'Simpan sisa ekstrak pada suhu 4 &deg;C bila analisis tidak segera dilakukan.',
                        ] as $item)
                            <li class="checklist-item">
                                <span class="checklist-box"></span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    @else
        <section class="section">
            <h2 class="font-medium text-primary-900">Parameter Pengujian Instrumen</h2>
            <ol class="list-decimal pl-5 space-y-1.5 text-xs">
                <li>Nama instrumen dan nomor seri</li>
                <li>Metode/Standar yang digunakan</li>
                <li>Parameter operasi utama (misal: suhu, tekanan, waktu)</li>
                <li>Kalibrasi/standar acuan</li>
                <li>Catatan hasil awal/observasi</li>
            </ol>
        </section>
    @endif

    <section class="signature-section">
        <h2 class="font-medium text-primary-900 mb-2">Tanda Tangan</h2>
        @if($stage->value === 'preparation')
            <div class="signature-single text-xs">
                <div class="signature-line"></div>
                <div class="mt-1">Analis</div>
                <div class="font-medium">{{ $process->analyst->display_name_with_title ?? '________________' }}</div>
            </div>
        @else
            <div class="signature-grid text-xs">
                <div>
                    <div class="signature-line"></div>
                    <div class="mt-1">Analis</div>
                    <div class="font-medium">{{ $process->analyst->display_name_with_title ?? '________________' }}</div>
                </div>
                <div>
                    <div class="signature-line"></div>
                    <div class="mt-1">Penyetuju</div>
                    <div class="font-medium">________________</div>
                </div>
            </div>
        @endif
    </section>

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
