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

    /**
     * Type-to-subdirectory mapping
     */
    private array $typeDirs = [
        'request_letter'          => 'uploads/request_letter',
        'sample_photo'            => 'uploads/sample_photo',
        'evidence_photo'          => 'uploads/evidence_photo',
        'form_preparation'        => 'generated/form_preparation',
        'instrument_uv_vis'       => 'generated/instrument_uv_vis',
        'instrument_gc_ms'        => 'generated/instrument_gc_ms',
        'instrument_lc_ms'        => 'generated/instrument_lc_ms',
        'instrument_result'       => 'generated/instrument_result',
        'ba_penerimaan'           => 'generated/ba_penerimaan',
        'ba_penerimaan_html'      => 'generated/ba_penerimaan_html',
        'laporan_hasil_uji'       => 'generated/laporan_hasil_uji',
        'laporan_hasil_uji_html'  => 'generated/laporan_hasil_uji_html',
        'ba_penyerahan'           => 'generated/ba_penyerahan',
        'ba_penyerahan_html'      => 'generated/ba_penyerahan_html',
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
     * Store an uploaded file
     *
     * @param UploadedFile $file
     * @param Investigator $inv
     * @param TestRequest|null $req
     * @param string $type Document type (e.g., 'request_letter', 'sample_photo')
     * @return Document
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
            
            $path = $invDir . '/';
            if ($reqDir) {
                $path .= $reqDir . '/';
            }
            $path .= $dir . '/';
            
            // Generate filename: timestamp-slug.ext
            $extension = $file->getClientOriginalExtension();
            $originalFilename = $file->getClientOriginalName();
            $slug = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME));
            $timestamp = now()->format('YmdHis');
            $filename = "{$timestamp}-{$slug}.{$extension}";
            
            // Store file
            $filePath = $path . $filename;
            Storage::disk($this->disk)->put($filePath, file_get_contents($file->getRealPath()));

            // Create document record
            return Document::create([
                'investigator_id' => $inv->id,
                'test_request_id' => $req?->id,
                'document_type' => $type,
                'source' => 'upload',
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
     * @param string $binary Binary content of the file
     * @param string $ext File extension (e.g., 'pdf', 'docx')
     * @param Investigator $inv
     * @param TestRequest|null $req
     * @param string $type Document type (e.g., 'lhu', 'ba_penyerahan')
     * @param string $baseName Base name for the file (will be slugified)
     * @return Document
     * @throws \Exception
     */
    public function storeGenerated(
        string $binary,
        string $ext,
        Investigator $inv,
        ?TestRequest $req,
        string $type,
        string $baseName
    ): Document {
        return DB::transaction(function () use ($binary, $ext, $inv, $req, $type, $baseName) {
            // Build path: investigators/{folder_key}/{request_number}/{dir}/
            $invDir = "investigators/{$inv->folder_key}";
            $reqDir = $req ? $req->request_number : null;
            $dir = $this->typeDirs[$type] ?? ('generated/'.$type);

            $segments = [$invDir];
            if (!empty($reqDir)) {
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
     * Store a generated file for a SampleTestProcess
     *
     * Convenience wrapper around storeGenerated for SampleTestProcess documents.
     * Automatically resolves investigator and request from the process relationships.
     *
     * @param \App\Models\SampleTestProcess $process The sample test process
     * @param string $ext File extension: "pdf" | "png" | "csv" | "html"
     * @param string $type Document type: form_preparation | instrument_uv_vis | instrument_gc_ms | instrument_lc_ms | instrument_result
     * @param string $baseName Base name for the file (e.g., "Hasil-UV-VIS-W1X2025-REQ001")
     * @param string $binary Binary content of the file
     * @return Document The created document record
     * @throws \Exception
     */
    public function storeForSampleProcess(
        \App\Models\SampleTestProcess $process,
        string $ext,
        string $type,
        string $baseName,
        string $binary
    ): Document {
        $process->loadMissing(['sample.testRequest.investigator']);
        $req = $process->sample->testRequest;
        $inv = $req->investigator;

        return $this->storeGenerated(
            binary:   $binary,
            ext:      $ext,
            inv:      $inv,
            req:      $req,
            type:     $type,
            baseName: $baseName
        );
    }

    /**
     * Get documents for an investigator
     *
     * @param Investigator $investigator
     * @param array $filters Optional filters (type, source, request_id)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDocuments(Investigator $investigator, array $filters = [])
    {
        $query = Document::where('investigator_id', $investigator->id)
            ->with(['testRequest:id,request_number,case_number'])
            ->orderByDesc('created_at');

        if (!empty($filters['type'])) {
            $query->where('document_type', $filters['type']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['request_id'])) {
            $query->where('test_request_id', $filters['request_id']);
        }

        return $query->get();
    }

    /**
     * Delete a document
     *
     * @param Document $document
     * @return bool
     */
    public function delete(Document $document): bool
    {
        return DB::transaction(function () use ($document) {
            // Delete file from storage
            if ($document->file_path && Storage::disk($this->disk)->exists($document->file_path)) {
                Storage::disk($this->disk)->delete($document->file_path);
            }

            // Delete record
            return $document->delete();
        });
    }

    /**
     * Validate uploaded file
     *
     * @param UploadedFile $file
     * @throws \Exception
     */
    protected function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception(
                'File size exceeds maximum allowed size of ' . 
                ($this->maxFileSize / 1024 / 1024) . 'MB'
            );
        }

        // Check MIME type
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \Exception('File type not allowed: ' . $file->getMimeType());
        }

        // Check if file is valid
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }
    }

    /**
     * Get document download URL
     *
     * @param Document $document
     * @return string
     */
    public function getDownloadUrl(Document $document): string
    {
        return route('investigator.documents.download', ['document' => $document->id]);
    }

    /**
     * Get file path for download
     *
     * @param Document $document
     * @return string
     */
    public function getFilePath(Document $document): string
    {
        return Storage::disk($this->disk)->path($document->file_path);
    }

    /**
     * Check if document file exists
     *
     * @param Document $document
     * @return bool
     */
    public function fileExists(Document $document): bool
    {
        return Storage::disk($this->disk)->exists($document->file_path);
    }

    /**
     * Get MIME type from file extension
     *
     * @param string $ext
     * @return string
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
}
