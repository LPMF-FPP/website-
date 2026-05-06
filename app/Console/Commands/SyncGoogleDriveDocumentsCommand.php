<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\User;
use App\Services\GoogleDriveDocumentSyncService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SyncGoogleDriveDocumentsCommand extends Command
{
    protected $signature = 'lims:google-drive-sync-documents
        {--request= : Batasi ke ID permintaan tertentu}
        {--document=* : Batasi ke ID dokumen tertentu, bisa diulang}
        {--status=skipped,failed : Status Google Drive yang di-retry, pisahkan dengan koma; gunakan all untuk semua}
        {--type= : Batasi tipe dokumen, pisahkan dengan koma}
        {--user= : User fallback untuk token Google Drive bila uploader global belum diset}
        {--dry-run : Tampilkan kandidat tanpa mengupload}';

    protected $description = 'Retry sinkronisasi dokumen lokal ke Google Drive untuk status skipped/failed.';

    public function handle(GoogleDriveDocumentSyncService $syncService): int
    {
        $documents = $this->documentsQuery()->get();

        if ($documents->isEmpty()) {
            $this->info('Tidak ada dokumen kandidat untuk sinkronisasi Google Drive.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Permintaan', 'Tipe', 'File', 'Status Drive'],
            $documents->map(fn (Document $document): array => [
                $document->id,
                $document->test_request_id,
                $document->document_type,
                $document->filename,
                data_get($document->extra, 'google_drive.status', 'none'),
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run selesai; tidak ada upload yang dilakukan.');

            return self::SUCCESS;
        }

        $result = $syncService->syncUploadedDocuments($documents, $this->fallbackUser());

        $this->info(sprintf(
            'Sinkronisasi selesai. Uploaded: %d, skipped: %d, failed: %d.',
            $result['uploaded'],
            $result['skipped'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Builder<Document>
     */
    private function documentsQuery(): Builder
    {
        $query = Document::query()
            ->where('source', 'upload')
            ->orderBy('id');

        if ($requestId = $this->option('request')) {
            $query->where('test_request_id', (int) $requestId);
        }

        $documentIds = collect($this->option('document'))
            ->flatMap(fn (string $value): array => explode(',', $value))
            ->map(fn (string $value): int => (int) trim($value))
            ->filter()
            ->values();

        if ($documentIds->isNotEmpty()) {
            $query->whereIn('id', $documentIds->all());
        } else {
            $statuses = $this->csvOption('status');
            if (! in_array('all', $statuses, true)) {
                $query->where(function (Builder $query) use ($statuses): void {
                    foreach ($statuses as $status) {
                        $query->orWhere('extra->google_drive->status', $status);
                    }
                });
            }
        }

        $types = $this->csvOption('type');
        if ($types !== []) {
            $query->whereIn('document_type', $types);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function csvOption(string $name): array
    {
        $value = $this->option($name);
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function fallbackUser(): ?User
    {
        $userId = $this->option('user');
        if (! $userId) {
            return null;
        }

        return User::find((int) $userId);
    }
}
