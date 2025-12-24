<?php

/**
 * REFACTORED METHODS FOR RequestController
 * 
 * Copy these methods to replace the old ones in:
 * app/Http/Controllers/RequestController.php
 * 
 * Methods to replace:
 * 1. generateBeritaAcara() - Lines ~826-971
 * 2. downloadBeritaAcara() - Lines ~973-983  
 * 3. viewBeritaAcara() - Lines ~985-997
 * 
 * Note: checkBeritaAcara() has already been refactored in the controller
 * 
 * This file is for reference only. The methods below are wrapped in a dummy class
 * to prevent PHP syntax errors. Copy the method bodies into your actual controller.
 */

use App\Models\Document;
use App\Models\TestRequest;
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * This is a reference class - DO NOT use directly
 * Copy individual methods into your RequestController
 */
class RefactoredRequestControllerMethods
{
    /**
     * Generate Berita Acara Penerimaan
     * REPLACES OLD METHOD (lines ~826-971)
     */
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

    /**
     * Download Berita Acara Penerimaan
     * REPLACES OLD METHOD (lines ~973-983)
     */
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

    /**
     * View Berita Acara Penerimaan in browser
     * REPLACES OLD METHOD (lines ~985-997)
     */
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
}
