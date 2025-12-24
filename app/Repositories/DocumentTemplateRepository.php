<?php

namespace App\Repositories;

use App\Enums\DocumentFormat;
use App\Enums\DocumentRenderEngine;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentTemplateRepository
{
    /**
     * Get the active template for a specific type and format
     */
    public function getActiveTemplate(DocumentType $type, DocumentFormat $format): ?DocumentTemplate
    {
        return DocumentTemplate::active()
            ->ofType($type)
            ->ofFormat($format)
            ->first();
    }

    /**
     * Get all templates for a specific type
     */
    public function getTemplatesByType(DocumentType $type): Collection
    {
        return DocumentTemplate::ofType($type)
            ->orderBy('format')
            ->orderByDesc('version')
            ->get();
    }

    /**
     * Get all active templates
     */
    public function getAllActiveTemplates(): Collection
    {
        return DocumentTemplate::active()
            ->orderBy('type')
            ->orderBy('format')
            ->get();
    }

    /**
     * Get all templates with defaults (virtual templates for legacy views)
     */
    public function getAllTemplatesWithDefaults(): \Illuminate\Support\Collection
    {
        $dbTemplates = DocumentTemplate::all();
        $result = collect();

        // Define main document types for Penerimaan/Pengujian/Penyerahan
        $mainTypes = [
            DocumentType::BA_PENERIMAAN,
            DocumentType::LHU,
            DocumentType::BA_PENYERAHAN,
        ];

        foreach ($mainTypes as $docType) {
            foreach ($docType->supportedFormats() as $format) {
                // Check if DB has templates for this type+format
                $typeFormatTemplates = $dbTemplates->filter(fn($t) => 
                    $t->type === $docType && $t->format === $format
                );

                if ($typeFormatTemplates->isNotEmpty()) {
                    // Add DB templates
                    foreach ($typeFormatTemplates as $tpl) {
                        $result->push([
                            'id' => $tpl->id,
                            'type' => $tpl->type->value,
                            'format' => $tpl->format->value,
                            'name' => $tpl->name,
                            'is_active' => $tpl->is_active,
                            'is_default' => false,
                            'version' => $tpl->version,
                            'created_at' => $tpl->created_at?->toIso8601String(),
                            'render_engine' => $tpl->render_engine?->value ?? DocumentRenderEngine::DOMPDF->value,
                            'preview_url' => url("/api/settings/document-templates/preview/{$tpl->type->value}/{$tpl->format->value}"),
                        ]);
                    }
                } else {
                    // Add virtual default template
                    $result->push([
                        'id' => null,
                        'type' => $docType->value,
                        'format' => $format->value,
                        'name' => $docType->label() . ' (Default)',
                        'is_active' => true, // Default is always active if no DB template
                        'is_default' => true,
                        'version' => 1,
                        'created_at' => null,
                        'render_engine' => DocumentRenderEngine::DOMPDF->value,
                        'preview_url' => url("/api/settings/document-templates/preview/{$docType->value}/{$format->value}"),
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * Create a new template version
     *
     * @param array $data Template data including type, format, name, storage_path, content/checksum
     * @return DocumentTemplate
     * @throws \Exception
     */
    public function createTemplateVersion(array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($data) {
            $type = DocumentType::from($data['type']);
            $format = DocumentFormat::from($data['format']);
            $renderEngine = DocumentRenderEngine::tryFrom($data['render_engine'] ?? null)
                ?? DocumentRenderEngine::tryFrom(config('document-templates.default_render_engine'))
                ?? DocumentRenderEngine::DOMPDF;

            // Get next version number
            $latestVersion = DocumentTemplate::ofType($type)
                ->ofFormat($format)
                ->max('version') ?? 0;

            $nextVersion = $latestVersion + 1;

            // Generate code if not provided
            $code = $data['code'] ?? strtoupper("{$type->value}_{$format->value}_V{$nextVersion}");

            // Create template record
            $template = DocumentTemplate::create([
                'code' => $code,
                'type' => $type,
                'format' => $format,
                'name' => $data['name'],
                'storage_path' => $data['storage_path'] ?? null,
                'content_html' => $data['content_html'] ?? null,
                'content_css' => $data['content_css'] ?? null,
                'render_engine' => $renderEngine,
                'is_active' => $data['is_active'] ?? false,
                'version' => $nextVersion,
                'checksum' => $data['checksum'] ?? null,
                'meta' => $data['meta'] ?? [],
                'created_by' => $data['created_by'] ?? auth()->id(),
                'updated_by' => $data['updated_by'] ?? auth()->id(),
            ]);

            // If marked as active, deactivate others
            if ($template->is_active) {
                $this->activateTemplate($template->id);
            }

            return $template->fresh();
        });
    }

    /**
     * Activate a template (and deactivate others of same type+format)
     *
     * @param int $templateId
     * @return DocumentTemplate
     * @throws \Exception
     */
    public function activateTemplate(int $templateId): DocumentTemplate
    {
        return DB::transaction(function () use ($templateId) {
            $template = DocumentTemplate::findOrFail($templateId);

            // Deactivate all other templates of same type and format
            DocumentTemplate::ofType($template->type)
                ->ofFormat($template->format)
                ->where('id', '!=', $templateId)
                ->update(['is_active' => false]);

            // Activate this template
            $template->update(['is_active' => true]);

            return $template->fresh();
        });
    }

    /**
     * Deactivate a template
     */
    public function deactivateTemplate(int $templateId): DocumentTemplate
    {
        $template = DocumentTemplate::findOrFail($templateId);
        $template->update(['is_active' => false]);
        
        return $template->fresh();
    }

    /**
     * Delete a template (and its file if exists)
     *
     * @param int $templateId
     * @return bool
     * @throws \Exception
     */
    public function deleteTemplate(int $templateId): bool
    {
        return DB::transaction(function () use ($templateId) {
            $template = DocumentTemplate::findOrFail($templateId);

            // Cannot delete active template
            if ($template->is_active) {
                throw new \Exception('Cannot delete an active template. Please activate another template first.');
            }

            // Delete file from storage
            $disk = data_get($template->meta, 'disk', config('filesystems.default'));
            if ($template->storage_path && Storage::disk($disk)->exists($template->storage_path)) {
                Storage::disk($disk)->delete($template->storage_path);
            }

            return $template->delete();
        });
    }

    /**
     * Update template content (create new version)
     *
     * @param int $templateId
     * @param string $content New content
     * @param array $meta Additional metadata
     * @return DocumentTemplate New version
     */
    public function updateTemplateContent(int $templateId, string $content, array $meta = []): DocumentTemplate
    {
        $oldTemplate = DocumentTemplate::findOrFail($templateId);

        // File-based template
        if ($oldTemplate->storage_path) {
            $disk = data_get($oldTemplate->meta, 'disk', config('filesystems.default'));
            $basePath = dirname($oldTemplate->storage_path);
            $extension = pathinfo($oldTemplate->storage_path, PATHINFO_EXTENSION);
            $timestamp = now()->format('YmdHis');
            $newPath = "{$basePath}/{$timestamp}-v" . ($oldTemplate->version + 1) . ".{$extension}";

            Storage::disk($disk)->put($newPath, $content);

            return $this->createTemplateVersion([
                'type' => $oldTemplate->type->value,
                'format' => $oldTemplate->format->value,
                'name' => $oldTemplate->name,
                'storage_path' => $newPath,
                'is_active' => $oldTemplate->is_active,
                'checksum' => md5($content),
                'meta' => array_merge($oldTemplate->meta ?? [], $meta, ['disk' => $disk]),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'render_engine' => $oldTemplate->render_engine?->value ?? DocumentRenderEngine::DOMPDF->value,
            ]);
        }

        // Editor-based template
        return $this->createTemplateVersion([
            'type' => $oldTemplate->type->value,
            'format' => $oldTemplate->format->value,
            'name' => $oldTemplate->name,
            'content_html' => $content,
            'content_css' => data_get($meta, 'content_css', $oldTemplate->content_css),
            'is_active' => $oldTemplate->is_active,
            'checksum' => md5($content),
            'meta' => array_merge($oldTemplate->meta ?? [], $meta),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'render_engine' => $oldTemplate->render_engine?->value ?? DocumentRenderEngine::DOMPDF->value,
        ]);
    }

    /**
     * Get template content
     */
    public function getTemplateContent(DocumentTemplate $template): string
    {
        if (!empty($template->content_html)) {
            return $template->content_html;
        }

        $disk = data_get($template->meta, 'disk', config('filesystems.default'));
        $path = $template->storage_path;

        if (!$path || !Storage::disk($disk)->exists($path)) {
            throw new FileNotFoundException("Template file not found: {$path}");
        }

        return Storage::disk($disk)->get($path);
    }

    /**
     * Check if a template exists for type and format
     */
    public function hasActiveTemplate(DocumentType $type, DocumentFormat $format): bool
    {
        return DocumentTemplate::active()
            ->ofType($type)
            ->ofFormat($format)
            ->exists();
    }
}
