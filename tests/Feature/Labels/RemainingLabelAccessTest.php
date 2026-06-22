<?php

declare(strict_types=1);

use App\Models\Investigator;
use App\Models\RemainingUnit;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\LabelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->user = User::factory()->create(['role' => 'admin']);
});

it('shows remaining label section in testing page for in-testing request', function (): void {
    $investigator = Investigator::factory()->create();
    $testRequest = TestRequest::factory()->create([
        'investigator_id' => $investigator->id,
        'status' => 'in_testing',
    ]);

    $this->actingAs($this->user)
        ->get(route('testing.show', $testRequest))
        ->assertOk()
        ->assertSee('Label Sisa Sampel')
        ->assertDontSee('Cetak label sisa tersedia setelah kaji ulang permintaan selesai.');
});

it('blocks remaining sheet printing before review completion', function (): void {
    $testRequest = TestRequest::factory()->create([
        'status' => 'submitted',
    ]);

    $this->actingAs($this->user)
        ->from(route('testing.show', $testRequest))
        ->get(route('labels.remaining.sheet', $testRequest->id))
        ->assertRedirect(route('testing.show', $testRequest))
        ->assertSessionHas('error', 'Cetak label sisa tersedia setelah kaji ulang permintaan selesai.');
});

it('returns pdf response for remaining sheet when request is in testing and labels exist', function (): void {
    $investigator = Investigator::factory()->create();
    $testRequest = TestRequest::factory()->create([
        'investigator_id' => $investigator->id,
        'status' => 'in_testing',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
    ]);

    /** @var LabelService $labelService */
    $labelService = app(LabelService::class);
    $evidenceUnits = $labelService->createEvidenceUnits($testRequest->id, [$sample->id]);
    $evidenceUnit = $evidenceUnits->firstOrFail();
    $labelService->createRemainingUnit($evidenceUnit->id, [
        'qty_remaining' => 0.5,
        'uom' => 'gram',
    ]);

    $this->actingAs($this->user)
        ->get(route('labels.remaining.sheet', $testRequest->id))
        ->assertOk();
});

it('reconciles stale single remaining label quantity before rendering remaining sheet', function (): void {
    $testRequest = TestRequest::factory()->create([
        'status' => 'in_testing',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'package_quantity' => 30,
        'quantity' => 10,
        'unit' => 'tablet',
        'quantity_unit' => 'tablet',
    ]);

    /** @var LabelService $labelService */
    $labelService = app(LabelService::class);
    $evidenceUnit = $labelService->createEvidenceUnits($testRequest->id, [$sample->id])->firstOrFail();

    $remainingUnit = RemainingUnit::query()->create([
        'evidence_unit_id' => $evidenceUnit->id,
        'sample_code' => $sample->sample_code,
        'qty_remaining' => 30,
        'uom' => 'tablet',
        'delivered_at' => now(),
    ]);

    $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $mockPdf->shouldReceive('setPaper')->once()->andReturnSelf();
    $mockPdf->shouldReceive('output')->once()->andReturn('remaining-sheet-pdf');
    Pdf::shouldReceive('loadView')
        ->once()
        ->with('labels.remaining-sheet', \Mockery::on(function (array $data): bool {
            $unit = $data['remainingUnits']->first();

            return (float) $unit->qty_remaining === 20.0
                && $unit->qty_with_uom === '20.00 tablet';
        }))
        ->andReturn($mockPdf);

    $this->actingAs($this->user)
        ->get(route('labels.remaining.sheet', $testRequest->id))
        ->assertOk();

    expect($remainingUnit->fresh()->qty_remaining)->toBe('20.00');
});

it('returns render-ready payload when creating remaining label from web endpoint', function (): void {
    Storage::fake('public');

    $testRequest = TestRequest::factory()->create([
        'status' => 'in_testing',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
    ]);

    /** @var LabelService $labelService */
    $labelService = app(LabelService::class);
    $evidenceUnit = $labelService->createEvidenceUnits($testRequest->id, [$sample->id])->firstOrFail();

    $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $mockPdf->shouldReceive('setPaper')->andReturnSelf();
    $mockPdf->shouldReceive('output')->andReturn('remaining-label-pdf');
    Pdf::shouldReceive('loadView')->once()->andReturn($mockPdf);

    $this->actingAs($this->user)
        ->postJson('/labels/remaining-units', [
            'evidence_unit_id' => $evidenceUnit->id,
            'qty_remaining' => 1.25,
            'uom' => 'ml',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('drive_status', 'skipped')
        ->assertJsonStructure([
            'success',
            'message',
            'drive_status',
            'data' => [
                'id',
                'sample_code',
                'remaining_code',
                'qty_remaining',
                'uom',
                'seal_status_delivered',
                'condition_delivered',
                'handover_doc_no',
                'created_at',
                'qr_content',
            ],
        ]);

    $this->assertDatabaseHas('documents', [
        'test_request_id' => $testRequest->id,
        'document_type' => 'label_remaining',
        'source' => 'generated',
    ]);
});

it('updates remaining label from web endpoint with editable fields', function (): void {
    $testRequest = TestRequest::factory()->create([
        'status' => 'in_testing',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'package_quantity' => 10,
        'quantity' => 4,
        'unit' => 'ml',
        'quantity_unit' => 'ml',
    ]);

    /** @var LabelService $labelService */
    $labelService = app(LabelService::class);
    $evidenceUnit = $labelService->createEvidenceUnits($testRequest->id, [$sample->id])->firstOrFail();
    $remainingUnit = $labelService->createRemainingUnit($evidenceUnit->id, [
        'qty_remaining' => 1.25,
        'uom' => 'ml',
        'seal_status_delivered' => 'disegel',
    ]);

    $this->actingAs($this->user)
        ->putJson('/labels/remaining-units/'.$remainingUnit->id, [
            'qty_remaining' => 0.75,
            'uom' => 'gram',
            'seal_status_delivered' => 'rusak ringan',
            'condition_delivered' => 'wadah baik',
            'handover_doc_no' => 'BAST-001/IV/2026',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $remainingUnit->id)
        ->assertJsonPath('data.sample_code', $remainingUnit->sample_code)
        ->assertJsonPath('data.remaining_code', $remainingUnit->remaining_code)
        ->assertJsonPath('data.qty_remaining', '0.75')
        ->assertJsonPath('data.uom', 'gram')
        ->assertJsonPath('data.seal_status_delivered', 'rusak ringan')
        ->assertJsonPath('data.condition_delivered', 'wadah baik')
        ->assertJsonPath('data.handover_doc_no', 'BAST-001/IV/2026')
        ->assertJsonPath('data.qr_content', $remainingUnit->qr_content)
        ->assertJsonPath('data.created_at', optional($remainingUnit->created_at)?->toISOString());

    expect($remainingUnit->fresh())
        ->qty_remaining->toBe('0.75')
        ->uom->toBe('gram')
        ->seal_status_delivered->toBe('rusak ringan')
        ->condition_delivered->toBe('wadah baik')
        ->handover_doc_no->toBe('BAST-001/IV/2026');

    expect($sample->fresh())
        ->quantity->toBe('9.25')
        ->quantity_unit->toBe('gram');
});

it('creates a fresh remaining label document on update so Drive sync is retried', function (): void {
    Storage::fake('public');

    $testRequest = TestRequest::factory()->create([
        'status' => 'in_testing',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
    ]);

    /** @var LabelService $labelService */
    $labelService = app(LabelService::class);
    $evidenceUnit = $labelService->createEvidenceUnits($testRequest->id, [$sample->id])->firstOrFail();
    $remainingUnit = $labelService->createRemainingUnit($evidenceUnit->id, [
        'qty_remaining' => 1.25,
        'uom' => 'gram',
    ]);

    $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $mockPdf->shouldReceive('setPaper')->andReturnSelf();
    $mockPdf->shouldReceive('output')->twice()->andReturn('remaining-label-pdf');
    Pdf::shouldReceive('loadView')->twice()->andReturn($mockPdf);

    $this->actingAs($this->user)
        ->postJson('/labels/remaining-units', [
            'evidence_unit_id' => $evidenceUnit->id,
            'qty_remaining' => 1.25,
            'uom' => 'gram',
        ])
        ->assertOk();

    $firstDocument = \App\Models\Document::query()
        ->where('test_request_id', $testRequest->id)
        ->where('document_type', 'label_remaining')
        ->latest()
        ->firstOrFail();

    $this->actingAs($this->user)
        ->putJson('/labels/remaining-units/'.$remainingUnit->id, [
            'qty_remaining' => 1,
            'uom' => 'gram',
        ])
        ->assertOk();

    $latestDocument = \App\Models\Document::query()
        ->where('test_request_id', $testRequest->id)
        ->where('document_type', 'label_remaining')
        ->latest()
        ->firstOrFail();

    expect($latestDocument->id)->not->toBe($firstDocument->id);
    expect($latestDocument->fresh()->extra)->not->toBeNull();
});

it('stores and syncs single remaining label print as a document', function (): void {
    Storage::fake('public');

    $investigator = Investigator::factory()->create();
    $testRequest = TestRequest::factory()->create([
        'investigator_id' => $investigator->id,
        'status' => 'in_testing',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
    ]);

    /** @var LabelService $labelService */
    $labelService = app(LabelService::class);
    $evidenceUnit = $labelService->createEvidenceUnits($testRequest->id, [$sample->id])->firstOrFail();
    $remainingUnit = $labelService->createRemainingUnit($evidenceUnit->id, [
        'qty_remaining' => 1.25,
        'uom' => 'gram',
    ]);

    $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $mockPdf->shouldReceive('setPaper')->andReturnSelf();
    $mockPdf->shouldReceive('output')->once()->andReturn('single-remaining-label-pdf');
    Pdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data) use ($remainingUnit): bool {
            $this->assertSame('labels.remaining-single', $view);
            $this->assertSame($remainingUnit->remaining_code, $data['remainingUnit']->remaining_code);
            $this->assertSame('1.25', $data['remainingUnit']->qty_remaining);
            $this->assertSame($remainingUnit->evidenceUnit->receipt_code, $data['remainingUnit']->evidenceUnit->receipt_code);

            return true;
        })
        ->andReturn($mockPdf);

    $this->actingAs($this->user)
        ->get(route('labels.remaining.single', $remainingUnit->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertSee('single-remaining-label-pdf', false);

    $document = \App\Models\Document::query()
        ->where('test_request_id', $testRequest->id)
        ->where('document_type', 'remaining_label')
        ->latest()
        ->firstOrFail();

    expect($document->original_filename)->toContain($remainingUnit->remaining_code);
    expect(data_get($document->fresh()->extra, 'google_drive.status'))->toBe('skipped');
});
