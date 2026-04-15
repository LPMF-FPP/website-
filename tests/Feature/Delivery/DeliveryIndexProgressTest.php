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

it('sorts delivery history by receipt number', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $older = TestRequest::factory()->create([
        'status' => 'completed',
        'receipt_number' => 'RESI-300',
        'request_number' => 'REQ-300',
        'completed_at' => now()->subDay(),
    ]);

    $middle = TestRequest::factory()->create([
        'status' => 'completed',
        'receipt_number' => 'RESI-200',
        'request_number' => 'REQ-200',
        'completed_at' => now()->subDays(2),
    ]);

    $newer = TestRequest::factory()->create([
        'status' => 'completed',
        'receipt_number' => 'RESI-100',
        'request_number' => 'REQ-100',
        'completed_at' => now()->subHours(6),
    ]);

    $ascending = $this->actingAs($user)->get(route('delivery.index', [
        'sort' => 'receipt_number',
        'direction' => 'asc',
    ]));

    $ascending->assertOk();
    $ascending->assertSeeInOrder([
        $newer->receipt_number,
        $middle->receipt_number,
        $older->receipt_number,
    ], false);

    $descending = $this->actingAs($user)->get(route('delivery.index', [
        'sort' => 'receipt_number',
        'direction' => 'desc',
    ]));

    $descending->assertOk();
    $descending->assertSeeInOrder([
        $older->receipt_number,
        $middle->receipt_number,
        $newer->receipt_number,
    ], false);
});

it('returns delivery history fragment for ajax sort requests', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $alpha = TestRequest::factory()->create([
        'status' => 'completed',
        'receipt_number' => 'RESI-AJAX-200',
        'request_number' => 'REQ-AJAX-200',
        'suspect_name' => 'Ajax B',
        'completed_at' => now()->subDay(),
    ]);

    $beta = TestRequest::factory()->create([
        'status' => 'completed',
        'receipt_number' => 'RESI-AJAX-100',
        'request_number' => 'REQ-AJAX-100',
        'suspect_name' => 'Ajax A',
        'completed_at' => now()->subHours(12),
    ]);

    $response = $this->actingAs($user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->withHeader('X-Fragment', 'delivery-history')
        ->get(route('delivery.index', [
            'sort' => 'receipt_number',
            'direction' => 'asc',
        ]));

    $response->assertOk();
    $response->assertDontSee('Penyerahan Hasil Pengujian');
    $response->assertSeeInOrder([
        $beta->receipt_number,
        $alpha->receipt_number,
    ], false);
});

it('keeps full page response for generic ajax delivery index requests', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('delivery.index'));

    $response->assertOk();
    $response->assertSee('Penyerahan Hasil Pengujian');
    $response->assertSee('Riwayat Penyerahan');
});
