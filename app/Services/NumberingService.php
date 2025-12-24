<?php

namespace App\Services;

use App\Events\NumberIssued;
use App\Models\Sequence;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    /**
     * Issue a new number for the given scope.
     *
     * @param  array<string, mixed>  $context
     */
    public function issue(string $scope, array $context = []): string
    {
        $config = $this->getConfig($scope);

        $pattern = $config['pattern'];
        $reset = $config['reset'] ?? 'never';
        $startFrom = (int) ($config['start_from'] ?? 1);
        $contextWithNow = $this->contextWithNow($context);

        $bucket = $this->makeBucket($scope, $reset, $contextWithNow, $config);

        $number = DB::transaction(function () use ($scope, $bucket, $pattern, $contextWithNow, $startFrom) {
            $sequence = Sequence::query()
                ->where('scope', $scope)
                ->where('bucket', $bucket)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequence = Sequence::create([
                    'scope' => $scope,
                    'bucket' => $bucket,
                    'current_value' => max($startFrom - 1, 0),
                ]);
            }

            $sequence->current_value += 1;
            $sequence->save();

            return $this->render($pattern, $sequence->current_value, $contextWithNow);
        });

        Audit::log('ISSUE_NUMBER', $scope, null, ['number' => $number, 'bucket' => $bucket], [
            'context' => $contextWithNow,
        ]);

        event(new NumberIssued($scope, $number, $context));

        return $number;
    }

    /**
     * Preview the next number without mutating the sequence table.
     *
     * @param  array<string, mixed>  $context
     */
    public function preview(string $scope, array $context = [], ?int $sequenceValue = null): string
    {
        $config = $this->getConfig($scope);
        $pattern = $config['pattern'];
        $reset = $config['reset'] ?? 'never';
        $startFrom = (int) ($config['start_from'] ?? 1);
        $contextWithNow = $this->contextWithNow($context);

        if ($sequenceValue === null) {
            $bucket = $this->makeBucket($scope, $reset, $contextWithNow, $config);
            $sequenceValue = Sequence::query()
                ->where('scope', $scope)
                ->where('bucket', $bucket)
                ->value('current_value') ?? ($startFrom - 1);
            $sequenceValue++;
        }

        return $this->render($pattern, $sequenceValue, $contextWithNow);
    }

    /**
     * Retrieve the current and next numbers for a scope.
     *
     * @param  array<string, mixed>  $context
     * @return array{current:?string,next:string,pattern:string}
     */
    public function currentSnapshot(string $scope, array $context = []): array
    {
        try {
            $config = $this->getConfig($scope);
            $pattern = $config['pattern'];
            $reset = $config['reset'] ?? 'never';
            $startFrom = (int) ($config['start_from'] ?? 1);
            $contextWithNow = $this->contextWithNow($context);
            $bucket = $this->makeBucket($scope, $reset, $contextWithNow, $config);

            $currentValue = Sequence::query()
                ->where('scope', $scope)
                ->where('bucket', $bucket)
                ->value('current_value');

            $issuedValue = $currentValue ?? ($startFrom - 1);
            $currentNumber = $issuedValue >= $startFrom
                ? $this->render($pattern, $issuedValue, $contextWithNow)
                : null;

            $nextValue = max($issuedValue, $startFrom - 1) + 1;
            $nextNumber = $this->render($pattern, $nextValue, $contextWithNow);

            return [
                'current' => $currentNumber,
                'next' => $nextNumber,
                'pattern' => $pattern,
            ];
        } catch (\Exception $e) {
            // Return safe defaults if anything fails
            return [
                'current' => null,
                'next' => 'N/A',
                'pattern' => '{YYYY}-{MM}-{DD}-{SEQ:4}',
            ];
        }
    }

    /**
     * Generate an example number based on the provided or stored pattern.
     *
     * @param  array<string, mixed>  $context
     */
    public function example(string $scope, ?string $pattern = null, array $context = []): string
    {
        $config = $this->getConfig($scope);
        $pattern = $pattern ?? $config['pattern'];

        $defaultContext = [
            'test_code' => 'GCMS',
            'instrument_code' => 'QS2020',
            'request_short' => 'REQ-25-0102',
            'investigator_id' => 7,
            'doc_code' => strtoupper($scope),
        ];

        $contextWithDefaults = $this->contextWithNow($defaultContext + $context);

        return $this->render($pattern, 123, $contextWithDefaults);
    }

    /**
     * Render a pattern into a formatted number.
     *
     * @param  array<string, mixed>  $context
     */
    public function render(string $pattern, int $sequenceValue, array $context): string
    {
        $now = $context['now'] ?? CarbonImmutable::now();

        $sanitizer = fn ($value) => strtoupper(preg_replace('/[^A-Z0-9_-]/', '', (string) ($value ?? '')));

        $map = [
            '{LAB}' => settings('branding.lab_code', 'LAB'),
            '{YYYY}' => $now->format('Y'),
            '{YY}' => $now->format('y'),
            '{MM}' => $now->format('m'),
            // Roman numeral month (I..XII)
            '{RM}' => $this->intToRoman((int) $now->format('m')),
            '{DD}' => $now->format('d'),
            '{INV}' => str_pad((string) ($context['investigator_id'] ?? 0), 2, '0', STR_PAD_LEFT),
            '{TEST}' => $sanitizer($context['test_code'] ?? 'GEN'),
            '{INST}' => $sanitizer($context['instrument_code'] ?? 'NA'),
            '{REQ}' => $sanitizer($context['request_short'] ?? 'REQ'),
            '{DOC}' => $sanitizer($context['doc_code'] ?? 'DOC'),
        ];

        $output = preg_replace_callback('/\{SEQ:(\d+)\}/', function ($matches) use ($sequenceValue) {
            $width = (int) $matches[1];
            $width = max($width, 1);

            return str_pad((string) $sequenceValue, $width, '0', STR_PAD_LEFT);
        }, $pattern);

        return strtr($output, $map);
    }

    /**
     * Convert an integer to uppercase Roman numerals.
     * Intended for month numbers (1..12). Returns empty string for invalid values.
     */
    protected function intToRoman(int $number): string
    {
        if ($number <= 0) {
            return '';
        }

        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $result = '';
        foreach ($map as $value => $symbol) {
            while ($number >= $value) {
                $result .= $symbol;
                $number -= $value;
            }
        }

        return $result;
    }

    /**
     * Build a sequence bucket based on reset strategy and context.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $config
     */
    protected function makeBucket(string $scope, string $reset, array $context, array $config): string
    {
        $now = ($context['now'] ?? null) instanceof CarbonImmutable
            ? $context['now']
            : CarbonImmutable::now();

        $parts = [];

        if ($reset === 'yearly') {
            $parts[] = $now->format('Y');
        } elseif ($reset === 'monthly') {
            $parts[] = $now->format('Y-m');
        } elseif ($reset === 'daily') {
            $parts[] = $now->format('Y-m-d');
        }

        if ($reset === 'per_investigator') {
            $parts[] = 'INV'.str_pad((string) ($context['investigator_id'] ?? 0), 2, '0', STR_PAD_LEFT);
        }

        if ($scope === 'lhu' && Arr::get($config, 'per_test_type')) {
            $parts[] = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', (string) ($context['test_code'] ?? 'GEN')));
        }

        return $parts ? implode('|', $parts) : 'default';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function contextWithNow(array $context): array
    {
        if (isset($context['now']) && $context['now'] instanceof CarbonImmutable) {
            return $context;
        }

        return $context + ['now' => CarbonImmutable::now()];
    }

    protected function getConfig(string $scope): array
    {
        // First try reading from individual dot-notated keys
        $pattern = settings("numbering.$scope.pattern");
        $reset = settings("numbering.$scope.reset");
        $startFrom = settings("numbering.$scope.start_from");

        // If individual keys exist, use them
        if ($pattern !== null && $pattern !== '') {
            return [
                'pattern' => $pattern,
                'reset' => $reset ?? 'never',
                'start_from' => (int) ($startFrom ?? 1),
            ];
        }

        // Fall back to legacy array format (numbering.{scope} as array)
        $config = settings("numbering.$scope");

        if (is_array($config) && !empty($config['pattern'])) {
            return $config;
        }

        // Return safe defaults
        return [
            'pattern' => '{YYYY}-{MM}-{DD}-{SEQ:4}',
            'reset' => 'never',
            'start_from' => 1,
        ];
    }
}
