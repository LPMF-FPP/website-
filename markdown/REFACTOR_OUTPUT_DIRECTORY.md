# Refactor Output Directory Usage

## Summary
Found multiple instances of direct file writing to `output/` directory. All must be replaced with DocumentService.

## Files to Refactor

### 1. **SampleTestProcessController.php** (lines 491-590)
**Method**: `generateReport()`
**Current behavior**:
- Writes temp JSON to `base_path("output/temp_lhu_data_{$id}.json")`
- Calls Python script to generate HTML to `base_path('output/laporan-hasil-uji')`
- Stores relative path in metadata

**Issues**:
- Direct file operations with `file_put_contents()`
- Files written to unmanaged `output/` directory
- No Document records created
- No investigator/request folder structure

**Refactor approach**:
```php
// AFTER Python script generates the file:
if ($result && isset($result['success']) && $result['success']) {
    $generatedPath = $result['html_path'] ?? null;
    
    if ($generatedPath && file_exists($generatedPath)) {
        // Read the generated HTML
        $htmlBinary = file_get_contents($generatedPath);
        
        // Store via DocumentService
        $docs = app(\App\Services\DocumentService::class);
        $process = $sampleProcess->loadMissing(['sample.testRequest']);
        $method = $sampleProcess->stage ?? 'instrument';
        $type = match ($method) {
            'uv_vis' => 'instrument_uv_vis',
            'gc_ms'  => 'instrument_gc_ms',
            'lc_ms'  => 'instrument_lc_ms',
            default  => 'instrument_result',
        };
        $sampleCode = $sampleProcess->sample->sample_code;
        $reqNo = $sampleProcess->sample->testRequest->request_number;
        $baseName = "LHU-{$reportNumber}-{$sampleCode}";
        
        $doc = $docs->storeForSampleProcess($sampleProcess, 'html', $type, $baseName, $htmlBinary);
        
        // Update metadata with Document ID instead of file path
        $metadata['report_document_id'] = $doc->id;
        $metadata['report_generated_at'] = now()->format('d/m/Y H:i');
        $metadata['report_number'] = $reportNumber;
        $sampleProcess->update(['metadata' => $metadata]);
        
        // Delete temp files
        if (file_exists($tempDataFile)) @unlink($tempDataFile);
        if (file_exists($generatedPath)) @unlink($generatedPath);
        
        return redirect()
            ->route('sample-processes.show', $sampleProcess)
            ->with('success', 'Laporan Hasil Uji berhasil dibuat dengan nomor ' . $reportNumber);
    }
}
```

**Also update**:
- Remove route: `Route::get('laporan-hasil-uji/{filename}', ...)` (web.php line 84-90)
- Replace with: Document download route via DocumentService

---

### 2. **DeliveryController.php** (lines 261-462)
**Methods**: 
- `generateHandoverSummary()` (line 262)
- `viewHandoverSummary()` (line 471)
- `downloadHandoverPdf()` (line 493)
- `handoverStatus()` (line 501)
- `handoverBasePath()` (line 22)

**Current behavior**:
- Writes temp JSON to `base_path('output/temp_ba_penyerahan_*.json')`
- Calls Python script to generate HTML/PDF to `base_path('output')`
- Serves files directly from `output/` folder

**Issues**:
- Same as #1 above
- Multiple file formats (HTML + PDF) for same document
- No Document records

**Refactor approach**:
```php
public function generateHandoverSummary(TestRequest $request)
{
    try {
        // ... existing code to build $payload ...
        
        $tempDataFile = sys_get_temp_dir() . '/temp_ba_penyerahan_' . $request->request_number . '.json';
        file_put_contents($tempDataFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Call Python script to generate to temp location
        $tempOutput = sys_get_temp_dir() . '/ba_output_' . $request->request_number;
        
        $process = new \Symfony\Component\Process\Process([
            $python,
            $script,
            '--id', $request->request_number,
            '--file', $tempDataFile,
            '--templates', $templates,
            '--outdir', $tempOutput,
            '--logo-tribrata', public_path('images/logo-tribrata-polri.png'),
            '--logo-pusdokkes', public_path('images/logo-pusdokkes-polri.png'),
            '--pdf'
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            if (file_exists($tempDataFile)) @unlink($tempDataFile);
            $err = trim($process->getErrorOutput());
            return back()->with('error', 'Gagal generate BA Penyerahan. ' . $err);
        }

        // Store generated files via DocumentService
        $docs = app(\App\Services\DocumentService::class);
        $inv = $request->investigator;
        
        $htmlPath = $tempOutput . '/BA_Penyerahan_Ringkasan_' . $request->request_number . '.html';
        $pdfPath = $tempOutput . '/BA_Penyerahan_Ringkasan_' . $request->request_number . '.pdf';
        
        if (file_exists($htmlPath)) {
            $htmlBinary = file_get_contents($htmlPath);
            $htmlDoc = $docs->storeGenerated(
                binary: $htmlBinary,
                ext: 'html',
                inv: $inv,
                req: $request,
                type: 'ba_penyerahan_html',
                baseName: 'BA-Penyerahan-' . $request->request_number
            );
        }
        
        if (file_exists($pdfPath)) {
            $pdfBinary = file_get_contents($pdfPath);
            $pdfDoc = $docs->storeGenerated(
                binary: $pdfBinary,
                ext: 'pdf',
                inv: $inv,
                req: $request,
                type: 'ba_penyerahan',
                baseName: 'BA-Penyerahan-' . $request->request_number
            );
        }
        
        // Cleanup temp files
        if (file_exists($tempDataFile)) @unlink($tempDataFile);
        if (file_exists($htmlPath)) @unlink($htmlPath);
        if (file_exists($pdfPath)) @unlink($pdfPath);
        if (is_dir($tempOutput)) @rmdir($tempOutput);

        return back()->with('success', 'Berita Acara Penyerahan berhasil dibuat.');
    } catch (\Exception $e) {
        \Log::error('Exception generate BA Penyerahan', ['error' => $e->getMessage()]);
        return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
    }
}

public function viewHandoverSummary(TestRequest $request)
{
    $document = Document::where('test_request_id', $request->id)
        ->where('document_type', 'ba_penyerahan_html')
        ->where('source', 'generated')
        ->latest()
        ->first();

    if (!$document || !Storage::disk('public')->exists($document->path)) {
        return back()->with('error', 'BA Penyerahan belum tersedia. Silakan generate dahulu.');
    }

    $filePath = Storage::disk('public')->path($document->path);
    return response()->file($filePath, ['Content-Type' => 'text/html']);
}

public function downloadHandoverPdf(TestRequest $request)
{
    $document = Document::where('test_request_id', $request->id)
        ->where('document_type', 'ba_penyerahan')
        ->where('source', 'generated')
        ->latest()
        ->first();

    if (!$document || !Storage::disk('public')->exists($document->path)) {
        return back()->with('error', 'BA PDF belum tersedia. Silakan generate dahulu.');
    }

    $filePath = Storage::disk('public')->path($document->path);
    $filename = 'BA_Penyerahan_Ringkasan_' . $request->request_number . '.pdf';
    return response()->download($filePath, $filename, ['Content-Type' => 'application/pdf']);
}

public function handoverStatus(TestRequest $request)
{
    $htmlDoc = Document::where('test_request_id', $request->id)
        ->where('document_type', 'ba_penyerahan_html')
        ->where('source', 'generated')
        ->latest()
        ->first();
        
    $pdfDoc = Document::where('test_request_id', $request->id)
        ->where('document_type', 'ba_penyerahan')
        ->where('source', 'generated')
        ->latest()
        ->first();

    $status = [
        'request_number' => $request->request_number,
        'html' => [
            'exists' => $htmlDoc && Storage::disk('public')->exists($htmlDoc->path),
            'document_id' => $htmlDoc->id ?? null,
            'created_at' => $htmlDoc->created_at ?? null,
        ],
        'pdf' => [
            'exists' => $pdfDoc && Storage::disk('public')->exists($pdfDoc->path),
            'document_id' => $pdfDoc->id ?? null,
            'created_at' => $pdfDoc->created_at ?? null,
        ],
    ];

    return response()->json($status);
}

// REMOVE handoverBasePath() method - no longer needed
```

---

### 3. **Routes (web.php)**

**Remove** (line 84-90):
```php
Route::get('laporan-hasil-uji/{filename}', function($filename) {
    $path = base_path('output/laporan-hasil-uji/' . $filename);
    if (!file_exists($path)) {
        abort(404, 'Laporan tidak ditemukan');
    }
    return response()->file($path);
})->where('filename', '.*\.html')->name('laporan-hasil-uji.view');
```

**Replace with**:
```php
Route::get('sample-processes/{process}/report/view', [SampleTestProcessController::class, 'viewReport'])
    ->name('sample-processes.report.view');
Route::get('sample-processes/{process}/report/download', [SampleTestProcessController::class, 'downloadReport'])
    ->name('sample-processes.report.download');
```

**Add to SampleTestProcessController**:
```php
public function viewReport(SampleTestProcess $process)
{
    $document = Document::where('test_request_id', $process->sample->test_request_id)
        ->where('document_type', 'LIKE', 'instrument_%')
        ->where('source', 'generated')
        ->where('extra->sample_process_id', $process->id) // if you add this to extra
        ->latest()
        ->first();

    if (!$document || !Storage::disk('public')->exists($document->path)) {
        return back()->with('error', 'Laporan belum tersedia.');
    }

    $filePath = Storage::disk('public')->path($document->path);
    return response()->file($filePath, ['Content-Type' => 'text/html']);
}
```

---

### 4. **Console Commands (GenerateBeritaAcara.php)**

**Lines 53, 83**: Uses `base_path('output')`

**Decision**: 
- If this is a legacy command for old system, consider deprecating
- If still needed, refactor similar to DeliveryController approach above

---

## Migration Steps

1. **Test in development first**
2. **Create database backup** (documents table)
3. **Run refactored code** to generate new documents
4. **Verify** files are in `storage/app/public/investigators/...`
5. **Verify** Document records are created
6. **Clean up** old `output/` directory files (optional - keep for rollback)
7. **Update** any views/templates that link to old paths
8. **Remove** deprecated routes

## Benefits After Refactoring

✅ All documents tracked in database
✅ Proper folder structure by investigator/request
✅ Audit trail (created_at, source, etc.)
✅ Consistent document management
✅ Easier to query/filter documents
✅ Backup/migration friendly
✅ No orphaned files in `output/`

## Document Types to Use

- `instrument_uv_vis` - UV-VIS instrument forms/results
- `instrument_gc_ms` - GC-MS instrument forms/results
- `instrument_lc_ms` - LC-MS instrument forms/results
- `instrument_result` - Generic instrument results
- `ba_penyerahan` - BA Penyerahan PDF
- `ba_penyerahan_html` - BA Penyerahan HTML version
