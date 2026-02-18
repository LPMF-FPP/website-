<?php

namespace App\Console\Commands;

use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentLock;
use App\Models\QmhDocumentRelation;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplateFallbackRequest;
use App\Models\QmhWorkflowEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QmhSmokeCleanupCommand extends Command
{
    protected $signature = 'qmh:smoke:cleanup
        {--marker=SMOKE-QMH- : Marker prefix pada doc_code/title smoke data}
        {--execute : Jalankan penghapusan (default hanya dry-run)}';

    protected $description = 'Safely cleanup QMH smoke data by marker without touching non-smoke records';

    public function handle(): int
    {
        $marker = trim((string) $this->option('marker'));
        $execute = (bool) $this->option('execute');

        if ($marker === '' || mb_strlen($marker) < 4) {
            $this->error('Marker minimal 4 karakter dan tidak boleh kosong.');

            return self::FAILURE;
        }

        $documents = QmhDocument::query()
            ->select(['id', 'doc_code', 'title'])
            ->where(function ($query) use ($marker): void {
                $query->where('doc_code', 'like', $marker.'%')
                    ->orWhere('title', 'like', $marker.'%');
            })
            ->orderBy('id')
            ->get();

        if ($documents->isEmpty()) {
            $this->info("Tidak ada smoke data dengan marker '{$marker}'.");

            return self::SUCCESS;
        }

        $documentIds = $documents->pluck('id')->all();

        $revisions = QmhDocumentRevision::query()
            ->select(['id', 'document_id', 'source_pdf_disk', 'source_pdf_path'])
            ->whereIn('document_id', $documentIds)
            ->get();

        $revisionIds = $revisions->pluck('id')->all();

        $idempotencyRows = $this->collectIdempotencyRows($revisionIds, $marker);
        $idempotencyIds = $idempotencyRows->pluck('id')->all();

        $counts = [
            'documents' => count($documentIds),
            'revisions' => count($revisionIds),
            'workflow_events' => empty($revisionIds)
                ? 0
                : QmhWorkflowEvent::query()->whereIn('revision_id', $revisionIds)->count(),
            'locks' => empty($revisionIds)
                ? 0
                : QmhDocumentLock::query()->whereIn('revision_id', $revisionIds)->count(),
            'download_logs' => QmhDocumentDownloadLog::query()->whereIn('document_id', $documentIds)->count(),
            'relations' => QmhDocumentRelation::query()
                ->whereIn('source_document_id', $documentIds)
                ->orWhereIn('target_document_id', $documentIds)
                ->count(),
            'fallback_requests' => QmhTemplateFallbackRequest::query()->whereIn('document_id', $documentIds)->count(),
            'idempotency_keys' => count($idempotencyIds),
        ];

        $files = $this->collectSourcePdfFiles($revisions);

        $this->info('Target smoke data terdeteksi:');
        foreach ($documents as $document) {
            $this->line(sprintf('- [%d] %s | %s', $document->id, $document->doc_code, $document->title));
        }

        $this->line('');
        $this->line('Ringkasan dampak:');
        foreach ($counts as $label => $value) {
            $this->line('- '.$label.': '.$value);
        }
        $this->line('- source_pdf_files: '.$files->count());

        if (! $execute) {
            $this->warn('Dry-run mode aktif. Tidak ada data yang dihapus.');
            $this->line("Untuk eksekusi: php artisan qmh:smoke:cleanup --marker='{$marker}' --execute");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($documentIds, $idempotencyIds): void {
            if (! empty($idempotencyIds)) {
                DB::table('qmh_workflow_idempotency_keys')->whereIn('id', $idempotencyIds)->delete();
            }

            QmhDocument::query()->whereIn('id', $documentIds)->delete();
        });

        $deletedFiles = 0;
        $missingFiles = 0;
        $failedFiles = [];

        foreach ($files as $file) {
            $diskName = (string) $file['disk'];
            $path = (string) $file['path'];

            try {
                $disk = Storage::disk($diskName);
                if (! $disk->exists($path)) {
                    $missingFiles++;

                    continue;
                }

                if ($disk->delete($path)) {
                    $deletedFiles++;

                    continue;
                }

                $failedFiles[] = $diskName.':'.$path;
            } catch (\Throwable) {
                $failedFiles[] = $diskName.':'.$path;
            }
        }

        $this->info('Cleanup smoke data selesai.');
        $this->line('- documents_deleted: '.$counts['documents']);
        $this->line('- idempotency_deleted: '.$counts['idempotency_keys']);
        $this->line('- source_pdf_deleted: '.$deletedFiles);
        $this->line('- source_pdf_missing: '.$missingFiles);

        if (! empty($failedFiles)) {
            $this->warn('Ada file source PDF yang gagal dihapus:');
            foreach ($failedFiles as $failedFile) {
                $this->line('- '.$failedFile);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $revisionIds
     */
    private function collectIdempotencyRows(array $revisionIds, string $marker): Collection
    {
        return DB::table('qmh_workflow_idempotency_keys')
            ->select(['id', 'idempotency_key', 'result_ref'])
            ->where(function ($query) use ($revisionIds, $marker): void {
                if (! empty($revisionIds)) {
                    $resultRefs = array_map(static fn (int $id): string => 'revision:'.$id, $revisionIds);
                    $query->whereIn('result_ref', $resultRefs);
                }

                $query->orWhere('idempotency_key', 'like', $marker.'%');
            })
            ->get();
    }

    private function collectSourcePdfFiles(Collection $revisions): Collection
    {
        return $revisions
            ->filter(static fn (QmhDocumentRevision $revision): bool => is_string($revision->source_pdf_path) && trim($revision->source_pdf_path) !== '')
            ->map(static function (QmhDocumentRevision $revision): array {
                $disk = is_string($revision->source_pdf_disk) && trim($revision->source_pdf_disk) !== ''
                    ? trim($revision->source_pdf_disk)
                    : (string) config('filesystems.default', 'local');

                return [
                    'disk' => $disk,
                    'path' => trim((string) $revision->source_pdf_path),
                ];
            })
            ->unique(static fn (array $entry): string => $entry['disk'].'|'.$entry['path'])
            ->values();
    }
}
