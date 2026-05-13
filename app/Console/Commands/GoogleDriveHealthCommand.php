<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoogleDriveDocumentSyncService;
use Illuminate\Console\Command;

class GoogleDriveHealthCommand extends Command
{
    protected $signature = 'lims:google-drive-health';

    protected $description = 'Cek kesehatan konfigurasi dan token Google Drive tanpa mengupload file.';

    public function handle(GoogleDriveDocumentSyncService $syncService): int
    {
        $health = $syncService->googleDriveHealth();

        $this->table(['Item', 'Nilai'], [
            ['Uploader terpusat', $health['configured_uploader_user_id'] ?? 'belum diset'],
            ['Uploader tersedia', $health['configured_uploader_available'] ? 'ya' : 'tidak'],
            ['User aktif dengan token Drive', $health['active_users_with_drive_token']],
            ['Status token uploader', $health['token_status']],
            ['Dokumen retry skipped/failed', $health['retryable_documents']],
            ['Dokumen upload belum terlacak', $health['untracked_upload_documents']],
        ]);

        if ($health['token_message']) {
            $this->warn($health['token_message']);
        }

        if ($health['token_status'] !== 'ok') {
            $this->error('Google Drive belum sehat. Hubungkan ulang akun uploader sebelum mengandalkan sinkronisasi otomatis.');

            return self::FAILURE;
        }

        if ($health['retryable_documents'] > 0) {
            $this->warn('Ada dokumen tertunda. Jalankan: php artisan lims:google-drive-sync-documents');
        }

        if ($health['untracked_upload_documents'] > 0) {
            $this->line('Ada dokumen upload lama tanpa status Drive. Gunakan --status=all bila ingin sinkronisasi arsip lama.');
        }

        return self::SUCCESS;
    }
}
