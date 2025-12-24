<?php

namespace App\Console\Commands;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Repositories\DocumentTemplateRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class SyncDocumentTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:sync 
                            {--force : Force sync even if templates exist}
                            {--type= : Sync only specific document type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync document templates from legacy Blade views';

    /**
     * Execute the console command.
     */
    public function handle(DocumentTemplateRepository $repository)
    {
        $this->info('Starting document template sync...');

        $types = $this->option('type')
            ? [DocumentType::from($this->option('type'))]
            : DocumentType::cases();

        $synced = 0;
        $skipped = 0;

        foreach ($types as $type) {
            $this->line("Processing {$type->value}...");

            foreach ($type->supportedFormats() as $format) {
                // Check if template already exists
                if (!$this->option('force') && $repository->hasActiveTemplate($type, $format)) {
                    $this->warn("  - {$format->value}: Already exists (use --force to override)");
                    $skipped++;
                    continue;
                }

                try {
                    $this->syncTemplate($repository, $type, $format);
                    $this->info("  ✓ {$format->value}: Synced successfully");
                    $synced++;
                } catch (\Exception $e) {
                    $this->error("  ✗ {$format->value}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("Sync completed: {$synced} templates synced, {$skipped} skipped.");

        return Command::SUCCESS;
    }

    /**
     * Sync a single template
     */
    private function syncTemplate(
        DocumentTemplateRepository $repository,
        DocumentType $type,
        DocumentFormat $format
    ): void {
        $viewName = $type->legacyView();

        if (!$viewName || !View::exists($viewName)) {
            throw new \Exception("Legacy view not found: {$viewName}");
        }

        // Get view content
        $viewPath = View::getFinder()->find($viewName);
        $content = File::get($viewPath);

        // Store template file
        $disk = config('filesystems.default');
        $path = "templates/synced/{$type->value}/{$format->value}/template-v1.blade.php";
        
        Storage::disk($disk)->put($path, $content);

        // Create template record
        $repository->createTemplateVersion([
            'type' => $type->value,
            'format' => $format->value,
            'name' => $type->label() . ' (Legacy)',
            'storage_path' => $path,
            'checksum' => md5($content),
            'is_active' => true, // Activate by default
            'meta' => [
                'disk' => $disk,
                'synced_from' => $viewName,
                'synced_at' => now()->toIso8601String(),
                'source' => 'legacy_view',
            ],
            'created_by' => null,
            'updated_by' => null,
        ]);
    }
}

