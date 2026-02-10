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

it('blocks deletion of preparation when instrumentation exists', function () {
    $request = TestRequest::factory()->create(['status' => 'in_testing']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'instrumentation_pending',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    $prep = SampleTestProcess::factory()->preparation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDays(2),
    ]);

    SampleTestProcess::factory()->instrumentation()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('testing.processes.destroy', $prep));

    $response->assertRedirect();
    $response->assertSessionHasErrors('error');
    expect(SampleTestProcess::find($prep->id))->not->toBeNull();
});

it('blocks deletion of any stage when sample is ready_for_delivery', function () {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    $instr = SampleTestProcess::factory()->instrumentation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('testing.processes.destroy', $instr));

    $response->assertRedirect();
    $response->assertSessionHasErrors('error');
    expect(SampleTestProcess::find($instr->id))->not->toBeNull();
});

it('allows deletion when no subsequent stages exist and sample is not ready_for_delivery', function () {
    $request = TestRequest::factory()->create(['status' => 'in_testing']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'preparation_in_progress',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    $prep = SampleTestProcess::factory()->preparation()->create([
        'sample_id' => $sample->id,
        'started_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('testing.processes.destroy', $prep));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(SampleTestProcess::find($prep->id))->toBeNull();
});
