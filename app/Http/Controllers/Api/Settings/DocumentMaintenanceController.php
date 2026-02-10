<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentService;
use App\Support\DocumentTypes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentMaintenanceController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'request_number' => ['nullable', 'string', 'max:60'],
            'request_id' => ['nullable', 'integer', 'exists:test_requests,id'],
            'type' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', Rule::in(['upload', 'generated', 'filesystem'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = $validated['per_page'] ?? 25;
        $page = $validated['page'] ?? 1;

        $disk = Storage::disk('public');
        $files = collect($disk->allFiles())->map(fn ($path) => ltrim($path, '/'));

        $documents = $files->isEmpty()
            ? collect()
            : Document::query()
                ->with([
                    'investigator:id,name',
                    'testRequest:id,request_number,case_number',
                ])
                ->where(function ($query) use ($files) {
                    $query->whereIn('file_path', $files->all())
                        ->orWhereIn('path', $files->all());
                })
                ->get()
                ->groupBy(fn (Document $doc) => ltrim($doc->file_path ?? $doc->path ?? '', '/'));

        $entries = $files
            ->map(fn ($path) => $this->mapFileEntry($disk, $path, $documents->get($path)))
            ->reject(fn (?array $entry) => $entry === null)
            ->filter(fn (array $entry) => $this->passesFilters($entry, $validated))
            ->sortByDesc('last_modified_timestamp')
            ->values();

        $total = $entries->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $items = $entries->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'total' => $total,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $data = $request->validate([
            'path' => ['required', 'string'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        $path = ltrim($data['path'], '/');

        if (Str::contains($path, ['..', '//'])) {
            return response()->json([
                'message' => 'Path tidak valid.',
            ], 422);
        }

        $disk = Storage::disk('public');

        $document = null;
        if (! empty($data['document_id'])) {
            $document = Document::query()->find($data['document_id']);
        }

        if ($document && $document->file_path !== $path && $document->path !== $path) {
            return response()->json([
                'message' => 'Path tidak sesuai dengan dokumen yang dipilih.',
            ], 422);
        }

        if ($document) {
            $this->documents->delete($document);

            return response()->json([
                'deleted' => true,
                'path' => $path,
                'document_removed' => true,
            ]);
        }

        if (! $disk->exists($path)) {
            return response()->json([
                'message' => 'File tidak ditemukan.',
            ], 404);
        }

        $disk->delete($path);

        return response()->json([
            'deleted' => true,
            'path' => $path,
            'document_removed' => false,
        ]);
    }

    private function mapFileEntry($disk, string $path, ?Collection $documents = null): ?array
    {
        $documents ??= collect();
        $document = $documents->first();

        try {
            $size = $disk->size($path);
            $timestamp = $disk->lastModified($path);
        } catch (\Throwable $e) {
            return null;
        }

        $lastModified = Carbon::createFromTimestamp($timestamp);

        $documentData = $document ? $this->transformDocument($document) : null;

        $directory = Str::contains($path, '/')
            ? Str::beforeLast($path, '/')
            : '/';

        return [
            'path' => $path,
            'directory' => $directory,
            'name' => basename($path),
            'size' => $size,
            'size_label' => $this->formatFileSize($size),
            'last_modified' => $lastModified->toIso8601String(),
            'last_modified_for_humans' => $lastModified->diffForHumans(),
            'last_modified_timestamp' => $timestamp,
            'type' => $document?->document_type,
            'type_label' => DocumentTypes::label($document?->document_type),
            'source' => $document?->source ?? 'filesystem',
            'document' => $documentData,
            'preview_url' => $documentData
                ? route('investigator.documents.show', ['document' => $documentData['id']])
                : null,
            'download_url' => $documentData
                ? URL::temporarySignedRoute(
                    'investigator.documents.download',
                    now()->addMinutes(15),
                    ['document' => $documentData['id']]
                )
                : null,
            'can_delete' => true,
        ];
    }

    private function passesFilters(array $entry, array $filters): bool
    {
        $document = $entry['document'] ?? null;

        if (! empty($filters['type'])) {
            if (empty($entry['type']) || $entry['type'] !== $filters['type']) {
                return false;
            }
        }

        if (! empty($filters['source'])) {
            if (($entry['source'] ?? 'filesystem') !== $filters['source']) {
                return false;
            }
        }

        if (! empty($filters['request_id'])) {
            if (empty($document['request_id'] ?? null) || (int) $document['request_id'] !== (int) $filters['request_id']) {
                return false;
            }
        }

        if (! empty($filters['request_number'])) {
            $needle = Str::lower($filters['request_number']);
            $haystack = Str::lower($document['request_number'] ?? '');
            if (! Str::contains($haystack, $needle)) {
                return false;
            }
        }

        if (! empty($filters['query'])) {
            $needle = Str::lower($filters['query']);
            $haystacks = [
                Str::lower($entry['name'] ?? ''),
                Str::lower($entry['path'] ?? ''),
                Str::lower($document['type_label'] ?? ''),
                Str::lower($document['investigator']['name'] ?? ''),
                Str::lower($document['request_number'] ?? ''),
                Str::lower($document['case_number'] ?? ''),
            ];

            $matches = collect($haystacks)->contains(fn ($value) => Str::contains((string) $value, $needle));
            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    private function transformDocument(Document $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->original_filename ?? $document->filename ?? 'Dokumen',
            'type' => $document->document_type,
            'type_label' => DocumentTypes::label($document->document_type),
            'source' => $document->source,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'created_at' => optional($document->created_at)->toIso8601String(),
            'request_id' => $document->test_request_id,
            'request_number' => $document->testRequest?->request_number,
            'case_number' => $document->testRequest?->case_number,
            'investigator' => [
                'id' => $document->investigator_id,
                'name' => $document->investigator?->name,
            ],
        ];
    }

    private function formatFileSize(?int $bytes): string
    {
        if (empty($bytes) || $bytes < 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp = (int) floor(log($bytes, 1024));
        $exp = min($exp, count($units) - 1);

        $value = $bytes / (1024 ** $exp);

        return sprintf('%s %s', number_format($value, $value >= 10 ? 0 : 2), $units[$exp]);
    }

    /**
     * Get cleanup statistics (orphaned folders and duplicate documents)
     */
    public function cleanupStats(): JsonResponse
    {
        Gate::authorize('manage-settings');

        $disk = Storage::disk('public');

        // Orphaned investigator folders
        $validFolderKeys = \App\Models\Investigator::pluck('folder_key')->filter()->toArray();
        $investigatorDirs = $disk->directories('investigators');

        $orphanedFolders = [];
        $orphanedSize = 0;

        foreach ($investigatorDirs as $dir) {
            $folderName = basename($dir);
            if (! in_array($folderName, $validFolderKeys)) {
                $orphanedFolders[] = $folderName;
                foreach ($disk->allFiles($dir) as $file) {
                    try {
                        $orphanedSize += $disk->size($file);
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
            }
        }

        // Duplicate documents - check both database AND filesystem
        $totalDuplicates = 0;
        $duplicateSize = 0;
        $duplicateGroups = 0;

        // First check database duplicates
        $dbDuplicates = Document::select(
            'test_request_id',
            'document_type',
            \Illuminate\Support\Facades\DB::raw('COUNT(*) as count')
        )
            ->where('source', 'generated')
            ->whereNotNull('test_request_id')
            ->groupBy('test_request_id', 'document_type')
            ->having(\Illuminate\Support\Facades\DB::raw('COUNT(*)'), '>', 1)
            ->get();

        foreach ($dbDuplicates as $dup) {
            $docsToRemove = Document::where('test_request_id', $dup->test_request_id)
                ->where('document_type', $dup->document_type)
                ->where('source', 'generated')
                ->orderByDesc('created_at')
                ->skip(1)
                ->take(1000)
                ->get();

            foreach ($docsToRemove as $doc) {
                $totalDuplicates++;
                $path = $doc->file_path ?? $doc->path;
                if ($path && $disk->exists($path)) {
                    try {
                        $duplicateSize += $disk->size($path);
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
            }
        }
        $duplicateGroups = $dbDuplicates->count();

        // Also check filesystem duplicates (files not tracked in database)
        $filesystemDuplicates = $this->findFilesystemDuplicates($disk);
        $totalDuplicates += $filesystemDuplicates['count'];
        $duplicateSize += $filesystemDuplicates['size'];
        $duplicateGroups += $filesystemDuplicates['groups'];

        return response()->json([
            'orphaned_folders' => [
                'count' => count($orphanedFolders),
                'size' => $orphanedSize,
                'size_label' => $this->formatFileSize($orphanedSize),
                'samples' => array_slice($orphanedFolders, 0, 5),
            ],
            'duplicate_documents' => [
                'count' => $totalDuplicates,
                'size' => $duplicateSize,
                'size_label' => $this->formatFileSize($duplicateSize),
                'groups' => $duplicateGroups,
            ],
        ]);
    }

    /**
     * Find duplicate files in filesystem that are not tracked in database.
     * Groups files by request folder + base document type (ignoring _html suffix).
     *
     * @return array{count: int, size: int, groups: int, files: array}
     */
    private function findFilesystemDuplicates($disk): array
    {
        $allFiles = collect($disk->allFiles())
            ->filter(fn ($f) => Str::endsWith($f, ['.pdf', '.html', '.docx', '.doc', '.xlsx', '.xls']))
            ->values();

        // Get files that are tracked in database
        $trackedPaths = Document::pluck('file_path')
            ->merge(Document::pluck('path'))
            ->filter()
            ->map(fn ($p) => ltrim($p, '/'))
            ->unique()
            ->toArray();

        // Group files by request folder + base document type + file extension
        // Pattern: investigators/FOLDER_KEY/DATE-REQUEST_ID/generated/DOC_TYPE/filename
        // Normalize doc type by removing _html suffix to group PDF and HTML together
        $grouped = $allFiles->groupBy(function ($path) {
            if (preg_match('#investigators/[^/]+/(\d{4}-\d{2}-\d{2}-\d+)/generated/([^/]+)/#', $path, $matches)) {
                $requestId = $matches[1];
                $docType = $matches[2];
                // Normalize: laporan_hasil_uji_html -> laporan_hasil_uji
                $baseDocType = preg_replace('/_html$/', '', $docType);
                // Include file extension in grouping so PDF and HTML are separate groups
                $ext = pathinfo($path, PATHINFO_EXTENSION);

                return $requestId.'|'.$baseDocType.'|'.$ext;
            }

            return null;
        })->filter();

        $duplicateCount = 0;
        $duplicateSize = 0;
        $duplicateGroups = 0;
        $filesToDelete = [];

        foreach ($grouped as $key => $files) {
            // Filter out files that are already tracked in database
            $untrackedFiles = $files->filter(fn ($f) => ! in_array(ltrim($f, '/'), $trackedPaths))->values();

            if ($untrackedFiles->count() <= 1) {
                continue;
            }

            // Sort by filename (which includes timestamp) to keep the newest
            $sorted = $untrackedFiles->sort()->values();

            // Keep the last one (newest based on timestamp in filename), mark others as duplicates
            $duplicates = $sorted->slice(0, -1)->values();

            if ($duplicates->isEmpty()) {
                continue;
            }

            $duplicateGroups++;

            foreach ($duplicates as $file) {
                $duplicateCount++;
                $filesToDelete[] = $file;
                try {
                    $duplicateSize += $disk->size($file);
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
        }

        return [
            'count' => $duplicateCount,
            'size' => $duplicateSize,
            'groups' => $duplicateGroups,
            'files' => $filesToDelete,
        ];
    }

    /**
     * Clean up orphaned investigator folders
     */
    public function cleanupOrphanedFolders(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $dryRun = $request->boolean('dry_run', false);
        $disk = Storage::disk('public');

        $validFolderKeys = \App\Models\Investigator::pluck('folder_key')->filter()->toArray();
        $investigatorDirs = $disk->directories('investigators');

        $orphanedFolders = [];
        $totalSize = 0;

        foreach ($investigatorDirs as $dir) {
            $folderName = basename($dir);
            if (! in_array($folderName, $validFolderKeys)) {
                $orphanedFolders[] = $dir;
                foreach ($disk->allFiles($dir) as $file) {
                    try {
                        $totalSize += $disk->size($file);
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
            }
        }

        if (empty($orphanedFolders)) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada folder orphan yang ditemukan.',
                'deleted' => 0,
                'failed' => 0,
                'size_reclaimed' => 0,
                'size_label' => '0 B',
            ]);
        }

        if ($dryRun) {
            return response()->json([
                'dry_run' => true,
                'message' => count($orphanedFolders).' folder orphan akan dihapus.',
                'count' => count($orphanedFolders),
                'size' => $totalSize,
                'size_label' => $this->formatFileSize($totalSize),
                'samples' => array_slice(array_map('basename', $orphanedFolders), 0, 10),
            ]);
        }

        $deleted = 0;
        $failed = 0;

        foreach ($orphanedFolders as $folder) {
            try {
                $files = $disk->allFiles($folder);
                Document::whereIn('path', $files)
                    ->orWhereIn('file_path', $files)
                    ->delete();

                $disk->deleteDirectory($folder);
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menghapus {$deleted} folder orphan.",
            'deleted' => $deleted,
            'failed' => $failed,
            'size_reclaimed' => $totalSize,
            'size_label' => $this->formatFileSize($totalSize),
        ]);
    }

    /**
     * Clean up duplicate documents (keep latest only)
     */
    public function cleanupDuplicates(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $dryRun = $request->boolean('dry_run', false);
        $disk = Storage::disk('public');

        // Collect database duplicates
        $dbDuplicates = Document::select(
            'test_request_id',
            'document_type',
            \Illuminate\Support\Facades\DB::raw('COUNT(*) as count')
        )
            ->where('source', 'generated')
            ->whereNotNull('test_request_id')
            ->groupBy('test_request_id', 'document_type')
            ->having(\Illuminate\Support\Facades\DB::raw('COUNT(*)'), '>', 1)
            ->get();

        $documentsToDelete = collect();
        $totalSize = 0;

        foreach ($dbDuplicates as $dup) {
            $docsToRemove = Document::where('test_request_id', $dup->test_request_id)
                ->where('document_type', $dup->document_type)
                ->where('source', 'generated')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->skip(1)
                ->take(1000)
                ->get();

            foreach ($docsToRemove as $doc) {
                $documentsToDelete->push($doc);
                $path = $doc->file_path ?? $doc->path;
                if ($path && $disk->exists($path)) {
                    try {
                        $totalSize += $disk->size($path);
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
            }
        }

        // Also collect filesystem duplicates not tracked in database
        $filesystemDuplicates = $this->findFilesystemDuplicates($disk);
        $filesToDelete = $filesystemDuplicates['files'];
        $totalSize += $filesystemDuplicates['size'];

        $totalCount = $documentsToDelete->count() + count($filesToDelete);
        $totalGroups = $dbDuplicates->count() + $filesystemDuplicates['groups'];

        if ($totalCount === 0) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada dokumen duplikat yang ditemukan.',
                'deleted' => 0,
                'failed' => 0,
                'size_reclaimed' => 0,
                'size_label' => '0 B',
            ]);
        }

        if ($dryRun) {
            return response()->json([
                'dry_run' => true,
                'message' => $totalCount.' dokumen duplikat akan dihapus.',
                'count' => $totalCount,
                'size' => $totalSize,
                'size_label' => $this->formatFileSize($totalSize),
                'groups' => $totalGroups,
            ]);
        }

        $deleted = 0;
        $failed = 0;

        // Delete database documents
        foreach ($documentsToDelete as $doc) {
            try {
                $path = $doc->file_path ?? $doc->path;
                if ($path && $disk->exists($path)) {
                    $disk->delete($path);
                }
                $doc->forceDelete();
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Duplicate document cleanup failed (DB)', [
                    'document_id' => $doc->id ?? null,
                    'path' => $doc->file_path ?? $doc->path ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Delete filesystem-only duplicates
        foreach ($filesToDelete as $filePath) {
            try {
                if ($disk->exists($filePath)) {
                    $disk->delete($filePath);
                    $deleted++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Duplicate document cleanup failed (Filesystem)', [
                    'path' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menghapus {$deleted} dokumen duplikat.",
            'deleted' => $deleted,
            'failed' => $failed,
            'size_reclaimed' => $totalSize,
            'size_label' => $this->formatFileSize($totalSize),
        ]);
    }
}
