<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BrandingSettingsRequest;
use App\Http\Requests\Settings\PdfPreviewRequest;
use App\Services\Settings\SettingsResponseBuilder;
use App\Services\Settings\SettingsWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class BrandingController extends Controller
{
    public function __construct(
        private readonly SettingsWriter $writer,
        private readonly SettingsResponseBuilder $builder
    ) {
    }

    public function update(BrandingSettingsRequest $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        $validated = $request->validated();

        $this->writer->put([
            'branding' => $validated['branding'],
            'pdf' => $validated['pdf'],
        ], 'UPDATE_BRANDING', $request->user());

        $snapshot = $this->builder->build();

        return response()->json([
            'branding' => Arr::get($snapshot, 'branding', []),
            'pdf' => Arr::get($snapshot, 'pdf', []),
        ]);
    }

    public function previewPdf(PdfPreviewRequest $request)
    {
        Gate::authorize('manage-settings');

        $incoming = $request->validated();
        $current = $this->builder->build();

        $branding = array_merge($current['branding'] ?? [], $incoming['branding'] ?? []);
        $pdfConfig = array_merge($current['pdf'] ?? [], $incoming['pdf'] ?? []);

        $binary = Pdf::loadView('pdf.settings-preview', [
            'branding' => $branding,
            'pdf' => $pdfConfig,
        ])->setPaper('a4')->setOption('dpi', 96)->output();

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="settings-preview.pdf"',
        ]);
    }
}
