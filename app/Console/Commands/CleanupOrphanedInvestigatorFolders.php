<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Investigator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedInvestigatorFolders extends Command
{
    protected $signature = 'storage:cleanup-investigators
        {--dry-run : Show what would be deleted without actually deleting}
        {--force : Skip confirmation prompt}';

    protected $description = 'Clean up orphaned investigator folders and duplicate generated documents';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');

        $this->info('Scanning investigator folders...');

        // Get all investigator folder_keys from database
        $validFolderKeys = Investigator::pluck('folder_key')->filter()->toArray();

        // Get all folders in investigators directory
        $investigatorDirs = $disk->directories('investigators');

        $orphanedFolders = [];
        $totalSize = 0;

        foreach ($investigatorDirs as $dir) {
            $folderName = basename($dir);

            // Check if this folder belongs to an existing investigator
            if (! in_array($folderName, $validFolderKeys)) {
                $orphanedFolders[] = $dir;

                // Calculate size of files in this folder
                $files = $disk->allFiles($dir);
                foreach ($files as $file) {
                    try {
                        $totalSize += $disk->size($file);
                    } catch (\Throwable $e) {
                        // Ignore size errors
                    }
                }
            }
        }

        $this->line('');
        $this->info('Found '.count($orphanedFolders).' orphaned folders');
        $this->info('Total size: '.number_format($totalSize / 1024 / 1024, 2).' MB');

        if (empty($orphanedFolders)) {
            $this->info('No orphaned folders found. Storage is clean!');

            return self::SUCCESS;
        }

        // List orphaned folders
        $this->line('');
        $this->warn('Orphaned folders to delete:');
        foreach (array_slice($orphanedFolders, 0, 20) as $folder) {
            $this->line("  - {$folder}");
        }

        if (count($orphanedFolders) > 20) {
            $this->line('  ... and '.(count($orphanedFolders) - 20).' more');
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('[DRY RUN] No files were deleted.');

            return self::SUCCESS;
        }

        // Confirm deletion
        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to delete these orphaned folders?')) {
                $this->info('Cleanup cancelled.');

                return self::SUCCESS;
            }
        }

        $this->line('');
        $this->info('Deleting orphaned folders...');

        $deleted = 0;
        $failed = 0;

        foreach ($orphanedFolders as $folder) {
            try {
                // Also delete any Document records that reference files in this folder
                $files = $disk->allFiles($folder);
                Document::whereIn('path', $files)
                    ->orWhereIn('file_path', $files)
                    ->delete();

                // Delete the folder
                $disk->deleteDirectory($folder);
                $deleted++;
            } catch (\Throwable $e) {
                $this->error("Failed to delete {$folder}: ".$e->getMessage());
                $failed++;
            }
        }

        $this->line('');
        $this->info("✓ Deleted {$deleted} orphaned folders");

        if ($failed > 0) {
            $this->warn("⚠ Failed to delete {$failed} folders");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
