<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQmhTemplateRequest;
use App\Models\QmhTemplate;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QmhTemplateController extends Controller
{
    private const DEFAULT_CLAUSE = 4;

    public function index(Request $request)
    {
        $filters = validator($request->only(['doc_type', 'status', 'search']), [
            'doc_type' => ['nullable', 'in:sop,ik,fr'],
            'status' => ['nullable', 'in:active,inactive'],
            'search' => ['nullable', 'string'],
        ])->validate();

        $templates = QmhTemplate::query()
            ->when(isset($filters['doc_type']), fn ($query) => $query->where('doc_type', $filters['doc_type']))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $query->where('name', 'like', '%'.$filters['search'].'%');
            })
            ->orderBy('doc_type')
            ->orderByDesc('version')
            ->paginate(20)
            ->appends($request->query());

        return view('quality.templates.index', [
            'templates' => $templates,
        ]);
    }

    public function store(StoreQmhTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $uploadedFile = $request->file('file');
        $disk = 'local';
        $folder = sprintf('qmh/templates/%s', $validated['doc_type']);
        $path = $uploadedFile->store($folder, $disk);

        DB::transaction(function () use ($validated, $request, $path, $disk): void {
            $nextVersion = (int) QmhTemplate::query()
                ->where('doc_type', $validated['doc_type'])
                ->max('version') + 1;

            QmhTemplate::query()
                ->where('doc_type', $validated['doc_type'])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $template = QmhTemplate::query()->create([
                'name' => $validated['name'],
                'clause' => self::DEFAULT_CLAUSE,
                'doc_type' => $validated['doc_type'],
                'version' => $nextVersion,
                'storage_disk' => $disk,
                'source_docx_path' => $path,
                'is_active' => true,
                'metadata' => [
                    'version_notes' => $validated['version_notes'] ?? null,
                    'uploaded_by' => $request->user()?->id,
                ],
            ]);

            Audit::log('QMH_TEMPLATE_UPLOAD', (string) $template->id, null, [
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
                'path' => $template->source_docx_path,
            ]);
        });

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template QMH berhasil diunggah dan diaktifkan.');
    }

    public function activate(QmhTemplate $template): RedirectResponse
    {
        DB::transaction(function () use ($template): void {
            QmhTemplate::query()
                ->where('doc_type', $template->doc_type)
                ->where('id', '!=', $template->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $before = $template->is_active;

            $template->forceFill(['is_active' => true])->save();

            Audit::log('QMH_TEMPLATE_ACTIVATE', (string) $template->id, [
                'is_active' => $before,
            ], [
                'is_active' => true,
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
            ]);
        });

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template berhasil diaktifkan.');
    }

    public function deactivate(QmhTemplate $template): RedirectResponse
    {
        $before = $template->is_active;

        $template->forceFill(['is_active' => false])->save();

        Audit::log('QMH_TEMPLATE_DEACTIVATE', (string) $template->id, [
            'is_active' => $before,
        ], [
            'is_active' => false,
            'clause' => $template->clause,
            'doc_type' => $template->doc_type,
            'version' => $template->version,
        ]);

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template berhasil dinonaktifkan.');
    }

    public function preview(Request $request, QmhTemplate $template): StreamedResponse
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

        $disk = $template->storage_disk;
        $path = $template->source_docx_path;

        abort_if($path === null || ! Storage::disk($disk)->exists($path), 404, 'File template tidak ditemukan.');

        $fileName = sprintf(
            '%s-v%d.docx',
            Str::slug($template->name ?: $template->doc_type.'-template'),
            (int) $template->version
        );

        return Storage::disk($disk)->response(
            $path,
            $fileName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]
        );
    }
}
