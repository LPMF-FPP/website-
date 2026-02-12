<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQmhDocumentRequest;
use App\Models\QmhDocument;
use App\Models\User;
use App\Services\Quality\QmhDashboardSummaryService;
use App\Services\Quality\QmhDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class QmhDocumentController extends Controller
{
    public function landing(Request $request): View
    {
        return $this->index($request);
    }

    public function index(Request $request): View
    {
        $summaryFilters = validator($request->only(['clause', 'doc_type', 'from', 'to']), [
            'clause' => ['nullable', 'integer', 'in:4,5,6,7,8'],
            'doc_type' => ['nullable', 'in:sop,ik,formulir'],
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
                $query->where('doc_type', $request->string('doc_type')->toString());
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
        return view('quality.create');
    }

    public function show(QmhDocument $document): View
    {
        $document->load([
            'currentRevision.lock.owner',
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
        ]);

        return view('quality.edit', [
            'document' => $document,
            'revision' => $document->currentRevision,
        ]);
    }

    public function store(StoreQmhDocumentRequest $request, QmhDocumentService $service): RedirectResponse
    {
        try {
            $service->createDraft($request->validated(), (int) $request->user()->id);

            return redirect()
                ->route('quality.documents.index')
                ->with('success', 'Dokumen QMH berhasil dibuat.');
        } catch (\Throwable $exception) {
            Log::error('Gagal membuat dokumen QMH dari web form', [
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['general' => 'Gagal membuat dokumen QMH.']);
        }
    }
}
