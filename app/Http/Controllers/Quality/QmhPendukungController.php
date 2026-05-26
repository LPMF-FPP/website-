<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQmhPendukungRequest;
use App\Http\Requests\Quality\UpdateQmhPendukungRequest;
use App\Models\QmhDocument;
use App\Services\Quality\QmhPendukungService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QmhPendukungController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        $documents = QmhDocument::query()
            ->pendukung()
            ->with('currentRevision')
            ->search($request->string('search')->toString())
            ->when($request->filled('clause'), function ($query) use ($request) {
                $query->where('clause', (int) $request->input('clause'));
            })
            ->orderBy('clause')
            ->orderBy('doc_code')
            ->paginate($perPage)
            ->appends($request->query());

        return view('quality.pendukung.index', [
            'documents' => $documents,
        ]);
    }

    public function show(QmhDocument $document, QmhPendukungService $service): View
    {
        $this->ensurePendukungDocument($document);

        $document->load(['currentRevision']);

        return view('quality.pendukung.show', [
            'document' => $document,
            'usage' => $service->getPendukungUsage($document),
            'fileExists' => $service->fileExists($document),
        ]);
    }

    public function create(): View
    {
        return view('quality.pendukung.create');
    }

    public function store(StoreQmhPendukungRequest $request, QmhPendukungService $service): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $document = $service->create($validated, $request->file('file'), (int) $request->user()->id);

            return redirect()
                ->route('quality.pendukung.show', $document)
                ->with('success', 'Dokumen pendukung berhasil dibuat.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Gagal membuat dokumen pendukung QMH', [
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['general' => 'Gagal membuat dokumen pendukung.']);
        }
    }

    public function edit(QmhDocument $document): View
    {
        $this->ensurePendukungDocument($document);

        $document->load(['currentRevision', 'revisions' => fn ($query) => $query->orderByDesc('id')]);

        return view('quality.pendukung.edit', [
            'document' => $document,
        ]);
    }

    public function update(UpdateQmhPendukungRequest $request, QmhDocument $document, QmhPendukungService $service): RedirectResponse
    {
        $this->ensurePendukungDocument($document);

        try {
            $updated = $service->updateVersion(
                $document,
                $request->validated(),
                $request->file('file'),
                (int) $request->user()->id
            );

            return redirect()
                ->route('quality.pendukung.show', $updated)
                ->with('success', 'Versi dokumen pendukung berhasil diperbarui.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Gagal memperbarui dokumen pendukung QMH', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['general' => 'Gagal memperbarui dokumen pendukung.']);
        }
    }

    public function destroy(Request $request, QmhDocument $document, QmhPendukungService $service): RedirectResponse
    {
        $this->ensurePendukungDocument($document);

        try {
            $service->delete($document, (int) $request->user()->id);

            return redirect()
                ->route('quality.pendukung.index')
                ->with('success', 'Dokumen pendukung berhasil dihapus permanen.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Gagal menghapus dokumen pendukung QMH', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors(['general' => 'Gagal menghapus dokumen pendukung.']);
        }
    }

    public function file(Request $request, QmhDocument $document, QmhPendukungService $service)
    {
        $this->ensurePendukungDocument($document);

        $document->load('currentRevision');
        $revision = $document->currentRevision ?? $document->latestRevision;

        if (! $service->handleMissingFile($document)) {
            abort(404, 'File tidak ditemukan');
        }

        if (! $service->verifyFileIntegrity($document)) {
            Log::error('File integrity check failed', [
                'doc_id' => $document->id,
            ]);

            abort(500, 'File corrupted');
        }

        $disk = (string) ($revision?->source_pdf_disk ?: config('quality.pendukung.storage_disk', 'local'));
        $path = (string) ($revision?->source_pdf_path ?? '');
        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'File tidak ditemukan');
        }

        $filename = $document->doc_code.'.'.pathinfo($path, PATHINFO_EXTENSION);
        $download = $request->boolean('download');
        $contentDisposition = $download ? 'attachment' : 'inline';

        $headers = [
            'Content-Type' => (string) ($revision?->source_pdf_mime ?: 'application/octet-stream'),
            'Content-Disposition' => $contentDisposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($download) {
            return Storage::disk($disk)->download($path, $filename, $headers);
        }

        return Storage::disk($disk)->response($path, $filename, $headers);
    }

    private function ensurePendukungDocument(QmhDocument $document): void
    {
        abort_unless($document->doc_type === 'pendukung', 404);
    }
}
