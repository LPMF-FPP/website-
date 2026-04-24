<?php

declare(strict_types=1);

use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->user = User::factory()->create(['role' => 'admin']);
});

it('shows remaining label section in testing page for in-testing request', function (): void {
    $testRequest = TestRequest::factory()->create([
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
    $testRequest = TestRequest::factory()->create([
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

it('returns render-ready payload when creating remaining label from web endpoint', function (): void {
    $testRequest = TestRequest::factory()->create([
        'status' => 'in_testing',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
    ]);

    /** @var LabelService $labelService */
    $labelService = app(LabelService::class);
    $evidenceUnit = $labelService->createEvidenceUnits($testRequest->id, [$sample->id])->firstOrFail();

    $this->actingAs($this->user)
        ->postJson('/labels/remaining-units', [
            'evidence_unit_id' => $evidenceUnit->id,
            'qty_remaining' => 1.25,
            'uom' => 'ml',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
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
});

it('updates remaining label from web endpoint with editable fields', function (): void {
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
});
