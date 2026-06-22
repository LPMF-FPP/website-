<?php

declare(strict_types=1);

use App\Models\CustomerSurvey;
use App\Models\Document;
use App\Models\EvidenceUnit;
use App\Models\RemainingUnit;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemSettingSeeder::class);
    $this->seed(PermissionSeeder::class);
    settings_fake(['notifications.whatsapp.enabled' => false]);
    settings_forget_cache();
    Queue::fake();
    $this->user = User::factory()->create(['role' => 'admin']);
});

it('allows editing remaining sample quantity from delivery page', function (): void {
    $request = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'sample_code' => 'SAMP-SISA-EDIT',
        'package_quantity' => 10,
        'quantity' => 4,
        'unit' => 'tablet',
        'quantity_unit' => 'tablet',
    ]);

    createHandoverDocument($request);

    $response = $this->actingAs($this->user)
        ->patch(route('delivery.remaining-quantities.update', $request), [
            'samples' => [
                $sample->id => [
                    'qty_remaining' => '3.5',
                    'uom' => 'tablet',
                ],
            ],
        ]);

    $response->assertRedirect(route('delivery.show', $request));
    $response->assertSessionHas('success');

    $evidenceUnit = EvidenceUnit::query()->where('sample_id', $sample->id)->first();
    expect($evidenceUnit)->not->toBeNull();

    $remainingUnit = RemainingUnit::query()->where('evidence_unit_id', $evidenceUnit?->id)->first();
    expect($remainingUnit)->not->toBeNull()
        ->and($remainingUnit?->qty_remaining)->toBe('3.50')
        ->and($remainingUnit?->uom)->toBe('tablet')
        ->and($sample->fresh()->quantity)->toBe('6.50')
        ->and($sample->fresh()->quantity_unit)->toBe('tablet');

    $this->actingAs($this->user)
        ->get(route('delivery.show', $request))
        ->assertOk()
        ->assertSee('Edit jumlah sisa sampel')
        ->assertSee('6.5 tablet')
        ->assertSee('3.5 tablet');
});

it('rejects remaining quantity greater than delivered quantity', function (): void {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'package_quantity' => 10,
        'quantity' => 4,
        'unit' => 'tablet',
        'quantity_unit' => 'tablet',
    ]);

    $this->actingAs($this->user)
        ->patch(route('delivery.remaining-quantities.update', $request), [
            'samples' => [
                $sample->id => ['qty_remaining' => '11'],
            ],
        ])
        ->assertSessionHasErrors('remaining_quantities');

    expect(RemainingUnit::query()->count())->toBe(0);
});

it('rejects remaining quantity updates for samples outside the request', function (): void {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $otherRequest = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $otherSample = Sample::factory()->create([
        'test_request_id' => $otherRequest->id,
        'package_quantity' => 10,
    ]);

    $this->actingAs($this->user)
        ->patch(route('delivery.remaining-quantities.update', $request), [
            'samples' => [
                $otherSample->id => ['qty_remaining' => '1'],
            ],
        ])
        ->assertSessionHasErrors('remaining_quantities');

    expect(RemainingUnit::query()->count())->toBe(0);
});

it('derives remaining unit uom server side', function (): void {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'package_quantity' => 10,
        'quantity' => 4,
        'unit' => 'tablet',
        'quantity_unit' => 'tablet',
    ]);

    $this->actingAs($this->user)
        ->patch(route('delivery.remaining-quantities.update', $request), [
            'samples' => [
                $sample->id => [
                    'qty_remaining' => '3',
                    'uom' => 'kg',
                ],
            ],
        ])
        ->assertSessionHas('success');

    $remainingUnit = RemainingUnit::query()->first();
    expect($remainingUnit?->uom)->toBe('tablet');
});

it('rejects remaining quantity updates after delivery is finalized', function (): void {
    $request = TestRequest::factory()->create(['status' => 'completed']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'package_quantity' => 10,
        'quantity' => 4,
    ]);

    $this->actingAs($this->user)
        ->patch(route('delivery.remaining-quantities.update', $request), [
            'samples' => [
                $sample->id => ['qty_remaining' => '3'],
            ],
        ])
        ->assertSessionHasErrors('remaining_quantities');

    $this->actingAs($this->user)
        ->get(route('delivery.show', $request))
        ->assertOk()
        ->assertSee('Jumlah sisa sampel sudah final dan tidak dapat diedit pada status ini.')
        ->assertDontSee('Edit jumlah sisa sampel');
});

it('rejects direct remaining quantity edits for split remaining labels', function (): void {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'package_quantity' => 10,
        'quantity' => 4,
        'unit' => 'tablet',
    ]);
    $evidenceUnit = EvidenceUnit::query()->create([
        'request_id' => $request->id,
        'sample_id' => $sample->id,
        'receipt_code' => $request->receipt_number,
        'sample_code' => $sample->sample_code,
    ]);
    RemainingUnit::query()->create([
        'evidence_unit_id' => $evidenceUnit->id,
        'qty_remaining' => 2,
        'uom' => 'tablet',
    ]);
    RemainingUnit::query()->create([
        'evidence_unit_id' => $evidenceUnit->id,
        'qty_remaining' => 4,
        'uom' => 'tablet',
    ]);

    $this->actingAs($this->user)
        ->patch(route('delivery.remaining-quantities.update', $request), [
            'samples' => [
                $sample->id => ['qty_remaining' => '3'],
            ],
        ])
        ->assertSessionHasErrors('remaining_quantities');

    $this->actingAs($this->user)
        ->get(route('delivery.show', $request))
        ->assertOk()
        ->assertSee('6 tablet')
        ->assertSee('Sampel ini memiliki beberapa label sisa.');
});

it('prefers arithmetic reconciliation over stale single remaining label on delivery page', function (): void {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'package_quantity' => 30,
        'quantity' => 10,
        'unit' => 'tablet',
        'quantity_unit' => 'tablet',
    ]);

    $evidenceUnit = EvidenceUnit::query()->create([
        'request_id' => $request->id,
        'sample_id' => $sample->id,
        'receipt_code' => $request->receipt_number,
        'sample_code' => $sample->sample_code,
    ]);

    RemainingUnit::query()->create([
        'evidence_unit_id' => $evidenceUnit->id,
        'qty_remaining' => 30,
        'uom' => 'tablet',
    ]);

    createHandoverDocument($request);

    $this->actingAs($this->user)
        ->get(route('delivery.show', $request))
        ->assertOk()
        ->assertSee('30 tablet')
        ->assertSee('10 tablet')
        ->assertSee('20 tablet')
        ->assertDontSee('Edit jumlah sisa sampel</label>')
        ->assertSee('value="20"', false);
});

function createHandoverDocument(TestRequest $request): void
{
    Document::factory()->create([
        'investigator_id' => $request->investigator_id,
        'test_request_id' => $request->id,
        'document_type' => 'ba_penyerahan',
        'source' => 'generated',
        'filename' => 'ba-penyerahan.pdf',
        'original_filename' => 'BA-ST/001/IV/2026/FARMAPOL-ba-penyerahan.pdf',
        'file_path' => 'documents/ba-penyerahan.pdf',
        'path' => 'documents/ba-penyerahan.pdf',
        'storage_disk' => 'public',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
    ]);
}

it('marks remaining label step as complete when no sample has leftover quantity', function (): void {
    $request = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
    ]);

    Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'sample_code' => 'SAMP-NO-SISA',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    createHandoverDocument($request);

    $response = $this->actingAs($this->user)
        ->get(route('delivery.show', $request));

    $response->assertOk();
    $response->assertSee('Tidak ada sisa sampel yang perlu dilabeli.');
    $response->assertSee('langkah ini ditandai selesai otomatis', false);
    $response->assertSee('Kirim Notifikasi');
});

it('allows delivery completion without remaining labels when survey is complete and no leftover exists', function (): void {
    $request = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
    ]);

    Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'sample_code' => 'SAMP-HABIS-UJI',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    createHandoverDocument($request);

    CustomerSurvey::create([
        'test_request_id' => $request->id,
        'respondent_name' => 'Pengguna Uji',
        'respondent_institution' => 'Penyidik',
        'respondent_job_category' => 'Polri',
        'request_type' => 'Kimia - Fisika',
        'voluntary_statement' => true,
        'answers' => [
            'persyaratan' => 4,
            'prosedur' => 4,
            'ketepatan_waktu' => 4,
            'kesesuaian_hasil' => 4,
            'kompetensi' => 4,
            'sikap' => 4,
            'pengaduan' => 4,
            'fasilitas' => 4,
        ],
        'suggestion' => 'Sudah baik.',
        'score_avg' => 4,
        'submitted_at' => now(),
        'submitted_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('delivery.complete', $request))
        ->assertRedirect()
        ->assertSessionHas('success', 'Penyerahan berhasil diselesaikan. Status permintaan telah diperbarui.');

    expect($request->fresh())
        ->status->toBe('completed');
});
