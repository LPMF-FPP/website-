<?php

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Services\Quality\QmhRevisionDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders qmh pdf template html with header fields', function () {
    $document = QmhDocument::query()->create([
        'doc_code' => '4.1',
        'title' => 'Ketidakberpihakan',
        'clause' => 4,
        'doc_type' => 'sop',
        'owner_label' => 'Laboratorium',
        'is_active' => true,
    ]);

    $revision = QmhDocumentRevision::query()->create([
        'document_id' => $document->id,
        'edition_number' => 1,
        'revision_number' => 0,
        'version_label' => 'E1-R0',
        'status' => 'draft',
        'content_html' => '<p>Isi dokumen</p>',
        'effective_date' => '2026-02-14',
    ]);

    /** @var QmhRevisionDownloadService $service */
    $service = app(QmhRevisionDownloadService::class);

    $html = $service->buildWatermarkedHtml($revision->fresh('document'), 'UNCONTROLLED COPY');

    expect($html)->toContain('No. Dokumen');
    expect($html)->toContain('4.1');
    expect($html)->toContain('E1/R0');
    expect($html)->toContain('KETIDAKBERPIHAKAN');
    expect($html)->toContain('UNCONTROLLED COPY');
});
