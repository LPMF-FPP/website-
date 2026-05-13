<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GoogleDriveDocumentSyncService
{
    public function __construct(
        private readonly GoogleDriveOAuthService $oauth,
        private readonly GoogleDriveService $drive,
    ) {}

    /**
     * @param  Collection<int, Document>|iterable<int, Document>  $documents
     * @return array{uploaded:int, skipped:int, failed:int, reason:?string}
     */
    public function syncUploadedDocuments(iterable $documents, ?User $user): array
    {
        $result = ['uploaded' => 0, 'skipped' => 0, 'failed' => 0, 'reason' => null];
        $syncUser = null;
        $accessToken = null;
        $accessFailure = null;

        $syncCandidates = $this->syncUsersFor($user);
        if ($syncCandidates === []) {
            foreach ($documents as $document) {
                $this->markSkipped($document, $this->noActiveAccountReason());
                $result['skipped']++;
            }
            $result['reason'] = $this->noActiveAccountReason();

            return $result;
        }

        foreach ($syncCandidates as $candidate) {
            try {
                $accessToken = $this->oauth->accessTokenFor($candidate);
                $syncUser = $candidate;
                break;
            } catch (Throwable $exception) {
                $accessFailure = $exception;

                Log::warning('Google Drive OAuth token unavailable for document sync candidate', [
                    'user_id' => $candidate->id,
                    'using_configured_uploader' => $candidate->is($this->configuredUploaderUser()),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if (! $syncUser || ! is_string($accessToken)) {
            $reason = $accessFailure
                ? $this->humanTokenFailureReason($accessFailure->getMessage())
                : $this->noActiveAccountReason();

            foreach ($documents as $document) {
                $this->markSkipped($document, $reason);
                $result['skipped']++;
            }
            $result['reason'] = $reason;

            return $result;
        }

        foreach ($documents as $document) {
            try {
                $this->syncDocument($document, $accessToken, $syncUser);
                $result['uploaded']++;
            } catch (Throwable $exception) {
                $this->markFailed($document, $exception->getMessage());
                $result['failed']++;

                Log::warning('Google Drive document sync failed', [
                    'document_id' => $document->id,
                    'document_type' => $document->document_type,
                    'user_id' => $syncUser->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $result;
    }

    public function googleDriveHealth(): array
    {
        $configuredUploader = $this->configuredUploaderUser();
        $configuredUploaderId = settings('google_drive.uploader_user_id');
        $activeTokenUsers = User::query()
            ->where('is_active', true)
            ->whereHas('googleDriveToken')
            ->count();

        $retryable = Document::query()
            ->where('source', 'upload')
            ->whereIn('extra->google_drive->status', ['skipped', 'failed'])
            ->count();

        $untracked = Document::query()
            ->where('source', 'upload')
            ->whereNull('extra->google_drive->status')
            ->count();

        $tokenStatus = 'missing';
        $tokenMessage = null;

        if ($configuredUploader) {
            try {
                $this->oauth->accessTokenFor($configuredUploader);
                $tokenStatus = 'ok';
            } catch (Throwable $exception) {
                $tokenStatus = 'invalid';
                $tokenMessage = $this->humanTokenFailureReason($exception->getMessage());
            }
        }

        return [
            'configured_uploader_user_id' => is_numeric($configuredUploaderId) ? (int) $configuredUploaderId : null,
            'configured_uploader_available' => (bool) $configuredUploader,
            'active_users_with_drive_token' => $activeTokenUsers,
            'token_status' => $tokenStatus,
            'token_message' => $tokenMessage,
            'retryable_documents' => $retryable,
            'untracked_upload_documents' => $untracked,
        ];
    }

    public function deleteSyncedDocument(Document $document, ?User $user): bool
    {
        $fileId = data_get($document->extra, 'google_drive.file_id');
        if (! is_string($fileId) || $fileId === '' || ! $user) {
            return ! is_string($fileId) || $fileId === '';
        }

        try {
            $deleteUser = $this->deleteUserFor($document, $user);
            $this->drive->deleteWithAccessToken($this->oauth->accessTokenFor($deleteUser), $fileId);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Google Drive document delete failed', [
                'document_id' => $document->id,
                'google_drive_file_id' => $fileId,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function deleteUserFor(Document $document, User $fallbackUser): User
    {
        $uploadedBy = data_get($document->extra, 'google_drive.uploaded_by_user_id');
        if (is_numeric($uploadedBy)) {
            $user = User::find((int) $uploadedBy);
            if ($user) {
                return $user;
            }
        }

        return $fallbackUser;
    }

    /**
     * @return list<User>
     */
    private function syncUsersFor(?User $user): array
    {
        $users = [];
        $configuredUser = $this->configuredUploaderUser();
        if ($configuredUser) {
            $users[] = $configuredUser;
        }

        if ($user?->googleDriveToken) {
            $alreadyAdded = collect($users)->contains(fn (User $candidate): bool => $candidate->is($user));
            if (! $alreadyAdded) {
                $users[] = $user;
            }
        }

        return $users;
    }

    private function configuredUploaderUser(): ?User
    {
        $userId = settings('google_drive.uploader_user_id');
        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()
            ->whereKey((int) $userId)
            ->where('is_active', true)
            ->whereHas('googleDriveToken')
            ->first();
    }

    private function noActiveAccountReason(): string
    {
        return 'Tidak ada akun Google Drive aktif untuk sinkronisasi. Hubungkan profil user atau pilih akun uploader Google Drive di Pengaturan.';
    }

    private function humanTokenFailureReason(string $reason): string
    {
        if (str_contains(strtolower($reason), 'expired or revoked')) {
            return 'Token Google Drive akun uploader sudah tidak valid atau dicabut oleh Google. Hubungkan ulang akun Google Drive uploader di Profil, lalu jalankan sinkronisasi ulang dokumen tertunda.';
        }

        return $reason;
    }

    private function syncDocument(Document $document, string $accessToken, User $user): void
    {
        $localContents = null;
        $localHash = null;

        if (
            data_get($document->extra, 'google_drive.file_id')
            && data_get($document->extra, 'google_drive.local_path') === ($document->file_path ?: $document->path)
            && (int) data_get($document->extra, 'google_drive.local_file_size') === (int) $document->file_size
            && is_string(data_get($document->extra, 'google_drive.local_sha256'))
        ) {
            $disk = $document->storage_disk ?: 'public';
            $path = $document->file_path ?: $document->path;
            if (is_string($path) && $path !== '' && Storage::disk($disk)->exists($path)) {
                $localContents = Storage::disk($disk)->get($path);
                $localHash = hash('sha256', $localContents);
                if (data_get($document->extra, 'google_drive.local_sha256') === $localHash) {
                    return;
                }
            }
        }

        $disk = $document->storage_disk ?: 'public';
        $path = $document->file_path ?: $document->path;

        if (! is_string($path) || $path === '' || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('File lokal dokumen tidak ditemukan.');
        }

        $localContents ??= Storage::disk($disk)->get($path);
        $localHash ??= hash('sha256', $localContents);

        if (data_get($document->extra, 'google_drive.file_id')) {
            if (! $this->deleteSyncedDocument($document, $user)) {
                throw new RuntimeException('File Google Drive lama belum dapat dihapus. Sinkronisasi ulang dibatalkan untuk mencegah duplikasi remote.');
            }
        }

        $file = $this->drive->uploadWithAccessToken(
            $accessToken,
            $this->driveFilenameFor($document, $path),
            $localContents,
            $document->mime_type ?: 'application/octet-stream',
            $this->folderIdFor($document, $accessToken),
        );

        $extra = $document->extra ?? [];
        $extra['google_drive'] = [
            'status' => 'uploaded',
            'file_id' => $file['id'],
            'name' => $file['name'] ?? null,
            'web_view_link' => $file['webViewLink'] ?? null,
            'folder_path' => $this->folderPathFor($document),
            'local_path' => $path,
            'local_file_size' => (int) $document->file_size,
            'local_sha256' => $localHash,
            'document_source' => $document->source,
            'document_type' => $document->document_type,
            'uploaded_by_user_id' => $user->id,
            'uploaded_at' => now()->toIso8601String(),
        ];

        $document->forceFill(['extra' => $extra])->save();
    }

    private function markSkipped(Document $document, string $reason): void
    {
        $this->markStatus($document, 'skipped', $reason);
    }

    private function markFailed(Document $document, string $reason): void
    {
        $this->markStatus($document, 'failed', $reason);
    }

    private function markStatus(Document $document, string $status, string $reason): void
    {
        $extra = $document->extra ?? [];
        $googleDrive = $extra['google_drive'] ?? [];
        $path = $document->file_path ?: $document->path;
        $disk = $document->storage_disk ?: 'public';
        $localHash = null;

        if (is_string($path) && $path !== '' && Storage::disk($disk)->exists($path)) {
            $localHash = hash('sha256', Storage::disk($disk)->get($path));
        }

        $extra['google_drive'] = array_merge($googleDrive, [
            'status' => $status,
            'reason' => $reason,
            'local_path' => $path,
            'local_file_size' => (int) $document->file_size,
            'local_sha256' => $localHash,
            'checked_at' => now()->toIso8601String(),
        ]);

        $document->forceFill(['extra' => $extra])->save();
    }

    private function folderIdFor(Document $document, string $accessToken): string
    {
        $rootFolderName = (string) settings('google_drive.uploads_folder_name', config('google-drive.uploads_folder_name', 'LPMF LIMS Uploads'));
        $rootFolderId = $this->firstConfiguredFolderId() ?: $this->ensureFolder($accessToken, $rootFolderName);

        $parentFolderId = $rootFolderId;
        foreach ($this->requestFolderSegments($document) as $segment) {
            $parentFolderId = $this->ensureFolder($accessToken, $segment, $parentFolderId);
        }

        $sourceFolderId = $this->ensureFolder(
            $accessToken,
            $this->processFolderName($document),
            $parentFolderId,
        );

        return $sourceFolderId;
    }

    private function ensureFolder(string $accessToken, string $name, ?string $parentId = null): string
    {
        $existing = $this->drive->findFoldersWithAccessToken($accessToken, $name, $parentId);
        if ($existing !== []) {
            return $existing[0]['id'];
        }

        $metadata = ['name' => $name];
        if ($parentId) {
            $metadata['parents'] = [$parentId];
        }

        $folder = $this->drive->createFolderWithAccessToken($accessToken, $metadata);

        return $folder['id'];
    }

    private function firstConfiguredFolderId(): ?string
    {
        $folderId = settings('google_drive.folder_id', config('google-drive.folder_id'));

        return is_string($folderId) && $folderId !== '' ? $folderId : null;
    }

    /**
     * @return array<int, string>
     */
    private function requestFolderSegments(Document $document): array
    {
        $requestNumber = $this->safeFolderName((string) ($document->testRequest?->request_number ?: 'request-'.$document->test_request_id));
        $receiptNumber = $this->safeFolderName((string) ($document->testRequest?->receipt_number ?: $requestNumber));
        $mode = (string) settings('google_drive.request_folder_mode', 'month_suspect');

        if (! (bool) settings('google_drive.use_suspect_name', true)) {
            return $mode === 'month_suspect'
                ? [$this->monthFolderName($document), $receiptNumber]
                : [$requestNumber];
        }

        $suspectName = $this->safeFolderName((string) ($document->testRequest?->suspect_name ?: 'Tanpa Tersangka'));
        $receiptSuspectName = $receiptNumber.' - '.$suspectName;

        return match ($mode) {
            'month_suspect' => [$this->monthFolderName($document), $receiptSuspectName],
            'suspect_request_number' => [$suspectName.' - '.$requestNumber],
            'request_number_suspect' => [$requestNumber.' - '.$suspectName],
            default => [$requestNumber],
        };
    }

    private function processFolderName(Document $document): string
    {
        return match ($document->document_type) {
            'request_letter',
            'expert_witness_request',
            'evidence_photo',
            'sample_photo',
            'ba_penerimaan',
            'ba_penerimaan_html',
            'label_evidence',
            'sample_label',
            'label_sample' => 'Permintaan',

            'laporan_hasil_uji',
            'laporan_hasil_uji_html',
            'lhu',
            'instrument_result',
            'instrument_uv_vis',
            'instrument_gc_ms',
            'instrument_lc_ms',
            'test_results',
            'form_preparation' => 'Pengujian',

            'ba_penyerahan',
            'ba_penyerahan_html',
            'label_remaining',
            'remaining_label',
            'handover_report',
            'sample_handover' => 'Penyerahan',

            default => $document->source === 'generated' ? 'Pengujian' : 'Permintaan',
        };
    }

    private function driveFilenameFor(Document $document, string $path): string
    {
        $extension = pathinfo($document->original_filename ?: $document->filename ?: $path, PATHINFO_EXTENSION);
        $extension = $extension !== '' ? '.'.$extension : '';

        return $this->safeFilename($this->documentLabel($document).' - '.$this->documentSubjectFor($document).$extension);
    }

    private function documentLabel(Document $document): string
    {
        return match ($document->document_type) {
            'request_letter' => 'Surat Permintaan',
            'expert_witness_request' => 'Permintaan Saksi Ahli',
            'evidence_photo' => 'Foto Barang Bukti',
            'sample_photo' => 'Foto Sampel',
            'label_evidence', 'sample_label', 'label_sample' => 'Label Barang Bukti',
            'ba_penerimaan', 'ba_penerimaan_html' => 'Berita Acara Penerimaan',
            'laporan_hasil_uji', 'laporan_hasil_uji_html', 'lhu' => 'LHU',
            'instrument_result', 'instrument_uv_vis', 'instrument_gc_ms', 'instrument_lc_ms', 'test_results' => 'Lampiran Pengujian',
            'form_preparation' => 'Form Persiapan',
            'ba_penyerahan', 'ba_penyerahan_html' => 'Berita Acara Penyerahan',
            'label_remaining', 'remaining_label' => 'Label Sisa Sampel',
            'handover_report', 'sample_handover' => 'Dokumen Penyerahan',
            default => str((string) $document->document_type)->replace('_', ' ')->headline()->toString(),
        };
    }

    private function documentSubjectFor(Document $document): string
    {
        if (in_array($document->document_type, [
            'laporan_hasil_uji',
            'laporan_hasil_uji_html',
            'lhu',
            'instrument_result',
            'instrument_uv_vis',
            'instrument_gc_ms',
            'instrument_lc_ms',
            'test_results',
        ], true)) {
            return $this->sampleCodeFor($document);
        }

        return $this->suspectNameFor($document);
    }

    private function sampleCodeFor(Document $document): string
    {
        return $this->safeFilename((string) ($document->sample?->sample_code ?: 'Tanpa Kode Sampel'));
    }

    private function suspectNameFor(Document $document): string
    {
        return $this->safeFilename((string) ($document->testRequest?->suspect_name ?: 'Tanpa Tersangka'));
    }

    private function folderPathFor(Document $document): string
    {
        return implode('/', array_filter([
            $this->firstConfiguredFolderId() ? '[configured-folder]' : (string) settings('google_drive.uploads_folder_name', config('google-drive.uploads_folder_name', 'LPMF LIMS Uploads')),
            ...$this->requestFolderSegments($document),
            $this->processFolderName($document),
        ]));
    }

    private function monthFolderName(Document $document): string
    {
        $date = $document->testRequest?->created_at ?? $document->created_at ?? now();

        return $date->format('Y-m');
    }

    private function safeFolderName(string $name): string
    {
        $name = trim(str_replace(['/', '\\'], '-', $name));

        return $name !== '' ? $name : 'Tanpa Nama';
    }

    private function safeFilename(string $name): string
    {
        $name = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $name));

        return $name !== '' ? $name : 'Dokumen';
    }
}
