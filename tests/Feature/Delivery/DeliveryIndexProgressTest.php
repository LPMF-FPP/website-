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
});

it('shows complete progress when all 3 stages are done for every sample', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);

    foreach (range(1, 2) as $i) {
        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'status' => 'ready_for_delivery',
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
    }

    $response = $this->actingAs($user)->get(route('delivery.index'));

    $response->assertOk();
    $response->assertSee('2 Sampel');
    $response->assertDontSee('1/2 Sampel');
    $response->assertDontSee('0/2 Sampel');
});

it('shows partial progress when a sample is missing preparation', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);

    $s1 = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);
    SampleTestProcess::factory()->preparation()->completed()->create([
        'sample_id' => $s1->id,
        'started_at' => now()->subDays(3),
    ]);
    SampleTestProcess::factory()->instrumentation()->completed()->create([
        'sample_id' => $s1->id,
        'started_at' => now()->subDays(2),
    ]);
    SampleTestProcess::factory()->interpretation()->completed()->create([
        'sample_id' => $s1->id,
        'started_at' => now()->subDay(),
    ]);

    $s2 = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);
    SampleTestProcess::factory()->instrumentation()->completed()->create([
        'sample_id' => $s2->id,
        'started_at' => now()->subDays(2),
    ]);
    SampleTestProcess::factory()->interpretation()->completed()->create([
        'sample_id' => $s2->id,
        'started_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->get(route('delivery.index'));

    $response->assertOk();
    $response->assertSee('1/2 Sampel');
});
