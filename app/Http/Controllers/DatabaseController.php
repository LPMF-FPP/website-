<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\TestRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class DatabaseController extends Controller
{
    private const DOC_LABELS = [
        'sample_receipt' => 'sample receipt',
        'request_letter_receipt' => 'surat permintaan',
        'handover_report' => 'berita acara penyerahan',
        'lhu' => 'laporan hasil uji',
        'ba_permintaan' => 'berita acara permintaan',
        'ba_penyerahan' => 'berita acara penyerahan',
        'instrument_result' => 'hasil pengujian instrumen',
        'evidence_photo' => 'foto barang bukti',
    ];

    private const DOC_ALIASES = [
        'request letter receipt' => 'request_letter_receipt',
        'request letter' => 'request_letter_receipt',
        'request_letter' => 'request_letter_receipt',
        'surat permintaan' => 'request_letter_receipt',
        'sample receipt' => 'sample_receipt',
        'sample_receipt' => 'sample_receipt',
        'handover report' => 'handover_report',
        'berita acara penyerahan' => 'ba_penyerahan',
        'laporan hasil uji' => 'lhu',
        'ba permintaan' => 'ba_permintaan',
        'berita acara permintaan' => 'ba_permintaan',
        'ba penyerahan' => 'ba_penyerahan',
        'hasil pengujian instrumen' => 'instrument_result',
        'instrument result' => 'instrument_result',
        'foto barang bukti' => 'evidence_photo',
    ];
    public function index(Request $request)
    {
        // Input validation
        $validator = Validator::make($request->all(), [
            'q' => 'nullable|string|max:500',
            'status' => 'nullable|string|in:submitted,verified,received,in_testing,analysis,quality_check,ready_for_delivery,completed',
            'tipe' => 'nullable|string|in:input,generate',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->route('database.index')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $rawQuery = trim((string) ($validated['q'] ?? ''));
        $statusParam = $validated['status'] ?? null;
        $typeParam = $validated['tipe'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        [$ops, $freeTexts] = $this->parseQueryTokens($rawQuery);

        $statusFilter = $statusParam ?: ($ops['status'] ?? null);
        $typeFilter = $typeParam ?: ($ops['tipe'] ?? null);
        $dateOperatorValue = $ops['tanggal'] ?? null;
        $documentFilterKey = isset($ops['dokumen']) ? $this->docKeyFromLabel($ops['dokumen']) : null;

        $baseQuery = TestRequest::query()
            ->with([
                'investigator:id,name,rank',
                'samples:id,test_request_id,sample_name,sample_description,test_methods,quantity,quantity_unit',
                'samples.testProcesses' => function ($query) {
                    $query->where('stage', 'interpretation')
                        ->select('id', 'sample_id', 'stage', 'metadata');
                },
                'documents' => function ($query) use ($documentFilterKey) {
                    $query->select('id', 'test_request_id', 'document_type', 'file_path', 'original_filename', 'mime_type');
                    if ($documentFilterKey) {
                        $query->where('document_type', $documentFilterKey);
                    }
                },
            ])
            ->withCount('samples')
            ->when($statusFilter, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($typeFilter, function (Builder $query, string $type) {
                if ($type === 'generate') {
                    $query->whereNotNull('completed_at');
                } elseif ($type === 'input') {
                    $query->whereNull('completed_at');
                }
            })
            ->when($dateFrom, fn (Builder $query, string $value) => $this->applyDateBoundary($query, $value, '>='))
            ->when($dateTo, fn (Builder $query, string $value) => $this->applyDateBoundary($query, $value, '<='))
            ->when($dateOperatorValue, function (Builder $query, string $value) {
                [$start, $end] = $this->resolveDateRangeFromToken($value);
                if ($start) {
                    $this->applyDateBoundary($query, $start->toDateString(), '>=');
                }
                if ($end) {
                    $this->applyDateBoundary($query, $end->toDateString(), '<=');
                }
            })
            ->when($documentFilterKey, function (Builder $query, string $documentKey) {
                $query->whereHas('documents', fn (Builder $documents) => $documents
                    ->where('document_type', $documentKey));
            })
            ->when($freeTexts->isNotEmpty(), function (Builder $query) use ($freeTexts) {
                $query->where(function (Builder $outer) use ($freeTexts) {
                    foreach ($freeTexts as $term) {
                        $outer->where(function (Builder $inner) use ($term) {
                            $like = '%' . $term . '%';
                            $inner->where('request_number', 'like', $like)
                                ->orWhere('case_number', 'like', $like)
                                ->orWhere('suspect_name', 'like', $like)
                                ->orWhere('to_office', 'like', $like)
                                ->orWhere('incident_location', 'like', $like)
                                ->orWhereHas('investigator', fn (Builder $investigator) => $investigator
                                    ->where('name', 'like', $like)
                                    ->orWhere('rank', 'like', $like))
                                ->orWhereHas('samples', fn (Builder $samples) => $samples
                                    ->where('sample_name', 'like', $like)
                                    ->orWhere('sample_description', 'like', $like));
                        });
                    }
                });
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->orderBy('request_number');

        // Pagination for performance
        $perPage = 50;
        $results = $baseQuery->paginate($perPage);
        
        // Add generated documents from output folder
        foreach ($results->items() as $requestModel) {
            $generatedDocs = $this->collectGeneratedDocuments($requestModel);
            
            // Merge generated documents with database documents
            $existingDocs = $requestModel->documents ?? collect();
            
            // Convert to base collection to avoid getKey() issues with stdClass
            $allDocs = collect($existingDocs->all())->merge($generatedDocs->all());
            
            // Deduplicate based on document_type + unique identifier
            $seen = [];
            $uniqueDocs = collect();
            
            foreach ($allDocs as $doc) {
                $docType = $doc->document_type ?? 'unknown';
                $uniqueKey = null;
                
                if (is_object($doc) && isset($doc->is_generated) && $doc->is_generated) {
                    $uniqueKey = 'gen_' . $docType . '_' . $doc->file_path;
                } else {
                    $uniqueKey = 'db_' . $docType . '_' . ($doc->id ?? $doc->file_path);
                }
                
                if (!isset($seen[$uniqueKey])) {
                    $seen[$uniqueKey] = true;
                    $uniqueDocs->push($doc);
                }
            }
            
            $requestModel->setRelation('documents', $uniqueDocs);
        }

        $grouped = $results->groupBy(function (TestRequest $requestModel) {
            $investigator = $requestModel->investigator;
            if (!$investigator) {
                return '— (Tanpa Penyidik)';
            }

            return $investigator->full_name ?? $investigator->name;
        })->sortKeys();

        $statsPerGroup = $grouped->map(fn (Collection $items) => $this->computeGroupMetrics($items));

        $latestUpdate = $results->map(fn (TestRequest $item) => $item->updated_at ?? $item->created_at)
            ->filter()
            ->max();

        $aggregates = [
            'totalRequests' => $results->count(),
            'totalSamples' => $results->sum('samples_count'),
            'completed' => $results->where('status', 'completed')->count(),
            'statusBreakdown' => $results->groupBy('status')->map->count()->sortDesc(),
            'latestUpdate' => $latestUpdate,
        ];

        return view('database.index', [
            'filters' => [
                'q' => $rawQuery,
                'status' => $statusFilter,
                'tipe' => $typeFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'operator_tanggal' => $dateOperatorValue,
            ],
            'statusOptions' => $this->statusOptions(),
            'typeOptions' => [
                'input' => 'Input (belum selesai)',
                'generate' => 'Generate (selesai)',
            ],
            'results' => $results, // Paginator for pagination links
            'groups' => $grouped,
            'statsPerGroup' => $statsPerGroup,
            'aggregates' => $aggregates,
            'docLabels' => self::DOC_LABELS,
            'docFilterKey' => $documentFilterKey,
        ]);
    }

    /**
     * @return array{0: array{status: ?string, tipe: ?string, tanggal: ?string}, 1: Collection<int, string>}
     */
    protected function parseQueryTokens(string $rawQuery): array
    {
        $ops = [
            'status' => null,
            'tipe' => null,
            'tanggal' => null,
            'dokumen' => null,
        ];
        $freeTexts = collect();

        if ($rawQuery === '') {
            return [$ops, $freeTexts];
        }

        $tokens = collect(preg_split('/\s+/', $rawQuery, -1, PREG_SPLIT_NO_EMPTY));
        foreach ($tokens as $token) {
            if (Str::startsWith($token, 'status:')) {
                $ops['status'] = Str::after($token, 'status:');
            } elseif (Str::startsWith($token, 'tipe:')) {
                $ops['tipe'] = Str::after($token, 'tipe:');
            } elseif (Str::startsWith($token, 'tanggal:')) {
                $ops['tanggal'] = Str::after($token, 'tanggal:');
            } elseif (Str::startsWith($token, 'dokumen:')) {
                $ops['dokumen'] = Str::after($token, 'dokumen:');
            } else {
                $freeTexts->push($token);
            }
        }

        return [$ops, $freeTexts];
    }

    private function docKeyFromLabel(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }

        $normalized = Str::of($raw)->lower()->replace(['_', '-'], ' ')->trim()->value();
        $rawLower = Str::of($raw)->lower()->trim()->value();

        if (array_key_exists($rawLower, self::DOC_LABELS)) {
            return $rawLower;
        }

        if (array_key_exists($raw, self::DOC_LABELS)) {
            return $raw;
        }

        foreach (self::DOC_LABELS as $key => $label) {
            if ($normalized === Str::of($label)->lower()->trim()->value()) {
                return $key;
            }
        }

        return self::DOC_ALIASES[$normalized] ?? null;
    }

    protected function applyDateBoundary(Builder $query, string $date, string $operator): Builder
    {
        return $query->where(function (Builder $inner) use ($date, $operator) {
            $inner->whereDate('submitted_at', $operator, $date)
                ->orWhere(function (Builder $fallback) use ($date, $operator) {
                    $fallback->whereNull('submitted_at')
                        ->whereDate('created_at', $operator, $date);
                });
        });
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    protected function resolveDateRangeFromToken(string $value): array
    {
        if (str_contains($value, '..')) {
            [$startRaw, $endRaw] = array_pad(explode('..', $value, 2), 2, null);

            $start = $startRaw ? $this->safeParseDate($startRaw) : null;
            $end = $endRaw ? $this->safeParseDate($endRaw, true) : null;

            return [$start, $end];
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            $start = Carbon::createFromFormat('Y-m', $value)->startOfMonth();
            $end = (clone $start)->endOfMonth();

            return [$start, $end];
        }

        $single = $this->safeParseDate($value);

        return [$single, $single];
    }

    protected function safeParseDate(string $value, bool $endOfDay = false): ?Carbon
    {
        try {
            $dt = Carbon::parse($value);
            return $endOfDay ? $dt->endOfDay() : $dt->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{
     *     input: int,
     *     generate: int,
     *     completed: int,
     *     minDate: string|null,
     *     maxDate: string|null,
     *     avgDays: float|null,
     *     topTest: string|null
     * }
     */
    protected function computeGroupMetrics(Collection $items): array
    {
        $inputCount = $items->whereNull('completed_at')->count();
        $generateCount = $items->whereNotNull('completed_at')->count();
        $completedCount = $items->where('status', 'completed')->count();

        $dates = $items->map(fn (TestRequest $item) => $item->submitted_at ?? $item->created_at)
            ->filter()
            ->sort();

        $minDate = $dates->first();
        $maxDate = $dates->last();

        $avgDays = $items->filter(fn (TestRequest $item) => $item->submitted_at && $item->completed_at)
            ->avg(fn (TestRequest $item) => $item->submitted_at->diffInDays($item->completed_at));

        $topTest = $this->resolveTopTestMethod($items);

        return [
            'input' => $inputCount,
            'generate' => $generateCount,
            'completed' => $completedCount,
            'minDate' => $minDate ? $minDate->format('Y-m-d') : null,
            'maxDate' => $maxDate ? $maxDate->format('Y-m-d') : null,
            'avgDays' => $avgDays ? round($avgDays, 1) : null,
            'topTest' => $topTest,
        ];
    }

    protected function resolveTopTestMethod(Collection $items): ?string
    {
        $counter = [];

        foreach ($items as $request) {
            foreach ($request->samples as $sample) {
                $methods = $sample->test_methods;
                if ($methods === null) {
                    continue;
                }

                if (is_string($methods)) {
                    $decoded = json_decode($methods, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $methods = $decoded;
                    }
                }

                if (is_string($methods)) {
                    $methods = [$methods];
                }

                if (!is_iterable($methods)) {
                    continue;
                }

                foreach ($methods as $method) {
                    if (!$method) {
                        continue;
                    }
                    $label = (string) $method;
                    $counter[$label] = ($counter[$label] ?? 0) + 1;
                }
            }
        }

        if ($counter === []) {
            return null;
        }

        arsort($counter);

        return array_key_first($counter);
    }

    protected function statusOptions(): array
    {
        return [
            'submitted' => 'Submitted',
            'verified' => 'Verified',
            'received' => 'Received',
            'in_testing' => 'In Testing',
            'analysis' => 'Analysis',
            'quality_check' => 'Quality Check',
            'ready_for_delivery' => 'Ready for Delivery',
            'completed' => 'Completed',
        ];
    }

    public function suggest(Request $request)
    {
        $queryString = trim((string) $request->query('q', ''));
        if ($queryString === '') {
            return response()->json(['items' => []]);
        }

        $tokens = Str::of($queryString)->explode(' ')->filter();
        $lastToken = $tokens->last() ?? $queryString;
        $lastLower = Str::lower($lastToken);

        $suggestions = collect();

        $operatorSuggestions = collect([
            'status:submitted',
            'status:verified',
            'status:received',
            'status:in_testing',
            'status:analysis',
            'status:quality_check',
            'status:ready_for_delivery',
            'status:completed',
            'tipe:input',
            'tipe:generate',
            'tanggal:YYYY-MM-DD',
            'tanggal:YYYY-MM',
            'tanggal:2025-01-01..2025-12-31',
        ])->filter(function (string $candidate) use ($lastLower) {
            return Str::contains(Str::lower($candidate), $lastLower);
        })->map(fn (string $value) => [
            'label' => $value,
            'insert' => $value,
            'type' => 'operator',
        ]);

        $suggestions = $suggestions->merge($operatorSuggestions);

        foreach (self::DOC_LABELS as $key => $label) {
            $token = "dokumen:{$key}";
            $haystack = Str::lower($label . ' ' . $token);
            if ($lastLower === '' || Str::contains($haystack, $lastLower)) {
                $suggestions->push([
                    'label' => "Dokumen: {$label}",
                    'insert' => $token,
                    'type' => 'dokumen',
                ]);
            }
        }

        $investigatorMatches = Investigator::query()
            ->when($lastLower !== '', function (Builder $builder) use ($lastLower) {
                $builder->where('name', 'like', '%' . $lastLower . '%');
            })
            ->orderBy('name')
            ->limit(5)
            ->pluck('name');

        $suggestions = $suggestions->merge($investigatorMatches->map(fn (string $name) => [
            'label' => "Penyidik: {$name}",
            'insert' => $name,
            'type' => 'penyidik',
        ]));

        $requestNumberMatches = TestRequest::query()
            ->when($lastLower !== '', function (Builder $builder) use ($lastLower) {
                $builder->where('request_number', 'like', '%' . $lastLower . '%');
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->pluck('request_number');

        $suggestions = $suggestions->merge($requestNumberMatches->map(fn (string $requestNumber) => [
            'label' => "Request#: {$requestNumber}",
            'insert' => $requestNumber,
            'type' => 'request',
        ]));

        $suspectMatches = TestRequest::query()
            ->when($lastLower !== '', function (Builder $builder) use ($lastLower) {
                $builder->where('suspect_name', 'like', '%' . $lastLower . '%');
            })
            ->orderBy('suspect_name')
            ->limit(5)
            ->pluck('suspect_name')
            ->unique();

        $suggestions = $suggestions->merge($suspectMatches->map(fn (string $suspect) => [
            'label' => "Tersangka: {$suspect}",
            'insert' => $suspect,
            'type' => 'tersangka',
        ]));

        $caseMatches = TestRequest::query()
            ->when($lastLower !== '', function (Builder $builder) use ($lastLower) {
                $builder->where('case_number', 'like', '%' . $lastLower . '%');
            })
            ->orderBy('case_number')
            ->limit(5)
            ->pluck('case_number')
            ->filter();

        $suggestions = $suggestions->merge($caseMatches->map(fn (string $case) => [
            'label' => "Perkara: {$case}",
            'insert' => $case,
            'type' => 'perkara',
        ]));

        return response()->json([
            'items' => $suggestions->take(12)->values(),
        ]);
    }

    public function download(Document $doc = null, Request $request = null)
    {
        // Handle generated documents (not in database)
        if (!$doc && $request && $request->has('generated')) {
            $filePath = $request->input('file_path');
            $filename = $request->input('filename', basename($filePath));
            
            // Security: Validate path to prevent directory traversal
            $absolutePath = base_path($filePath);
            $outputPath = base_path('output');
            
            // Ensure file is within output directory
            $realPath = realpath($absolutePath);
            $realOutputPath = realpath($outputPath);
            
            if (!$realPath || !$realOutputPath || !str_starts_with($realPath, $realOutputPath)) {
                Log::warning('Attempted path traversal in download', [
                    'file_path' => $filePath,
                    'absolute_path' => $absolutePath,
                    'ip' => $request->ip(),
                ]);
                abort(403, 'Akses ditolak');
            }
            
            if (!file_exists($realPath)) {
                abort(404, 'Dokumen yang digenerate tidak ditemukan');
            }
            
            try {
                return response()->download($realPath, $filename);
            } catch (\Exception $e) {
                Log::error('Download failed', ['file' => $realPath, 'error' => $e->getMessage()]);
                abort(500, 'Gagal mengunduh dokumen');
            }
        }
        
        // Handle regular database documents
        if (!$doc) {
            abort(404, 'Dokumen tidak ditemukan');
        }
        
        $disk = config('filesystems.default');
        $path = $doc->file_path;

        abort_unless(Storage::disk($disk)->exists($path), 404, 'Dokumen tidak ditemukan');

        $filename = $doc->original_filename ?? basename($path);
        $absolute = Storage::disk($disk)->path($path);

        try {
            return response()->download($absolute, $filename);
        } catch (\Exception $e) {
            Log::error('Download failed', ['doc_id' => $doc->id, 'error' => $e->getMessage()]);
            abort(500, 'Gagal mengunduh dokumen');
        }
    }

    public function preview(Document $doc = null, Request $httpRequest = null)
    {
        // Handle generated documents (not in database)
        if (!$doc && $httpRequest && $httpRequest->has('generated')) {
            $filePath = $httpRequest->input('file_path');
            $filename = $httpRequest->input('filename', basename($filePath));
            $mimeType = $httpRequest->input('mime_type', 'application/octet-stream');
            
            // Security: Validate path to prevent directory traversal
            $absolutePath = base_path($filePath);
            $outputPath = base_path('output');
            
            // Ensure file is within output directory
            $realPath = realpath($absolutePath);
            $realOutputPath = realpath($outputPath);
            
            if (!$realPath || !$realOutputPath || !str_starts_with($realPath, $realOutputPath)) {
                Log::warning('Attempted path traversal in preview', [
                    'file_path' => $filePath,
                    'absolute_path' => $absolutePath,
                    'ip' => $httpRequest->ip(),
                ]);
                abort(403, 'Akses ditolak');
            }
            
            if (!file_exists($realPath)) {
                abort(404, 'Preview tidak tersedia - file tidak ditemukan');
            }
            
            try {
                if ($mimeType && Str::startsWith($mimeType, 'image/')) {
                    return response()->file($realPath, [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => 'inline; filename="' . $filename . '"',
                    ]);
                }
                
                return response()->download($realPath, $filename);
            } catch (\Exception $e) {
                Log::error('Preview failed', ['file' => $realPath, 'error' => $e->getMessage()]);
                abort(500, 'Gagal menampilkan preview');
            }
        }
        
        // Handle regular database documents
        if (!$doc) {
            abort(404, 'Dokumen tidak ditemukan');
        }
        
        $disk = config('filesystems.default');
        $path = $doc->file_path;

        abort_unless(Storage::disk($disk)->exists($path), 404, 'Dokumen tidak ditemukan');

        $filename = $doc->original_filename ?? basename($path);
        $absolute = Storage::disk($disk)->path($path);
        $mimeType = $doc->mime_type ?? mime_content_type($absolute);

        try {
            if ($mimeType && Str::startsWith($mimeType, 'image/')) {
                return response()->file($absolute, [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ]);
            }

            return response()->download($absolute, $filename);
        } catch (\Exception $e) {
            Log::error('Preview failed', ['doc_id' => $doc->id, 'error' => $e->getMessage()]);
            abort(500, 'Gagal menampilkan preview');
        }
    }

    public function bundle(TestRequest $testRequest)
    {
        if (!class_exists('ZipArchive')) {
            abort(500, 'Fitur ZIP tidak tersedia. PHP zip extension belum diaktifkan.');
        }

        $categoryKey = $this->docKeyFromLabel(request()->query('category'));

        $documents = $testRequest->documents()
            ->when($categoryKey, fn (Builder $query) => $query->where('document_type', $categoryKey))
            ->get();
        abort_if($documents->isEmpty(), 404, 'Tidak ada dokumen untuk permintaan ini.');

        $zipName = 'docs-' . Str::slug($testRequest->request_number ?? ('request-' . $testRequest->id)) . '.zip';

        $tempDirectory = storage_path('app/tmp');
        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        $tempPath = $tempDirectory . DIRECTORY_SEPARATOR . Str::uuid()->toString() . '.zip';

        $zip = new \ZipArchive();
        $openResult = $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        
        if ($openResult !== true) {
            abort(500, 'Gagal membuat paket dokumen. Error code: ' . $openResult);
        }

        $disk = config('filesystems.default');
        $filesAdded = 0;

        foreach ($documents as $document) {
            if (!$document->file_path) {
                continue;
            }

            if (!Storage::disk($disk)->exists($document->file_path)) {
                continue;
            }

            $category = $document->document_type ?? 'documents';
            $filename = $document->original_filename ?? basename($document->file_path);
            $archivePath = trim($category, '/') . '/' . $filename;

            $fileContent = Storage::disk($disk)->get($document->file_path);
            if ($zip->addFromString($archivePath, $fileContent)) {
                $filesAdded++;
            }
        }

        $zip->close();

        if ($filesAdded === 0) {
            @unlink($tempPath);
            abort(404, 'Tidak ada dokumen yang dapat diunduh.');
        }

        if (!file_exists($tempPath)) {
            abort(500, 'Gagal membuat file ZIP.');
        }

        return response()->download($tempPath, $zipName)->deleteFileAfterSend();
    }
    
    /**
     * Collect generated documents from output folder with caching
     */
    protected function collectGeneratedDocuments(TestRequest $request): Collection
    {
        // Cache key based on request ID and updated timestamp
        $cacheKey = "generated_docs_{$request->id}_" . ($request->updated_at?->timestamp ?? 'new');
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function() use ($request) {
            return $this->scanGeneratedDocuments($request);
        });
    }
    
    /**
     * Scan filesystem for generated documents
     */
    protected function scanGeneratedDocuments(TestRequest $request): Collection
    {
        $documents = collect();
        
        // Check for BA Penyerahan (Berita Acara Penyerahan)
        $sanitizedReqNo = preg_replace('/[^A-Za-z0-9_-]+/', '_', $request->request_number);
        $baPath = base_path("output/BA_Penyerahan_Ringkasan_{$sanitizedReqNo}.html");
        
        if (file_exists($baPath)) {
            $documents->push((object) [
                'id' => 'generated_ba_' . $request->id,
                'test_request_id' => $request->id,
                'document_type' => 'ba_penyerahan',
                'file_path' => "output/BA_Penyerahan_Ringkasan_{$sanitizedReqNo}.html",
                'original_filename' => "BA_Penyerahan_Ringkasan_{$request->request_number}.html",
                'mime_type' => 'text/html',
                'is_generated' => true,
            ]);
        }
        
        // Check for LHU (Laporan Hasil Uji) for each sample
        // Note: testProcesses already eager loaded in main query
        foreach ($request->samples as $sample) {
            $interpProcess = $sample->testProcesses->where('stage', 'interpretation')->first();
            
            if ($interpProcess) {
                $metadata = $interpProcess->metadata ?? [];
                $reportNumber = $metadata['report_number'] ?? null;
                
                if ($reportNumber) {
                    $lhuPath = base_path("output/laporan-hasil-uji/Laporan_Hasil_Uji_{$reportNumber}.html");
                    
                    if (file_exists($lhuPath)) {
                        $documents->push((object) [
                            'id' => 'generated_lhu_' . $interpProcess->id,
                            'test_request_id' => $request->id,
                            'document_type' => 'lhu',
                            'file_path' => "output/laporan-hasil-uji/Laporan_Hasil_Uji_{$reportNumber}.html",
                            'original_filename' => "Laporan_Hasil_Uji_{$reportNumber}.html",
                            'mime_type' => 'text/html',
                            'is_generated' => true,
                        ]);
                    }
                }
            }
        }
        
        return $documents;
    }
}
