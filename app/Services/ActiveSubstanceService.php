<?php

namespace App\Services;

use App\Models\Sample;
use App\Models\TestResult;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActiveSubstanceService
{
    protected array $defaultColors = [
        '#DC2626', '#EA580C', '#D97706', '#65A30D', '#059669', '#0891B2'
    ];

    protected array $fallbackCounts = [];

    protected ?array $cachedCounts = null;
    protected bool $usingFallback = false;

    public function breakdown(int $limit = 6): array
    {
        $counts = $this->loadCounts();
        $uniqueTotal = count($counts);

        $workingCounts = $counts;
        if ($limit > 0 && count($workingCounts) > $limit) {
            $workingCounts = array_slice($workingCounts, 0, $limit, true);
        }

        $total = array_sum($workingCounts);
        $labels = array_keys($workingCounts);
        $data = array_values($workingCounts);
        $percentages = array_map(static function ($value) use ($total) {
            return $total > 0 ? round(($value / $total) * 100, 1) : 0;
        }, $data);

        return [
            'labels' => $labels,
            'data' => $data,
            'percentages' => $percentages,
            'colors' => array_slice($this->defaultColors, 0, count($data)),
            'total' => $total,
            'unique_total' => $uniqueTotal,
            'fallback' => $this->usingFallback,
        ];
    }

    public function totalDetected(): int
    {
        return array_sum($this->loadCounts());
    }

    protected function loadCounts(): array
    {
        if ($this->cachedCounts !== null) {
            return $this->cachedCounts;
        }

        $counts = [];

        try {
            if (class_exists(Sample::class)) {
                $samples = Sample::whereNotNull('active_substance')->pluck('active_substance');

                foreach ($samples as $rawSubstance) {
                    foreach ($this->extractSubstances($rawSubstance) as $substance) {
                        $counts[$substance] = ($counts[$substance] ?? 0) + 1;
                    }
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to load active substance statistics from samples: ' . $exception->getMessage());
        }

        if (empty($counts)) {
            try {
                if (!class_exists(TestResult::class)) {
                    throw new \RuntimeException('TestResult model unavailable');
                }

                $results = TestResult::whereNotNull('active_substances')->get();

                foreach ($results as $result) {
                    foreach ($this->extractSubstances($result->active_substances) as $substance) {
                        $counts[$substance] = ($counts[$substance] ?? 0) + 1;
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('Unable to load fallback active substance statistics from test results: ' . $exception->getMessage());
            }
        }

        if (empty($counts)) {
            $this->usingFallback = true;
            $counts = $this->fallbackCounts;
        } else {
            $this->usingFallback = false;
        }

        arsort($counts);

        return $this->cachedCounts = $counts;
    }

    protected function extractSubstances(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->extractSubstances($decoded);
            }

            $parts = preg_split('/[,;\n]+/', $raw);

            return $this->normalizeParts($parts ?: []);
        }

        if (is_array($raw)) {
            $collected = [];

            foreach ($raw as $item) {
                if (is_array($item) || is_object($item)) {
                    $collected = array_merge($collected, $this->extractSubstances($item));
                    continue;
                }

                $collected = array_merge($collected, $this->extractSubstances((string) $item));
            }

            return $this->normalizeParts($collected);
        }

        if (is_object($raw)) {
            return $this->extractSubstances((array) $raw);
        }

        return $this->normalizeParts([(string) $raw]);
    }

    protected function normalizeParts(array $parts): array
    {
        $normalized = [];

        foreach ($parts as $part) {
            if (is_array($part) || is_object($part)) {
                $normalized = array_merge($normalized, $this->extractSubstances($part));
                continue;
            }

            $name = trim((string) $part);

            if ($name === '') {
                continue;
            }

            if (!preg_match('/[a-zA-Z]/', $name)) {
                continue;
            }

            $name = preg_replace('/\s+/', ' ', $name);
            $name = ucwords(strtolower($name));

            if ($name === 'Unknown') {
                continue;
            }

            $normalized[] = $name;
        }

        return $normalized;
    }
}
