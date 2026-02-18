<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhTemplate;
use App\Models\QmhTemplateFallbackRequest;
use App\Support\QmhFrLayoutProfile;
use App\Support\QmhHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QmhTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->all(), [
            'clause' => ['nullable', 'integer', Rule::in([4, 5, 6, 7, 8])],
            'doc_type' => ['required', Rule::in(['sop', 'ik', 'fr', 'formulir'])],
            'layout_profile' => ['nullable', 'string', Rule::in(QmhFrLayoutProfile::allowedProfiles())],
            'shell_mode' => ['nullable', 'string', Rule::in(QmhFrLayoutProfile::allowedShellModes())],
            'orientation_policy' => ['nullable', 'string', Rule::in(QmhFrLayoutProfile::allowedOrientationPolicies())],
            'show_signoff_footer' => ['nullable', 'boolean'],
            'document_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
        ])->validate();

        $docType = $validated['doc_type'] === 'formulir' ? 'fr' : $validated['doc_type'];
        $clause = isset($validated['clause']) ? (int) $validated['clause'] : null;
        $requestedLayoutProfile = isset($validated['layout_profile'])
            ? QmhFrLayoutProfile::normalizeProfile((string) $validated['layout_profile'])
            : null;
        $requestedShellMode = isset($validated['shell_mode'])
            ? QmhFrLayoutProfile::normalizeShellMode((string) $validated['shell_mode'])
            : null;
        $requestedOrientationPolicy = isset($validated['orientation_policy'])
            ? QmhFrLayoutProfile::normalizeOrientationPolicy((string) $validated['orientation_policy'])
            : null;
        $requestedSignoffFooter = array_key_exists('show_signoff_footer', $validated)
            ? QmhFrLayoutProfile::normalizeShowSignoffFooter($validated['show_signoff_footer'])
            : null;

        $templates = QmhTemplate::query()
            ->where('doc_type', $docType)
            ->where('is_active', true)
            ->when($clause !== null, fn ($query) => $query->where('clause', $clause))
            ->orderByDesc('version')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'clause',
                'doc_type',
                'version',
                'is_active',
                'metadata',
                'updated_at',
            ]);

        if ($docType === 'fr' && ($requestedLayoutProfile !== null || $requestedShellMode !== null || $requestedOrientationPolicy !== null || $requestedSignoffFooter !== null)) {
            $templates = $templates->filter(function (QmhTemplate $template) use ($requestedLayoutProfile, $requestedShellMode, $requestedOrientationPolicy, $requestedSignoffFooter): bool {
                $metadata = is_array($template->metadata) ? $template->metadata : [];
                $layoutConfig = QmhFrLayoutProfile::fromMetadata($metadata);
                $templateLayoutProfile = isset($layoutConfig['layout_profile'])
                    ? (string) $layoutConfig['layout_profile']
                    : QmhFrLayoutProfile::defaultAuthoringProfile();

                if ($requestedLayoutProfile !== null && $templateLayoutProfile !== $requestedLayoutProfile) {
                    return false;
                }

                if ($requestedShellMode !== null && (string) ($layoutConfig['shell_mode'] ?? '') !== $requestedShellMode) {
                    return false;
                }

                if ($requestedOrientationPolicy !== null && (string) ($layoutConfig['orientation_policy'] ?? '') !== $requestedOrientationPolicy) {
                    return false;
                }

                if ($requestedSignoffFooter !== null && (bool) ($layoutConfig['show_signoff_footer'] ?? true) !== $requestedSignoffFooter) {
                    return false;
                }

                return true;
            })->values();
        }

        $resolvedFrom = $templates->isNotEmpty() ? 'exact' : 'none';

        if (
            $resolvedFrom === 'none'
            && $clause !== null
            && $clause !== 4
            && isset($validated['document_id'])
        ) {
            $approvedFallback = QmhTemplateFallbackRequest::query()
                ->where('document_id', (int) $validated['document_id'])
                ->where('requested_clause', $clause)
                ->where('requested_doc_type', $docType)
                ->where('status', 'approved')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->latest('id')
                ->first();

            if ($approvedFallback !== null) {
                $fallbackTemplate = QmhTemplate::query()
                    ->whereKey((int) $approvedFallback->fallback_template_id)
                    ->where('is_active', true)
                    ->where('doc_type', $docType)
                    ->where('clause', 4)
                    ->first();

                if ($fallbackTemplate !== null) {
                    if ($docType === 'fr' && ($requestedLayoutProfile !== null || $requestedShellMode !== null || $requestedOrientationPolicy !== null || $requestedSignoffFooter !== null)) {
                        $fallbackMetadata = is_array($fallbackTemplate->metadata) ? $fallbackTemplate->metadata : [];
                        $fallbackLayout = QmhFrLayoutProfile::fromMetadata($fallbackMetadata);
                        $fallbackProfile = isset($fallbackLayout['layout_profile'])
                            ? (string) $fallbackLayout['layout_profile']
                            : QmhFrLayoutProfile::defaultAuthoringProfile();

                        $profileMatches = $requestedLayoutProfile === null || $fallbackProfile === $requestedLayoutProfile;
                        $shellMatches = $requestedShellMode === null || (string) ($fallbackLayout['shell_mode'] ?? '') === $requestedShellMode;
                        $orientationMatches = $requestedOrientationPolicy === null || (string) ($fallbackLayout['orientation_policy'] ?? '') === $requestedOrientationPolicy;
                        $signoffMatches = $requestedSignoffFooter === null || (bool) ($fallbackLayout['show_signoff_footer'] ?? true) === $requestedSignoffFooter;

                        if ($profileMatches && $shellMatches && $orientationMatches && $signoffMatches) {
                            $templates = collect([$fallbackTemplate]);
                            $resolvedFrom = 'fallback';
                        }
                    } else {
                        $templates = collect([$fallbackTemplate]);
                        $resolvedFrom = 'fallback';
                    }
                }
            }
        }

        $templates = $templates->map(function (QmhTemplate $template): array {
            $metadata = is_array($template->metadata) ? $template->metadata : [];
            $isFr = ((string) $template->doc_type) === 'fr';

            $formSchema = $metadata['form_schema'] ?? $this->defaultFormSchema((string) $template->doc_type);
            $layoutConfig = $isFr
                ? QmhFrLayoutProfile::fromMetadata($metadata)
                : [
                    'layout_profile' => null,
                    'shell_mode' => null,
                    'orientation_policy' => null,
                    'show_signoff_footer' => null,
                    'logo_source' => null,
                    'logo_path' => null,
                    'declaration_header' => null,
                    'risk_matrix_columns' => null,
                ];

            $runtimeProfile = $isFr
                ? QmhFrLayoutProfile::runtimeProfileFromMetadata($metadata)
                : null;

            $compatLayoutProfile = $isFr
                ? (isset($layoutConfig['layout_profile'])
                    ? (string) $layoutConfig['layout_profile']
                    : QmhFrLayoutProfile::defaultAuthoringProfile())
                : null;

            return [
                'id' => $template->id,
                'name' => $template->name,
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
                'is_active' => $template->is_active,
                'updated_at' => $template->updated_at,
                'preview_url' => route('quality.templates.preview', $template),
                'content_html' => QmhHtmlSanitizer::sanitize(is_string($metadata['content_html'] ?? null) ? $metadata['content_html'] : ''),
                'form_schema' => $formSchema,
                'layout_profile' => $compatLayoutProfile,
                'layout_profile_runtime' => $runtimeProfile,
                'is_legacy_layout' => $runtimeProfile === 'legacy',
                'shell_mode' => $layoutConfig['shell_mode'] ?? null,
                'orientation_policy' => $layoutConfig['orientation_policy'] ?? null,
                'show_signoff_footer' => $layoutConfig['show_signoff_footer'] ?? null,
                'logo_source' => $layoutConfig['logo_source'] ?? null,
                'logo_path' => $layoutConfig['logo_path'] ?? null,
                'declaration_header' => $layoutConfig['declaration_header'] ?? null,
                'risk_matrix_columns' => $layoutConfig['risk_matrix_columns'] ?? null,
            ];
        })->values();

        $templates = $templates->map(function (array $template) use ($resolvedFrom): array {
            $template['resolved_from'] = $resolvedFrom;

            return $template;
        })->values();

        return response()->json([
            'data' => $templates,
            'resolved_from' => $resolvedFrom,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormSchema(string $docType): array
    {
        if ($docType === 'ik') {
            return [
                'version' => 1,
                'doc_type' => 'ik',
                'questions' => [
                    ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                    ['id' => 'scope', 'label' => 'Ruang Lingkup', 'type' => 'textarea', 'required' => true],
                    ['id' => 'responsibilities', 'label' => 'Tanggung Jawab', 'type' => 'textarea', 'required' => false],
                    ['id' => 'reference', 'label' => 'Acuan', 'type' => 'textarea', 'required' => false],
                    ['id' => 'instructions', 'label' => 'Instruksi Kerja', 'type' => 'textarea', 'required' => true],
                    ['id' => 'required_docs', 'label' => 'Dokumentasi Yang Diperlukan', 'type' => 'list', 'required' => false],
                    ['id' => 'closing', 'label' => 'Penutup', 'type' => 'textarea', 'required' => false],
                ],
            ];
        }

        if ($docType !== 'sop') {
            return [
                'version' => 1,
                'doc_type' => $docType,
                'questions' => [],
            ];
        }

        return [
            'version' => 1,
            'doc_type' => 'sop',
            'questions' => [
                ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                ['id' => 'scope', 'label' => 'Ruang Lingkup', 'type' => 'textarea', 'required' => true],
                ['id' => 'definitions', 'label' => 'Definisi', 'type' => 'list', 'required' => false],
                ['id' => 'references', 'label' => 'Referensi', 'type' => 'list', 'required' => false],
                ['id' => 'procedure', 'label' => 'Prosedur', 'type' => 'textarea', 'required' => true],
                ['id' => 'records', 'label' => 'Rekaman / Form Terkait', 'type' => 'list', 'required' => false],
                ['id' => 'responsibilities', 'label' => 'Tanggung Jawab', 'type' => 'textarea', 'required' => false],
                ['id' => 'attachments', 'label' => 'Lampiran', 'type' => 'list', 'required' => false],
            ],
        ];
    }
}
