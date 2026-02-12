<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Sequence;
use App\Services\NumberingRepairService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NumberingRepairController extends Controller
{
    public function __construct(
        protected NumberingRepairService $repairService
    ) {}

    private function authorizeSettings(): void
    {
        Gate::authorize('manage-settings');
    }

    /**
     * Get counter status for a scope
     */
    public function counterStatus(string $scope): JsonResponse
    {
        $this->authorizeSettings();

        try {
            $status = $this->repairService->getCounterStatus($scope);

            return response()->json($status);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Scan for problems in a scope
     */
    public function scan(string $scope): JsonResponse
    {
        $this->authorizeSettings();

        try {
            $result = $this->repairService->scanProblems($scope);
            $status = $this->repairService->getCounterStatus($scope);

            return response()->json([
                'scope' => $scope,
                'bucket' => $result['bucket'],
                'counter_status' => $status,
                'problems' => $result['problems'],
                'problem_count' => $result['problem_count'],
                'uses_inherited_sequence' => $result['uses_inherited_sequence'] ?? false,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Reset counter manually
     */
    public function reset(Request $request, string $scope): JsonResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'new_value' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ], [
            'new_value.required' => 'Nilai counter wajib diisi',
            'new_value.min' => 'Nilai counter tidak boleh negatif',
            'reason.required' => 'Alasan perubahan wajib diisi',
        ]);

        try {
            $result = $this->repairService->resetCounter(
                $scope,
                $validated['new_value'],
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Counter berhasil direset',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Sync counter from max or count
     */
    public function sync(Request $request, string $scope): JsonResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'method' => 'required|in:max,count',
            'reason' => 'required|string|max:500',
        ], [
            'method.required' => 'Metode sinkronisasi wajib dipilih',
            'method.in' => 'Metode harus max atau count',
            'reason.required' => 'Alasan perubahan wajib diisi',
        ]);

        try {
            $result = $this->repairService->syncCounter(
                $scope,
                $validated['method'],
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Counter berhasil disinkronkan',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Edit individual document number
     */
    public function repair(Request $request, string $scope, int $id): JsonResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'new_number' => 'required|string|max:100',
            'reason' => 'required|string|max:500',
        ], [
            'new_number.required' => 'Nomor baru wajib diisi',
            'reason.required' => 'Alasan perubahan wajib diisi',
        ]);

        try {
            $result = $this->repairService->editNumber(
                $scope,
                $id,
                $validated['new_number'],
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Nomor berhasil diperbarui',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Dokumen tidak ditemukan'], 404);
        }
    }

    /**
     * Get change logs
     */
    public function changeLogs(Request $request): JsonResponse
    {
        $this->authorizeSettings();

        $scope = $request->query('scope');
        $limit = min((int) $request->query('limit', 20), 100);

        $logs = $this->repairService->getChangeLogs($scope, $limit);

        return response()->json([
            'logs' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'scope' => $log->scope,
                'scope_label' => $log->scope_label,
                'action_type' => $log->action_type,
                'action_label' => $log->action_label,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'reason' => $log->reason,
                'user' => $log->user?->name ?? 'System',
                'created_at' => $log->created_at->format('d-m-Y H:i'),
            ]),
        ]);
    }

    /**
     * Get entity history
     */
    public function entityHistory(string $scope, int $id): JsonResponse
    {
        $this->authorizeSettings();

        $config = $this->repairService->getScopeConfig($scope);
        if (! $config) {
            return response()->json(['error' => 'Scope tidak valid'], 400);
        }

        $entityType = $config['model'];
        $logs = $this->repairService->getEntityHistory($entityType, $id);

        return response()->json([
            'logs' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'action_label' => $log->action_label,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'reason' => $log->reason,
                'user' => $log->user?->name ?? 'System',
                'created_at' => $log->created_at->format('d-m-Y H:i'),
            ]),
        ]);
    }

    /**
     * Search documents by number
     */
    public function search(Request $request, string $scope): JsonResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'q' => 'required|string|min:1|max:100',
        ], [
            'q.required' => 'Query pencarian wajib diisi',
            'q.min' => 'Query minimal 1 karakter',
        ]);

        try {
            $results = $this->repairService->searchDocuments(
                $scope,
                $validated['q'],
                20
            );

            return response()->json([
                'success' => true,
                'results' => $results,
                'count' => $results->count(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get single document by ID
     */
    public function getDocument(string $scope, int $id): JsonResponse
    {
        $this->authorizeSettings();

        try {
            $document = $this->repairService->getDocument($scope, $id);

            if (! $document) {
                return response()->json(['error' => 'Dokumen tidak ditemukan'], 404);
            }

            return response()->json([
                'success' => true,
                'document' => $document,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get paginated document list sorted by sequence number
     */
    public function documentList(Request $request, string $scope): JsonResponse
    {
        $this->authorizeSettings();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));

        try {
            $result = $this->repairService->getDocumentList($scope, $page, $perPage);

            return response()->json([
                'success' => true,
                'documents' => $result['data'],
                'meta' => $result['meta'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Check if gap can be reclaimed for a scope
     */
    public function canReclaim(string $scope): JsonResponse
    {
        $this->authorizeSettings();

        try {
            $result = $this->repairService->canReclaimGap($scope);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Execute gap reclaim for a scope
     */
    public function reclaim(Request $request, string $scope): JsonResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Alasan reclaim wajib diisi',
        ]);

        try {
            $result = $this->repairService->reclaimGap(
                $scope,
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Gap berhasil di-reclaim',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Preview compaction plan (sample_code, ba, tracking).
     */
    public function compactPreview(string $scope): JsonResponse
    {
        $this->authorizeSettings();

        if (! in_array($scope, ['sample_code', 'ba', 'tracking'])) {
            return response()->json(['error' => 'Scope tidak didukung untuk aksi rapatkan'], 400);
        }

        try {
            if ($scope === 'ba' || $scope === 'tracking') {
                $result = $this->repairService->previewCompactRequestNumbersInCurrentBucket();

                return response()->json($result);
            }

            $result = $this->repairService->previewCompactSampleCodesForBucket(CarbonImmutable::now());

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Apply compaction (sample_code, ba, tracking).
     */
    public function compact(Request $request, string $scope): JsonResponse
    {
        $this->authorizeSettings();

        if (! in_array($scope, ['sample_code', 'ba', 'tracking'])) {
            return response()->json(['error' => 'Scope tidak didukung untuk aksi rapatkan'], 400);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Alasan wajib diisi',
        ]);

        try {
            $bucketNow = CarbonImmutable::now();

            if ($scope === 'ba' || $scope === 'tracking') {
                $apply = $this->repairService->compactRequestNumbersInCurrentBucket($validated['reason']);

                if (! empty($apply['fs_ops'])) {
                    $this->repairService->executePostCommitFilesystemOps($apply['fs_ops']);
                }

                // Read counters fresh from DB (consistent with sample_code path)
                $baReset = settings('numbering.ba.reset') ?? 'never';
                $trackingReset = settings('numbering.tracking.reset') ?? 'never';
                $baBucket = match ($baReset) {
                    'yearly' => $bucketNow->format('Y'),
                    'monthly' => $bucketNow->format('Y-m'),
                    'daily' => $bucketNow->format('Y-m-d'),
                    default => 'default',
                };
                $trackingBucket = match ($trackingReset) {
                    'yearly' => $bucketNow->format('Y'),
                    'monthly' => $bucketNow->format('Y-m'),
                    'daily' => $bucketNow->format('Y-m-d'),
                    default => 'default',
                };

                $baCounterAfter = (int) (Sequence::query()
                    ->where('scope', 'ba')
                    ->where('bucket', $baBucket)
                    ->value('current_value') ?? 0);
                $trackingCounterAfter = (int) (Sequence::query()
                    ->where('scope', 'tracking')
                    ->where('bucket', $trackingBucket)
                    ->value('current_value') ?? 0);

                return response()->json([
                    'success' => true,
                    'message' => 'Nomor BA dan Resi berhasil dirapatkan',
                    'rename_count' => (int) ($apply['renamed'] ?? 0),
                    'counter_after' => $baCounterAfter,
                    'tracking_counter_after' => $trackingCounterAfter,
                ]);
            }

            $preview = $this->repairService->previewCompactSampleCodesForBucket($bucketNow);
            $apply = $this->repairService->compactSampleCodesForBucket($bucketNow, $validated['reason']);

            $reset = settings('numbering.sample_code.reset') ?? 'never';
            $bucket = match ($reset) {
                'yearly' => $bucketNow->format('Y'),
                'monthly' => $bucketNow->format('Y-m'),
                'daily' => $bucketNow->format('Y-m-d'),
                default => 'default',
            };
            $counterAfter = (int) (Sequence::query()
                ->where('scope', 'sample_code')
                ->where('bucket', $bucket)
                ->value('current_value') ?? 0);

            return response()->json([
                'success' => true,
                'message' => 'Kode sampel berhasil dirapatkan',
                'rename_count' => (int) ($apply['renamed'] ?? 0),
                'locked_count' => (int) ($preview['locked_count'] ?? 0),
                'counter_before' => (int) ($preview['counter_before'] ?? 0),
                'counter_after' => $counterAfter,
                'examples' => $preview['examples'] ?? [],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
