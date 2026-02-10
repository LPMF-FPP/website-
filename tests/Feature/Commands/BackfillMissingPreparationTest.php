<?php

declare(strict_types=1);

use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\SystemSettingSeeder::class);
    Queue::fake();
});

it('detects and lists missing preparation records in dry-run mode', function () {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
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

    $this->artisan('app:backfill-missing-preparation', ['--dry-run' => true])
        ->expectsOutputToContain($sample->sample_code)
        ->assertExitCode(0);

    expect(SampleTestProcess::where('sample_id', $sample->id)
        ->where('stage', 'preparation')
        ->exists())->toBeFalse();
});

it('backfills missing preparation records with correct timestamps', function () {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    $instrumentation = SampleTestProcess::factory()->instrumentation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDays(2),
    ]);
    SampleTestProcess::factory()->interpretation()->completed()->create([
        'sample_id' => $sample->id,
        'started_at' => now()->subDay(),
    ]);

    $this->artisan('app:backfill-missing-preparation')->assertExitCode(0);

    $prep = SampleTestProcess::where('sample_id', $sample->id)
        ->where('stage', 'preparation')
        ->first();

    expect($prep)->not->toBeNull();
    expect($prep->completed_at)->not->toBeNull();
    expect($prep->started_at->toDateTimeString())
        ->toBe($instrumentation->started_at->toDateTimeString());
    expect($prep->notes)->toContain('Backfill');
    expect($prep->metadata['backfilled'])->toBeTrue();
});

it('skips samples that already have all three stages', function () {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
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

    $this->artisan('app:backfill-missing-preparation')
        ->expectsOutputToContain('Tidak ada sampel')
        ->assertExitCode(0);

    expect(SampleTestProcess::where('sample_id', $sample->id)->count())->toBe(3);
});

it('is idempotent — running twice does not create duplicates', function () {
    $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
    $sample = Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
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

    $this->artisan('app:backfill-missing-preparation')->assertExitCode(0);
    $this->artisan('app:backfill-missing-preparation')->assertExitCode(0);

    expect(SampleTestProcess::where('sample_id', $sample->id)
        ->where('stage', 'preparation')
        ->count())->toBe(1);
    expect(SampleTestProcess::where('sample_id', $sample->id)->count())->toBe(3);
});
