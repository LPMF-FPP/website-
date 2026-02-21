<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQmhPendukungRequest;
use App\Http\Requests\Quality\UpdateQmhPendukungRequest;
use App\Models\QmhDocument;
use App\Services\Quality\QmhPendukungService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class QmhPendukungController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        $documents = QmhDocument::query()
            ->pendukung()
            ->with('currentRevision')
            ->search($request->string('search')->toString())
            ->when($request->filled('clause'), function ($query) use ($request) {
                $query->where('clause', (int) $request->input('clause'));
            })
            ->orderBy('clause')
            ->orderBy('doc_code')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($documents);
    }

    public function store(StoreQmhPendukungRequest $request, QmhPendukungService $service): JsonResponse
    {
        try {
            $document = $service->create($request->validated(), $request->file('file'), (int) $request->user()->id);

            return response()->json([
                'message' => 'Dokumen pendukung berhasil dibuat.',
                'data' => $document,
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('API gagal membuat dokumen pendukung QMH', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal membuat dokumen pendukung.',
            ], 500);
        }
    }

    public function show(QmhDocument $document, QmhPendukungService $service): JsonResponse
    {
        $this->ensurePendukungDocument($document);

        $document->load('currentRevision');

        return response()->json([
            'data' => $document,
            'usage_count' => $service->getPendukungUsage($document)->count(),
            'file_url' => $service->getFileUrl($document),
        ]);
    }

    public function update(UpdateQmhPendukungRequest $request, QmhDocument $document, QmhPendukungService $service): JsonResponse
    {
        $this->ensurePendukungDocument($document);

        try {
            $updated = $service->updateVersion(
                $document,
                $request->validated(),
                $request->file('file'),
                (int) $request->user()->id
            );

            return response()->json([
                'message' => 'Dokumen pendukung berhasil diperbarui.',
                'data' => $updated,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('API gagal memperbarui dokumen pendukung QMH', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal memperbarui dokumen pendukung.',
            ], 500);
        }
    }

    public function destroy(Request $request, QmhDocument $document, QmhPendukungService $service): JsonResponse
    {
        $this->ensurePendukungDocument($document);

        try {
            $service->delete($document, (int) $request->user()->id);

            return response()->json([
                'message' => 'Dokumen pendukung berhasil dihapus.',
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('API gagal menghapus dokumen pendukung QMH', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal menghapus dokumen pendukung.',
            ], 500);
        }
    }

    public function createVersion(UpdateQmhPendukungRequest $request, QmhDocument $document, QmhPendukungService $service): JsonResponse
    {
        return $this->update($request, $document, $service);
    }

    public function byClause(Request $request, int $clause): JsonResponse
    {
        if (! in_array($clause, [4, 5, 6, 7, 8], true)) {
            return response()->json([
                'message' => 'Klausul tidak valid',
            ], 422);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        $documents = QmhDocument::query()
            ->pendukung()
            ->with('currentRevision')
            ->where('clause', $clause)
            ->search($request->string('search')->toString())
            ->orderBy('doc_code')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($documents);
    }

    private function ensurePendukungDocument(QmhDocument $document): void
    {
        abort_unless($document->doc_type === 'pendukung', 404);
    }
}
