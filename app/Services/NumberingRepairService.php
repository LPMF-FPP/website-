<?php

namespace App\Services;

use App\Models\Document;
use App\Models\EvidenceUnit;
use App\Models\NumberingChangeLog;
use App\Models\RemainingUnit;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\Sequence;
use App\Models\TestRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        if (! $config) {
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
        $sequenceNumbers = $documents->map(fn ($doc) => $this->extractSequenceNumber($scope, $doc))
            ->filter(fn ($num) => $num !== null);

        $maxNumber = $sequenceNumbers->max() ?? 0;
        $maxDocument = null;

        if ($maxNumber > 0) {
            $maxDocument = $documents->first(fn ($doc) => $this->extractSequenceNumber($scope, $doc) === $maxNumber
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
                ->whereNotNull('metadata->lhu_number');
        }

        // Exclude null/empty numbers
        if (! str_contains($column, '->')) {
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
        if (! $number) {
            return null;
        }

        // 1. Try specific known prefixes (High Priority)
        // Matches: BA-ST/001, BA-RIM-001, LHU-BB/001
        if (preg_match('/(?:BA-ST|BA-RIM|LHU-BB)[-\/](\d+)[-\/]/i', $number, $matches)) {
            return (int) ltrim($matches[1], '0') ?: 0;
        }

        // 2. Try to find SEQ pattern in middle with Slash or Dash (Standard format)
        // Matches: /001/, -001-
        if (preg_match('/[-\/](\d{1,5})[-\/]/', $number, $matches)) {
            return (int) ltrim($matches[1], '0') ?: 0;
        }

        // 3. For sample_code like LS072I2026, W003I2026, or TR-LPMF001 (1-3 letter prefix + Digits)
        if (preg_match('/^(?:TR-LPMF|[A-Z]{1,3})(\d{3,4})/', $number, $matches)) {
            return (int) ltrim($matches[1], '0') ?: 0;
        }

        // 4. Fallback: Extract sequence at the end of string (Lowest Priority)
        // Only use this if no other pattern matches, as it might incorrectly match the year (e.g. ...-2026.pdf)
        if (preg_match('/(\d+)(?=[^\d]*$)/', $number, $matches)) {
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
        if (! $config) {
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
        usort($problems, fn ($a, $b) => ($a['sequence_number'] ?? 0) <=> ($b['sequence_number'] ?? 0)
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
            if (! $number) {
                continue;
            }

            $numberCounts[$number] = ($numberCounts[$number] ?? 0) + 1;
            $documentsByNumber[$number][] = $doc;
        }

        $duplicates = [];
        $suggestedSeq = $documents->count() + 1;

        foreach ($numberCounts as $number => $count) {
            if ($count <= 1) {
                continue;
            }

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
            ->map(fn ($doc) => $this->extractSequenceNumber($scope, $doc))
            ->filter(fn ($num) => $num !== null && $num > 0)
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
            if (! isset($existingNumbers[$i])) {
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
        if (! $config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        if ($newValue < 0) {
            throw new \InvalidArgumentException('Counter value cannot be negative');
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
        if (! $config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        if (! in_array($method, ['max', 'count'])) {
            throw new \InvalidArgumentException("Invalid sync method: {$method}");
        }

        $status = $this->getCounterStatus($scope);
        $newValue = $method === 'max' ? $status['from_max'] : $status['from_count'];
        $actionType = $method === 'max'
            ? NumberingChangeLog::ACTION_SYNC_MAX
            : NumberingChangeLog::ACTION_SYNC_COUNT;

        $bucket = $this->getCurrentBucket($scope);

        return DB::transaction(function () use ($scope, $bucket, $newValue, $reason, $actionType) {
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
        if (! $config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        $model = $config['model'];
        $column = $config['column'];

        $entity = $model::findOrFail($entityId);
        $oldNumber = $this->getDocumentNumber($scope, $entity);

        // Validate not same
        if ($oldNumber === $newNumber) {
            throw new \InvalidArgumentException('New number must be different from current number');
        }

        // Validate not duplicate
        if ($this->isNumberDuplicate($scope, $newNumber, $entityId)) {
            throw new \InvalidArgumentException('Number is already used by another document');
        }

        return DB::transaction(function () use ($scope, $entity, $column, $oldNumber, $newNumber, $reason) {
            if ($scope === 'lhu') {
                $metadata = $entity->metadata ?? [];
                $metadata['lhu_number'] = $newNumber;
                $entity->metadata = $metadata;
            } else {
                $entity->{$column} = $newNumber;
            }

            $entity->save();

            // CASCADE: Update related tables
            $cascadeCount = 0;

            if ($scope === 'sample_code' && $oldNumber) {
                $cascadeCount = $this->cascadeSampleCodeChange($entity, $oldNumber, $newNumber);
            }

            if ($scope === 'tracking' && $oldNumber) {
                // Update evidence_units receipt_code if needed
                $cascadeCount = EvidenceUnit::where('request_id', $entity->id)
                    ->where('receipt_code', $oldNumber)
                    ->update(['receipt_code' => $newNumber]);
            }

            NumberingChangeLog::log(
                $scope,
                NumberingChangeLog::ACTION_EDIT,
                $oldNumber ?? '',
                $newNumber,
                $reason.($cascadeCount > 0 ? " (cascade: {$cascadeCount} related records)" : ''),
                get_class($entity),
                $entity->id
            );

            return [
                'success' => true,
                'old_number' => $oldNumber,
                'new_number' => $newNumber,
                'entity_id' => $entity->id,
                'cascade_count' => $cascadeCount,
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
            $query->where('metadata->lhu_number', $number);
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

    /**
     * Search documents by number (partial match)
     */
    public function searchDocuments(string $scope, string $query, int $limit = 20): Collection
    {
        $config = $this->getScopeConfig($scope);
        if (! $config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        $model = $config['model'];
        $column = $config['column'];

        $dbQuery = $model::query();

        // Search based on column type
        if ($scope === 'lhu') {
            $dbQuery->where('metadata->lhu_number', 'like', "%{$query}%");
        } elseif ($scope === 'ba_penyerahan') {
            // Handle slash vs dash difference in filenames
            $normalizedQuery = str_replace('/', '-', $query);
            $dbQuery->where('document_type', 'ba_penyerahan')
                ->where(function ($q) use ($column, $query, $normalizedQuery) {
                    $q->where($column, 'like', "%{$query}%")
                        ->orWhere($column, 'like', "%{$normalizedQuery}%");
                });
        } else {
            $dbQuery->where($column, 'like', "%{$query}%");
        }

        return $dbQuery->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($doc) => [
                'entity_id' => $doc->id,
                'entity_type' => get_class($doc),
                'current_number' => $this->getDocumentNumber($scope, $doc),
                'entity_name' => $this->getEntityName($scope, $doc),
                'created_at' => $doc->created_at?->format('Y-m-d H:i'),
                'sequence_number' => $this->extractSequenceNumber($scope, $doc),
            ]);
    }

    /**
     * Get single document by ID
     */
    public function getDocument(string $scope, int $id): ?array
    {
        $config = $this->getScopeConfig($scope);
        if (! $config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        $model = $config['model'];
        $doc = $model::find($id);

        if (! $doc) {
            return null;
        }

        // For ba_penyerahan, verify document_type
        if ($scope === 'ba_penyerahan' && $doc->document_type !== 'ba_penyerahan') {
            return null;
        }

        return [
            'entity_id' => $doc->id,
            'entity_type' => get_class($doc),
            'current_number' => $this->getDocumentNumber($scope, $doc),
            'entity_name' => $this->getEntityName($scope, $doc),
            'created_at' => $doc->created_at?->format('Y-m-d H:i'),
            'sequence_number' => $this->extractSequenceNumber($scope, $doc),
        ];
    }

    /**
     * Get paginated document list sorted by sequence number
     */
    public function getDocumentList(string $scope, int $page = 1, int $perPage = 50): array
    {
        $config = $this->getScopeConfig($scope);
        if (! $config) {
            throw new \InvalidArgumentException("Unknown scope: {$scope}");
        }

        $bucket = $this->getCurrentBucket($scope);
        $reset = settings("numbering.$scope.reset") ?? 'never';

        // Get all documents in bucket
        $allDocuments = $this->getDocumentsInBucket($scope, $bucket, $reset);

        // Map and sort by sequence number
        $mapped = $allDocuments->map(fn ($doc) => [
            'entity_id' => $doc->id,
            'entity_type' => get_class($doc),
            'current_number' => $this->getDocumentNumber($scope, $doc),
            'entity_name' => $this->getEntityName($scope, $doc),
            'created_at' => $doc->created_at?->format('Y-m-d H:i'),
            'sequence_number' => $this->extractSequenceNumber($scope, $doc),
        ])->sortBy('sequence_number')->values();

        // Detect issues for each document
        $sequenceNumbers = $mapped->pluck('sequence_number')->filter()->toArray();
        $numberCounts = array_count_values(
            $mapped->pluck('current_number')->filter()->toArray()
        );

        // Add issue flags
        $mapped = $mapped->map(function ($doc, $index) use ($numberCounts, $mapped) {
            $issues = [];

            // Check for duplicate
            $currentNumber = $doc['current_number'];
            if ($currentNumber && ($numberCounts[$currentNumber] ?? 0) > 1) {
                $issues[] = 'duplicate';
            }

            // Check for gap (compare with previous)
            if ($index > 0) {
                $prevSeq = $mapped[$index - 1]['sequence_number'] ?? 0;
                $currentSeq = $doc['sequence_number'] ?? 0;
                if ($currentSeq > 0 && $prevSeq > 0 && ($currentSeq - $prevSeq) > 1) {
                    $issues[] = 'gap';
                }
            }

            $doc['issues'] = $issues;
            $doc['has_issue'] = ! empty($issues);

            return $doc;
        });

        // Paginate
        $total = $mapped->count();
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $items = $mapped->slice($offset, $perPage)->values();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
            ],
        ];
    }

    /**
     * Check if a gap can be reclaimed for a scope.
     *
     * A gap can be reclaimed when:
     * 1. There is at least one gap
     * 2. There is a document at the current counter position
     * 3. The gap is at position (counter - 1) - i.e., we can rename the last doc to fill the gap
     *
     * Returns null if no reclaimable gap, or array with reclaim info.
     */
    public function canReclaimGap(string $scope): ?array
    {
        $config = $this->getScopeConfig($scope);
        if (! $config) {
            return null;
        }

        $bucket = $this->getCurrentBucket($scope);
        $reset = settings("numbering.$scope.reset") ?? 'never';
        $documents = $this->getDocumentsInBucket($scope, $bucket, $reset);

        // Get all sequence numbers
        $sequenceNumbers = $documents
            ->map(fn ($doc) => $this->extractSequenceNumber($scope, $doc))
            ->filter(fn ($num) => $num !== null && $num > 0)
            ->unique()
            ->sort()
            ->values();

        if ($sequenceNumbers->isEmpty()) {
            return null;
        }

        $maxNumber = $sequenceNumbers->max();
        $totalDocs = $sequenceNumbers->count();

        // Find all gaps
        $gaps = [];
        for ($i = 1; $i <= $maxNumber; $i++) {
            if (! $sequenceNumbers->contains($i)) {
                $gaps[] = $i;
            }
        }

        if (empty($gaps)) {
            return null; // No gaps
        }

        // Get current counter value
        $sequence = Sequence::where('scope', $scope)
            ->where('bucket', $bucket)
            ->first();

        $currentCounter = $sequence?->current_value ?? 0;

        // For reclaim to work, we need:
        // 1. A document at position = currentCounter (the "last" document)
        // 2. A gap that we can fill by renaming that document

        // Find the document at the highest sequence number
        $lastDoc = $documents->first(fn ($doc) => $this->extractSequenceNumber($scope, $doc) === $maxNumber
        );

        if (! $lastDoc) {
            return null;
        }

        // The simplest reclaim: if counter matches max and there's a gap just before
        // Example: counter=73, docs=1..71,73 (gap at 72) → rename 73→72, counter→72
        $lastGap = end($gaps);

        // Can only reclaim if the gap is exactly (maxNumber - 1)
        // This ensures we're just "shifting" the last document back by 1
        if ($lastGap !== $maxNumber - 1) {
            return [
                'can_reclaim' => false,
                'reason' => 'Gap tidak berada di posisi yang bisa di-reclaim. Gap terakhir di posisi '.$lastGap.', dokumen terakhir di posisi '.$maxNumber.'.',
                'gaps' => $gaps,
                'current_counter' => $currentCounter,
                'max_number' => $maxNumber,
                'suggestion' => 'Gunakan fitur "Edit Nomor" untuk memperbaiki secara manual, atau biarkan gap tersebut.',
            ];
        }

        // We can reclaim!
        $lastDocNumber = $this->getDocumentNumber($scope, $lastDoc);
        $newNumber = $this->numberingService->preview($scope, [], $lastGap); // Pass exact sequence value we want

        return [
            'can_reclaim' => true,
            'gap_position' => $lastGap,
            'current_counter' => $currentCounter,
            'max_number' => $maxNumber,
            'document_to_rename' => [
                'entity_id' => $lastDoc->id,
                'entity_type' => get_class($lastDoc),
                'current_number' => $lastDocNumber,
                'new_number' => $newNumber,
                'entity_name' => $this->getEntityName($scope, $lastDoc),
            ],
            'counter_change' => [
                'from' => $currentCounter,
                'to' => $lastGap,
            ],
            'total_gaps' => count($gaps),
            'all_gaps' => $gaps,
            'preview_message' => sprintf(
                'Rename %s → %s, Counter %d → %d',
                $lastDocNumber,
                $newNumber,
                $currentCounter,
                $lastGap
            ),
        ];
    }

    /**
     * Execute gap reclaim for a scope.
     *
     * This will:
     * 1. Rename the last document to fill the gap
     * 2. Update related records (cascade)
     * 3. Rollback the counter
     * 4. Log the change
     */
    public function reclaimGap(string $scope, string $reason): array
    {
        $reclaimInfo = $this->canReclaimGap($scope);

        if (! $reclaimInfo || ! $reclaimInfo['can_reclaim']) {
            throw new \InvalidArgumentException(
                $reclaimInfo['reason'] ?? 'Tidak ada gap yang bisa di-reclaim untuk scope ini'
            );
        }

        $config = $this->getScopeConfig($scope);
        $model = $config['model'];
        $column = $config['column'];
        $bucket = $this->getCurrentBucket($scope);

        $docInfo = $reclaimInfo['document_to_rename'];
        $counterChange = $reclaimInfo['counter_change'];

        return DB::transaction(function () use (
            $scope, $model, $column, $bucket, $docInfo, $counterChange, $reason
        ) {
            // 1. Find and update the document
            $entity = $model::findOrFail($docInfo['entity_id']);
            $oldNumber = $docInfo['current_number'];
            $newNumber = $docInfo['new_number'];

            if ($scope === 'lhu') {
                $metadata = $entity->metadata ?? [];
                $metadata['lhu_number'] = $newNumber;
                $entity->metadata = $metadata;
            } else {
                $entity->{$column} = $newNumber;
            }
            $entity->save();

            // 2. Cascade update related records
            $cascadeCount = 0;

            if ($scope === 'sample_code' && $oldNumber) {
                $cascadeCount = $this->cascadeSampleCodeChange($entity, $oldNumber, $newNumber);
            }

            if ($scope === 'tracking' && $oldNumber) {
                $cascadeCount = EvidenceUnit::where('request_id', $entity->id)
                    ->where('receipt_code', $oldNumber)
                    ->update(['receipt_code' => $newNumber]);
            }

            // 3. Rollback the counter
            $sequence = Sequence::where('scope', $scope)
                ->where('bucket', $bucket)
                ->lockForUpdate()
                ->first();

            $oldCounter = $sequence->current_value;
            $sequence->current_value = $counterChange['to'];
            $sequence->save();

            // 4. Log the change
            NumberingChangeLog::log(
                $scope,
                NumberingChangeLog::ACTION_RECLAIM,
                sprintf('%s (counter: %d)', $oldNumber, $oldCounter),
                sprintf('%s (counter: %d)', $newNumber, $counterChange['to']),
                $reason.($cascadeCount > 0 ? " (cascade: {$cascadeCount} related records)" : ''),
                get_class($entity),
                $entity->id
            );

            return [
                'success' => true,
                'renamed' => [
                    'from' => $oldNumber,
                    'to' => $newNumber,
                ],
                'counter' => [
                    'from' => $oldCounter,
                    'to' => $counterChange['to'],
                ],
                'cascade_count' => $cascadeCount,
                'entity_id' => $entity->id,
            ];
        });
    }

    /**
     * Cascade sample_code renames to denormalized label tables.
     */
    protected function cascadeSampleCodeChange(Sample $sample, string $oldNumber, string $newNumber): int
    {
        $cascadeCount = 0;

        // evidence_units.sample_code
        $cascadeCount += EvidenceUnit::where('sample_id', $sample->id)
            ->where('sample_code', $oldNumber)
            ->update(['sample_code' => $newNumber]);

        // remaining_units.sample_code + remaining_units.remaining_code
        $evidenceUnitIds = EvidenceUnit::where('sample_id', $sample->id)->pluck('id');
        if ($evidenceUnitIds->isEmpty()) {
            return $cascadeCount;
        }

        $remainingUnits = RemainingUnit::whereIn('evidence_unit_id', $evidenceUnitIds)
            ->where('sample_code', $oldNumber)
            ->get();

        foreach ($remainingUnits as $remainingUnit) {
            $currentRemaining = (string) ($remainingUnit->remaining_code ?? '');

            $suffix = '';
            if ($currentRemaining !== '' && str_starts_with($currentRemaining, $oldNumber)) {
                $suffix = substr($currentRemaining, strlen($oldNumber));
            } elseif (($pos = strpos($currentRemaining, '-SISA')) !== false) {
                // Fallback: preserve the "-SISA" suffix even if the prefix drifted
                $suffix = substr($currentRemaining, $pos);
            } else {
                $suffix = '-SISA';
            }

            $remainingUnit->forceFill([
                'sample_code' => $newNumber,
                'remaining_code' => $newNumber.$suffix,
            ])->save();

            $cascadeCount++;
        }

        return $cascadeCount;
    }

    /**
     * Compact sample_code numbering within the active bucket (e.g. current year).
     *
     * Rules:
     * - Skip locked samples (any sample_test_processes row exists)
     * - Never rename locked samples
     * - Use two-phase rename (TMP then FINAL) to avoid unique collisions
     * - Cascade updates to evidence_units and remaining_units
     */
    public function previewCompactSampleCodesInCurrentBucket(int $examplesLimit = 10): array
    {
        return $this->previewCompactSampleCodesForBucket(CarbonImmutable::now(), $examplesLimit);
    }

    /**
     * Preview compaction plan for a specific bucket anchor date.
     * Uses the exact same plan-building logic as the apply method.
     */
    public function previewCompactSampleCodesForBucket(CarbonImmutable $bucketNow, int $examplesLimit = 10): array
    {
        $reset = settings('numbering.sample_code.reset') ?? 'never';
        $bucket = match ($reset) {
            'yearly' => $bucketNow->format('Y'),
            'monthly' => $bucketNow->format('Y-m'),
            'daily' => $bucketNow->format('Y-m-d'),
            default => 'default',
        };

        $counterBefore = (int) (Sequence::query()
            ->where('scope', 'sample_code')
            ->where('bucket', $bucket)
            ->value('current_value') ?? 0);

        $samples = $this->querySamplesForBucket($bucketNow, $reset)
            ->orderBy('created_at')
            ->get();

        if ($samples->isEmpty()) {
            return [
                'success' => true,
                'rename_count' => 0,
                'locked_count' => 0,
                'examples' => [],
                'counter_before' => $counterBefore,
                'counter_after' => $counterBefore,
            ];
        }

        $lockedIds = SampleTestProcess::query()
            ->whereIn('sample_id', $samples->pluck('id'))
            ->distinct()
            ->pluck('sample_id')
            ->all();

        $built = $this->buildSampleCodeCompactionPlanFromSamples($samples, $lockedIds);
        $plan = $built['plan'];

        $examples = array_slice(array_map(fn (array $item) => [
            'from' => (string) ($item['old_code'] ?? ''),
            'to' => (string) ($item['final_code'] ?? ''),
        ], $plan), 0, max(0, $examplesLimit));

        $counterAfter = (int) $built['counter_after'];

        return [
            'success' => true,
            'rename_count' => count($plan),
            'locked_count' => count($lockedIds),
            'examples' => $examples,
            'counter_before' => $counterBefore,
            'counter_after' => $counterAfter,
        ];
    }

    public function compactSampleCodesInCurrentBucket(string $reason = 'Auto compact sample codes after deletions'): array
    {
        return $this->compactSampleCodesForBucket(CarbonImmutable::now(), $reason);
    }

    /**
     * Compact sample_code numbering for a specific bucket anchor date.
     * This is used when deleting/editing historical requests so we compact
     * the correct year/month/day bucket.
     */
    public function compactSampleCodesForBucket(CarbonImmutable $bucketNow, string $reason = 'Auto compact sample codes after deletions'): array
    {
        $reset = settings('numbering.sample_code.reset') ?? 'never';
        $now = $bucketNow;

        $samples = $this->querySamplesForBucket($now, $reset)
            ->orderBy('created_at')
            ->get();

        if ($samples->isEmpty()) {
            return ['success' => true, 'renamed' => 0];
        }

        $lockedIds = SampleTestProcess::query()
            ->whereIn('sample_id', $samples->pluck('id'))
            ->distinct()
            ->pluck('sample_id')
            ->all();

        $built = $this->buildSampleCodeCompactionPlanFromSamples($samples, $lockedIds);
        $rows = $built['rows'];
        $plan = $built['plan'];

        if ($rows->isEmpty()) {
            $this->syncSequenceToBucketMaxFromSample($samples->first());

            return ['success' => true, 'renamed' => 0];
        }

        if (empty($plan)) {
            $this->syncSequenceToBucketMaxFromSample($samples->first());

            return ['success' => true, 'renamed' => 0];
        }

        return DB::transaction(function () use ($plan, $reason, $samples) {
            $affectedIds = collect($plan)->pluck('sample_id')->all();

            /** @var Collection<int, Sample> $affectedSamples */
            $affectedSamples = Sample::query()
                ->whereIn('id', $affectedIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Re-check locking inside the transaction to avoid renaming newly-locked samples
            $becameLocked = SampleTestProcess::query()
                ->whereIn('sample_id', $affectedIds)
                ->exists();
            if ($becameLocked) {
                throw new \RuntimeException('Some samples became locked during sample_code compaction');
            }

            $finalCodes = collect($plan)->pluck('final_code')->all();
            if (count($finalCodes) !== count(array_unique($finalCodes))) {
                throw new \RuntimeException('Duplicate final_code values generated during sample_code compaction');
            }
            $collision = Sample::query()
                ->whereIn('sample_code', $finalCodes)
                ->whereNotIn('id', $affectedIds)
                ->exists();
            if ($collision) {
                throw new \RuntimeException('Collision detected during sample_code compaction');
            }

            // Phase 1: move to TMP codes
            $tmpMap = [];
            foreach ($plan as $item) {
                $sample = $affectedSamples[$item['sample_id']];
                $oldCode = (string) $item['old_code'];
                $tmpCode = 'TMP-SC-'.$sample->id.'-'.Str::upper(Str::random(8));

                $sample->sample_code = $tmpCode;
                $sample->save();

                if ($oldCode !== '') {
                    $this->cascadeSampleCodeChange($sample, $oldCode, $tmpCode);
                }

                $tmpMap[$sample->id] = [
                    'tmp' => $tmpCode,
                    'final' => (string) $item['final_code'],
                ];
            }

            // Phase 2: TMP -> FINAL
            foreach ($tmpMap as $sampleId => $codes) {
                $sample = $affectedSamples[$sampleId];

                $tmpCode = $codes['tmp'];
                $finalCode = $codes['final'];

                $sample->sample_code = $finalCode;
                $sample->save();

                $this->cascadeSampleCodeChange($sample, $tmpCode, $finalCode);
            }

            $this->syncSequenceToBucketMaxFromSample($samples->first());

            return [
                'success' => true,
                'renamed' => count($tmpMap),
                'reason' => $reason,
            ];
        });
    }

    /**
     * Compact BA (request_number) and Tracking (receipt_number) in the bucket.
     */
    public function previewCompactRequestNumbersInCurrentBucket(int $examplesLimit = 10): array
    {
        return $this->previewCompactRequestNumbersForBucket(CarbonImmutable::now(), $examplesLimit);
    }

    public function previewCompactRequestNumbersForBucket(CarbonImmutable $bucketNow, int $examplesLimit = 10): array
    {
        // Use BA settings as anchor for the bucket
        $reset = settings('numbering.ba.reset') ?? 'never';
        $bucket = match ($reset) {
            'yearly' => $bucketNow->format('Y'),
            'monthly' => $bucketNow->format('Y-m'),
            'daily' => $bucketNow->format('Y-m-d'),
            default => 'default',
        };

        $baCounterBefore = (int) (Sequence::query()
            ->where('scope', 'ba')
            ->where('bucket', $bucket)
            ->value('current_value') ?? 0);

        // For tracking counter, check current tracking bucket
        $trackingReset = settings('numbering.tracking.reset') ?? 'never';
        $trackingBucket = match ($trackingReset) {
            'yearly' => $bucketNow->format('Y'),
            'monthly' => $bucketNow->format('Y-m'),
            'daily' => $bucketNow->format('Y-m-d'),
            default => 'default',
        };
        $trackingCounterBefore = (int) (Sequence::query()
            ->where('scope', 'tracking')
            ->where('bucket', $trackingBucket)
            ->value('current_value') ?? 0);

        $requests = $this->queryRequestsForBucket($bucketNow, $reset)
            ->orderBy('created_at')
            ->get();

        if ($requests->isEmpty()) {
            return [
                'success' => true,
                'rename_count' => 0,
                'locked_count' => 0,
                'examples' => [],
                'ba_counter_before' => $baCounterBefore,
                'ba_counter_after' => $baCounterBefore,
                'tracking_counter_before' => $trackingCounterBefore,
                'tracking_counter_after' => $trackingCounterBefore,
            ];
        }

        $lockedRequestIds = $this->getLockedRequestIds($requests);
        $built = $this->buildRequestCompactionPlan($requests, $lockedRequestIds);
        $plan = $built['plan'];

        $examples = array_slice(array_map(fn (array $item) => [
            'from_ba' => (string) ($item['old_ba'] ?? ''),
            'to_ba' => (string) ($item['final_ba'] ?? ''),
            'from_tracking' => (string) ($item['old_tracking'] ?? ''),
            'to_tracking' => (string) ($item['final_tracking'] ?? ''),
        ], $plan), 0, max(0, $examplesLimit));

        return [
            'success' => true,
            'rename_count' => count($plan),
            'locked_count' => count($lockedRequestIds),
            'examples' => $examples,
            'ba_counter_before' => $baCounterBefore,
            'ba_counter_after' => (int) $built['ba_counter_after'],
            'tracking_counter_before' => $trackingCounterBefore,
            'tracking_counter_after' => (int) $built['tracking_counter_after'],
        ];
    }

    public function compactRequestNumbersInCurrentBucket(string $reason = 'Auto compact requests after deletions'): array
    {
        return $this->compactRequestNumbersForBucket(CarbonImmutable::now(), $reason);
    }

    public function compactRequestNumbersForBucket(CarbonImmutable $bucketNow, string $reason = 'Auto compact requests after deletions'): array
    {
        $reset = settings('numbering.ba.reset') ?? 'never';
        $now = $bucketNow;

        $requests = $this->queryRequestsForBucket($now, $reset)
            ->orderBy('created_at')
            ->get();

        if ($requests->isEmpty()) {
            return ['success' => true, 'renamed' => 0];
        }

        $lockedIds = $this->getLockedRequestIds($requests);
        $built = $this->buildRequestCompactionPlan($requests, $lockedIds);
        $plan = $built['plan'];

        if (empty($plan)) {
            $this->syncSequenceToBucketMaxFromRequest($requests->first());

            return ['success' => true, 'renamed' => 0];
        }

        return DB::transaction(function () use ($plan, $reason, $requests) {
            $affectedIds = collect($plan)->pluck('request_id')->all();

            /** @var Collection<int, TestRequest> $affectedRequests */
            $affectedRequests = TestRequest::query()
                ->whereIn('id', $affectedIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $currentLockedIds = $this->getLockedRequestIds($affectedRequests);
            $newlyLocked = array_intersect($affectedIds, $currentLockedIds);

            if (! empty($newlyLocked)) {
                throw new \RuntimeException('Some requests became locked during compaction');
            }

            $finalBaCodes = collect($plan)->pluck('final_ba')->all();
            if (count($finalBaCodes) !== count(array_unique($finalBaCodes))) {
                throw new \RuntimeException('Duplicate final BA codes generated');
            }

            $collision = TestRequest::query()
                ->whereIn('request_number', $finalBaCodes)
                ->whereNotIn('id', $affectedIds)
                ->exists();
            if ($collision) {
                throw new \RuntimeException('Collision detected during BA compaction');
            }

            $fsOps = [];
            $tmpMap = [];

            // Phase 1: Move to TMP
            foreach ($plan as $item) {
                $req = $affectedRequests[$item['request_id']];
                $tmpBa = 'TMP-BA-'.$req->id.'-'.Str::upper(Str::random(8));
                $tmpTr = 'TMP-TR-'.$req->id.'-'.Str::upper(Str::random(8));

                $req->request_number = $tmpBa;
                $req->receipt_number = $tmpTr;
                $req->save();

                $tmpMap[$req->id] = [
                    'tmp_ba' => $tmpBa,
                    'tmp_tr' => $tmpTr,
                    'final_ba' => (string) $item['final_ba'],
                    'final_tr' => (string) $item['final_tracking'],
                    'old_ba' => (string) $item['old_ba'],
                    'old_tr' => (string) $item['old_tracking'],
                ];
            }

            // Phase 2: TMP -> Final
            foreach ($tmpMap as $reqId => $codes) {
                $req = $affectedRequests[$reqId];

                $req->request_number = $codes['final_ba'];
                $req->receipt_number = $codes['final_tr'];
                $req->save();

                if ($codes['old_ba']) {
                    $generatedOps = $this->cleanupGeneratedDocs($req);
                    $fsOps = array_merge($fsOps, $generatedOps);

                    $this->cascadeRequestNumberInDocumentPaths($req, $codes['old_ba'], $codes['final_ba']);

                    if ($req->investigator && $req->investigator->folder_key) {
                        $fsOps[] = [
                            'type' => 'move_directory',
                            'from' => "investigators/{$req->investigator->folder_key}/{$codes['old_ba']}",
                            'to' => "investigators/{$req->investigator->folder_key}/{$codes['final_ba']}",
                        ];
                    }
                }

                if ($codes['old_tr']) {
                    $this->cascadeTrackingNumberChange($req, $codes['old_tr'], $codes['final_tr']);
                }

                // Clear tracking cache for old numbers
                if ($codes['old_ba']) {
                    Cache::forget('track:condensed:'.$codes['old_ba']);
                }
                if ($codes['old_tr']) {
                    Cache::forget('track:condensed:'.$codes['old_tr']);
                }
            }

            $this->syncSequenceToBucketMaxFromRequest($requests->first());

            return [
                'success' => true,
                'renamed' => count($tmpMap),
                'reason' => $reason,
                'fs_ops' => $fsOps,
            ];
        });
    }

    protected function getLockedRequestIds(Collection $requests): array
    {
        $requestIds = $requests->pluck('id')->all();

        $lockedSampleIds = SampleTestProcess::query()
            ->whereIn('sample_id', function ($query) use ($requestIds) {
                $query->select('id')->from('samples')->whereIn('test_request_id', $requestIds);
            })
            ->distinct()
            ->pluck('sample_id');

        if ($lockedSampleIds->isEmpty()) {
            return [];
        }

        return Sample::whereIn('id', $lockedSampleIds)
            ->distinct()
            ->pluck('test_request_id')
            ->all();
    }

    protected function buildRequestCompactionPlan(Collection $requests, array $lockedIds): array
    {
        $rows = $requests->map(function (TestRequest $req) use ($lockedIds) {
            $seq = $this->extractSequenceNumber('ba', $req);

            return [
                'request' => $req,
                'seq' => $seq,
                'locked' => in_array($req->id, $lockedIds, true),
            ];
        })->filter(fn (array $row) => is_int($row['seq']) && $row['seq'] > 0)
            ->sortBy('seq')
            ->values();

        if ($rows->isEmpty()) {
            return ['rows' => $rows, 'plan' => [], 'ba_counter_after' => 0, 'tracking_counter_after' => 0];
        }

        $reservedLockedSeq = $rows->filter(fn ($row) => $row['locked'])
            ->pluck('seq')->unique()->flip()->all();

        $maxLocked = empty($reservedLockedSeq) ? 0 : max(array_keys($reservedLockedSeq));
        $nextSeq = 1;
        $maxAssigned = 0;
        $plan = [];

        foreach ($rows as $row) {
            /** @var TestRequest $req */
            $req = $row['request'];
            $currentSeq = (int) $row['seq'];

            if ($row['locked']) {
                $nextSeq = max($nextSeq, $currentSeq + 1);

                continue;
            }

            while (isset($reservedLockedSeq[$nextSeq])) {
                $nextSeq++;
            }

            $desiredSeq = $nextSeq;
            $nextSeq++;
            $maxAssigned = max($maxAssigned, $desiredSeq);

            if ($desiredSeq === $currentSeq) {
                continue;
            }

            $context = ['now' => CarbonImmutable::parse($req->created_at)];
            $finalBa = $this->numberingService->preview('ba', $context, $desiredSeq);
            $finalTr = $this->numberingService->preview('tracking', $context, $desiredSeq);

            $plan[] = [
                'request_id' => $req->id,
                'old_ba' => $req->request_number,
                'old_tracking' => $req->receipt_number,
                'final_ba' => $finalBa,
                'final_tracking' => $finalTr,
            ];
        }

        return [
            'rows' => $rows,
            'plan' => $plan,
            'ba_counter_after' => max($maxLocked, $maxAssigned),
            'tracking_counter_after' => max($maxLocked, $maxAssigned),
        ];
    }

    protected function queryRequestsForBucket(CarbonImmutable $now, string $reset): \Illuminate\Database\Eloquent\Builder
    {
        $query = TestRequest::query();

        if ($reset === 'yearly') {
            $query->whereYear('created_at', $now->year);
        } elseif ($reset === 'monthly') {
            $query->whereYear('created_at', $now->year)->whereMonth('created_at', $now->month);
        } elseif ($reset === 'daily') {
            $query->whereDate('created_at', $now->toDateString());
        }

        return $query;
    }

    protected function syncSequenceToBucketMaxFromRequest(TestRequest $request): void
    {
        $this->syncOneSequenceFromRequest('ba', $request);
        $this->syncOneSequenceFromRequest('tracking', $request);
    }

    protected function syncOneSequenceFromRequest(string $scope, TestRequest $request): void
    {
        $reset = settings("numbering.$scope.reset") ?? 'never';
        $now = $request->created_at ? CarbonImmutable::parse($request->created_at) : CarbonImmutable::now();
        $bucket = match ($reset) {
            'yearly' => $now->format('Y'),
            'monthly' => $now->format('Y-m'),
            'daily' => $now->format('Y-m-d'),
            default => 'default',
        };

        $query = TestRequest::query();
        if ($reset === 'yearly') {
            $query->whereYear('created_at', $now->year);
        } elseif ($reset === 'monthly') {
            $query->whereYear('created_at', $now->year)->whereMonth('created_at', $now->month);
        } elseif ($reset === 'daily') {
            $query->whereDate('created_at', $now->toDateString());
        }

        $maxSeq = $query->get()
            ->map(fn (TestRequest $r) => $this->extractSequenceNumber($scope, $r))
            ->filter(fn ($n) => is_int($n) && $n > 0)
            ->max() ?? 0;

        $sequence = Sequence::firstOrCreate(
            ['scope' => $scope, 'bucket' => $bucket],
            ['current_value' => (int) $maxSeq]
        );

        if ($sequence->current_value !== (int) $maxSeq) {
            $sequence->current_value = (int) $maxSeq;
            $sequence->save();
        }
    }

    protected function cleanupGeneratedDocs(TestRequest $request): array
    {
        $generatedTypes = [
            'ba_penerimaan', 'ba_penerimaan_html',
            'ba_penyerahan', 'ba_penyerahan_html',
            'laporan_hasil_uji', 'laporan_hasil_uji_html',
            'form_preparation',
        ];

        $docsToDelete = Document::where('test_request_id', $request->id)
            ->whereIn('document_type', $generatedTypes)
            ->where('source', 'generated')
            ->get();

        $fsOps = [];
        foreach ($docsToDelete as $doc) {
            $path = $doc->file_path ?? $doc->path;
            if ($path) {
                $fsOps[] = [
                    'type' => 'delete_file',
                    'path' => $path,
                ];
            }
            $doc->forceDelete();
        }

        if ($request->request_number) {
            $legacyPattern = 'output/Berita_Acara_Penerimaan_'.$request->request_number.'_ID-'.$request->id.'.html';
            $fsOps[] = [
                'type' => 'delete_legacy',
                'path' => $legacyPattern,
            ];
        }

        return $fsOps;
    }

    protected function cascadeRequestNumberInDocumentPaths(TestRequest $request, string $oldRequestNumber, string $newRequestNumber): void
    {
        $investigator = $request->investigator;
        if (! $investigator || ! $investigator->folder_key) {
            return;
        }

        $oldDir = "investigators/{$investigator->folder_key}/{$oldRequestNumber}";
        $newDir = "investigators/{$investigator->folder_key}/{$newRequestNumber}";

        $documents = Document::withTrashed()
            ->where('test_request_id', $request->id)
            ->get();

        foreach ($documents as $doc) {
            $updated = false;
            if ($doc->file_path && str_contains($doc->file_path, $oldDir)) {
                $doc->file_path = str_replace($oldDir, $newDir, $doc->file_path);
                $updated = true;
            }
            if ($doc->path && str_contains($doc->path, $oldDir)) {
                $doc->path = str_replace($oldDir, $newDir, $doc->path);
                $updated = true;
            }
            if ($updated) {
                $doc->saveQuietly();
            }
        }
    }

    protected function cascadeTrackingNumberChange(TestRequest $request, string $oldNumber, string $newNumber): int
    {
        return EvidenceUnit::where('request_id', $request->id)
            ->where('receipt_code', $oldNumber)
            ->update(['receipt_code' => $newNumber]);
    }

    public function executePostCommitFilesystemOps(array $fsOps): array
    {
        $disk = Storage::disk('public');
        $succeeded = 0;
        $failed = 0;

        foreach ($fsOps as $op) {
            try {
                match ($op['type']) {
                    'move_directory' => $this->moveDirectory($op['from'], $op['to']),
                    'delete_file' => $this->deleteFileIfExists($disk, $op['path']),
                    'delete_legacy' => @unlink(base_path($op['path'])),
                    default => null,
                };
                $succeeded++;
            } catch (\Throwable $e) {
                Log::warning('Post-commit filesystem op failed', [
                    'op' => $op,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    protected function moveDirectory(string $from, string $to): void
    {
        $disk = Storage::disk('public');
        $basePath = $disk->path('');

        $absFrom = $basePath.'/'.ltrim($from, '/');
        $absTo = $basePath.'/'.ltrim($to, '/');

        if (! is_dir($absFrom)) {
            return;
        }

        $parentDir = dirname($absTo);
        if (! is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        if (! rename($absFrom, $absTo)) {
            throw new \RuntimeException("Failed to rename directory: {$absFrom} -> {$absTo}");
        }
    }

    protected function deleteFileIfExists($disk, string $path): void
    {
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * Build compaction rows + plan without mutating state.
     *
     * @return array{rows:Collection, plan:array, ba_counter_after:int, tracking_counter_after:int}
     */
    protected function buildSampleCodeCompactionPlanFromSamples(Collection $samples, array $lockedIds): array
    {
        $rows = $samples->map(function (Sample $sample) use ($lockedIds) {
            $seq = $this->extractSequenceNumber('sample_code', $sample);

            return [
                'sample' => $sample,
                'seq' => $seq,
                'locked' => in_array($sample->id, $lockedIds, true),
            ];
        })->filter(fn (array $row) => is_int($row['seq']) && $row['seq'] > 0)
            ->sortBy('seq')
            ->values();

        if ($rows->isEmpty()) {
            return [
                'rows' => $rows,
                'plan' => [],
                'counter_after' => 0,
            ];
        }

        $reservedLockedSeq = $rows->filter(fn (array $row) => $row['locked'])
            ->pluck('seq')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $reservedLockedSeq = array_flip($reservedLockedSeq);

        $maxLocked = 0;
        foreach (array_keys($reservedLockedSeq) as $seq) {
            $maxLocked = max($maxLocked, (int) $seq);
        }

        $nextSeq = 1;
        $maxAssigned = 0;
        $plan = [];

        foreach ($rows as $row) {
            /** @var Sample $sample */
            $sample = $row['sample'];
            $currentSeq = (int) $row['seq'];

            if ($row['locked']) {
                $nextSeq = max($nextSeq, $currentSeq + 1);

                continue;
            }

            while (isset($reservedLockedSeq[$nextSeq])) {
                $nextSeq++;
            }

            $desiredSeq = $nextSeq;
            $nextSeq++;
            $maxAssigned = max($maxAssigned, $desiredSeq);

            if ($desiredSeq === $currentSeq) {
                continue;
            }

            $finalCode = $this->numberingService->preview('sample_code', [
                'now' => CarbonImmutable::parse($sample->created_at),
            ], $desiredSeq);

            $plan[] = [
                'sample_id' => $sample->id,
                'old_code' => $sample->sample_code,
                'final_code' => $finalCode,
            ];
        }

        return [
            'rows' => $rows,
            'plan' => $plan,
            'counter_after' => max($maxLocked, $maxAssigned),
        ];
    }

    protected function querySamplesForBucket(CarbonImmutable $now, string $reset): \Illuminate\Database\Eloquent\Builder
    {
        $query = Sample::query();

        if ($reset === 'yearly') {
            $query->whereYear('created_at', $now->year);
        } elseif ($reset === 'monthly') {
            $query->whereYear('created_at', $now->year)->whereMonth('created_at', $now->month);
        } elseif ($reset === 'daily') {
            $query->whereDate('created_at', $now->toDateString());
        }

        return $query;
    }

    protected function syncSequenceToBucketMaxFromSample(Sample $sample): void
    {
        DB::transaction(function () use ($sample) {
            $reset = settings('numbering.sample_code.reset') ?? 'never';
            $now = $sample->created_at ? CarbonImmutable::parse($sample->created_at) : CarbonImmutable::now();
            $bucket = match ($reset) {
                'yearly' => $now->format('Y'),
                'monthly' => $now->format('Y-m'),
                'daily' => $now->format('Y-m-d'),
                default => 'default',
            };

            $query = Sample::query();

            if ($reset === 'yearly') {
                $query->whereYear('created_at', $now->year);
            } elseif ($reset === 'monthly') {
                $query->whereYear('created_at', $now->year)->whereMonth('created_at', $now->month);
            } elseif ($reset === 'daily') {
                $query->whereDate('created_at', $now->toDateString());
            }

            $maxSeq = $query->get()->map(fn (Sample $s) => $this->extractSequenceNumber('sample_code', $s))
                ->filter(fn ($n) => is_int($n) && $n > 0)
                ->max() ?? 0;

            $sequence = Sequence::query()
                ->where('scope', 'sample_code')
                ->where('bucket', $bucket)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                Sequence::create([
                    'scope' => 'sample_code',
                    'bucket' => $bucket,
                    'current_value' => (int) $maxSeq,
                ]);

                return;
            }

            $sequence->current_value = (int) $maxSeq;
            $sequence->save();
        });
    }
}
