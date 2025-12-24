<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Investigator;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvestigatorDocumentController extends Controller
{
    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display a listing of documents for an investigator
     */
    public function index(Request $request, Investigator $investigator)
    {
        Gate::authorize('viewDocuments', $investigator);

        $filters = $request->only(['type', 'source', 'request_id']);
        $documents = $this->documentService->getDocuments($investigator, $filters);

        // Group by request
        $groupedDocuments = $documents->groupBy(function ($doc) {
            return $doc->test_request_id 
                ? "{$doc->testRequest->request_number} - {$doc->testRequest->case_number}"
                : 'General Documents';
        });

        return view('investigators.documents.index', [
            'investigator' => $investigator,
            'groupedDocuments' => $groupedDocuments,
            'filters' => $filters,
            'totalDocuments' => $documents->count(),
            'totalSize' => $documents->sum('file_size'),
        ]);
    }

    /**
     * Show the form for uploading a new document
     */
    public function create(Investigator $investigator)
    {
        Gate::authorize('uploadDocument', $investigator);

        $testRequests = $investigator->testRequests()
            ->select('id', 'request_number', 'case_number')
            ->orderByDesc('created_at')
            ->get();

        return view('investigators.documents.create', [
            'investigator' => $investigator,
            'testRequests' => $testRequests,
            'documentTypes' => $this->getDocumentTypes(),
        ]);
    }

    /**
     * Store a newly uploaded document
     */
    public function store(Request $request, Investigator $investigator)
    {
        Gate::authorize('uploadDocument', $investigator);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,html,txt'],
            'test_request_id' => ['nullable', 'exists:test_requests,id'],
            'document_type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $testRequest = $validated['test_request_id'] 
                ? \App\Models\TestRequest::find($validated['test_request_id'])
                : null;

            $document = $this->documentService->storeUpload(
                $request->file('file'),
                $investigator,
                $testRequest,
                $validated['document_type']
            );

            return redirect()
                ->route('investigator.documents.index', $investigator)
                ->with('success', 'Document uploaded successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to upload document: ' . $e->getMessage());
        }
    }

    /**
     * Download a document as attachment
     */
    public function download(Document $document)
    {
        Gate::authorize('download', $document);

        if (!$this->documentService->fileExists($document)) {
            abort(404, 'Document file not found');
        }

        $path = $document->file_path ?? $document->path ?? '';
        $filename = $document->original_filename ?? $document->filename ?? ($path ? basename($path) : 'document');

        return response()->download(
            $this->documentService->getFilePath($document),
            $filename
        );
    }

    /**
     * Preview a document inline (for PDFs, images, etc.)
     */
    public function show(Document $document)
    {
        Gate::authorize('view', $document);

        if (!$this->documentService->fileExists($document)) {
            abort(404, 'Document file not found');
        }

        $path = $document->file_path ?? $document->path ?? '';
        $filename = $document->original_filename ?? $document->filename ?? ($path ? basename($path) : 'document');
        $mimeType = $document->mime_type ?? 'application/octet-stream';

        return response()->file(
            $this->documentService->getFilePath($document),
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * Remove the specified document
     */
    public function destroy(Document $document)
    {
        Gate::authorize('delete', $document);

        try {
            $investigatorId = $document->investigator_id;
            $this->documentService->delete($document);

            return redirect()
                ->route('investigator.documents.index', ['investigator' => $investigatorId])
                ->with('success', 'Document deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete document: ' . $e->getMessage());
        }
    }

    /**
     * Get available document types
     */
    protected function getDocumentTypes(): array
    {
        return [
            'request_letter' => 'Surat Permintaan',
            'sample_photo' => 'Foto Sampel',
            'evidence_photo' => 'Foto Barang Bukti',
            'test_result' => 'Hasil Pengujian',
            'lhu' => 'Laporan Hasil Uji',
            'ba_penyerahan' => 'Berita Acara Penyerahan',
            'ba_penerimaan' => 'Berita Acara Penerimaan',
            'other' => 'Lainnya',
        ];
    }
}
