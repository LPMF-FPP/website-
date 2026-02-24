<?php

declare(strict_types=1);

use App\Enums\SampleStatus;
use App\Models\EvidenceUnit;
use App\Models\RemainingUnit;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->user = User::factory()->create(['role' => 'admin']);
});

it('redirects guest when accessing kaji ulang route', function (): void {
    $this->get(route('review.create'))
        ->assertRedirect(route('login'));
});

it('forbids non admin user without review permissions', function (): void {
    $unauthorizedUser = User::factory()->create(['role' => 'staff']);

    $this->actingAs($unauthorizedUser)
        ->get(route('review.create'))
        ->assertForbidden();
});

it('shows only requests that are still eligible for kaji ulang', function (): void {
    $eligible = TestRequest::factory()->create([
        'status' => 'submitted',
        'request_number' => 'REQ-ELIGIBLE-001',
        'receipt_number' => 'RESI-ELIGIBLE-001',
    ]);

    $notEligible = TestRequest::factory()->create([
        'status' => 'in_testing',
        'request_number' => 'REQ-HIDDEN-001',
        'receipt_number' => 'RESI-HIDDEN-001',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('review.create'));

    $response->assertOk();
    $response->assertSee($eligible->receipt_number);
    $response->assertDontSee($notEligible->receipt_number);
    $response->assertViewHas('requests', fn ($requests) => $requests->pluck('id')->contains($eligible->id));
    $response->assertViewHas('requests', fn ($requests) => ! $requests->pluck('id')->contains($notEligible->id));
});

it('stores kaji ulang successfully and creates workflow stages', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 5,
        'unit' => 'gram',
    ]);

    $payload = [
        'request_id' => $testRequest->id,
        'test_date' => now()->format('Y-m-d'),
        'samples' => [
            [
                'id' => $sample->id,
                'assigned_analyst_id' => $analyst->id,
                'test_methods' => ['uv_vis'],
                'physical_identification' => 'Tablet putih dalam blister.',
                'quantity' => 1.50,
                'quantity_unit' => 'gram',
                'batch_number' => 'BATCH-001',
                'expiry_date' => now()->addYear()->format('Y-m-d'),
                'test_type' => 'kualitatif',
                'notes' => 'Catatan kaji ulang.',
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->post(route('review.store'), $payload);

    $response->assertRedirect(route('testing.show', $testRequest->id));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('samples', [
        'id' => $sample->id,
        'assigned_analyst_id' => $analyst->id,
        'status' => SampleStatus::PREPARATION_PENDING->value,
        'physical_identification' => 'Tablet putih dalam blister.',
        'batch_number' => 'BATCH-001',
        'test_type' => 'kualitatif',
    ]);

    $this->assertDatabaseHas('test_requests', [
        'id' => $testRequest->id,
        'status' => 'in_testing',
    ]);

    $this->assertDatabaseHas('sample_test_processes', [
        'sample_id' => $sample->id,
        'stage' => 'preparation',
    ]);
    $this->assertDatabaseHas('sample_test_processes', [
        'sample_id' => $sample->id,
        'stage' => 'instrumentation',
        'performed_by' => $analyst->id,
    ]);
    $this->assertDatabaseHas('sample_test_processes', [
        'sample_id' => $sample->id,
        'stage' => 'interpretation',
    ]);

    expect(SampleTestProcess::query()
        ->where('sample_id', $sample->id)
        ->where('stage', 'preparation')
        ->whereNotNull('started_at')
        ->exists())->toBeTrue();

    expect(SampleTestProcess::query()
        ->where('sample_id', $sample->id)
        ->where('stage', 'instrumentation')
        ->whereNotNull('started_at')
        ->exists())->toBeTrue();

    $evidenceUnit = EvidenceUnit::query()->where('sample_id', $sample->id)->first();
    expect($evidenceUnit)->not->toBeNull();

    $remainingUnit = RemainingUnit::query()->where('evidence_unit_id', $evidenceUnit->id)->first();
    expect($remainingUnit)->not->toBeNull();
    expect((float) $remainingUnit->qty_remaining)->toBe(3.5);

    $showResponse = $this->actingAs($this->user)
        ->get(route('testing.show', $testRequest));

    $showResponse->assertOk();
    $showResponse->assertSee('Cetak Label Sisa', false);
});

it('does not create remaining label when leftover quantity is zero', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 2,
        'unit' => 'gram',
    ]);

    $payload = [
        'request_id' => $testRequest->id,
        'test_date' => now()->format('Y-m-d'),
        'samples' => [
            [
                'id' => $sample->id,
                'assigned_analyst_id' => $analyst->id,
                'test_methods' => ['uv_vis'],
                'physical_identification' => 'Sampel seimbang untuk uji.',
                'quantity' => 2.00,
                'quantity_unit' => 'gram',
                'batch_number' => 'BATCH-NOL-001',
                'expiry_date' => now()->addYear()->format('Y-m-d'),
                'test_type' => 'kualitatif',
                'notes' => 'Tidak ada sisa.',
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->post(route('review.store'), $payload);

    $response->assertRedirect(route('testing.show', $testRequest->id));
    $this->assertDatabaseCount('remaining_units', 0);
});

it('does not create duplicate remaining labels when review save is triggered again', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 4,
        'unit' => 'gram',
    ]);

    $payload = [
        'request_id' => $testRequest->id,
        'test_date' => now()->format('Y-m-d'),
        'samples' => [
            [
                'id' => $sample->id,
                'assigned_analyst_id' => $analyst->id,
                'test_methods' => ['uv_vis'],
                'physical_identification' => 'Sampel untuk uji idempoten.',
                'quantity' => 1.00,
                'quantity_unit' => 'gram',
                'batch_number' => 'BATCH-IDEMPOTEN-001',
                'expiry_date' => now()->addYear()->format('Y-m-d'),
                'test_type' => 'kualitatif',
                'notes' => 'Pengulangan simpan tidak boleh duplikat.',
            ],
        ],
    ];

    $first = $this->actingAs($this->user)->post(route('review.store'), $payload);
    $first->assertRedirect(route('testing.show', $testRequest->id));

    $second = $this->actingAs($this->user)->post(route('review.store'), $payload);
    $second->assertRedirect(route('testing.show', $testRequest->id));

    $this->assertDatabaseCount('evidence_units', 1);
    $this->assertDatabaseCount('remaining_units', 1);
});

it('updates existing remaining label when reviewed testing quantity changes', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 10,
        'unit' => 'gram',
    ]);

    $buildPayload = static function (float $quantity) use ($testRequest, $sample, $analyst): array {
        return [
            'request_id' => $testRequest->id,
            'test_date' => now()->format('Y-m-d'),
            'samples' => [
                [
                    'id' => $sample->id,
                    'assigned_analyst_id' => $analyst->id,
                    'test_methods' => ['uv_vis'],
                    'physical_identification' => 'Sampel update sisa.',
                    'quantity' => $quantity,
                    'quantity_unit' => 'gram',
                    'batch_number' => 'BATCH-UPDATE-SISA-001',
                    'expiry_date' => now()->addYear()->format('Y-m-d'),
                    'test_type' => 'kualitatif',
                    'notes' => 'Uji update qty sisa.',
                ],
            ],
        ];
    };

    $this->actingAs($this->user)
        ->post(route('review.store'), $buildPayload(2.00))
        ->assertRedirect(route('testing.show', $testRequest->id));

    $this->actingAs($this->user)
        ->post(route('review.store'), $buildPayload(7.00))
        ->assertRedirect(route('testing.show', $testRequest->id));

    $evidenceUnit = EvidenceUnit::query()->where('sample_id', $sample->id)->firstOrFail();
    $remainingUnits = RemainingUnit::query()->where('evidence_unit_id', $evidenceUnit->id)->get();

    expect($remainingUnits)->toHaveCount(1);
    expect((float) $remainingUnits->first()->qty_remaining)->toBe(3.0);
});

it('removes existing remaining label when reviewed quantity reaches delivered quantity', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 5,
        'unit' => 'gram',
    ]);

    $buildPayload = static function (float $quantity) use ($testRequest, $sample, $analyst): array {
        return [
            'request_id' => $testRequest->id,
            'test_date' => now()->format('Y-m-d'),
            'samples' => [
                [
                    'id' => $sample->id,
                    'assigned_analyst_id' => $analyst->id,
                    'test_methods' => ['uv_vis'],
                    'physical_identification' => 'Sampel cleanup sisa.',
                    'quantity' => $quantity,
                    'quantity_unit' => 'gram',
                    'batch_number' => 'BATCH-CLEANUP-SISA-001',
                    'expiry_date' => now()->addYear()->format('Y-m-d'),
                    'test_type' => 'kualitatif',
                    'notes' => 'Uji penghapusan sisa saat habis.',
                ],
            ],
        ];
    };

    $this->actingAs($this->user)
        ->post(route('review.store'), $buildPayload(2.00))
        ->assertRedirect(route('testing.show', $testRequest->id));

    $this->assertDatabaseCount('remaining_units', 1);

    $this->actingAs($this->user)
        ->post(route('review.store'), $buildPayload(5.00))
        ->assertRedirect(route('testing.show', $testRequest->id));

    $this->assertDatabaseCount('remaining_units', 0);
});

it('removes existing remaining label when reviewed quantity exceeds delivered quantity', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 5,
        'unit' => 'gram',
    ]);

    $buildPayload = static function (float $quantity) use ($testRequest, $sample, $analyst): array {
        return [
            'request_id' => $testRequest->id,
            'test_date' => now()->format('Y-m-d'),
            'samples' => [
                [
                    'id' => $sample->id,
                    'assigned_analyst_id' => $analyst->id,
                    'test_methods' => ['uv_vis'],
                    'physical_identification' => 'Sampel cleanup sisa negatif.',
                    'quantity' => $quantity,
                    'quantity_unit' => 'gram',
                    'batch_number' => 'BATCH-CLEANUP-SISA-NEGATIF-001',
                    'expiry_date' => now()->addYear()->format('Y-m-d'),
                    'test_type' => 'kualitatif',
                    'notes' => 'Uji penghapusan sisa saat kuantitas melebihi diserahkan.',
                ],
            ],
        ];
    };

    $this->actingAs($this->user)
        ->post(route('review.store'), $buildPayload(2.00))
        ->assertRedirect(route('testing.show', $testRequest->id));

    $this->assertDatabaseCount('remaining_units', 1);

    $this->actingAs($this->user)
        ->post(route('review.store'), $buildPayload(6.00))
        ->assertRedirect(route('testing.show', $testRequest->id));

    $this->assertDatabaseCount('remaining_units', 0);
});

it('syncs remaining labels only for reviewed sample ids from payload', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $reviewedSample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 8,
        'unit' => 'gram',
    ]);

    $notReviewedSample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 6,
        'quantity' => 2,
        'unit' => 'gram',
    ]);

    $payload = [
        'request_id' => $testRequest->id,
        'test_date' => now()->format('Y-m-d'),
        'samples' => [
            [
                'id' => $reviewedSample->id,
                'assigned_analyst_id' => $analyst->id,
                'test_methods' => ['uv_vis'],
                'physical_identification' => 'Sampel direview.',
                'quantity' => 3.00,
                'quantity_unit' => 'gram',
                'batch_number' => 'BATCH-SCOPE-001',
                'expiry_date' => now()->addYear()->format('Y-m-d'),
                'test_type' => 'kualitatif',
                'notes' => 'Hanya satu sampel direview.',
            ],
        ],
    ];

    $this->actingAs($this->user)
        ->post(route('review.store'), $payload)
        ->assertRedirect(route('testing.show', $testRequest->id));

    $reviewedEvidenceUnit = EvidenceUnit::query()->where('sample_id', $reviewedSample->id)->first();
    $notReviewedEvidenceUnit = EvidenceUnit::query()->where('sample_id', $notReviewedSample->id)->first();

    expect($reviewedEvidenceUnit)->not->toBeNull();
    expect($notReviewedEvidenceUnit)->toBeNull();
});

it('handles mixed leftover samples and shows remaining label indicator in testing table', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $sampleWithLeftover = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 9,
        'unit' => 'gram',
    ]);

    $sampleWithoutLeftover = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis']),
        'test_methods' => json_encode(['uv_vis']),
        'status' => 'pending',
        'package_quantity' => 4,
        'unit' => 'gram',
    ]);

    $payload = [
        'request_id' => $testRequest->id,
        'test_date' => now()->format('Y-m-d'),
        'samples' => [
            [
                'id' => $sampleWithLeftover->id,
                'assigned_analyst_id' => $analyst->id,
                'test_methods' => ['uv_vis'],
                'physical_identification' => 'Sampel dengan sisa.',
                'quantity' => 3.00,
                'quantity_unit' => 'gram',
                'batch_number' => 'BATCH-MIX-001',
                'expiry_date' => now()->addYear()->format('Y-m-d'),
                'test_type' => 'kualitatif',
                'notes' => 'Sampel pertama menyisakan barang.',
            ],
            [
                'id' => $sampleWithoutLeftover->id,
                'assigned_analyst_id' => $analyst->id,
                'test_methods' => ['uv_vis'],
                'physical_identification' => 'Sampel tanpa sisa.',
                'quantity' => 4.00,
                'quantity_unit' => 'gram',
                'batch_number' => 'BATCH-MIX-002',
                'expiry_date' => now()->addYear()->format('Y-m-d'),
                'test_type' => 'kualitatif',
                'notes' => 'Sampel kedua habis dipakai.',
            ],
        ],
    ];

    $this->actingAs($this->user)
        ->post(route('review.store'), $payload)
        ->assertRedirect(route('testing.show', $testRequest->id));

    $evidenceWithLeftover = EvidenceUnit::query()->where('sample_id', $sampleWithLeftover->id)->first();
    $evidenceWithoutLeftover = EvidenceUnit::query()->where('sample_id', $sampleWithoutLeftover->id)->first();

    expect($evidenceWithLeftover)->not->toBeNull();
    expect($evidenceWithoutLeftover)->toBeNull();
    $this->assertDatabaseCount('remaining_units', 1);

    $response = $this->actingAs($this->user)
        ->get(route('testing.show', $testRequest));

    $response->assertOk();
    $response->assertSee('Label sisa tersedia', false);
});

it('fails validation when requested method is removed', function (): void {
    $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

    $testRequest = TestRequest::factory()->create([
        'status' => 'verified',
    ]);

    $sample = Sample::factory()->create([
        'test_request_id' => $testRequest->id,
        'requested_test_methods' => json_encode(['uv_vis', 'gc_ms']),
        'test_methods' => json_encode(['uv_vis', 'gc_ms']),
        'status' => 'pending',
        'package_quantity' => 5,
        'unit' => 'gram',
    ]);

    $payload = [
        'request_id' => $testRequest->id,
        'test_date' => now()->format('Y-m-d'),
        'samples' => [
            [
                'id' => $sample->id,
                'assigned_analyst_id' => $analyst->id,
                'test_methods' => ['gc_ms'],
                'physical_identification' => 'Sampel valid.',
                'quantity' => 1,
                'batch_number' => 'BATCH-VAL-001',
                'test_type' => 'kualitatif',
                'notes' => 'Catatan valid untuk pengujian.',
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->from(route('review.create', ['request_id' => $testRequest->id]))
        ->post(route('review.store'), $payload);

    $response->assertRedirect(route('review.create', ['request_id' => $testRequest->id]));
    $response->assertSessionHasErrors('samples.0.test_methods');

    expect($testRequest->fresh()->status)->toBe('verified');
    expect(SampleTestProcess::query()->where('sample_id', $sample->id)->count())->toBe(0);
});

it('rejects request successfully on allowed status', function (): void {
    $testRequest = TestRequest::factory()->create([
        'status' => 'received',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('review.reject', $testRequest), [
            'rejection_reason' => 'Dokumen pendukung belum lengkap.',
        ]);

    $response->assertRedirect(route('review.create'));
    $response->assertSessionHas('success');

    $testRequest->refresh();
    expect($testRequest->status)->toBe('rejected');
    expect($testRequest->rejected_reason)->toBe('Dokumen pendukung belum lengkap.');
    expect($testRequest->rejected_by)->toBe($this->user->id);
    expect($testRequest->rejected_at)->not->toBeNull();
});

it('prevents rejecting request with disallowed status', function (): void {
    $testRequest = TestRequest::factory()->create([
        'status' => 'in_testing',
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('review.create', ['request_id' => $testRequest->id]))
        ->post(route('review.reject', $testRequest), [
            'rejection_reason' => 'Status sudah masuk pengujian.',
        ]);

    $response->assertRedirect(route('review.create', ['request_id' => $testRequest->id]));
    $response->assertSessionHasErrors('rejection_reason');

    expect($testRequest->fresh()->status)->toBe('in_testing');
    expect($testRequest->fresh()->rejected_reason)->toBeNull();
});
