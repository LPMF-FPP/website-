<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Gabungan Periodik - {{ $report->period_label }}</title>
    <style>
        @page { size: A4; margin: 12mm 12mm 20mm 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; line-height: 1.28; margin: 0; padding-bottom: 15mm; }

        /* Kop Surat - matching berita-acara-penerimaan.blade.php */
        .header { position: relative; margin: 0 0 6px; min-height: 52px; padding: 0 72px; border-bottom: 1px solid #000; padding-bottom: 4px; }
        .logo { height: 52px; position: absolute; top: 0; }
        .logo-left { left: 0; }
        .logo-right { right: 0; }
        .center { text-align: center; line-height: 1.18; }
        .instansi { font-weight: 700; text-transform: uppercase; margin: 0; font-size: 11pt; }
        .lab { font-weight: 700; text-transform: uppercase; margin: 0; font-size: 12pt; }
        .meta { font-size: 8.8pt; margin: 1px 0 0; }

        /* Judul */
        .title { text-align: center; margin-bottom: 20px; margin-top: 10px; }
        .title h1 { font-size: 14pt; font-weight: bold; text-decoration: underline; text-transform: uppercase; margin: 0; }
        .title p { font-size: 11pt; font-weight: bold; margin: 5px 0 0; }

        /* General Content */
        p { text-align: justify; margin-bottom: 10px; }
        .section-title { font-weight: bold; margin: 12px 0 6px; text-transform: uppercase; font-size: 10pt; }

        /* Tables */
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10pt; }
        table.data-table th, table.data-table td { border: 1px solid #000; padding: 4px 6px; }
        table.data-table th { background-color: #f0f0f0; text-align: center; font-weight: bold; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-green { color: #166534; }
        .text-red { color: #991b1b; }

        /* Compact Box */
        .compact-box { border: 1px solid #000; padding: 6px; margin-bottom: 10px; font-size: 9pt; }
        .compact-box p { margin: 0 0 3px 0; }
        .compact-box ul { margin: 0; padding-left: 15px; }

        /* Signatures - table-based layout for proper column separation */
        .signatures { width: 100%; margin-top: 25px; page-break-inside: avoid; }
        .signature-table { width: 100%; border-collapse: collapse; }
        .signature-table td { width: 33.33%; text-align: center; vertical-align: top; padding: 0 4px; }
        .signature-role { font-size: 9pt; margin-bottom: 5px; }
        .signature-space { height: 60px; }
        .signature-name { 
            font-size: 7.5pt; 
            font-weight: bold; 
            text-decoration: underline; 
            text-transform: uppercase;
            line-height: 1.2;
            word-wrap: break-word;
        }
        .signature-position { 
            font-size: 7pt;
            margin-top: 2px;
            line-height: 1.2;
            word-wrap: break-word;
        }
        .signature-nip { 
            font-size: 7pt;
            margin-top: 1px;
        }

        /* Footer - fixed at bottom of each page */
        .footer { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            height: 15mm;
            font-size: 8pt; 
            color: #555; 
            text-align: right; 
            font-style: italic;
            padding-top: 5mm;
        }
        .page-number:before { content: counter(page); }
    </style>
</head>
<body>
    <!-- Footer on every page -->
    <div class="footer">
        Dicetak secara elektronis pada {{ $report->generated_at->translatedFormat('d F Y H:i') }} WIB | Halaman <span class="page-number"></span>
    </div>

    <!-- Kop Surat -->
    <div class="header">
        @php
            $leftLogoPath = public_path('images/logo-tribrata-polri.png');
            $rightLogoPath = public_path('images/logo-pusdokkes-polri.png');
        @endphp
        @if(file_exists($leftLogoPath))
            <img class="logo logo-left" src="{{ $leftLogoPath }}" alt="Logo Polri">
        @endif
        <div class="center">
            <div class="instansi">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</div>
            <div class="lab">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</div>
            <div class="meta">Jl. Cipinang Baru Raya No. 3B, Jakarta Timur 13240 • Telp/Fax: 021-4700921 • Email: labmutufarmapol@gmail.com</div>
        </div>
        @if(file_exists($rightLogoPath))
            <img class="logo logo-right" src="{{ $rightLogoPath }}" alt="Logo Pusdokkes">
        @endif
    </div>

    <!-- Judul -->
    <div class="title">
        <h1>Laporan Gabungan Periodik</h1>
        <p>Periode: {{ $report->period_label }}</p>
    </div>

    <!-- Narasi Pembuka -->
    <div class="content">
        <p>{!! nl2br(e($report->narrative_sections['opening'] ?? '')) !!}</p>
    </div>

    <!-- I. Statistik Operasional -->
    <div class="section">
        <div class="section-title">I. Statistik Operasional</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40%">Metrik</th>
                    <th style="width: 20%">Nilai Periode Ini</th>
                    <th style="width: 20%">Periode Sebelumnya</th>
                    <th style="width: 20%">Perubahan</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $stats = $report->report_data['statistics'];
                    $changes = $report->comparison_data['changes'] ?? [];
                    
                    $metrics = [
                        'total_requests_received' => 'Permintaan Masuk',
                        'total_requests_completed' => 'Permintaan Selesai',
                        'total_samples_received' => 'Sampel Diterima',
                        'total_samples_tested' => 'Sampel yang Telah Diuji',
                        'total_lhu_issued' => 'LHU Terbit',
                    ];
                @endphp

                @foreach($metrics as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="text-center font-bold">{{ $stats[$key] ?? 0 }}</td>
                        <td class="text-center">{{ $changes[$key]['previous'] ?? '-' }}</td>
                        <td class="text-center">
                            @if(isset($changes[$key]))
                                @php $diff = $changes[$key]['diff']; @endphp
                                <span class="{{ $diff >= 0 ? 'text-green' : 'text-red' }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ $diff }} 
                                    ({{ $changes[$key]['diff_percent'] }}%)
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
                
                <tr>
                    <td>Rata-rata Waktu Pengerjaan</td>
                    <td class="text-center font-bold">{{ $stats['avg_processing_days'] ?? 0 }} hari</td>
                    <td class="text-center">{{ $changes['avg_processing_days']['previous'] ?? '-' }} hari</td>
                    <td class="text-center">
                        @if(isset($changes['avg_processing_days']))
                            @php $diff = $changes['avg_processing_days']['diff']; @endphp
                            <!-- Logic reversed for time: negative is green (faster) -->
                            <span class="{{ $diff <= 0 ? 'text-green' : 'text-red' }}">
                                {{ $diff > 0 ? '+' : '' }}{{ $diff }} 
                                ({{ $changes['avg_processing_days']['diff_percent'] }}%)
                            </span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- II. Rekap Zat Aktif -->
    <div class="section">
        <div class="section-title">II. Rekap Zat Aktif</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 10%">No</th>
                    <th style="width: 50%">Nama Zat Aktif</th>
                    <th style="width: 20%">Jumlah</th>
                    <th style="width: 20%">Persentase</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report->report_data['active_substances']['items'] ?? [] as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td class="text-center">{{ $item['count'] }}</td>
                        <td class="text-center">{{ $item['percentage'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center" style="font-style: italic; color: #777;">
                            Tidak ada zat aktif terdeteksi pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if(!empty($report->report_data['active_substances']['items']))
            <tfoot>
                <tr style="background-color: #f9f9f9; font-weight: bold;">
                    <td colspan="2" class="text-right">TOTAL</td>
                    <td class="text-center">{{ $report->report_data['active_substances']['total'] ?? 0 }}</td>
                    <td class="text-center">100%</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <!-- III. KECEPATAN PENGERJAAN & IV. KEPUASAN PELANGGAN -->
    <div style="page-break-inside: avoid; margin-bottom: 10px;">
        <table style="width: 100%; border-collapse: collapse; border: none;">
            <tr>
                <td style="width: 48%; vertical-align: top; border: none; padding: 0;">
                    @if(isset($report->report_data['processing_time']))
                    <div class="section-title">III. Kecepatan Pengerjaan</div>
                    <div class="compact-box">
                        <p><strong>Rata-rata Waktu Pengerjaan:</strong> {{ $report->report_data['processing_time']['avg_days'] }} hari</p>
                        <p><strong>Total Permintaan Selesai:</strong> {{ $report->report_data['processing_time']['total'] }}</p>
                        <p class="font-bold">Breakdown:</p>
                        <ul>
                            @foreach($report->report_data['processing_time']['categories'] as $item)
                                <li>{{ $item['label'] }}: {{ $item['count'] }} ({{ $item['percentage'] }}%)</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </td>
                <td style="width: 4%; border: none;"></td>
                <td style="width: 48%; vertical-align: top; border: none; padding: 0;">
                    @if(isset($report->report_data['satisfaction']))
                    <div class="section-title">IV. Kepuasan Pelanggan</div>
                    <div class="compact-box">
                        <p><strong>Skor Rata-rata:</strong> {{ $report->report_data['satisfaction']['avg_score'] }} / 4.00</p>
                        <p><strong>Total Responden:</strong> {{ $report->report_data['satisfaction']['total_respondents'] }}</p>
                        <p class="font-bold">Distribusi Rating:</p>
                        <ul>
                            @foreach($report->report_data['satisfaction']['ratings'] as $item)
                                <li>{{ $item['label'] }}: {{ $item['count'] }} ({{ $item['percentage'] }}%)</li>
                            @endforeach
                        </ul>
                        <p style="font-size: 8pt; color: #666; margin-top: 4px; font-style: italic;">*Distribusi berdasarkan pembulatan skor. Rata-rata dihitung dari nilai eksak.</p>
                    </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- V. DEMOGRAFI TERSANGKA & VI. RENTANG UMUR -->
    <div style="page-break-inside: avoid;">
        <table style="width: 100%; border-collapse: collapse; border: none;">
            <tr>
                <td style="width: 48%; vertical-align: top; border: none; padding: 0;">
                    @if(isset($report->report_data['gender']))
                    <div class="section-title">V. Gender Tersangka</div>
                    <div class="compact-box">
                        <p><strong>Total Tersangka:</strong> {{ $report->report_data['gender']['total'] }}</p>
                        <ul>
                            @foreach($report->report_data['gender']['items'] as $item)
                                <li>{{ $item['label'] ?? 'Tidak Diketahui' }}: {{ $item['count'] }} ({{ $item['percentage'] }}%)</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </td>
                <td style="width: 4%; border: none;"></td>
                <td style="width: 48%; vertical-align: top; border: none; padding: 0;">
                    @if(isset($report->report_data['age_range']))
                    <div class="section-title">VI. Rentang Umur</div>
                    <div class="compact-box">
                        <p><strong>Total Tersangka:</strong> {{ $report->report_data['age_range']['total'] }}</p>
                        <ul>
                            @foreach($report->report_data['age_range']['items'] as $item)
                                <li>{{ $item['label'] }}: {{ $item['count'] }} ({{ $item['percentage'] }}%)</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- VII. ASAL USER (TOP 10) -->
    <div class="section" style="margin-top: 8px;">
        <div class="section-title">VII. Asal User (Top 10 Jurisdiction)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 10%">No</th>
                    <th style="width: 50%">Jurisdiction / Satuan</th>
                    <th style="width: 20%">Jumlah</th>
                    <th style="width: 20%">Persentase</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report->report_data['jurisdiction']['items'] as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $item['label'] }}</td>
                        <td class="text-center">{{ $item['count'] }}</td>
                        <td class="text-center">{{ $item['percentage'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center" style="font-style: italic; color: #777;">Tidak ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- VIII. IKU (Only if available) -->
    @if(isset($report->report_data['iku']) && $report->report_data['iku'])
    <div class="section">
        <div class="section-title">VIII. Indeks Kinerja Utama (IKU)</div>
        
        <div style="border: 1px solid #000; padding: 6px 10px; margin-bottom: 8px; background-color: #f9f9f9;">
            <table style="width: 100%">
                <tr>
                    <td style="font-weight: bold;">Nilai IKU: {{ $report->report_data['iku']['iku_value'] }}</td>
                    <td style="text-align: right; font-weight: bold;">Kategori: {{ $report->report_data['iku']['iku_category'] }}</td>
                </tr>
            </table>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Komponen</th>
                    <th>Bobot</th>
                    <th>Nilai Indeks</th>
                    <th>Data Mentah (Realisasi/Target)</th>
                </tr>
            </thead>
            <tbody>
                @php $iku = $report->report_data['iku']; @endphp
                <tr>
                    <td>Registrasi Permohonan (R)</td>
                    <td class="text-center">{{ $iku['weights']['registration'] }}%</td>
                    <td class="text-center">{{ $iku['indexes']['registration'] }}</td>
                    <td class="text-center">{{ $iku['raw_counts']['A'] }} / {{ $iku['raw_counts']['B'] }}</td>
                </tr>
                <tr>
                    <td>Pemeriksaan Laboratorium (P)</td>
                    <td class="text-center">{{ $iku['weights']['lab_exam'] }}%</td>
                    <td class="text-center">{{ $iku['indexes']['lab_exam'] }}</td>
                    <td class="text-center">{{ $iku['raw_counts']['C'] }} / {{ $iku['raw_counts']['D'] }}</td>
                </tr>
                <tr>
                    <td>Laporan Hasil (L)</td>
                    <td class="text-center">{{ $iku['weights']['report'] }}%</td>
                    <td class="text-center">{{ $iku['indexes']['report'] }}</td>
                    <td class="text-center">{{ $iku['raw_counts']['E'] }} / {{ $iku['raw_counts']['A'] }}</td>
                </tr>
                <tr>
                    <td>Survei Kepuasan (S)</td>
                    <td class="text-center">{{ $iku['weights']['survey'] }}%</td>
                    <td class="text-center">{{ $iku['indexes']['survey'] }}</td>
                    <td class="text-center">{{ $iku['raw_counts']['F'] }} / {{ $iku['raw_counts']['A'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Narasi Penutup -->
    <div class="content" style="margin-top: 20px;">
        <p>{!! nl2br(e($report->narrative_sections['closing'] ?? '')) !!}</p>
    </div>

    <!-- Tanda Tangan -->
    <div class="signatures">
        <div style="margin-bottom: 10px; text-align: center;">
            Jakarta, {{ $report->generated_at->translatedFormat('d F Y') }}
        </div>
        
        <table class="signature-table">
            <tr>
                @foreach($report->signers as $signer)
                <td>
                    <div class="signature-role">{{ $signer['role'] }}</div>
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ $signer['name'] }}</div>
                    <div class="signature-position">{{ $signer['position'] }}</div>
                    @if(!empty($signer['nip']))
                        <div class="signature-nip">NIP/NRP: {{ $signer['nip'] }}</div>
                    @endif
                </td>
                @endforeach
            </tr>
        </table>
    </div>

</body>
</html>
