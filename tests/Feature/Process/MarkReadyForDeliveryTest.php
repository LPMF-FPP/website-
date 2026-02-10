<?php

declare(strict_types=1);

use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\SystemSettingSeeder::class);
    Queue::fake();
    $this->user = User::factory()->create(['role' => 'admin']);
});

it('rejects marking as ready when a sample is missing preparation stage', function () {
    $request = TestRequest::factory()->create(['status' => 'in_testing']);

    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'interpretation_done',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    SampleTestProcess::factory()->instrumentation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDays(2),
    ]);
    SampleTestProcess::factory()->interpretation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('testing.ready-for-delivery', $request));

    $response->assertRedirect();
    $response->assertSessionHasErrors('error');
    expect($request->fresh()->status)->toBe('in_testing');
});

it('allows marking as ready when all samples have all 3 stages completed', function () {
    $request = TestRequest::factory()->create(['status' => 'in_testing']);

    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'interpretation_done',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    SampleTestProcess::factory()->preparation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDays(3),
    ]);
    SampleTestProcess::factory()->instrumentation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDays(2),
    ]);
    SampleTestProcess::factory()->interpretation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('testing.ready-for-delivery', $request));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($request->fresh()->status)->toBe('ready_for_delivery');
});
