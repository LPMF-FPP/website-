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
        $config = settings("numbering.$scope");

        if (!$config || empty($config['pattern'])) {
            throw new \RuntimeException("Numbering config for [$scope] not found.");
        }

        return $config;
    }
}
