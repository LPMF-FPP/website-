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
        .appendix-card { border: 1px solid #000; padding: 6px; font-size: 8.5pt; }
        .appendix-card-title { font-weight: bold; margin-bottom: 3px; }
        .appendix-card-value { font-weight: bold; font-size: 12pt; margin-bottom: 3px; }
        .muted { color: #666; font-size: 8pt; }
        .visual-card { border: 1px solid #222; padding: 6px; margin-bottom: 6px; page-break-inside: avoid; }
        .visual-title { font-weight: bold; text-transform: uppercase; font-size: 8.5pt; margin-bottom: 5px; }
        .legend-row { font-size: 7.5pt; margin-bottom: 2px; }
        .legend-dot { display: inline-block; width: 7px; height: 7px; margin-right: 3px; vertical-align: middle; }
        .bar-track { height: 7px; background: #e5e7eb; border-radius: 3px; overflow: hidden; }
        .bar-fill { height: 7px; border-radius: 3px; }
        .micro-row { margin-bottom: 3px; font-size: 7.5pt; }
        .summary-pill { border: 1px solid #999; padding: 4px 6px; font-size: 8pt; margin-bottom: 4px; }
        .trend-table { width: 100%; border-collapse: collapse; font-size: 7pt; }
        .trend-table th, .trend-table td { border: 1px solid #ddd; padding: 2px 3px; vertical-align: middle; }
        .trend-table th { background: #f3f4f6; font-weight: bold; }
        .line-chart-image { width: 100%; height: 122px; display: block; margin-bottom: 5px; border: 1px solid #e5e7eb; }

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

    <!-- Lampiran Statistik Dashboard -->
    @php $appendix = $report->report_data['dashboard_appendix'] ?? null; @endphp
    @if(is_array($appendix))
    @php
        $summaryCards = array_values(array_filter(is_array($appendix['summary_cards'] ?? null) ? $appendix['summary_cards'] : [], 'is_array'));
        $summaryTable = array_values(array_filter(is_array($appendix['summary_table'] ?? null) ? $appendix['summary_table'] : [], 'is_array'));
        $appendixCharts = array_values(array_filter(is_array($appendix['charts'] ?? null) ? $appendix['charts'] : [], 'is_array'));
        $chartColors = ['#1d4ed8', '#dc2626', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d'];
        $compactCharts = array_values(array_filter($appendixCharts, fn ($chart) => ! in_array($chart['title'] ?? '', ['Permintaan per Bulan', 'Sampel vs Target IKU'], true)));
        $lineCharts = array_values(array_filter($appendixCharts, fn ($chart) => in_array($chart['title'] ?? '', ['Permintaan per Bulan', 'Sampel vs Target IKU'], true)));
        $topRows = fn ($rows, int $limit = 6): array => array_slice(array_values(array_filter(is_array($rows) ? $rows : [], 'is_array')), 0, $limit);
        $topRowsWithOther = function ($rows, int $limit = 5): array {
            $safeRows = array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
            $top = array_slice($safeRows, 0, $limit);
            $rest = array_slice($safeRows, $limit);

            if ($rest === []) {
                return $top;
            }

            $top[] = [
                'label' => 'Lainnya',
                'count' => array_sum(array_map(fn ($row) => (float) ($row['count'] ?? 0), $rest)),
                'percentage' => round(array_sum(array_map(fn ($row) => (float) ($row['percentage'] ?? 0), $rest)), 1),
            ];

            return $top;
        };
        $maxValue = function ($rows, string $key): float {
            $values = array_map(fn ($row) => (float) ($row[$key] ?? 0), array_values(array_filter(is_array($rows) ? $rows : [], 'is_array')));

            return max(1, ...$values);
        };
        $maxValueForKeys = function ($rows, array $keys): float {
            $values = [];
            foreach (array_values(array_filter(is_array($rows) ? $rows : [], 'is_array')) as $row) {
                foreach ($keys as $key) {
                    $values[] = (float) ($row[$key] ?? 0);
                }
            }

            return max(1, ...$values);
        };
        $barWidth = fn ($value, $max): int => (float) $value <= 0 ? 0 : max(3, (int) round(((float) $value / max(1, (float) $max)) * 100));
        $formatChartValue = function ($value): string {
            $number = (float) $value;

            return rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
        };
        $lineChartDataUri = function (array $rows, string $firstKey, string $secondKey, string $firstColor, string $secondColor, string $firstLabel, string $secondLabel) use ($formatChartValue, $maxValueForKeys): string {
            $safeRows = array_values(array_filter($rows, 'is_array'));
            $width = 720;
            $height = 220;
            $left = 42;
            $right = 18;
            $top = 22;
            $bottom = 44;
            $plotWidth = $width - $left - $right;
            $plotHeight = $height - $top - $bottom;
            $max = $maxValueForKeys($safeRows, [$firstKey, $secondKey]);
            $step = count($safeRows) > 1 ? $plotWidth / (count($safeRows) - 1) : $plotWidth;
            $escape = fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

            $pointsFor = function (string $key) use ($safeRows, $left, $top, $plotHeight, $max, $step): array {
                return collect($safeRows)->map(function ($row, int $index) use ($key, $left, $top, $plotHeight, $max, $step) {
                    $value = (float) ($row[$key] ?? 0);

                    return [
                        'x' => round($left + ($index * $step), 1),
                        'y' => round($top + $plotHeight - (($value / max(1, $max)) * $plotHeight), 1),
                        'value' => $value,
                        'label' => $row['label'] ?? '-',
                    ];
                })->toArray();
            };

            $firstPoints = $pointsFor($firstKey);
            $secondPoints = $pointsFor($secondKey);
            $pointsAttribute = fn (array $points): string => collect($points)->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');

            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">';
            $svg .= '<rect width="'.$width.'" height="'.$height.'" fill="#ffffff"/>';
            $svg .= '<line x1="'.$left.'" y1="'.($top + $plotHeight).'" x2="'.($width - $right).'" y2="'.($top + $plotHeight).'" stroke="#cbd5e1" stroke-width="1"/>';
            $svg .= '<line x1="'.$left.'" y1="'.$top.'" x2="'.$left.'" y2="'.($top + $plotHeight).'" stroke="#cbd5e1" stroke-width="1"/>';

            foreach ([0.25, 0.5, 0.75] as $gridRatio) {
                $gridY = round($top + ($plotHeight * $gridRatio), 1);
                $svg .= '<line x1="'.$left.'" y1="'.$gridY.'" x2="'.($width - $right).'" y2="'.$gridY.'" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4 4"/>';
            }

            $svg .= '<polyline points="'.$pointsAttribute($firstPoints).'" fill="none" stroke="'.$firstColor.'" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>';
            $svg .= '<polyline points="'.$pointsAttribute($secondPoints).'" fill="none" stroke="'.$secondColor.'" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="8 6"/>';

            foreach ($firstPoints as $index => $point) {
                $svg .= '<circle cx="'.$point['x'].'" cy="'.$point['y'].'" r="4" fill="'.$firstColor.'"/>';
                $svg .= '<text x="'.$point['x'].'" y="'.max(12, $point['y'] - 9).'" text-anchor="middle" font-size="11" font-weight="700" fill="'.$firstColor.'">'.$escape($formatChartValue($point['value'])).'</text>';

                $secondPoint = $secondPoints[$index] ?? null;
                if ($secondPoint) {
                    $svg .= '<circle cx="'.$secondPoint['x'].'" cy="'.$secondPoint['y'].'" r="3" fill="'.$secondColor.'"/>';
                    $svg .= '<text x="'.$secondPoint['x'].'" y="'.min($height - 22, $secondPoint['y'] + 17).'" text-anchor="middle" font-size="10" font-weight="700" fill="'.$secondColor.'">'.$escape($formatChartValue($secondPoint['value'])).'</text>';
                }

                $label = $escape($point['label']);
                $svg .= '<text x="'.$point['x'].'" y="'.($height - 16).'" text-anchor="middle" font-size="9" fill="#334155">'.$label.'</text>';
            }

            $svg .= '<rect x="'.$left.'" y="4" width="10" height="10" fill="'.$firstColor.'"/><text x="'.($left + 15).'" y="13" font-size="10" fill="#111827">'.$escape($firstLabel).'</text>';
            $svg .= '<rect x="'.($left + 92).'" y="4" width="10" height="10" fill="'.$secondColor.'"/><text x="'.($left + 107).'" y="13" font-size="10" fill="#111827">'.$escape($secondLabel).'</text>';
            $svg .= '</svg>';

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        };
    @endphp
    <div class="section" style="page-break-before: always;">
        <div class="section-title">Lampiran Statistik Dashboard</div>
        <p class="muted" style="margin-top: 0;">
            Lampiran ini merangkum data chart dan angka dashboard statistik sesuai periode laporan.
        </p>

        <table style="width: 100%; border-collapse: collapse; border: none; margin-bottom: 10px;">
            @foreach(array_chunk($summaryCards, 2) as $cardRow)
                <tr>
                    @foreach($cardRow as $card)
                        <td style="width: 50%; border: none; padding: 3px; vertical-align: top;">
                            <div class="appendix-card">
                                <div class="appendix-card-title">{{ $card['label'] ?? '-' }}</div>
                                <div class="appendix-card-value">{{ $card['value'] ?? 0 }}</div>
                                <div class="muted">{{ $card['note'] ?? '' }}</div>
                            </div>
                        </td>
                    @endforeach
                    @if(count($cardRow) === 1)
                        <td style="width: 50%; border: none; padding: 3px;"></td>
                    @endif
                </tr>
            @endforeach
        </table>

        @foreach($summaryTable as $row)
            <div class="summary-pill">
                <strong>{{ $row['category'] ?? '-' }}</strong>: {{ $row['period_value'] ?? 0 }} periode ini,
                {{ $row['year_value'] ?? 0 }} tahun berjalan, target {{ $row['target'] ?? '-' }}.
                Status: <strong>{{ $row['status'] ?? '-' }}</strong>
            </div>
        @endforeach

        <table style="width: 100%; border-collapse: collapse; border: none; margin-top: 8px;">
            @foreach(array_chunk($compactCharts, 2) as $chartRow)
                <tr>
                    @foreach($chartRow as $chart)
                        @php
                            $rows = is_array($chart['rows'] ?? null) ? $chart['rows'] : [];
                            $type = $chart['type'] ?? '';
                            $chartTitle = $chart['title'] ?? 'Data Dashboard';
                        @endphp
                        <td style="width: 50%; border: none; padding: 3px; vertical-align: top;">
                            <div class="visual-card">
                                <div class="visual-title">{{ $chartTitle }}</div>

                                @if(empty($rows))
                                    <div class="muted" style="text-align: center; padding: 10px; border: 1px dashed #aaa;">Tidak ada data pada periode ini.</div>
                                @elseif(in_array($type, ['pie', 'doughnut'], true))
                                    @php
                                        $pieRows = $topRowsWithOther($rows, 5);
                                        $pieTotal = max(1, array_sum(array_map(fn ($row) => (float) ($row['count'] ?? 0), $pieRows)));
                                    @endphp
                                    @foreach($pieRows as $index => $row)
                                        @php
                                            $slice = ((float) ($row['count'] ?? 0) / $pieTotal) * 100;
                                            $color = $chartColors[$index % count($chartColors)];
                                        @endphp
                                        <div class="micro-row">
                                            <div style="margin-bottom: 1px;">
                                                <span class="legend-dot" style="background: {{ $color }};"></span>{{ $row['label'] ?? '-' }}
                                                <strong style="float: right;">{{ $row['count'] ?? 0 }} ({{ $row['percentage'] ?? 0 }}%)</strong>
                                            </div>
                                            <div class="bar-track"><div class="bar-fill" style="width: {{ $barWidth($slice, 100) }}%; background: {{ $color }};"></div></div>
                                        </div>
                                    @endforeach
                                @else
                                    @php $maxCount = $maxValue($rows, 'count'); @endphp
                                    @foreach($topRows($rows, 6) as $index => $row)
                                        <div class="micro-row">
                                            <div style="margin-bottom: 1px;">{{ $row['label'] ?? '-' }} <strong style="float: right;">{{ $row['count'] ?? 0 }} ({{ $row['percentage'] ?? 0 }}%)</strong></div>
                                            <div class="bar-track"><div class="bar-fill" style="width: {{ $barWidth($row['count'] ?? 0, $maxCount) }}%; background: {{ $chartColors[$index % count($chartColors)] }};"></div></div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                    @endforeach
                    @if(count($chartRow) === 1)
                        <td style="width: 50%; border: none; padding: 3px;"></td>
                    @endif
                </tr>
            @endforeach
        </table>

        @foreach($lineCharts as $chart)
            @php
                $rows = is_array($chart['rows'] ?? null) ? $chart['rows'] : [];
                $chartTitle = $chart['title'] ?? 'Data Dashboard';
                $trendRows = $topRows($rows, 12);
            @endphp
            <div class="visual-card" style="margin-top: 6px;">
                <div class="visual-title">{{ $chartTitle }}</div>

                @if(empty($trendRows))
                    <div class="muted" style="text-align: center; padding: 10px; border: 1px dashed #aaa;">Tidak ada data pada periode ini.</div>
                @else
                    @php
                        $firstKey = $chartTitle === 'Permintaan per Bulan' ? 'requests' : 'samples';
                        $secondKey = $chartTitle === 'Permintaan per Bulan' ? 'completed' : 'target';
                        $firstColor = $chartTitle === 'Permintaan per Bulan' ? '#1d4ed8' : '#059669';
                        $secondColor = $chartTitle === 'Permintaan per Bulan' ? '#059669' : '#dc2626';
                        $firstLabel = $chartTitle === 'Permintaan per Bulan' ? 'Masuk' : 'Aktual';
                        $secondLabel = $chartTitle === 'Permintaan per Bulan' ? 'Selesai' : 'Target';
                        $scaleKeys = [$firstKey, $secondKey];
                        $maxTrendValue = $maxValueForKeys($trendRows, $scaleKeys);
                        $lineChartSrc = $lineChartDataUri($trendRows, $firstKey, $secondKey, $firstColor, $secondColor, $firstLabel, $secondLabel);
                    @endphp
                    <img class="line-chart-image" src="{{ $lineChartSrc }}" alt="Line chart {{ $chartTitle }}">
                    <table class="trend-table">
                        <thead>
                            <tr>
                                <th style="width: 18%;">Bulan</th>
                                <th>{{ $firstLabel }}</th>
                                <th>{{ $secondLabel }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trendRows as $row)
                                <tr>
                                    <td>{{ $row['label'] ?? '-' }}</td>
                                    <td>
                                        <div style="margin-bottom: 1px;"><strong>{{ $row[$firstKey] ?? 0 }}</strong></div>
                                        <div class="bar-track"><div class="bar-fill" style="width: {{ $barWidth($row[$firstKey] ?? 0, $maxTrendValue) }}%; background: {{ $firstColor }};"></div></div>
                                    </td>
                                    <td>
                                        <div style="margin-bottom: 1px;"><strong>{{ $row[$secondKey] ?? 0 }}</strong></div>
                                        <div class="bar-track"><div class="bar-fill" style="width: {{ $barWidth($row[$secondKey] ?? 0, $maxTrendValue) }}%; background: {{ $secondColor }};"></div></div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="legend-row"><span class="legend-dot" style="background: {{ $firstColor }};"></span>{{ $firstLabel }} <span class="legend-dot" style="background: {{ $secondColor }}; margin-left: 8px;"></span>{{ $secondLabel }} <strong style="float: right;">{{ $chart['total'] ?? 0 }} total</strong></div>
                @endif
            </div>
        @endforeach
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
