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
        {--force : Skip confirmation prompt}
        {--allow-upload-delete : Allow deleting orphaned folders that contain upload files}
        {--allow-large-delete : Allow deleting more folders than max-delete}
        {--max-delete=3 : Maximum folders deleted per run unless --allow-large-delete}';

    protected $description = 'Clean up orphaned investigator folders and duplicate generated documents';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $allowUploadDelete = (bool) $this->option('allow-upload-delete');
        $allowLargeDelete = (bool) $this->option('allow-large-delete');
        $maxDelete = max(1, min(500, (int) $this->option('max-delete')));
        $disk = Storage::disk('public');

        $this->info('Scanning investigator folders...');

        $orphanedFolders = collect($this->scanOrphanedFolders($disk));
        $totalSize = (int) $orphanedFolders->sum('size');
        $protectedUploadFolders = $orphanedFolders->where('has_upload_files', true)->values();

        $deletionCandidates = $allowUploadDelete
            ? $orphanedFolders
            : $orphanedFolders->reject(fn (array $folder) => $folder['has_upload_files'])->values();

        $this->line('');
        $this->info('Found '.$orphanedFolders->count().' orphaned folders');
        $this->info('Total size: '.number_format($totalSize / 1024 / 1024, 2).' MB');
        if ($protectedUploadFolders->isNotEmpty()) {
            $this->warn('Protected folders with uploads: '.$protectedUploadFolders->count().' (not deleted by default)');
        }

        if ($orphanedFolders->isEmpty()) {
            $this->info('No orphaned folders found. Storage is clean!');

            return self::SUCCESS;
        }

        if ($deletionCandidates->isEmpty()) {
            $this->warn('Cleanup cancelled: all orphaned folders contain upload files. Use --allow-upload-delete only with verified backup.');

            return self::FAILURE;
        }

        if ($deletionCandidates->count() > $maxDelete && ! $allowLargeDelete) {
            $this->error("Cleanup cancelled: {$deletionCandidates->count()} candidate folders exceed safety limit {$maxDelete}. Use --allow-large-delete after manual review.");

            return self::FAILURE;
        }

        // List orphaned folders
        $this->line('');
        $this->warn('Orphaned folders to delete (safe candidates):');
        foreach ($deletionCandidates->take(20) as $folder) {
            $this->line('  - '.$folder['path']);
        }

        if ($deletionCandidates->count() > 20) {
            $this->line('  ... and '.($deletionCandidates->count() - 20).' more');
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('[DRY RUN] No files were deleted.');
            $this->line('Candidates: '.$deletionCandidates->count());
            $this->line('Protected uploads skipped: '.$protectedUploadFolders->count());

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

        foreach ($deletionCandidates as $folderMeta) {
            $folder = $folderMeta['path'];
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
        if ($protectedUploadFolders->isNotEmpty()) {
            $this->warn('Skipped protected upload folders: '.$protectedUploadFolders->count());
        }

        if ($failed > 0) {
            $this->warn("⚠ Failed to delete {$failed} folders");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int,array{path:string,folder:string,size:int,has_upload_files:bool}>
     */
    private function scanOrphanedFolders($disk): array
    {
        $validFolderKeys = Investigator::pluck('folder_key')->filter()->toArray();
        $investigatorDirs = $disk->directories('investigators');

        $orphanedFolders = [];

        foreach ($investigatorDirs as $dir) {
            $folderName = basename($dir);

            if (in_array($folderName, $validFolderKeys, true)) {
                continue;
            }

            $folderSize = 0;
            $hasUploadFiles = false;
            foreach ($disk->allFiles($dir) as $file) {
                try {
                    $folderSize += $disk->size($file);
                } catch (\Throwable $e) {
                    // Ignore size errors
                }

                if (! $hasUploadFiles && str_contains('/'.ltrim($file, '/'), '/uploads/')) {
                    $hasUploadFiles = true;
                }
            }

            $orphanedFolders[] = [
                'path' => $dir,
                'folder' => $folderName,
                'size' => $folderSize,
                'has_upload_files' => $hasUploadFiles,
            ];
        }

        return $orphanedFolders;
    }
}
