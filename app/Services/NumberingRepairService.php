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

    /**
     * Scan for problems in a scope
     */
    public function scanProblems(string $scope): array
    {
        $config = $this->getScopeConfig($scope);
        if (!$config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        $bucket = $this->getCurrentBucket($scope);
        $reset = settings("numbering.$scope.reset") ?? 'never';
        $documents = $this->getDocumentsInBucket($scope, $bucket, $reset);

        $problems = [];
        $duplicates = $this->detectDuplicates($scope, $documents);
        $gaps = $this->detectGaps($scope, $documents);

        foreach ($duplicates as $duplicate) {
            $problems[] = array_merge($duplicate, ['type' => 'duplicate']);
        }

        foreach ($gaps as $gap) {
            $problems[] = array_merge($gap, ['type' => 'gap']);
        }

        // Sort by sequence number
        usort($problems, fn($a, $b) => 
            ($a['sequence_number'] ?? 0) <=> ($b['sequence_number'] ?? 0)
        );

        return [
            'scope' => $scope,
            'bucket' => $bucket,
            'problems' => $problems,
            'problem_count' => [
                'duplicate' => count($duplicates),
                'gap' => count($gaps),
                'total' => count($problems),
            ],
        ];
    }

    /**
     * Detect duplicate numbers
     */
    protected function detectDuplicates(string $scope, Collection $documents): array
    {
        $numberCounts = [];
        $documentsByNumber = [];

        foreach ($documents as $doc) {
            $number = $this->getDocumentNumber($scope, $doc);
            if (!$number) continue;

            $numberCounts[$number] = ($numberCounts[$number] ?? 0) + 1;
            $documentsByNumber[$number][] = $doc;
        }

        $duplicates = [];
        $suggestedSeq = $documents->count() + 1;

        foreach ($numberCounts as $number => $count) {
            if ($count <= 1) continue;

            $docs = $documentsByNumber[$number];
            $isFirst = true;

            foreach ($docs as $doc) {
                $duplicates[] = [
                    'entity_type' => get_class($doc),
                    'entity_id' => $doc->id,
                    'current_number' => $number,
                    'sequence_number' => $this->extractSequenceNumber($scope, $doc),
                    'entity_name' => $this->getEntityName($scope, $doc),
                    'created_at' => $doc->created_at?->format('Y-m-d'),
                    'suggested_number' => $isFirst ? null : $this->numberingService->preview($scope, [], $suggestedSeq++),
                    'is_first' => $isFirst,
                ];
                $isFirst = false;
            }
        }

        return $duplicates;
    }

    /**
     * Detect gaps in sequence
     */
    protected function detectGaps(string $scope, Collection $documents): array
    {
        $sequenceNumbers = $documents
            ->map(fn($doc) => $this->extractSequenceNumber($scope, $doc))
            ->filter(fn($num) => $num !== null && $num > 0)
            ->unique()
            ->sort()
            ->values();

        if ($sequenceNumbers->isEmpty()) {
            return [];
        }

        $gaps = [];
        $max = $sequenceNumbers->max();
        $existingNumbers = $sequenceNumbers->flip();

        for ($i = 1; $i <= $max; $i++) {
            if (!isset($existingNumbers[$i])) {
                $gaps[] = [
                    'sequence_number' => $i,
                    'missing_number' => $this->numberingService->preview($scope, [], $i),
                    'gap_position' => $i,
                ];
            }
        }

        return $gaps;
    }

    /**
     * Get entity name for display
     */
    protected function getEntityName(string $scope, $document): string
    {
        if ($scope === 'ba' || $scope === 'tracking') {
            $investigator = $document->investigator;
            return $investigator ? ($investigator->name ?? 'Unknown') : 'Unknown';
        }

        if ($scope === 'sample_code') {
            return $document->short_description ?? $document->sample_code ?? 'Unknown';
        }

        if ($scope === 'lhu') {
            $sample = $document->sample;
            return $sample?->short_description ?? 'Unknown';
        }

        if ($scope === 'ba_penyerahan') {
            $request = $document->testRequest;
            return $request?->investigator?->name ?? 'Unknown';
        }

        return 'Unknown';
    }

    /**
     * Reset counter manually
     */
    public function resetCounter(string $scope, int $newValue, string $reason): array
    {
        $config = $this->getScopeConfig($scope);
        if (!$config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        if ($newValue < 0) {
            throw new \InvalidArgumentException("Counter value cannot be negative");
        }

        $bucket = $this->getCurrentBucket($scope);

        return DB::transaction(function () use ($scope, $bucket, $newValue, $reason) {
            $sequence = Sequence::where('scope', $scope)
                ->where('bucket', $bucket)
                ->lockForUpdate()
                ->first();

            $oldValue = $sequence?->current_value ?? 0;

            if ($sequence) {
                $sequence->current_value = $newValue;
                $sequence->save();
            } else {
                Sequence::create([
                    'scope' => $scope,
                    'bucket' => $bucket,
                    'current_value' => $newValue,
                ]);
            }

            NumberingChangeLog::log(
                $scope,
                NumberingChangeLog::ACTION_RESET,
                (string) $oldValue,
                (string) $newValue,
                $reason
            );

            return [
                'success' => true,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ];
        });
    }

    /**
     * Sync counter from max or count
     */
    public function syncCounter(string $scope, string $method, string $reason): array
    {
        $config = $this->getScopeConfig($scope);
        if (!$config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        if (!in_array($method, ['max', 'count'])) {
            throw new \InvalidArgumentException("Invalid sync method: {$method}");
        }

        $status = $this->getCounterStatus($scope);
        $newValue = $method === 'max' ? $status['from_max'] : $status['from_count'];
        $actionType = $method === 'max' 
            ? NumberingChangeLog::ACTION_SYNC_MAX 
            : NumberingChangeLog::ACTION_SYNC_COUNT;

        $bucket = $this->getCurrentBucket($scope);

        return DB::transaction(function () use ($scope, $bucket, $newValue, $reason, $actionType, $status) {
            $sequence = Sequence::where('scope', $scope)
                ->where('bucket', $bucket)
                ->lockForUpdate()
                ->first();

            $oldValue = $sequence?->current_value ?? 0;

            if ($sequence) {
                $sequence->current_value = $newValue;
                $sequence->save();
            } else {
                Sequence::create([
                    'scope' => $scope,
                    'bucket' => $bucket,
                    'current_value' => $newValue,
                ]);
            }

            NumberingChangeLog::log(
                $scope,
                $actionType,
                (string) $oldValue,
                (string) $newValue,
                $reason
            );

            return [
                'success' => true,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'method' => $actionType,
            ];
        });
    }

    /**
     * Edit individual document number
     */
    public function editNumber(string $scope, int $entityId, string $newNumber, string $reason): array
    {
        $config = $this->getScopeConfig($scope);
        if (!$config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        $model = $config['model'];
        $column = $config['column'];

        $entity = $model::findOrFail($entityId);
        $oldNumber = $this->getDocumentNumber($scope, $entity);

        // Validate not same
        if ($oldNumber === $newNumber) {
            throw new \InvalidArgumentException("New number must be different from current number");
        }

        // Validate not duplicate
        if ($this->isNumberDuplicate($scope, $newNumber, $entityId)) {
            throw new \InvalidArgumentException("Number is already used by another document");
        }

        return DB::transaction(function () use ($scope, $entity, $column, $oldNumber, $newNumber, $reason, $config) {
            if ($scope === 'lhu') {
                $metadata = $entity->metadata ?? [];
                $metadata['lhu_number'] = $newNumber;
                $entity->metadata = $metadata;
            } else {
                $entity->{$column} = $newNumber;
            }

            $entity->save();

            NumberingChangeLog::log(
                $scope,
                NumberingChangeLog::ACTION_EDIT,
                $oldNumber ?? '',
                $newNumber,
                $reason,
                get_class($entity),
                $entity->id
            );

            return [
                'success' => true,
                'old_number' => $oldNumber,
                'new_number' => $newNumber,
                'entity_id' => $entity->id,
            ];
        });
    }

    /**
     * Check if a number is duplicate
     */
    protected function isNumberDuplicate(string $scope, string $number, ?int $excludeId = null): bool
    {
        $config = $this->getScopeConfig($scope);
        $model = $config['model'];
        $column = $config['column'];

        $query = $model::query();

        if ($scope === 'lhu') {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.lhu_number')) = ?", [$number]);
        } elseif ($scope === 'ba_penyerahan') {
            $query->where('document_type', 'ba_penyerahan')
                  ->where($column, $number);
        } else {
            $query->where($column, $number);
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get change logs for a scope
     */
    public function getChangeLogs(?string $scope = null, int $limit = 20): Collection
    {
        $query = NumberingChangeLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($scope) {
            $query->where('scope', $scope);
        }

        return $query->get();
    }

    /**
     * Get entity change history
     */
    public function getEntityHistory(string $entityType, int $entityId): Collection
    {
        return NumberingChangeLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
