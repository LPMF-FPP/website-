<?php

namespace App\Services;

use App\Models\Document;
use App\Models\NumberingChangeLog;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\Sequence;
use App\Models\TestRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NumberingRepairService
{
    protected array $scopeConfig = [
        'ba' => [
            'model' => TestRequest::class,
            'column' => 'request_number',
            'label' => 'BA Penerimaan',
        ],
        'sample_code' => [
            'model' => Sample::class,
            'column' => 'sample_code',
            'label' => 'Kode Sampel',
        ],
        'lhu' => [
            'model' => SampleTestProcess::class,
            'column' => 'metadata->lhu_number',
            'json_path' => 'lhu_number',
            'label' => 'Laporan Hasil Uji',
        ],
        'ba_penyerahan' => [
            'model' => Document::class,
            'column' => 'filename',
            'document_type' => 'ba_penyerahan',
            'label' => 'BA Penyerahan',
        ],
        'tracking' => [
            'model' => TestRequest::class,
            'column' => 'receipt_number',
            'label' => 'Nomor Resi',
        ],
    ];

    public function __construct(
        protected NumberingService $numberingService
    ) {}

    /**
     * Get all available scopes
     */
    public function getScopes(): array
    {
        return array_keys($this->scopeConfig);
    }

    /**
     * Get scope configuration
     */
    public function getScopeConfig(string $scope): ?array
    {
        return $this->scopeConfig[$scope] ?? null;
    }

    /**
     * Get current bucket for a scope based on reset period
     */
    public function getCurrentBucket(string $scope): string
    {
        $config = $this->numberingService->currentSnapshot($scope);
        $reset = settings("numbering.$scope.reset") ?? 'never';
        $now = CarbonImmutable::now();

        return match ($reset) {
            'yearly' => $now->format('Y'),
            'monthly' => $now->format('Y-m'),
            'daily' => $now->format('Y-m-d'),
            default => 'default',
        };
    }

    /**
     * Get counter status with multiple calculation methods
     */
    public function getCounterStatus(string $scope): array
    {
        $config = $this->getScopeConfig($scope);
        if (!$config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        $bucket = $this->getCurrentBucket($scope);
        $reset = settings("numbering.$scope.reset") ?? 'never';

        // Get current counter value
        $sequence = Sequence::where('scope', $scope)
            ->where('bucket', $bucket)
            ->first();

        $currentCounter = $sequence?->current_value ?? 0;

        // Get documents in current bucket
        $documents = $this->getDocumentsInBucket($scope, $bucket, $reset);
        $totalDocuments = $documents->count();

        // Extract sequence numbers and find max
        $sequenceNumbers = $documents->map(fn($doc) => $this->extractSequenceNumber($scope, $doc))
            ->filter(fn($num) => $num !== null);

        $maxNumber = $sequenceNumbers->max() ?? 0;
        $maxDocument = null;

        if ($maxNumber > 0) {
            $maxDocument = $documents->first(fn($doc) => 
                $this->extractSequenceNumber($scope, $doc) === $maxNumber
            );
        }

        return [
            'scope' => $scope,
            'bucket' => $bucket,
            'reset_period' => $reset,
            'current_counter' => $currentCounter,
            'from_max' => $maxNumber,
            'from_count' => $totalDocuments,
            'max_document' => $maxDocument ? $this->getDocumentNumber($scope, $maxDocument) : null,
            'total_documents' => $totalDocuments,
            'has_mismatch' => $currentCounter !== $maxNumber,
        ];
    }

    /**
     * Get documents in the current bucket based on reset period
     */
    protected function getDocumentsInBucket(string $scope, string $bucket, string $reset): Collection
    {
        $config = $this->getScopeConfig($scope);
        $model = $config['model'];
        $column = $config['column'];

        $query = $model::query();

        // Filter by date based on reset period
        $now = CarbonImmutable::now();
        
        if ($reset === 'yearly') {
            $query->whereYear('created_at', $now->year);
        } elseif ($reset === 'monthly') {
            $query->whereYear('created_at', $now->year)
                  ->whereMonth('created_at', $now->month);
        } elseif ($reset === 'daily') {
            $query->whereDate('created_at', $now->toDateString());
        }

        // Scope-specific filters
        if ($scope === 'ba_penyerahan') {
            $query->where('document_type', 'ba_penyerahan');
        }

        if ($scope === 'lhu') {
            $query->whereNotNull('metadata')
                  ->whereRaw("JSON_EXTRACT(metadata, '$.lhu_number') IS NOT NULL");
        }

        // Exclude null/empty numbers
        if (!str_contains($column, '->')) {
            $query->whereNotNull($column)->where($column, '!=', '');
        }

        return $query->get();
    }

    /**
     * Get document number based on scope
     */
    protected function getDocumentNumber(string $scope, $document): ?string
    {
        $config = $this->getScopeConfig($scope);
        $column = $config['column'];

        if ($scope === 'lhu') {
            $metadata = $document->metadata ?? [];
            return $metadata['lhu_number'] ?? $metadata['report_number'] ?? null;
        }

        if (str_contains($column, '->')) {
            $parts = explode('->', $column);
            $data = $document->{$parts[0]} ?? [];
            return $data[$parts[1]] ?? null;
        }

        return $document->{$column} ?? null;
    }

    /**
     * Extract sequence number from formatted document number
     */
    protected function extractSequenceNumber(string $scope, $document): ?int
    {
        $number = $this->getDocumentNumber($scope, $document);
        if (!$number) {
            return null;
        }

        // Extract digits from the number
        // Common patterns: BA/2026/01/0005 -> 5, W003I2026 -> 3, LHU-2026-0099 -> 99
        if (preg_match('/(\d+)(?=[^\d]*$)/', $number, $matches)) {
            return (int) ltrim($matches[1], '0') ?: 0;
        }

        // Try to find SEQ pattern in middle
        if (preg_match('/\/(\d{3,4})\//', $number, $matches)) {
            return (int) ltrim($matches[1], '0') ?: 0;
        }

        // For sample_code like W003I2026
        if (preg_match('/^[A-Z](\d{3,4})/', $number, $matches)) {
            return (int) ltrim($matches[1], '0') ?: 0;
        }

        return null;
    }
}
