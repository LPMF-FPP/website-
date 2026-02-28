<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQmhTemplateRequest;
use App\Http\Requests\Quality\UpdateQmhTemplateRequest;
use App\Models\QmhTemplate;
use App\Support\Audit;
use App\Support\QmhFrLayoutProfile;
use App\Support\QmhHtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QmhTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $filters = validator($request->only(['doc_type', 'clause', 'layout_profile', 'status', 'search']), [
            'doc_type' => ['nullable', 'in:sop,ik,fr'],
            'clause' => ['nullable', 'integer', 'in:4,5,6,7,8'],
            'layout_profile' => ['nullable', 'in:structured_form,risk_matrix,declaration'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'search' => ['nullable', 'string'],
        ])->validate();

        $templates = QmhTemplate::query()
            ->when(isset($filters['doc_type']), fn ($query) => $query->where('doc_type', $filters['doc_type']))
            ->when(isset($filters['clause']), fn ($query) => $query->where('clause', (int) $filters['clause']))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $query->where('name', 'like', '%'.$filters['search'].'%');
            })
            ->orderBy('doc_type')
            ->orderBy('clause')
            ->orderByDesc('version')
            ->paginate(20)
            ->appends($request->query());

        if (($filters['doc_type'] ?? null) === 'fr' && isset($filters['layout_profile'])) {
            $requestedProfile = QmhFrLayoutProfile::normalizeProfile((string) $filters['layout_profile']);

            $templates->setCollection(
                $templates->getCollection()->filter(function (QmhTemplate $template) use ($requestedProfile): bool {
                    $metadata = is_array($template->metadata) ? $template->metadata : [];
                    $profile = $this->resolveTemplateGroupProfile((string) $template->doc_type, $metadata);

                    return $profile === $requestedProfile;
                })->values()
            );
        }

        return view('quality.templates.index', [
            'templates' => $templates,
        ]);
    }

    public function store(StoreQmhTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request): void {
            $metadata = $this->buildTemplateMetadata(
                $validated,
                null,
                (int) ($request->user()?->id ?? 0),
                true
            );

            $groupProfile = $this->resolveTemplateGroupProfile((string) $validated['doc_type'], $metadata);
            $groupIdentity = $this->resolveTemplateGroupIdentity((string) $validated['doc_type'], $metadata);
            $finalPolicy = QmhFrLayoutProfile::fromMetadata($metadata);
            $nextVersion = $this->nextVersionForGroup(
                (string) $validated['doc_type'],
                (int) $validated['clause'],
                $groupIdentity
            );

            $this->deactivateActiveTemplatesInGroup(
                (string) $validated['doc_type'],
                (int) $validated['clause'],
                $groupIdentity
            );

            $template = QmhTemplate::query()->create([
                'name' => $validated['name'],
                'clause' => (int) $validated['clause'],
                'doc_type' => $validated['doc_type'],
                'shell_mode' => $validated['doc_type'] === 'fr' ? $finalPolicy['shell_mode'] : null,
                'orientation_policy' => $validated['doc_type'] === 'fr' ? $finalPolicy['orientation_policy'] : null,
                'show_signoff_footer' => $validated['doc_type'] === 'fr' ? (bool) $finalPolicy['show_signoff_footer'] : null,
                'version' => $nextVersion,
                'storage_disk' => 'local',
                'is_active' => true,
                'archived_at' => null,
                'metadata' => $metadata,
            ]);

            Audit::log('QMH_TEMPLATE_PUBLISH', (string) $template->id, null, [
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
                'is_active' => $template->is_active,
                'group_profile' => $groupProfile,
            ]);
        });

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template QMH berhasil dibuat.');
    }

    public function edit(Request $request, QmhTemplate $template): View
    {
        $this->authorizeTemplateManage($request);

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $contentHtml = isset($metadata['content_html']) && is_string($metadata['content_html'])
            ? $metadata['content_html']
            : '<p></p>';

        return view('quality.templates.edit', [
            'template' => $template,
            'resolvedContentHtml' => $this->normalizeTemplateContentHtml($contentHtml),
        ]);
    }

    public function update(UpdateQmhTemplateRequest $request, QmhTemplate $template): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $template, $validated): void {
            $beforeMetadata = is_array($template->metadata) ? $template->metadata : [];

            $nextMetadata = $this->buildTemplateMetadata(
                $validated,
                $template,
                (int) ($request->user()?->id ?? 0),
                false
            );

            $groupProfile = $this->resolveTemplateGroupProfile((string) $validated['doc_type'], $nextMetadata);
            $groupIdentity = $this->resolveTemplateGroupIdentity((string) $validated['doc_type'], $nextMetadata);
            $finalPolicy = QmhFrLayoutProfile::fromMetadata($nextMetadata);
            $nextVersion = $this->nextVersionForGroup(
                (string) $validated['doc_type'],
                (int) $validated['clause'],
                $groupIdentity
            );

            $this->deactivateActiveTemplatesInGroup(
                (string) $validated['doc_type'],
                (int) $validated['clause'],
                $groupIdentity,
                $template->id
            );

            if ($template->is_active) {
                $template->forceFill([
                    'is_active' => false,
                    'archived_at' => now(),
                ])->save();
            }

            $published = QmhTemplate::query()->create([
                'name' => $validated['name'],
                'clause' => (int) $validated['clause'],
                'doc_type' => $validated['doc_type'],
                'shell_mode' => $validated['doc_type'] === 'fr' ? $finalPolicy['shell_mode'] : null,
                'orientation_policy' => $validated['doc_type'] === 'fr' ? $finalPolicy['orientation_policy'] : null,
                'show_signoff_footer' => $validated['doc_type'] === 'fr' ? (bool) $finalPolicy['show_signoff_footer'] : null,
                'version' => $nextVersion,
                'storage_disk' => (string) ($template->storage_disk ?: 'local'),
                'is_active' => true,
                'archived_at' => null,
                'metadata' => $nextMetadata,
            ]);

            $schemaDiff = $this->buildSchemaDiff(
                is_array($beforeMetadata['form_schema'] ?? null) ? $beforeMetadata['form_schema'] : null,
                is_array($nextMetadata['form_schema'] ?? null) ? $nextMetadata['form_schema'] : null
            );

            Audit::log('QMH_TEMPLATE_PUBLISH', (string) $published->id, [
                'source_template_id' => $template->id,
                'name' => $template->name,
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
                'metadata' => $beforeMetadata,
            ], [
                'name' => $published->name,
                'clause' => $published->clause,
                'doc_type' => $published->doc_type,
                'version' => $published->version,
                'is_active' => $published->is_active,
                'group_profile' => $groupProfile,
                'metadata' => $nextMetadata,
                'schema_diff' => $schemaDiff,
            ]);
        });

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Perubahan diterbitkan sebagai versi baru Template QMH.');
    }

    public function activate(QmhTemplate $template): RedirectResponse
    {
        DB::transaction(function () use ($template): void {
            $metadata = is_array($template->metadata) ? $template->metadata : [];
            $groupProfile = $this->resolveTemplateGroupProfile((string) $template->doc_type, $metadata);
            $groupIdentity = $this->resolveTemplateGroupIdentity((string) $template->doc_type, $metadata);
            $finalPolicy = QmhFrLayoutProfile::fromMetadata($metadata);

            $this->deactivateActiveTemplatesInGroup(
                (string) $template->doc_type,
                (int) $template->clause,
                $groupIdentity,
                $template->id
            );

            $before = $template->is_active;

            $template->forceFill([
                'is_active' => true,
                'archived_at' => null,
                'shell_mode' => (string) $finalPolicy['shell_mode'],
                'orientation_policy' => (string) $finalPolicy['orientation_policy'],
                'show_signoff_footer' => (bool) $finalPolicy['show_signoff_footer'],
            ])->save();

            Audit::log('QMH_TEMPLATE_ACTIVATE', (string) $template->id, [
                'is_active' => $before,
            ], [
                'is_active' => true,
                'archived_at' => null,
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
                'group_profile' => $groupProfile,
                'group_identity' => $groupIdentity,
            ]);
        });

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template QMH versi ini berhasil diaktifkan.');
    }

    public function deactivate(QmhTemplate $template): RedirectResponse
    {
        $before = $template->is_active;

        $template->forceFill([
            'is_active' => false,
            'archived_at' => now(),
        ])->save();

        Audit::log('QMH_TEMPLATE_DEACTIVATE', (string) $template->id, [
            'is_active' => $before,
        ], [
            'is_active' => false,
            'archived_at' => $template->archived_at,
            'clause' => $template->clause,
            'doc_type' => $template->doc_type,
            'version' => $template->version,
        ]);

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template QMH versi ini berhasil dinonaktifkan.');
    }

    public function preview(Request $request, QmhTemplate $template): View
    {
        $this->authorizePreviewAccess($request);

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $contentHtml = isset($metadata['content_html']) && is_string($metadata['content_html'])
            ? $metadata['content_html']
            : '<p></p>';
        $contentHtml = $this->normalizeTemplateContentHtml($contentHtml);

        return view('quality.templates.preview', [
            'template' => $template,
            'hasDocx' => false,
            'previewFileUrl' => null,
            'officeViewerUrl' => null,
            'contentHtml' => $contentHtml,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildTemplateMetadata(array $validated, ?QmhTemplate $baseTemplate, int $actorId, bool $isStore): array
    {
        $baseMetadata = is_array($baseTemplate?->metadata) ? $baseTemplate->metadata : [];

        $submittedContentHtml = isset($validated['content_html']) && is_string($validated['content_html'])
            ? trim($validated['content_html'])
            : '';
        $contentHtml = $submittedContentHtml !== '' ? $submittedContentHtml : '<p></p>';
        $contentHtml = $this->normalizeTemplateContentHtml($contentHtml);

        $metadata = $baseMetadata;
        $metadata['version_notes'] = $validated['version_notes'] ?? null;
        $metadata[$isStore ? 'uploaded_by' : 'updated_by'] = $actorId;
        $metadata['content_html'] = $contentHtml;

        if (array_key_exists('form_schema', $validated)) {
            $metadata['form_schema'] = $validated['form_schema'];
        }

        if (($validated['doc_type'] ?? null) === 'fr') {
            $layoutConfig = QmhFrLayoutProfile::fromValidatedTemplateInput([
                'layout_profile' => $validated['layout_profile'] ?? data_get($baseMetadata, 'layout_profile'),
                'logo_source' => $validated['logo_source'] ?? data_get($baseMetadata, 'logo_source'),
                'logo_path' => array_key_exists('logo_path', $validated) ? $validated['logo_path'] : data_get($baseMetadata, 'logo_path'),
                'declaration_header' => array_key_exists('declaration_header', $validated) ? $validated['declaration_header'] : data_get($baseMetadata, 'declaration_header'),
                'risk_matrix_columns' => $validated['risk_matrix_columns'] ?? data_get($baseMetadata, 'risk_matrix_columns'),
            ]);

            $metadata = array_merge($metadata, $layoutConfig);

            $activeLayoutProfile = (string) ($layoutConfig['layout_profile'] ?? QmhFrLayoutProfile::defaultAuthoringProfile());
            if ($activeLayoutProfile !== 'declaration') {
                unset($metadata['declaration_header']);
            }

            if ($activeLayoutProfile !== 'risk_matrix') {
                unset($metadata['risk_matrix_columns']);
            }

            return $metadata;
        }

        unset(
            $metadata['layout_profile'],
            $metadata['logo_source'],
            $metadata['logo_path'],
            $metadata['declaration_header'],
            $metadata['risk_matrix_columns']
        );

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveTemplateGroupProfile(string $docType, array $metadata): ?string
    {
        if ($docType !== 'fr') {
            return null;
        }

        $layout = QmhFrLayoutProfile::fromExplicitMetadata($metadata);

        return isset($layout['layout_profile'])
            ? (string) $layout['layout_profile']
            : QmhFrLayoutProfile::defaultAuthoringProfile();
    }

    private function nextVersionForGroup(string $docType, int $clause, ?string $layoutProfile): int
    {
        $templates = $this->templatesInGroup($docType, $clause, $layoutProfile);
        $max = (int) $templates->max('version');

        return $max + 1;
    }

    private function deactivateActiveTemplatesInGroup(string $docType, int $clause, ?string $layoutProfile, ?int $exceptId = null): void
    {
        $templates = $this->templatesInGroup($docType, $clause, $layoutProfile)
            ->filter(fn (QmhTemplate $template): bool => $template->is_active)
            ->when($exceptId !== null, fn (Collection $items): Collection => $items->filter(fn (QmhTemplate $template): bool => $template->id !== $exceptId));

        foreach ($templates as $template) {
            $template->forceFill([
                'is_active' => false,
                'archived_at' => now(),
            ])->save();
        }
    }

    /**
     * @return Collection<int, QmhTemplate>
     */
    private function templatesInGroup(string $docType, int $clause, ?string $layoutProfile): Collection
    {
        $templates = QmhTemplate::query()
            ->where('doc_type', $docType)
            ->where('clause', $clause)
            ->orderByDesc('version')
            ->lockForUpdate()
            ->get();

        if ($docType !== 'fr') {
            return $templates;
        }

        $targetIdentity = $layoutProfile
            ?? QmhFrLayoutProfile::identityKey(QmhFrLayoutProfile::defaults());

        return $templates->filter(function (QmhTemplate $template) use ($targetIdentity): bool {
            $metadata = is_array($template->metadata) ? $template->metadata : [];
            $templateIdentity = $this->resolveTemplateGroupIdentity((string) $template->doc_type, $metadata);

            return $templateIdentity === $targetIdentity;
        })->values();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveTemplateGroupIdentity(string $docType, array $metadata): ?string
    {
        if ($docType !== 'fr') {
            return null;
        }

        return QmhFrLayoutProfile::identityKey($metadata);
    }

    /**
     * @param  array<string, mixed>|null  $beforeSchema
     * @param  array<string, mixed>|null  $afterSchema
     * @return array<string, mixed>
     */
    private function buildSchemaDiff(?array $beforeSchema, ?array $afterSchema): array
    {
        $beforeQuestions = is_array($beforeSchema['questions'] ?? null) ? $beforeSchema['questions'] : [];
        $afterQuestions = is_array($afterSchema['questions'] ?? null) ? $afterSchema['questions'] : [];

        $beforeMap = collect($beforeQuestions)
            ->filter(fn ($q): bool => is_array($q) && is_string($q['id'] ?? null) && trim((string) $q['id']) !== '')
            ->mapWithKeys(fn ($q): array => [(string) $q['id'] => $q]);

        $afterMap = collect($afterQuestions)
            ->filter(fn ($q): bool => is_array($q) && is_string($q['id'] ?? null) && trim((string) $q['id']) !== '')
            ->mapWithKeys(fn ($q): array => [(string) $q['id'] => $q]);

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($afterMap as $id => $question) {
            if (! $beforeMap->has($id)) {
                $added[] = $id;

                continue;
            }

            $before = (array) $beforeMap->get($id);
            $after = (array) $question;

            $fields = ['label', 'type', 'required'];
            $fieldDiff = [];
            foreach ($fields as $field) {
                if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                    $fieldDiff[$field] = [
                        'before' => $before[$field] ?? null,
                        'after' => $after[$field] ?? null,
                    ];
                }
            }

            if ($fieldDiff !== []) {
                $changed[$id] = $fieldDiff;
            }
        }

        foreach ($beforeMap as $id => $question) {
            if (! $afterMap->has($id)) {
                $removed[] = $id;
            }
        }

        return [
            'added_fields' => array_values($added),
            'removed_fields' => array_values($removed),
            'changed_fields' => $changed,
            'before_count' => count($beforeQuestions),
            'after_count' => count($afterQuestions),
        ];
    }

    private function normalizeTemplateContentHtml(string $contentHtml): string
    {
        $candidate = trim($contentHtml);
        if ($candidate === '') {
            return '<p></p>';
        }

        $decoded = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded !== $candidate) {
            $decodedHasRealTags = preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $decoded) === 1;
            $hasEncodedAngleBrackets = str_contains($candidate, '&lt;') || str_contains($candidate, '&gt;');

            if ($decodedHasRealTags && $hasEncodedAngleBrackets) {
                $candidate = $decoded;
            }
        }

        $sanitized = QmhHtmlSanitizer::sanitize($candidate);

        return trim($sanitized) === '' ? '<p></p>' : $sanitized;
    }

    private function authorizePreviewAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                $user->hasPermission('qmh.create')
                || $user->hasPermission('qmh.template.manage')
            ),
            403,
            'Anda tidak memiliki akses preview template.'
        );
    }

    private function authorizeTemplateManage(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && $user->hasPermission('qmh.template.manage'),
            403,
            'Anda tidak memiliki akses untuk mengelola template.'
        );
    }
}
