<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Services\NumberingRepairService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NumberingRepairController extends Controller
{
    public function __construct(
        protected NumberingRepairService $repairService
    ) {}

    /**
     * Get counter status for a scope
     */
    public function counterStatus(string $scope): JsonResponse
    {
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
        try {
            $result = $this->repairService->scanProblems($scope);
            $status = $this->repairService->getCounterStatus($scope);

            return response()->json([
                'scope' => $scope,
                'bucket' => $result['bucket'],
                'counter_status' => $status,
                'problems' => $result['problems'],
                'problem_count' => $result['problem_count'],
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
        $scope = $request->query('scope');
        $limit = min((int) $request->query('limit', 20), 100);

        $logs = $this->repairService->getChangeLogs($scope, $limit);

        return response()->json([
            'logs' => $logs->map(fn($log) => [
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
        $config = $this->repairService->getScopeConfig($scope);
        if (!$config) {
            return response()->json(['error' => 'Scope tidak valid'], 400);
        }

        $entityType = $config['model'];
        $logs = $this->repairService->getEntityHistory($entityType, $id);

        return response()->json([
            'logs' => $logs->map(fn($log) => [
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
        try {
            $document = $this->repairService->getDocument($scope, $id);

            if (!$document) {
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
}
