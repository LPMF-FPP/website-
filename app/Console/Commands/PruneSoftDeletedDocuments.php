<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneSoftDeletedDocuments extends Command
{
    protected $signature = 'storage:prune-soft-deleted
        {--days=30 : Only prune documents soft-deleted older than this many days}
        {--dry-run : Show what would be deleted without actually deleting}
        {--force : Skip confirmation prompt}';

    protected $description = 'Permanently delete documents that were soft-deleted more than N days ago';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');

        $cutoff = now()->subDays($days);

        $documentQuery = Document::onlyTrashed()
            ->where('deleted_at', '<', $cutoff);

        $docCount = $documentQuery->count();

        if ($docCount === 0) {
            $this->info("No soft-deleted documents older than {$days} days found.");

            return self::SUCCESS;
        }

        $this->line('');
        $this->warn("Found {$docCount} soft-deleted documents older than {$days} days");

        $this->line('');
        $this->info('Sample documents:');
        foreach ($documentQuery->take(10)->get() as $doc) {
            $this->line("  - [#{$doc->id}] ".($doc->document_type ?? 'unknown')." (deleted: {$doc->deleted_at})");
        }
        if ($docCount > 10) {
            $this->line('  ... and '.($docCount - 10).' more');
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('[DRY RUN] No documents were permanently deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Permanently delete these documents? This cannot be undone.')) {
                $this->info('Prune cancelled.');

                return self::SUCCESS;
            }
        }

        $this->line('');
        $this->info('Permanently deleting documents...');

        $deleted = 0;
        $failed = 0;

        $documentQuery->chunk(500, function ($docs) use ($disk, &$deleted, &$failed) {
            foreach ($docs as $doc) {
                try {
                    $doc->forceDelete();
                    $path = $doc->file_path ?? $doc->path;
                    $path = $path ? ltrim($path, '/') : null;
                    if ($path && $disk->exists($path)) {
                        $disk->delete($path);
                    }
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->error("Failed to delete document #{$doc->id}: ".$e->getMessage());
                    $failed++;
                }
            }
        });

        $this->line('');
        $this->info("✓ Permanently deleted {$deleted} documents");

        if ($failed > 0) {
            $this->warn("⚠ Failed to delete {$failed} documents");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
