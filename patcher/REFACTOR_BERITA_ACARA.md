# Refactoring Berita Acara Penerimaan

## Summary
Refactor Berita Acara Penerimaan generation from Python script + output/ folder to Blade template + DocumentService + storage/app/public/

## Files Created
1. ✅ `resources/views/pdf/berita-acara-penerimaan.blade.php` - Blade template for BA Penerimaan

## Files Modified
1. ✅ `app/Http/Controllers/RequestController.php` - Partially refactored (checkBeritaAcara done)
   - Added DocumentService import
   - ✅ `checkBeritaAcara()` - Now checks Document model instead of output/ folder
   - ⏳ `generateBeritaAcara()` - Needs refactoring (see below)
   - ⏳ `downloadBeritaAcara()` - Needs refactoring (see below)
   - ⏳ `viewBeritaAcara()` - Needs refactoring (see below)

## Remaining Work

### 1. Refactor `generateBeritaAcara()` method

Replace the entire method (lines ~826-971) with:

```php
public function generateBeritaAcara(TestRequest $request)
{
    try {
        // Reload request dengan relasi terbaru dari database
        $request->load(['investigator', 'samples']);

        \Log::info('Generating Berita Acara', [
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'investigator' => $request->investigator->name,
            'samples_count' => $request->samples->count(),
        ]);

        // Prepare data for Blade template
        $formatTestMethods = function($methods) {
            if (is_string($methods)) {
                $methods = json_decode($methods, true) ?? [];
            }
            $map = [
                'uv_vis' => 'Identifikasi Spektrofotometri UV-VIS',
                'gc_ms' => 'Identifikasi GC-MS',
                'lc_ms' => 'Identifikasi LC-MS'
            ];
            return collect($methods)->map(fn($m) => $map[$m] ?? $m)->join('; ');
        };

        $testsSummary = $request->samples->map(fn($s) => $formatTestMethods($s->test_methods))->unique()->join('; ');
        $submittedBy = $request->investigator->rank . ' ' . $request->investigator->name;
        $receivedBy = 'Petugas Administrasi (dokumen) & Petugas Laboratorium (sampel)';
        
        // Make $request available as $testRequest in the view to match template expectations
        $testRequest = $request;

        // Render Blade template to HTML
        $html = view('pdf.berita-acara-penerimaan', compact(
            'testRequest',
            'testsSummary',
            'submittedBy',
            'receivedBy'
        ))->render();

        // Convert HTML to PDF using DomPDF
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
        
        $pdfBinary = $pdf->output();

        // Get DocumentService
        $documentService = app(DocumentService::class);

        // Save PDF via DocumentService
        $baseName = 'BA-Penerimaan-' . $request->request_number;
        $pdfDocument = $documentService->storeGenerated(
            $pdfBinary,
            'pdf',
            $request->investigator,
            $request,
            'ba_penerimaan',
            $baseName
        );

        // Optional: Save HTML version
        $htmlDocument = $documentService->storeGenerated(
            $html,
            'html',
            $request->investigator,
            $request,
            'ba_penerimaan_html',
            $baseName
        );

        \Log::info('BA generated successfully', [
            'pdf_id' => $pdfDocument->id,
            'html_id' => $htmlDocument->id,
            'pdf_path' => $pdfDocument->path,
        ]);

        return back()->with('success', 'Berita Acara berhasil di-generate!');

    } catch (\Exception $e) {
        \Log::error('Exception in generateBeritaAcara', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
    }
}
```

### 2. Refactor `downloadBeritaAcara()` method

Replace method (lines ~973-983) with:

```php
public function downloadBeritaAcara(TestRequest $request)
{
    // Find the PDF document
    $document = Document::where('test_request_id', $request->id)
        ->where('document_type', 'ba_penerimaan')
        ->where('source', 'generated')
        ->latest()
        ->first();

    if (!$document || !Storage::disk('public')->exists($document->path)) {
        return back()->with('error', 'Berita Acara belum di-generate. Silakan generate terlebih dahulu.');
    }

    $filePath = Storage::disk('public')->path($document->path);
    return response()->download($filePath, $document->filename, [
        'Content-Type' => 'application/pdf',
    ]);
}
```

### 3. Refactor `viewBeritaAcara()` method

Replace method (lines ~985-997) with:

```php
public function viewBeritaAcara(TestRequest $request)
{
    // Find the HTML document first, fallback to PDF
    $htmlDocument = Document::where('test_request_id', $request->id)
        ->where('document_type', 'ba_penerimaan_html')
        ->where('source', 'generated')
        ->latest()
        ->first();

    if ($htmlDocument && Storage::disk('public')->exists($htmlDocument->path)) {
        $filePath = Storage::disk('public')->path($htmlDocument->path);
        return response()->file($filePath, [
            'Content-Type' => 'text/html',
        ]);
    }

    // Fallback to PDF if HTML not available
    $pdfDocument = Document::where('test_request_id', $request->id)
        ->where('document_type', 'ba_penerimaan')
        ->where('source', 'generated')
        ->latest()
        ->first();

    if ($pdfDocument && Storage::disk('public')->exists($pdfDocument->path)) {
        $filePath = Storage::disk('public')->path($pdfDocument->path);
        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    return back()->with('error', 'Berita Acara belum di-generate. Silakan generate terlebih dahulu.');
}
```

## Result

After refactoring, BA Penerimaan files will be stored at:
- **PDF**: `storage/app/public/investigators/{folder_key}/{REQUEST_NUMBER}/generated/ba_penerimaan/{timestamp}-ba-penerimaan-{request_number}.pdf`
- **HTML**: `storage/app/public/investigators/{folder_key}/{REQUEST_NUMBER}/generated/ba_penerimaan_html/{timestamp}-ba-penerimaan-{request_number}.html`

## Files to Delete (Optional)
After confirming the refactor works:
- `scripts/generate_berita_acara.py` - Python script no longer needed
- `templates/berita_acara_penerimaan.html.j2` - Jinja2 template replaced by Blade
- `output/` folder - No longer used

## Testing Checklist
- [ ] Generate BA for a test request
- [ ] Verify PDF is created in correct folder structure
- [ ] Verify HTML is created (optional)
- [ ] Test checkBeritaAcara endpoint
- [ ] Test downloadBeritaAcara endpoint
- [ ] Test viewBeritaAcara endpoint
- [ ] Verify old BA files in output/ are not affected
