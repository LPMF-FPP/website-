<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentService;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DocumentsIntegrityCheckCommand extends Command
{
    protected $signature = 'documents:integrity-check
        {--warn-threshold=1 : Fail when missing file count reaches this number}
        {--ratio-threshold=5 : Fail when missing percentage reaches this number}
        {--sample=10 : Number of missing rows displayed in output}
        {--json : Output report as JSON}';

    protected $description = 'Check integrity between document records and physical files in storage.';

    public function handle(DocumentService $documentService): int
    {
        $warnThreshold = max(0, (int) $this->option('warn-threshold'));
        $ratioThreshold = max(0, (float) $this->option('ratio-threshold'));
        $sampleLimit = max(1, (int) $this->option('sample'));

        $documents = Document::query()
            ->whereNull('deleted_at')
            ->get(['id', 'investigator_id', 'test_request_id', 'storage_disk', 'file_path', 'path', 'created_at']);

        $total = $documents->count();
        $missing = [];
        $byMonth = [];
        $byFolder = [];

        foreach ($documents as $document) {
            $exists = $documentService->fileExists($document);
            if ($exists) {
                continue;
            }

            $path = $document->file_path ?? $document->path;
            $disk = $document->storage_disk ?: 'public';
            $month = optional($document->created_at)->format('Y-m') ?: 'unknown';

            $folder = 'unknown';
            if ($path && preg_match('#^investigators/([^/]+)/#', $path, $matches)) {
                $folder = $matches[1];
            }

            $missing[] = [
                'id' => $document->id,
                'request_id' => $document->test_request_id,
                'investigator_id' => $document->investigator_id,
                'disk' => $disk,
                'path' => $path,
                'month' => $month,
            ];

            $byMonth[$month] = ($byMonth[$month] ?? 0) + 1;
            $byFolder[$folder] = ($byFolder[$folder] ?? 0) + 1;
        }

        arsort($byMonth);
        arsort($byFolder);

        $missingCount = count($missing);
        $presentCount = max(0, $total - $missingCount);
        $missingRatio = $total > 0 ? round(($missingCount / $total) * 100, 2) : 0.0;

        $summary = [
            'total_documents' => $total,
            'present_files' => $presentCount,
            'missing_files' => $missingCount,
            'missing_ratio_percent' => $missingRatio,
            'warn_threshold' => $warnThreshold,
            'ratio_threshold' => $ratioThreshold,
            'top_missing_months' => array_slice($byMonth, 0, 10, true),
            'top_missing_folders' => array_slice($byFolder, 0, 10, true),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'summary' => $summary,
                'samples' => array_slice($missing, 0, $sampleLimit),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Document Integrity Check');
            $this->line('----------------------------------------');
            $this->line("Total dokumen : {$total}");
            $this->line("File tersedia : {$presentCount}");
            $this->line("File hilang   : {$missingCount}");
            $this->line("Missing ratio : {$missingRatio}%");

            if ($missingCount > 0) {
                $this->line('');
                $this->warn('Top bulan dengan file hilang:');
                foreach (array_slice($byMonth, 0, 5, true) as $month => $count) {
                    $this->line("- {$month}: {$count}");
                }

                $this->line('');
                $this->warn('Top folder penyidik terdampak:');
                foreach (array_slice($byFolder, 0, 5, true) as $folder => $count) {
                    $this->line("- {$folder}: {$count}");
                }

                $this->line('');
                $this->warn('Contoh dokumen hilang:');
                foreach (array_slice($missing, 0, $sampleLimit) as $row) {
                    $this->line("- #{$row['id']} req={$row['request_id']} disk={$row['disk']} path={$row['path']}");
                }
            }
        }

        Audit::log('DOCUMENT_INTEGRITY_CHECK', 'documents', null, $summary);

        if ($missingCount > 0) {
            Log::warning('Document integrity mismatch detected', $summary);
        } else {
            Log::info('Document integrity check clean', $summary);
        }

        $failsByCount = $warnThreshold > 0 && $missingCount >= $warnThreshold;
        $failsByRatio = $ratioThreshold > 0 && $missingRatio >= $ratioThreshold;
        if ($failsByCount || $failsByRatio) {
            $this->error('Integrity threshold exceeded.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
