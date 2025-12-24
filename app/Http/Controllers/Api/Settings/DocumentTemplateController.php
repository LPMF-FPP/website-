<?php

namespace App\Http\Controllers\Api\Settings;

use App\Enums\DocumentFormat;
use App\Enums\DocumentRenderEngine;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Repositories\DocumentTemplateRepository;
use App\Services\DocumentGeneration\DocumentRenderService;
use App\Services\DocumentTemplates\DocumentTemplateRenderService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplateRepository $repository,
        private readonly DocumentRenderService $renderService,
        private readonly DocumentTemplateRenderService $templateRenderService
    ) {
    }

    /**
     * Get all document templates grouped by type
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Gate::authorize('manage-settings');
            $this->ensureJson($request);

            // Get DB templates
            $dbTemplates = $this->repository->getAllTemplatesWithDefaults();

            // Group by process: Penerimaan, Pengujian, Penyerahan
            $groups = [
                'penerimaan' => $dbTemplates->filter(fn($t) => $t['type'] === 'ba_penerimaan')->values(),
                'pengujian' => $dbTemplates->filter(fn($t) => $t['type'] === 'lhu')->values(),
                'penyerahan' => $dbTemplates->filter(fn($t) => $t['type'] === 'ba_penyerahan')->values(),
            ];

            $documentTypes = collect(DocumentType::cases())->map(function ($type) {
                return [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'defaultFormat' => $type->defaultFormat()->value,
                    'supportedFormats' => array_map(fn($f) => $f->value, $type->supportedFormats()),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $dbTemplates->values()->all(),
                'groups' => $groups,
                'documentTypes' => $documentTypes,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        } catch (\Exception $e) {
            \Log::error('Failed to load document templates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load templates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get templates for a specific document type
     */
    public function byType(string $type, Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        $validator = Validator::make(['type' => $type], [
            'type' => ['required', new Enum(DocumentType::class)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $docType = DocumentType::from($type);
        $templates = $this->repository->getTemplatesByType($docType);

        return response()->json($templates);
    }

    public function show(DocumentTemplate $template, Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        return response()->json([
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'type' => $template->type->value,
            'format' => $template->format->value,
            'version' => $template->version,
            'is_active' => (bool) $template->is_active,
            'render_engine' => $template->render_engine?->value ?? DocumentRenderEngine::DOMPDF->value,
            'content_html' => $template->content_html,
            'content_css' => $template->content_css,
            'meta' => $template->meta,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'preview_urls' => [
                'html' => route('settings.document-templates.preview-html', ['template' => $template]),
                'pdf' => route('settings.document-templates.preview-pdf', ['template' => $template]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        $validator = Validator::make($request->all(), [
            'type' => ['required', new Enum(DocumentType::class)],
            'format' => ['required', new Enum(DocumentFormat::class)],
            'name' => ['required', 'string', 'max:255'],
            'content_html' => ['required', 'string'],
            'content_css' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $template = $this->repository->createTemplateVersion([
            'type' => $data['type'],
            'format' => $data['format'],
            'name' => $data['name'],
            'content_html' => $data['content_html'],
            'content_css' => $data['content_css'] ?? null,
            'render_engine' => DocumentRenderEngine::DOMPDF->value,
            'checksum' => md5($data['content_html']),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'meta' => array_merge($data['meta'] ?? [], [
                'editor' => 'code',
                'created_via' => 'document_templates',
            ]),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        Audit::log(
            action: 'TEMPLATE_CREATE',
            target: $template->code ?? "template_{$template->id}",
            before: null,
            after: [
                'template_id' => $template->id,
                'name' => $template->name,
                'doc_type' => $template->type->value,
                'version' => $template->version,
                'is_active' => $template->is_active,
            ],
            context: [
                'template_id' => $template->id,
                'doc_type' => $template->type->value,
                'format' => $template->format->value,
                'render_engine' => $template->render_engine?->value,
            ]
        );

        return response()->json([
            'message' => 'Template created',
            'template' => $template,
        ], 201);
    }

    public function update(DocumentTemplate $template, Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        $validator = Validator::make($request->all(), [
            'type' => ['required', new Enum(DocumentType::class)],
            'format' => ['required', new Enum(DocumentFormat::class)],
            'name' => ['required', 'string', 'max:255'],
            'content_html' => ['required', 'string'],
            'content_css' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $newTemplate = $this->repository->createTemplateVersion([
            'type' => $data['type'],
            'format' => $data['format'],
            'name' => $data['name'],
            'content_html' => $data['content_html'],
            'content_css' => $data['content_css'] ?? null,
            'render_engine' => DocumentRenderEngine::DOMPDF->value,
            'checksum' => md5($data['content_html']),
            'is_active' => (bool) ($data['is_active'] ?? $template->is_active),
            'meta' => array_merge($template->meta ?? [], $data['meta'] ?? [], [
                'editor' => 'code',
                'updated_via' => 'document_templates',
                'source_template_id' => $template->id,
            ]),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        Audit::log(
            action: 'TEMPLATE_UPDATE_DRAFT',
            target: $template->code ?? "template_{$template->id}",
            before: [
                'template_id' => $template->id,
                'name' => $template->name,
                'version' => $template->version,
                'checksum' => $template->checksum,
                'is_active' => $template->is_active,
            ],
            after: [
                'template_id' => $newTemplate->id,
                'name' => $newTemplate->name,
                'version' => $newTemplate->version,
                'checksum' => $newTemplate->checksum,
                'is_active' => $newTemplate->is_active,
            ],
            context: [
                'old_template_id' => $template->id,
                'new_template_id' => $newTemplate->id,
                'doc_type' => $template->type->value,
                'content_changed' => $template->checksum !== $newTemplate->checksum,
            ]
        );

        return response()->json([
            'message' => 'Template updated',
            'template' => $newTemplate,
        ]);
    }

    public function previewTemplateHtml(DocumentTemplate $template, Request $request): Response
    {
        Gate::authorize('manage-settings');

        try {
            Audit::log(
                action: 'TEMPLATE_PREVIEW',
                target: $template->code ?? "template_{$template->id}",
                before: null,
                after: null,
                context: [
                    'template_id' => $template->id,
                    'doc_type' => $template->type->value,
                    'format' => 'html',
                    'preview_type' => 'template_html',
                ]
            );

            $context = $this->renderService->getSampleContext($template->type);
            $document = $this->templateRenderService->renderHtml($template, $template->type, $context, ['preview' => true]);
            return $document->toInlineResponse();
        } catch (\Throwable $e) {
            report($e);
            return $this->previewErrorResponse($request, 'Failed to render HTML preview', $e);
        }
    }

    public function previewTemplatePdf(DocumentTemplate $template, Request $request): Response
    {
        Gate::authorize('manage-settings');

        try {
            Audit::log(
                action: 'TEMPLATE_PREVIEW',
                target: $template->code ?? "template_{$template->id}",
                before: null,
                after: null,
                context: [
                    'template_id' => $template->id,
                    'doc_type' => $template->type->value,
                    'format' => 'pdf',
                    'preview_type' => 'template_pdf',
                ]
            );

            $context = $this->renderService->getSampleContext($template->type);
            $document = $this->templateRenderService->renderPdf($template, $template->type, $context, ['preview' => true]);
            return $document->toInlineResponse();
        } catch (\Throwable $e) {
            report($e);
            return $this->previewErrorResponse($request, 'Failed to render PDF preview', $e);
        }
    }

    /**
     * Upload a new document template
     */
    public function upload(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        $validator = Validator::make($request->all(), [
            'type' => ['required', new Enum(DocumentType::class)],
            'format' => ['required', new Enum(DocumentFormat::class)],
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'], // 10MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $docType = DocumentType::from($request->type);
        $format = DocumentFormat::from($request->format);

        // Store file
        $disk = config('filesystems.default');
        $path = $request->file('file')->store("templates/{$docType->value}/{$format->value}", $disk);

        // Calculate checksum
        $content = Storage::disk($disk)->get($path);
        $checksum = md5($content);

        // Create template version
        $template = $this->repository->createTemplateVersion([
            'type' => $docType->value,
            'format' => $format->value,
            'name' => $request->name,
            'storage_path' => $path,
            'checksum' => $checksum,
            'is_active' => false,
            'meta' => [
                'disk' => $disk,
                'uploaded_at' => now()->toIso8601String(),
                'original_filename' => $request->file('file')->getClientOriginalName(),
            ],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        Audit::log(
            action: 'TEMPLATE_UPLOAD',
            target: $template->code ?? "template_{$template->id}",
            before: null,
            after: [
                'template_id' => $template->id,
                'name' => $template->name,
                'version' => $template->version,
                'storage_path' => $template->storage_path,
            ],
            context: [
                'template_id' => $template->id,
                'doc_type' => $docType->value,
                'format' => $format->value,
                'original_filename' => $template->meta['original_filename'] ?? null,
                'file_size' => $request->file('file')->getSize(),
            ]
        );

        return response()->json($template, 201);
    }

    /**
     * Activate a template
     */
    public function activate(int $templateId, Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        try {
            // Get template state before activation
            $oldTemplate = DocumentTemplate::find($templateId);
            $previousActiveTemplate = DocumentTemplate::where('type', $oldTemplate->type)
                ->where('is_active', true)
                ->where('id', '!=', $templateId)
                ->first();

            $template = $this->repository->activateTemplate($templateId);

            Audit::log(
                action: 'TEMPLATE_ISSUE_ACTIVATE',
                target: $template->code ?? "template_{$template->id}",
                before: [
                    'template_id' => $oldTemplate->id,
                    'is_active' => $oldTemplate->is_active,
                    'previous_active_template_id' => $previousActiveTemplate?->id,
                    'previous_active_template_name' => $previousActiveTemplate?->name,
                ],
                after: [
                    'template_id' => $template->id,
                    'is_active' => $template->is_active,
                    'version' => $template->version,
                    'name' => $template->name,
                ],
                context: [
                    'template_id' => $template->id,
                    'doc_type' => $template->type->value,
                    'format' => $template->format->value,
                    'deactivated_template_id' => $previousActiveTemplate?->id,
                ]
            );

            return response()->json([
                'message' => 'Template activated successfully',
                'template' => $template,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to activate template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate a template
     */
    public function deactivate(int $templateId, Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        try {
            $oldTemplate = DocumentTemplate::find($templateId);
            $template = $this->repository->deactivateTemplate($templateId);

            Audit::log(
                action: 'TEMPLATE_DEACTIVATE',
                target: $template->code ?? "template_{$template->id}",
                before: [
                    'template_id' => $oldTemplate->id,
                    'is_active' => $oldTemplate->is_active,
                ],
                after: [
                    'template_id' => $template->id,
                    'is_active' => $template->is_active,
                ],
                context: [
                    'template_id' => $template->id,
                    'doc_type' => $template->type->value,
                ]
            );

            return response()->json([
                'message' => 'Template deactivated successfully',
                'template' => $template,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview a template with sample data
     */
    public function preview(string $type, string $format): JsonResponse | \Illuminate\Http\Response
    {
        Gate::authorize('manage-settings');

        // Whitelist validation for format
        $allowedFormats = ['pdf', 'html', 'docx'];
        if (!in_array(strtolower($format), $allowedFormats)) {
            return response()->json([
                'message' => 'Invalid format',
                'error' => "Format must be one of: " . implode(', ', $allowedFormats),
            ], 404);
        }

        $validator = Validator::make(compact('type', 'format'), [
            'type' => ['required', new Enum(DocumentType::class)],
            'format' => ['required', new Enum(DocumentFormat::class)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $docType = DocumentType::from($type);
            $docFormat = DocumentFormat::from($format);

            // Check if format is supported by document type
            if (!in_array($docFormat, $docType->supportedFormats())) {
                return response()->json([
                    'message' => 'Format not supported',
                    'error' => "Document type '{$type}' does not support '{$format}' format",
                ], 422);
            }

            Audit::log(
                action: 'TEMPLATE_PREVIEW',
                target: "preview_{$type}_{$format}",
                before: null,
                after: null,
                context: [
                    'doc_type' => $type,
                    'format' => $format,
                    'preview_type' => 'general_preview',
                ]
            );

            $rendered = $this->renderService->renderPreview($docType, $docFormat, [
                'audit' => false, // Don't audit previews
            ]);

            return $rendered->toInlineResponse();
        } catch (\InvalidArgumentException $e) {
            // Validation or configuration errors
            return response()->json([
                'message' => 'Invalid request',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            // Template file missing from storage
            return response()->json([
                'message' => 'Template file not found',
                'error' => 'The template file is missing from storage',
            ], 404);
        } catch (\Throwable $e) {
            // Log unexpected errors
            report($e);
            
            // Return user-friendly message
            return response()->json([
                'message' => 'Failed to generate preview',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Update template content (HTML only)
     */
    public function updateContent(int $templateId, Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $template = $this->repository->updateTemplateContent(
                $templateId,
                $request->content,
                [
                    'updated_via' => 'inline_editor',
                    'updated_at' => now()->toIso8601String(),
                ]
            );

            Audit::log('UPDATE_TEMPLATE_CONTENT', $template->code, null, [
                'template_id' => $template->id,
                'new_version' => $template->version,
            ]);

            return response()->json([
                'message' => 'Template content updated successfully',
                'template' => $template,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update template content',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a template
     */
    public function destroy(int $templateId, Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $this->ensureJson($request);

        try {
            // Get template before deletion
            $template = DocumentTemplate::find($templateId);
            if (!$template) {
                return response()->json([
                    'message' => 'Template not found',
                ], 404);
            }

            Audit::log(
                action: 'TEMPLATE_DELETE',
                target: $template->code ?? "template_{$templateId}",
                before: [
                    'template_id' => $template->id,
                    'name' => $template->name,
                    'doc_type' => $template->type->value,
                    'version' => $template->version,
                    'is_active' => $template->is_active,
                ],
                after: null,
                context: [
                    'template_id' => $templateId,
                    'doc_type' => $template->type->value,
                    'was_active' => $template->is_active,
                ]
            );

            $this->repository->deleteTemplate($templateId);

            return response()->json([
                'message' => 'Template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function previewErrorResponse(Request $request, string $message, \Throwable $exception): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => config('app.debug') ? $exception->getMessage() : 'Preview failed',
            ], 422);
        }

        return response("<h1>Preview Error</h1><p>{$message}</p>", 422, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    private function ensureJson(Request $request): void
    {
        if (!str_contains((string) $request->header('Accept'), 'application/json')) {
            throw new HttpResponseException(response()->json([
                'message' => 'Unsupported Accept header. Please include application/json.',
            ], 406));
        }
    }
}
