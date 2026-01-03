<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupDuplicateDocuments extends Command
{
    protected $signature = 'storage:cleanup-duplicates
        {--dry-run : Show what would be deleted without actually deleting}
        {--force : Skip confirmation prompt}';

    protected $description = 'Clean up duplicate generated documents, keeping only the latest for each type/request combination';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');

        $this->info('Scanning for duplicate generated documents...');

        // Find duplicates: same test_request_id + document_type + source='generated'
        // Group by these fields and find groups with more than 1 document
        $duplicates = Document::select(
            'test_request_id',
            'document_type',
            DB::raw('COUNT(*) as count'),
            DB::raw('MAX(created_at) as latest_created_at')
        )
            ->where('source', 'generated')
            ->whereNotNull('test_request_id')
            ->groupBy('test_request_id', 'document_type')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate documents found. Database is clean!');

            return self::SUCCESS;
        }

        $this->line('');
        $this->warn('Found '.$duplicates->count().' document type/request combinations with duplicates');

        $totalDuplicates = 0;
        $totalSize = 0;
        $documentsToDelete = collect();

        foreach ($duplicates as $dup) {
            // Get all documents for this combination except the latest
            $docsToRemove = Document::where('test_request_id', $dup->test_request_id)
                ->where('document_type', $dup->document_type)
                ->where('source', 'generated')
                ->where('created_at', '<', $dup->latest_created_at)
                ->get();

            foreach ($docsToRemove as $doc) {
                $documentsToDelete->push($doc);
                $totalDuplicates++;

                // Calculate size
                $path = $doc->file_path ?? $doc->path;
                if ($path && $disk->exists($path)) {
                    try {
                        $totalSize += $disk->size($path);
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
            }
        }

        $this->info("Total duplicate documents to remove: {$totalDuplicates}");
        $this->info('Total size to reclaim: '.number_format($totalSize / 1024 / 1024, 2).' MB');

        // Show some examples
        $this->line('');
        $this->info('Sample duplicates:');
        foreach ($documentsToDelete->take(10) as $doc) {
            $this->line("  - [{$doc->document_type}] {$doc->original_filename} (created: {$doc->created_at})");
        }

        if ($documentsToDelete->count() > 10) {
            $this->line('  ... and '.($documentsToDelete->count() - 10).' more');
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('[DRY RUN] No documents were deleted.');

            return self::SUCCESS;
        }

        // Confirm deletion
        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to delete these duplicate documents?')) {
                $this->info('Cleanup cancelled.');

                return self::SUCCESS;
            }
        }

        $this->line('');
        $this->info('Deleting duplicate documents...');

        $deleted = 0;
        $failed = 0;

        foreach ($documentsToDelete as $doc) {
            try {
                $path = $doc->file_path ?? $doc->path;

                // Delete file from storage
                if ($path && $disk->exists($path)) {
                    $disk->delete($path);
                }

                // Delete database record
                $doc->forceDelete();
                $deleted++;
            } catch (\Throwable $e) {
                $this->error("Failed to delete document #{$doc->id}: ".$e->getMessage());
                $failed++;
            }
        }

        $this->line('');
        $this->info("✓ Deleted {$deleted} duplicate documents");

        if ($failed > 0) {
            $this->warn("⚠ Failed to delete {$failed} documents");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
