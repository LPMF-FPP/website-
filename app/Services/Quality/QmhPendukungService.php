<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRelation;
use App\Models\QmhDocumentRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QmhPendukungService
{
    private string $disk;

    private string $dir;

    public function __construct()
    {
        $this->disk = (string) config('quality.pendukung.storage_disk', 'local');
        $this->dir = trim((string) config('quality.pendukung.storage_dir', 'qmh-pendukung'), '/');
    }

    public function create(array $data, UploadedFile $file, int $actorId): QmhDocument
    {
        $stored = $this->storeUploadedFile($file, (int) $data['clause']);

        try {
            return DB::transaction(function () use ($data, $actorId, $stored) {
                $document = QmhDocument::query()->create([
                    'doc_code' => $data['doc_code'],
                    'title' => $data['title'],
                    'clause' => (int) $data['clause'],
                    'doc_type' => 'pendukung',
                    'owner_label' => 'Laboratorium',
                    'is_active' => true,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $revision = new QmhDocumentRevision([
                    'document_id' => $document->id,
                    'change_summary' => $data['change_summary'] ?? null,
                    'version_bump_mode' => 'manual',
                    'status' => 'published',
                    'source_pdf_disk' => $this->disk,
                    'source_pdf_path' => $stored['path'],
                    'source_pdf_mime' => $stored['mime'],
                    'source_pdf_size' => $stored['size'],
                    'file_hash' => $stored['hash'],
                    'source_pdf_sha256' => $stored['hash'],
                    'source_pdf_uploaded_at' => now(),
                    'dibuat_oleh' => $actorId,
                ]);
                $revision->setPendukungVersion(1);
                $revision->save();

                $document->current_revision_id = $revision->id;
                $document->save();

                return $document->fresh(['currentRevision']);
            });
        } catch (\Throwable $exception) {
            if (Storage::disk($this->disk)->exists($stored['path'])) {
                Storage::disk($this->disk)->delete($stored['path']);
            }

            throw $exception;
        }
    }

    public function updateVersion(QmhDocument $document, array $data, ?UploadedFile $file, int $actorId): QmhDocument
    {
        $newStoredPath = null;

        try {
            return DB::transaction(function () use ($document, $data, $file, $actorId, &$newStoredPath) {
                $lockedDocument = QmhDocument::query()
                    ->whereKey($document->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensurePendukung($lockedDocument);

                $latestRevision = QmhDocumentRevision::query()
                    ->where('document_id', $lockedDocument->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (! $latestRevision instanceof QmhDocumentRevision) {
                    throw ValidationException::withMessages([
                        'document' => 'Dokumen pendukung tidak memiliki revisi aktif.',
                    ]);
                }

                $nextVersion = ((int) $latestRevision->revision_number) + 2;
                if ($nextVersion > 10) {
                    throw ValidationException::withMessages([
                        'file' => 'Maksimal 10 versi tercapai. Buat dokumen baru untuk versi lebih tinggi.',
                    ]);
                }

                $nextClause = isset($data['clause']) ? (int) $data['clause'] : (int) $lockedDocument->clause;
                $filePayload = null;
                if ($file instanceof UploadedFile) {
                    $filePayload = $this->storeUploadedFile($file, $nextClause);
                    $newStoredPath = $filePayload['path'];
                }

                $lockedDocument->fill([
                    'doc_code' => $data['doc_code'] ?? $lockedDocument->doc_code,
                    'title' => $data['title'] ?? $lockedDocument->title,
                    'clause' => $nextClause,
                    'updated_by' => $actorId,
                ]);
                $lockedDocument->save();

                $newRevision = new QmhDocumentRevision([
                    'document_id' => $lockedDocument->id,
                    'change_summary' => $data['change_summary'] ?? null,
                    'version_bump_mode' => 'manual',
                    'status' => 'published',
                    'source_pdf_disk' => $filePayload['disk'] ?? $latestRevision->source_pdf_disk,
                    'source_pdf_path' => $filePayload['path'] ?? $latestRevision->source_pdf_path,
                    'source_pdf_mime' => $filePayload['mime'] ?? $latestRevision->source_pdf_mime,
                    'source_pdf_size' => $filePayload['size'] ?? $latestRevision->source_pdf_size,
                    'file_hash' => $filePayload['hash'] ?? $latestRevision->file_hash,
                    'source_pdf_sha256' => $filePayload['hash'] ?? $latestRevision->source_pdf_sha256,
                    'source_pdf_uploaded_at' => now(),
                    'dibuat_oleh' => $actorId,
                ]);
                $newRevision->setPendukungVersion($nextVersion);
                $newRevision->save();

                $lockedDocument->current_revision_id = $newRevision->id;
                $lockedDocument->save();

                if ($filePayload !== null) {
                    $oldPath = $latestRevision->source_pdf_path;
                    $oldDisk = (string) ($latestRevision->source_pdf_disk ?: $this->disk);
                    if (is_string($oldPath) && $oldPath !== '' && Storage::disk($oldDisk)->exists($oldPath)) {
                        $deleted = Storage::disk($oldDisk)->delete($oldPath);
                        if (! $deleted) {
                            throw ValidationException::withMessages([
                                'file' => 'Gagal mengganti file versi sebelumnya.',
                            ]);
                        }
                    }
                }

                return $lockedDocument->fresh(['currentRevision']);
            });
        } catch (\Throwable $exception) {
            if (is_string($newStoredPath) && $newStoredPath !== '' && Storage::disk($this->disk)->exists($newStoredPath)) {
                Storage::disk($this->disk)->delete($newStoredPath);
            }

            throw $exception;
        }
    }

    public function delete(QmhDocument $document, ?int $actorId = null): void
    {
        DB::transaction(function () use ($document, $actorId) {
            $lockedDocument = QmhDocument::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePendukung($lockedDocument);

            $revisions = QmhDocumentRevision::query()
                ->where('document_id', $lockedDocument->id)
                ->lockForUpdate()
                ->get();

            foreach ($revisions as $revision) {
                $disk = (string) ($revision->source_pdf_disk ?: $this->disk);
                $path = (string) ($revision->source_pdf_path ?? '');
                if ($path !== '' && Storage::disk($disk)->exists($path)) {
                    $deleted = Storage::disk($disk)->delete($path);
                    if (! $deleted) {
                        throw ValidationException::withMessages([
                            'document' => 'Gagal menghapus file fisik dokumen pendukung.',
                        ]);
                    }
                }
            }

            QmhDocumentRelation::query()
                ->where('source_document_id', $lockedDocument->id)
                ->orWhere('target_document_id', $lockedDocument->id)
                ->delete();

            if ($actorId !== null) {
                $lockedDocument->updated_by = $actorId;
                $lockedDocument->save();
            }

            $lockedDocument->delete();
        });
    }

    public function getFilePath(QmhDocument $document): string
    {
        $revision = $document->currentRevision ?? $document->latestRevision;

        return (string) ($revision?->source_pdf_path ?? '');
    }

    public function getFileUrl(QmhDocument $document): string
    {
        return route('quality.pendukung.file', [
            'document' => $document,
            'v' => (int) $document->current_revision_id,
        ]);
    }

    public function fileExists(QmhDocument $document): bool
    {
        $path = $this->getFilePath($document);
        if ($path === '') {
            return false;
        }

        $revision = $document->currentRevision ?? $document->latestRevision;
        $disk = (string) ($revision?->source_pdf_disk ?: $this->disk);

        return Storage::disk($disk)->exists($path);
    }

    public function handleMissingFile(QmhDocument $document): bool
    {
        if (! $this->fileExists($document)) {
            $revision = $document->currentRevision ?? $document->latestRevision;
            Log::warning('Physical file missing for document', [
                'doc_id' => $document->id,
                'file_path' => (string) ($revision?->source_pdf_path ?? ''),
            ]);

            return false;
        }

        return true;
    }

    public function verifyFileIntegrity(QmhDocument $document): bool
    {
        $revision = $document->currentRevision ?? $document->latestRevision;
        if (! $revision instanceof QmhDocumentRevision) {
            return false;
        }

        $path = (string) ($revision->source_pdf_path ?? '');
        if ($path === '') {
            return false;
        }

        $disk = (string) ($revision->source_pdf_disk ?: $this->disk);
        if (! Storage::disk($disk)->exists($path)) {
            return false;
        }

        $expected = strtolower(trim((string) ($revision->file_hash ?: $revision->source_pdf_sha256)));
        if ($expected === '') {
            return true;
        }

        $binary = Storage::disk($disk)->get($path);
        $actual = hash('sha256', $binary);

        return hash_equals($expected, $actual);
    }

    public function getPendukungUsage(QmhDocument $document): Collection
    {
        return QmhDocumentRelation::query()
            ->where('target_document_id', $document->id)
            ->where('relation_type', 'pendukung')
            ->with('sourceDocument')
            ->get();
    }

    private function ensurePendukung(QmhDocument $document): void
    {
        if (! $document->isPendukung()) {
            throw ValidationException::withMessages([
                'document' => 'Dokumen yang dipilih bukan dokumen pendukung.',
            ]);
        }
    }

    /**
     * @return array{disk: string, path: string, mime: string, size: int, hash: string}
     */
    private function storeUploadedFile(UploadedFile $file, int $clause): array
    {
        $binary = file_get_contents($file->getRealPath());
        if (! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages([
                'file' => 'File tidak dapat dibaca.',
            ]);
        }

        $detectedMime = $this->detectMimeByMagicNumber($binary);
        if ($detectedMime === null) {
            throw ValidationException::withMessages([
                'file' => 'File content does not match its extension',
            ]);
        }

        $allowedMimes = config('quality.pendukung.allowed_mimes', []);
        if (! is_array($allowedMimes) || ! in_array($detectedMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => 'Tipe file tidak diizinkan. Gunakan: jpg, png, webp, pdf',
            ]);
        }

        $extension = $this->extensionForMime($detectedMime);
        $originalExtension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($originalExtension, $this->allowedExtensionsForMime($detectedMime), true)) {
            throw ValidationException::withMessages([
                'file' => 'File content does not match its extension',
            ]);
        }

        $safeClause = in_array($clause, [4, 5, 6, 7, 8], true) ? $clause : 4;
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $this->dir !== ''
            ? $this->dir.'/'.$safeClause.'/'.$filename
            : $safeClause.'/'.$filename;

        $stored = Storage::disk($this->disk)->put($path, $binary);
        if (! $stored) {
            throw ValidationException::withMessages([
                'file' => 'Gagal menyimpan file. Kapasitas storage mungkin penuh.',
            ]);
        }

        return [
            'disk' => $this->disk,
            'path' => $path,
            'mime' => $detectedMime,
            'size' => strlen($binary),
            'hash' => hash('sha256', $binary),
        ];
    }

    private function detectMimeByMagicNumber(string $binary): ?string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($binary, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        if (substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        if (str_starts_with($binary, '%PDF')) {
            return 'application/pdf';
        }

        return null;
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    /**
     * @return array<int, string>
     */
    private function allowedExtensionsForMime(string $mime): array
    {
        return match ($mime) {
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
            default => [],
        };
    }
}
