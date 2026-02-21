<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQmhDocumentRequest;
use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentLock;
use App\Models\QmhDocumentRelation;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use App\Models\User;
use App\Services\Quality\QmhDashboardSummaryService;
use App\Services\Quality\QmhDocumentService;
use App\Support\QmhFrV2Gate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class QmhDocumentController extends Controller
{
    public function landing(Request $request): View
    {
        $summaryFilters = validator($request->only(['clause', 'doc_type', 'from', 'to']), [
            'clause' => ['nullable', 'integer', 'in:4,5,6,7,8'],
            'doc_type' => ['nullable', 'in:sop,ik,formulir,pendukung'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();

        $summary = app(QmhDashboardSummaryService::class)->summarize($summaryFilters);

        return view('quality.dashboard', [
            'summary' => $summary,
        ]);
    }

    public function index(Request $request): View
    {
        $summaryFilters = validator($request->only(['clause', 'doc_type', 'from', 'to']), [
            'clause' => ['nullable', 'integer', 'in:4,5,6,7,8'],
            'doc_type' => ['nullable', 'in:sop,ik,fr,formulir,pendukung'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();

        $documents = QmhDocument::query()
            ->with('currentRevision')
            ->search($request->string('search')->toString())
            ->when($request->filled('clause'), function ($query) use ($request) {
                $query->where('clause', (int) $request->input('clause'));
            })
            ->when($request->filled('doc_type'), function ($query) use ($request) {
                $docType = $request->string('doc_type')->toString();
                $query->where('doc_type', $docType === 'fr' ? 'formulir' : $docType);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->whereHas('currentRevision', function ($revisionQuery) use ($request) {
                    $revisionQuery->where('status', $request->string('status')->toString());
                });
            })
            ->when($request->filled('edition_number'), function ($query) use ($request) {
                $query->whereHas('currentRevision', function ($revisionQuery) use ($request) {
                    $revisionQuery->where('edition_number', (int) $request->input('edition_number'));
                });
            })
            ->when($request->filled('revision_number'), function ($query) use ($request) {
                $query->whereHas('currentRevision', function ($revisionQuery) use ($request) {
                    $revisionQuery->where('revision_number', (int) $request->input('revision_number'));
                });
            })
            ->orderBy('doc_code')
            ->paginate(15)
            ->appends($request->query());

        $summary = app(QmhDashboardSummaryService::class)->summarize($summaryFilters);

        return view('quality.index', [
            'documents' => $documents,
            'summary' => $summary,
        ]);
    }

    public function create(): View
    {
        $sopOptions = QmhDocument::query()
            ->select('id', 'doc_code', 'title', 'clause')
            ->where('doc_type', 'sop')
            ->orderBy('clause')
            ->orderBy('doc_code')
            ->get();

        $ikOptions = QmhDocument::query()
            ->select('id', 'doc_code', 'title', 'clause', 'parent_sop_id')
            ->where('doc_type', 'ik')
            ->whereNotNull('parent_sop_id')
            ->orderBy('clause')
            ->orderBy('doc_code')
            ->get();

        $users = User::query()
            ->select('id', 'name', 'role')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('quality.create', [
            'sopOptions' => $sopOptions,
            'ikOptions' => $ikOptions,
            'users' => $users,
        ]);
    }

    public function show(QmhDocument $document): View
    {
        $document->load([
            'currentRevision.lock.owner',
            'currentRevision.template',
            'revisions' => fn ($query) => $query
                ->orderByDesc('edition_number')
                ->orderByDesc('revision_number'),
            'revisions.workflowEvents' => fn ($query) => $query->latest('id')->limit(5),
        ]);

        $users = User::query()
            ->select('id', 'name', 'role')
            ->orderBy('name')
            ->get();

        return view('quality.show', compact('document', 'users'));
    }

    public function edit(QmhDocument $document): View
    {
        $document->load([
            'currentRevision.lock.owner',
            'currentRevision.template',
        ]);

        $users = User::query()
            ->select('id', 'name', 'role')
            ->orderBy('name')
            ->get();

        return view('quality.edit', [
            'document' => $document,
            'revision' => $document->currentRevision,
            'users' => $users,
        ]);
    }

    public function store(StoreQmhDocumentRequest $request, QmhDocumentService $service): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $docType = (string) ($validated['doc_type'] ?? '');

            if (QmhFrV2Gate::isFrType($docType) && QmhFrV2Gate::isEnabled() && ! QmhFrV2Gate::isCreateEnabled($docType)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'source_pdf_file' => 'Pembuatan FR-v2 baru sedang ditutup sementara. Silakan hubungi admin QMH untuk jadwal cutover.',
                    ]);
            }

            $document = $service->createDraft($validated, (int) $request->user()->id);

            return redirect()
                ->route('quality.documents.edit', $document)
                ->with('success', 'Draft berhasil dibuat. Silakan lanjut tulis isi dokumen.');
        } catch (\Throwable $exception) {
            Log::error('Gagal membuat dokumen QMH dari web form', [
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['general' => 'Gagal membuat dokumen QMH.']);
        }
    }

    public function destroy(Request $request, QmhDocument $document): RedirectResponse
    {
        $document->load(['currentRevision', 'revisions']);

        $revision = $document->currentRevision;
        if ($revision === null) {
            return redirect()
                ->route('quality.documents.index')
                ->withErrors(['general' => 'Dokumen tidak memiliki revisi aktif.']);
        }

        $actor = $request->user();
        $isAdmin = (string) ($actor?->role ?? '') === 'admin';
        $isOwner = (int) ($revision->dibuat_oleh ?? 0) === (int) ($actor?->id ?? 0);

        if (! $isAdmin && ! $isOwner) {
            throw new AuthorizationException('Hanya admin atau pembuat revisi yang dapat menghapus dokumen draft.');
        }

        if ((string) ($revision->status ?? '') !== 'draft') {
            return back()->withErrors(['general' => 'Hanya dokumen dengan revisi draft yang dapat dihapus.']);
        }

        $hasHierarchyDependents = QmhDocument::query()
            ->where('parent_sop_id', $document->id)
            ->orWhere('paired_ik_id', $document->id)
            ->exists();

        $hasRelations = QmhDocumentRelation::query()
            ->where('source_document_id', $document->id)
            ->orWhere('target_document_id', $document->id)
            ->exists();

        if ($hasHierarchyDependents || $hasRelations) {
            return back()->withErrors([
                'general' => 'Dokumen ini masih punya relasi/hirarki. Lepaskan relasinya terlebih dahulu sebelum menghapus.',
            ]);
        }

        DB::transaction(function () use ($document) {
            $revisionIds = QmhDocumentRevision::query()
                ->where('document_id', $document->id)
                ->pluck('id')
                ->all();

            if (count($revisionIds) > 0) {
                QmhDocumentLock::query()->whereIn('revision_id', $revisionIds)->delete();
                QmhWorkflowEvent::query()->whereIn('revision_id', $revisionIds)->delete();

                QmhDocumentDownloadLog::query()
                    ->where(function ($query) use ($document, $revisionIds) {
                        $query->where('document_id', $document->id)
                            ->orWhereIn('revision_id', $revisionIds);
                    })
                    ->delete();
            } else {
                QmhDocumentDownloadLog::query()->where('document_id', $document->id)->delete();
            }

            QmhDocumentRelation::query()
                ->where('source_document_id', $document->id)
                ->orWhere('target_document_id', $document->id)
                ->delete();

            QmhDocumentRevision::query()->where('document_id', $document->id)->delete();
            $document->delete();
        });

        return redirect()
            ->route('quality.documents.index')
            ->with('success', 'Dokumen draft berhasil dihapus.');
    }
}
