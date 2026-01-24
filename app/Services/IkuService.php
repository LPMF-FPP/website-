<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerSurvey;
use App\Models\Document;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Repositories\SettingsRepository;
use Carbon\Carbon;

/**
 * Service untuk menghitung Indeks Kinerja Utama (IKU).
 *
 * IKU dihitung sebagai indeks 0-5 menggunakan 4 komponen berbobot:
 * - Registrasi Permohonan (R) = A / B
 * - Pemeriksaan Laboratorium (P) = C / D
 * - Laporan Hasil Pemeriksaan (L) = E / A
 * - Survei Kepuasan (S) = F / A
 *
 * Dimana:
 * - A = jumlah permohonan dikerjakan
 * - B = jumlah permohonan diterima
 * - C = jumlah sampel dikerjakan
 * - D = target sampel (konfigurasi per tahun)
 * - E = jumlah laporan diterbitkan
 * - F = jumlah survey diterima
 */
class IkuService
{
    // Default weights (sum = 100)
    public const DEFAULT_WEIGHTS = [
        'registration' => 10,
        'lab_exam' => 40,
        'report' => 40,
        'survey' => 10,
    ];

    // Default sources
    public const DEFAULT_SOURCES = [
        'A' => 'requests_completed_count',
        'B' => 'requests_submitted_count',
        'C' => 'samples_completed_count',
        'E' => 'lhu_issued_count',
    ];

    public const DEFAULT_TARGET_SAMPLES_BY_YEAR = [
        '2025' => 500,
        '2026' => 600,
        '2027' => 700,
    ];

    public function __construct(
        private readonly SettingsRepository $settings
    ) {}

    /**
     * Get IKU configuration from settings.
     */
    public function getConfig(): array
    {
        return [
            'enabled' => (bool) $this->settings->get('iku.enabled', true),
            'period_mode' => $this->settings->get('iku.period_mode', 'monthly'),
            'weights' => [
                'registration' => (int) $this->settings->get('iku.weights.registration', self::DEFAULT_WEIGHTS['registration']),
                'lab_exam' => (int) $this->settings->get('iku.weights.lab_exam', self::DEFAULT_WEIGHTS['lab_exam']),
                'report' => (int) $this->settings->get('iku.weights.report', self::DEFAULT_WEIGHTS['report']),
                'survey' => (int) $this->settings->get('iku.weights.survey', self::DEFAULT_WEIGHTS['survey']),
            ],
            'target_samples_by_year' => $this->settings->get('iku.target_samples_by_year', self::DEFAULT_TARGET_SAMPLES_BY_YEAR),
            'sources' => [
                'A' => $this->settings->get('iku.sources.A', self::DEFAULT_SOURCES['A']),
                'B' => $this->settings->get('iku.sources.B', self::DEFAULT_SOURCES['B']),
                'C' => $this->settings->get('iku.sources.C', self::DEFAULT_SOURCES['C']),
                'E' => $this->settings->get('iku.sources.E', self::DEFAULT_SOURCES['E']),
            ],
            'survey_required_for_delivery' => (bool) $this->settings->get('iku.survey_required_for_delivery', true),
        ];
    }

    /**
     * Compute IKU for a given period.
     *
     * @return array{
     *     iku_value: float,
     *     iku_category: string,
     *     components: array{R: float, P: float, L: float, S: float},
     *     indexes: array{registration: float, lab_exam: float, report: float, survey: float},
     *     raw_counts: array{A: int, B: int, C: int, D: int, E: int, F: int},
     *     weights: array{registration: int, lab_exam: int, report: int, survey: int},
     *     period: array{start: string, end: string}
     * }
     */
    public function computeForPeriod(Carbon $start, Carbon $end): array
    {
        $config = $this->getConfig();
        $weights = $config['weights'];
        $sources = $config['sources'];

        // Get raw counts based on configured sources
        $A = $this->getCount($sources['A'], $start, $end);
        $B = $this->getCount($sources['B'], $start, $end);
        $C = $this->getCount($sources['C'], $start, $end);
        $D = $this->getTargetSamples($start->year, $config['target_samples_by_year']);

        if ($config['period_mode'] === 'quarterly') {
            $D = (int) max(1, floor($D / 4));
        }

        $E = $this->getCount($sources['E'], $start, $end);
        $F = $this->getSurveyCount($start, $end);

        // Calculate ratios (handle divide by zero, clamp to [0, 1])
        $R = $this->safeRatio($A, $B);
        $P = $this->safeRatio($C, $D);
        $L = $this->safeRatio($E, $A);
        $S = $this->safeRatio($F, $A);

        // Calculate component indexes: Index_i = (Nilai_i * Bobot_i) / 20
        // Nilai_i is the ratio (0-1) scaled to 0-5 for calculation
        $indexRegistration = ($R * 5 * $weights['registration']) / 100;
        $indexLabExam = ($P * 5 * $weights['lab_exam']) / 100;
        $indexReport = ($L * 5 * $weights['report']) / 100;
        $indexSurvey = ($S * 5 * $weights['survey']) / 100;

        // Total IKU (0-5 scale)
        $ikuValue = round($indexRegistration + $indexLabExam + $indexReport + $indexSurvey, 2);

        return [
            'iku_value' => $ikuValue,
            'iku_category' => $this->getCategory($ikuValue),
            'components' => [
                'R' => round($R, 4),
                'P' => round($P, 4),
                'L' => round($L, 4),
                'S' => round($S, 4),
            ],
            'indexes' => [
                'registration' => round($indexRegistration, 4),
                'lab_exam' => round($indexLabExam, 4),
                'report' => round($indexReport, 4),
                'survey' => round($indexSurvey, 4),
            ],
            'raw_counts' => [
                'A' => $A,
                'B' => $B,
                'C' => $C,
                'D' => $D,
                'E' => $E,
                'F' => $F,
            ],
            'weights' => $weights,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Compute IKU for current month (dashboard default).
     */
    public function computeForCurrentMonth(): array
    {
        $config = $this->getConfig();

        if ($config['period_mode'] === 'yearly') {
            return $this->computeForPeriod(
                now()->startOfYear(),
                now()->endOfYear()
            );
        }

        if ($config['period_mode'] === 'quarterly') {
            return $this->computeForCurrentQuarter();
        }

        return $this->computeForPeriod(
            now()->startOfMonth(),
            now()->endOfMonth()
        );
    }

    /**
     * Compute IKU for current quarter.
     */
    public function computeForCurrentQuarter(): array
    {
        $now = Carbon::now();
        $quarter = $now->quarter;
        $start = $now->copy()->startOfQuarter();
        $end = $now->copy()->endOfQuarter();

        $result = $this->computeForPeriod($start, $end);
        $result['quarter'] = $quarter;
        $result['quarter_label'] = "Triwulan {$quarter} ".$now->year;

        return $result;
    }

    /**
     * Get count based on source type.
     */
    private function getCount(string $source, Carbon $start, Carbon $end): int
    {
        return match ($source) {
            'requests_submitted_count' => $this->getRequestsSubmittedCount($start, $end),
            'requests_completed_count' => $this->getRequestsCompletedCount($start, $end),
            'lhu_issued_count' => $this->getLhuIssuedCount($start, $end),
            'samples_completed_count' => $this->getSamplesCompletedCount($start, $end),
            default => 0,
        };
    }

    private function getRequestsSubmittedCount(Carbon $start, Carbon $end): int
    {
        return TestRequest::whereBetween('submitted_at', [$start, $end])
            ->orWhere(function ($query) use ($start, $end) {
                $query->whereNull('submitted_at')
                    ->whereBetween('created_at', [$start, $end]);
            })
            ->count();
    }

    private function getRequestsCompletedCount(Carbon $start, Carbon $end): int
    {
        return TestRequest::whereIn('status', ['completed', 'ready_for_delivery', 'delivered'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('completed_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('completed_at')
                            ->whereBetween('updated_at', [$start, $end])
                            ->whereIn('status', ['completed', 'ready_for_delivery', 'delivered']);
                    });
            })
            ->count();
    }

    private function getLhuIssuedCount(Carbon $start, Carbon $end): int
    {
        // Count LHU documents - check multiple possible document_type values
        return Document::whereIn('document_type', ['laporan_hasil_uji', 'lhu'])
            ->where('source', 'generated')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private function getSamplesCompletedCount(Carbon $start, Carbon $end): int
    {
        // Count samples with completed status (use valid enum values from SampleStatus enum)
        // ready_for_delivery is the final state, interpretation_done is also considered completed
        return Sample::whereIn('sample_status', [
            'ready_for_delivery',
            'interpretation_done',
            'tested',  // Legacy status
            'completed', // Legacy status
        ])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('testing_completed_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('testing_completed_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            })
            ->count();
    }

    private function getSurveyCount(Carbon $start, Carbon $end): int
    {
        return CustomerSurvey::whereBetween('submitted_at', [$start, $end])->count();
    }

    private function getTargetSamples(int $year, array|string|null $targetsByYear): int
    {
        if (is_string($targetsByYear)) {
            $targetsByYear = json_decode($targetsByYear, true) ?? [];
        }

        if (! is_array($targetsByYear)) {
            $targetsByYear = self::DEFAULT_TARGET_SAMPLES_BY_YEAR;
        }

        return (int) ($targetsByYear[(string) $year] ?? $targetsByYear[array_key_last($targetsByYear)] ?? 500);
    }

    /**
     * Safe division with clamping to [0, 1].
     */
    private function safeRatio(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        $ratio = $numerator / $denominator;

        // Clamp to [0, 1] to handle data mismatches
        return max(0.0, min(1.0, $ratio));
    }

    /**
     * Get IKU category (A-F based on value).
     */
    private function getCategory(float $iku): string
    {
        return match (true) {
            $iku >= 4.5 => 'A',
            $iku >= 3.5 => 'B',
            $iku >= 2.5 => 'C',
            $iku >= 1.5 => 'D',
            $iku >= 0.5 => 'E',
            default => 'F',
        };
    }

    /**
     * Update IKU configuration.
     *
     * @param  array  $data  Configuration data to update
     * @param  int|null  $userId  User performing the update
     */
    public function updateConfig(array $data, ?int $userId = null): void
    {
        // Get current config first to merge with updates
        $currentConfig = $this->getConfig();

        // Merge updates into current config
        if (isset($data['enabled'])) {
            $this->settings->put('iku.enabled', (bool) $data['enabled'], $userId);
        }

        if (isset($data['period_mode'])) {
            $this->settings->put('iku.period_mode', $data['period_mode'], $userId);
        }

        if (isset($data['survey_required_for_delivery'])) {
            $this->settings->put('iku.survey_required_for_delivery', (bool) $data['survey_required_for_delivery'], $userId);
        }

        // Handle weights - save individually for proper dot notation
        if (isset($data['weights']) && is_array($data['weights'])) {
            foreach (['registration', 'lab_exam', 'report', 'survey'] as $weightKey) {
                if (isset($data['weights'][$weightKey])) {
                    $this->settings->put("iku.weights.{$weightKey}", (int) $data['weights'][$weightKey], $userId);
                }
            }
        }

        // Handle target_samples_by_year - save as a single object
        if (isset($data['target_samples_by_year']) && is_array($data['target_samples_by_year'])) {
            $this->settings->put('iku.target_samples_by_year', $data['target_samples_by_year'], $userId);
        }

        // Handle sources
        if (isset($data['sources']) && is_array($data['sources'])) {
            foreach (['A', 'B', 'C', 'E'] as $sourceKey) {
                if (isset($data['sources'][$sourceKey])) {
                    $this->settings->put("iku.sources.{$sourceKey}", $data['sources'][$sourceKey], $userId);
                }
            }
        }

        // Clear settings cache
        settings_forget_cache();
    }
}
