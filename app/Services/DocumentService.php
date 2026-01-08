<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\TestRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    protected string $disk = 'public';

    protected array $candidateDisks = [];

    /**
     * Document type to numbering scope mapping
     */
    protected array $typeToScope = [
        'ba_penerimaan' => 'ba',
        'ba_penyerahan' => 'ba_penyerahan',
        'lhu' => 'lhu',
        'laporan_hasil_uji' => 'lhu',
    ];

    public function __construct()
    {
        // Only include local disks to avoid trying S3/remote storage during resolution
        $this->candidateDisks = collect(config('filesystems.disks', []))
            ->filter(fn ($disk) => ($disk['driver'] ?? '') === 'local')
            ->keys()
            ->all();
    }

    /**
     * Type-to-subdirectory mapping
     */
    private array $typeDirs = [
        'request_letter' => 'uploads/request_letter',
        'sample_photo' => 'uploads/sample_photo',
        'evidence_photo' => 'uploads/evidence_photo',
        'form_preparation' => 'generated/form_preparation',
        'instrument_uv_vis' => 'generated/instrument_uv_vis',
        'instrument_gc_ms' => 'generated/instrument_gc_ms',
        'instrument_lc_ms' => 'generated/instrument_lc_ms',
        'instrument_result' => 'generated/instrument_result',
        'ba_penerimaan' => 'generated/ba_penerimaan',
        'ba_penerimaan_html' => 'generated/ba_penerimaan_html',
        'laporan_hasil_uji' => 'generated/laporan_hasil_uji',
        'laporan_hasil_uji_html' => 'generated/laporan_hasil_uji_html',
        'ba_penyerahan' => 'generated/ba_penyerahan',
        'ba_penyerahan_html' => 'generated/ba_penyerahan_html',
    ];

    /**
     * Human-readable document type labels for filenames
     */
    protected array $typeLabels = [
        'ba_penerimaan' => 'ba-penerimaan',
        'ba_penyerahan' => 'ba-penyerahan',
        'lhu' => 'lhu',
        'laporan_hasil_uji' => 'lhu',
    ];

    /**
     * Allowed MIME types for uploads
     */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/html',
        'text/plain',
    ];

    /**
     * Maximum file size in bytes (20MB)
     */
    protected int $maxFileSize = 20 * 1024 * 1024;

    /**
     * Generate a document number for a given type using the NumberingService.
     * Issues a new number from the appropriate scope.
     *
     * @param  string  $type  Document type (e.g., 'ba_penerimaan', 'lhu')
     * @param  array  $context  Additional context for numbering
     * @return string|null  The generated document number, or null if no scope mapping
     */
    public function issueDocumentNumber(string $type, array $context = []): ?string
    {
        $scope = $this->typeToScope[$type] ?? null;
        if (!$scope) {
            return null;
        }

        /** @var NumberingService $numbering */
        $numbering = app(NumberingService::class);
        return $numbering->issue($scope, $context);
    }

    /**
     * Preview a document number for a given type without issuing it.
     *
     * @param  string  $type  Document type (e.g., 'ba_penerimaan', 'lhu')
     * @param  array  $context  Additional context for numbering
     * @return string|null  The preview document number, or null if no scope mapping
     */
    public function previewDocumentNumber(string $type, array $context = []): ?string
    {
        $scope = $this->typeToScope[$type] ?? null;
        if (!$scope) {
            return null;
        }

        /** @var NumberingService $numbering */
        $numbering = app(NumberingService::class);
        return $numbering->preview($scope, $context);
    }

    /**
     * Generate a filename for a document based on its type and document number.
     * Converts document number to filesystem-safe format.
     *
     * @param  string  $type  Document type
     * @param  string  $documentNumber  The issued document number
     * @return string  The base filename (without extension)
     */
    public function generateDocumentBaseName(string $type, string $documentNumber): string
    {
        $label = $this->typeLabels[$type] ?? str_replace('_', '-', $type);
        
        // Convert document number to filesystem-safe format
        // e.g., "BA/2026/01/0001" -> "BA-2026-01-0001"
        $safeNumber = preg_replace('/[\/\\\\]/', '-', $documentNumber);
        $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '', $safeNumber);
        
        return "{$safeNumber}-{$label}";
    }

    /**
     * Store an uploaded file
     *
     * @param  string  $type  Document type (e.g., 'request_letter', 'sample_photo')
     *
     * @throws \Exception
     */
    public function storeUpload(
        UploadedFile $file,
        Investigator $inv,
        ?TestRequest $req = null,
        string $type = 'document'
    ): Document {
        $this->validateFile($file);

        return DB::transaction(function () use ($file, $inv, $req, $type) {
            // Build path: investigators/{folder_key}/{request_number}/{dir}/
            $invDir = "investigators/{$inv->folder_key}";
            $reqDir = $req ? $req->request_number : '';
            $dir = $this->typeDirs[$type] ?? ('uploads/'.$type);

            $path = $invDir.'/';
            if ($reqDir) {
                $path .= $reqDir.'/';
            }
            $path .= $dir.'/';

            // Generate filename: timestamp-slug.ext
            $extension = $file->getClientOriginalExtension();
            $originalFilename = $file->getClientOriginalName();
            $slug = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME));
            $timestamp = now()->format('YmdHis');
            $filename = "{$timestamp}-{$slug}.{$extension}";

            // Store file
            $filePath = $path.$filename;
            Storage::disk($this->disk)->put($filePath, file_get_contents($file->getRealPath()));

            // Create document record
            return Document::create([
                'investigator_id' => $inv->id,
                'test_request_id' => $req?->id,
                'document_type' => $type,
                'source' => 'upload',
                'storage_disk' => $this->disk,
                'filename' => $originalFilename,
                'original_filename' => $originalFilename,
                'file_path' => $filePath,
                'path' => $filePath,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'extra' => null,
            ]);
        });
    }

    /**
     * Store a generated file from binary content
     *
     * @param  string  $binary  Binary content of the file
     * @param  string  $ext  File extension (e.g., 'pdf', 'docx')
     * @param  string  $type  Document type (e.g., 'lhu', 'ba_penyerahan')
     * @param  string  $baseName  Base name for the file (will be slugified)
     * @param  bool  $replaceExisting  If true, replace existing document of same type for this request
     *
     * @throws \Exception
     */
    public function storeGenerated(
        string $binary,
        string $ext,
        Investigator $inv,
        ?TestRequest $req,
        string $type,
        string $baseName,
        bool $replaceExisting = false
    ): Document {
        return DB::transaction(function () use ($binary, $ext, $inv, $req, $type, $baseName, $replaceExisting) {
            // Check for existing document if replaceExisting is true
            if ($replaceExisting && $req) {
                $existing = Document::where('test_request_id', $req->id)
                    ->where('document_type', $type)
                    ->where('source', 'generated')
                    ->latest()
                    ->first();

                if ($existing) {
                    // Delete old file from storage
                    $oldPath = $existing->file_path ?? $existing->path;
                    if ($oldPath && Storage::disk($this->disk)->exists($oldPath)) {
                        Storage::disk($this->disk)->delete($oldPath);
                    }

                    // Build new path and filename
                    $invDir = "investigators/{$inv->folder_key}";
                    $reqDir = $req->request_number;
                    $dir = $this->typeDirs[$type] ?? ('generated/'.$type);
                    $basePath = "{$invDir}/{$reqDir}/{$dir}";

                    $slug = Str::slug($baseName);
                    $timestamp = now()->format('YmdHis');
                    $filename = "{$timestamp}-{$slug}.{$ext}";
                    $originalFilename = "{$baseName}.{$ext}";
                    $relPath = "{$basePath}/{$filename}";

                    // Store new file
                    Storage::disk($this->disk)->put($relPath, $binary);

                    // Update existing document record
                    $existing->update([
                        'filename' => $originalFilename,
                        'original_filename' => $originalFilename,
                        'file_path' => $relPath,
                        'path' => $relPath,
                        'file_size' => strlen($binary),
                        'updated_at' => now(),
                    ]);

                    return $existing->fresh();
                }
            }

            // Build path: investigators/{folder_key}/{request_number}/{dir}/
            $invDir = "investigators/{$inv->folder_key}";
            $reqDir = $req ? $req->request_number : null;
            $dir = $this->typeDirs[$type] ?? ('generated/'.$type);

            $segments = [$invDir];
            if (! empty($reqDir)) {
                $segments[] = $reqDir;
            }
            $segments[] = trim($dir, '/');

            $basePath = implode('/', $segments);

            // Generate filename: timestamp-slug.ext
            $slug = Str::slug($baseName);
            $timestamp = now()->format('YmdHis');
            $filename = "{$timestamp}-{$slug}.{$ext}";
            $originalFilename = "{$baseName}.{$ext}";

            // Store file
            $relPath = "{$basePath}/{$filename}";
            Storage::disk($this->disk)->put($relPath, $binary);

            // Determine MIME type from extension
            $mimeType = $this->getMimeTypeFromExtension($ext);

            // Create document record
            return Document::create([
                'investigator_id' => $inv->id,
                'test_request_id' => $req?->id,
                'document_type' => $type,
                'source' => 'generated',
                'storage_disk' => $this->disk,
                'filename' => $originalFilename,
                'original_filename' => $originalFilename,
                'file_path' => $relPath,
                'path' => $relPath,
                'mime_type' => $mimeType,
                'file_size' => strlen($binary),
                'extra' => null,
            ]);
        });
    }

    /**
     * Get existing generated document or return null
     *
     * @param  TestRequest  $req  The test request
     * @param  string  $type  Document type
     * @return Document|null
     */
    public function getExistingGenerated(?TestRequest $req, string $type): ?Document
    {
        if (! $req) {
            return null;
        }

        return Document::where('test_request_id', $req->id)
            ->where('document_type', $type)
            ->where('source', 'generated')
            ->latest()
            ->first();
    }

    /**
     * Store a generated file for a SampleTestProcess
     *
     * Convenience wrapper around storeGenerated for SampleTestProcess documents.
     * Automatically resolves investigator and request from the process relationships.
     *
     * @param  \App\Models\SampleTestProcess  $process  The sample test process
     * @param  string  $ext  File extension: "pdf" | "png" | "csv" | "html"
     * @param  string  $type  Document type: form_preparation | instrument_uv_vis | instrument_gc_ms | instrument_lc_ms | instrument_result
     * @param  string  $baseName  Base name for the file (e.g., "Hasil-UV-VIS-W1X2025-REQ001")
     * @param  string  $binary  Binary content of the file
     * @param  bool  $replaceExisting  If true, replace existing document of same type for this request
     * @return Document The created document record
     *
     * @throws \Exception
     */
    public function storeForSampleProcess(
        \App\Models\SampleTestProcess $process,
        string $ext,
        string $type,
        string $baseName,
        string $binary,
        bool $replaceExisting = false
    ): Document {
        $process->loadMissing(['sample.testRequest.investigator']);
        $req = $process->sample->testRequest;
        $inv = $req->investigator;

        return $this->storeGenerated(
            binary: $binary,
            ext: $ext,
            inv: $inv,
            req: $req,
            type: $type,
            baseName: $baseName,
            replaceExisting: $replaceExisting
        );
    }

    /**
     * Store a standalone generated report (not associated with an investigator/request)
     *
     * Used for system-level reports like monthly environment logs, instrument usage logs, etc.
     *
     * @param  string  $binary  Binary content of the file
     * @param  string  $ext  File extension (e.g., 'pdf')
     * @param  string  $type  Document type (e.g., 'environment_monthly_log')
     * @param  string  $baseName  Base name for the file
     * @param  array  $metadata  Additional metadata (month, location_id, asset_id, generated_by)
     * @return Document
     */
    public function storeStandaloneReport(
        string $binary,
        string $ext,
        string $type,
        string $baseName,
        array $metadata = []
    ): Document {
        return DB::transaction(function () use ($binary, $ext, $type, $baseName, $metadata) {
            // Build path: reports/{type}/{YYYY}/{MM}/
            $month = $metadata['month'] ?? now()->format('Y-m');
            [$year, $monthNum] = explode('-', $month);
            $basePath = "reports/{$type}/{$year}/{$monthNum}";

            // Generate filename: timestamp-slug.ext
            $slug = Str::slug($baseName);
            $timestamp = now()->format('YmdHis');
            $filename = "{$timestamp}-{$slug}.{$ext}";
            $originalFilename = "{$baseName}.{$ext}";

            // Store file
            $relPath = "{$basePath}/{$filename}";
            Storage::disk($this->disk)->put($relPath, $binary);

            // Determine MIME type from extension
            $mimeType = $this->getMimeTypeFromExtension($ext);

            // Create document record with metadata in extra field
            return Document::create([
                'investigator_id' => null,  // Standalone report
                'test_request_id' => null,  // Standalone report
                'document_type' => $type,
                'source' => 'generated',
                'storage_disk' => $this->disk,
                'filename' => $originalFilename,
                'original_filename' => $originalFilename,
                'file_path' => $relPath,
                'path' => $relPath,
                'mime_type' => $mimeType,
                'file_size' => strlen($binary),
                'extra' => !empty($metadata) ? json_encode($metadata) : null,
            ]);
        });
    }

    /**
     * Get documents for an investigator
     *
     * @param  array  $filters  Optional filters (type, source, request_id)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDocuments(Investigator $investigator, array $filters = [])
    {
        $query = Document::where('investigator_id', $investigator->id)
            ->with(['testRequest:id,request_number,case_number'])
            ->orderByDesc('created_at');

        if (! empty($filters['type'])) {
            $query->where('document_type', $filters['type']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['request_id'])) {
            $query->where('test_request_id', $filters['request_id']);
        }

        return $query->get();
    }

    /**
     * Delete a document
     */
    public function delete(Document $document): bool
    {
        // Get the disk and path first, outside of the delete transaction
        $disk = $document->storage_disk ?: $this->disk;
        $path = $document->file_path ?? $document->path;

        // Delete file from storage if it exists
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        // Delete record
        return $document->delete();
    }

    /**
     * Validate uploaded file
     *
     * @throws \Exception
     */
    protected function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception(
                'File size exceeds maximum allowed size of '.
                ($this->maxFileSize / 1024 / 1024).'MB'
            );
        }

        // Check MIME type
        if (! in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \Exception('File type not allowed: '.$file->getMimeType());
        }

        // Check if file is valid
        if (! $file->isValid()) {
            throw new \Exception('Invalid file upload');
        }
    }

    /**
     * Get document download URL
     */
    public function getDownloadUrl(Document $document): string
    {
        return route('investigator.documents.download', ['document' => $document->id]);
    }

    /**
     * Get file path for download
     */
    public function getFilePath(Document $document): string
    {
        [$disk, $path] = $this->resolveDiskAndPath($document, true);

        if (! $path) {
            throw new \RuntimeException('Document path is not defined.');
        }

        return Storage::disk($disk)->path($path);
    }

    /**
     * Check if document file exists
     */
    public function fileExists(Document $document): bool
    {
        [$disk, $path] = $this->resolveDiskAndPath($document);
        if (! $path) {
            return false;
        }

        if (Storage::disk($disk)->exists($path)) {
            return true;
        }

        foreach ($this->candidateDisks as $candidate) {
            if ($candidate === $disk) {
                continue;
            }

            if (Storage::disk($candidate)->exists($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get MIME type from file extension
     */
    protected function getMimeTypeFromExtension(string $ext): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt' => 'text/plain',
            'html' => 'text/html',
        ];

        return $mimeTypes[strtolower($ext)] ?? 'application/octet-stream';
    }

    /**
     * Resolve disk/path for a document.
     *
     * @return array{0:string,1:?string}
     */
    private function resolveDiskAndPath(Document $document, bool $updateDisk = false): array
    {
        $path = $document->file_path ?? $document->path;
        $disk = $document->storage_disk ?: $this->disk;

        if (! $path) {
            return [$disk, null];
        }

        if (Storage::disk($disk)->exists($path)) {
            return [$disk, $path];
        }

        foreach ($this->candidateDisks as $candidate) {
            if ($candidate === $disk) {
                continue;
            }

            if (Storage::disk($candidate)->exists($path)) {
                if ($updateDisk && $document->storage_disk !== $candidate) {
                    $document->storage_disk = $candidate;
                    $document->save();
                }

                return [$candidate, $path];
            }
        }

        if ($updateDisk && ! $document->storage_disk) {
            $document->storage_disk = $disk;
            $document->save();
        }

        return [$disk, $path];
    }
}
