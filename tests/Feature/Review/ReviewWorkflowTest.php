<?php

declare(strict_types=1);

use App\Enums\SampleStatus;
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
                'active_substance' => 'Parasetamol',
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
                'active_substance' => 'Parasetamol',
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
