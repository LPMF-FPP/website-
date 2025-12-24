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
    public function __construct(private readonly DocumentService $documents)
    {
    }

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
        if (!empty($data['document_id'])) {
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

        if (!$disk->exists($path)) {
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

        if (!empty($filters['type'])) {
            if (empty($entry['type']) || $entry['type'] !== $filters['type']) {
                return false;
            }
        }

        if (!empty($filters['source'])) {
            if (($entry['source'] ?? 'filesystem') !== $filters['source']) {
                return false;
            }
        }

        if (!empty($filters['request_id'])) {
            if (empty($document['request_id'] ?? null) || (int) $document['request_id'] !== (int) $filters['request_id']) {
                return false;
            }
        }

        if (!empty($filters['request_number'])) {
            $needle = Str::lower($filters['request_number']);
            $haystack = Str::lower($document['request_number'] ?? '');
            if (!Str::contains($haystack, $needle)) {
                return false;
            }
        }

        if (!empty($filters['query'])) {
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
            if (!$matches) {
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
}
