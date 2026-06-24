<?php

declare(strict_types=1);

use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\Suspect;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

it('shows processing working days for ready and completed requests', function () {
    $user = User::factory()->create(['role' => 'admin']);

    TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'receipt_number' => 'RESI-WORKING-READY',
        'submitted_at' => Carbon::parse('2026-05-04 09:00:00'),
        'ready_for_delivery_at' => Carbon::parse('2026-05-07 15:00:00'),
        'completed_at' => Carbon::parse('2026-05-07 15:00:00'),
    ]);

    TestRequest::factory()->create([
        'status' => 'completed',
        'receipt_number' => 'RESI-WORKING-DONE',
        'submitted_at' => Carbon::parse('2026-05-01 09:00:00'),
        'ready_for_delivery_at' => Carbon::parse('2026-05-07 15:00:00'),
        'completed_at' => Carbon::parse('2026-05-07 16:00:00'),
    ]);

    $response = $this->actingAs($user)->get(route('delivery.index'));

    $response->assertOk();
    $response->assertSee('Waktu Pengerjaan');
    $response->assertSeeInOrder([
        'RESI-WORKING-READY',
        '3 hari kerja',
        'RESI-WORKING-DONE',
        '4 hari kerja',
    ], false);
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

it('sorts ready for delivery requests by receipt number', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $later = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'receipt_number' => 'RESI-READY-300',
        'request_number' => 'REQ-READY-300',
        'completed_at' => now()->subDay(),
    ]);

    $middle = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'receipt_number' => 'RESI-READY-200',
        'request_number' => 'REQ-READY-200',
        'completed_at' => now()->subDays(2),
    ]);

    $earlier = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'receipt_number' => 'RESI-READY-100',
        'request_number' => 'REQ-READY-100',
        'completed_at' => now()->subHours(6),
    ]);

    $ascending = $this->actingAs($user)->get(route('delivery.index', [
        'request_sort' => 'receipt_number',
        'request_direction' => 'asc',
    ]));

    $ascending->assertOk();
    $ascending->assertSeeInOrder([
        $earlier->receipt_number,
        $middle->receipt_number,
        $later->receipt_number,
    ], false);

    $descending = $this->actingAs($user)->get(route('delivery.index', [
        'request_sort' => 'receipt_number',
        'request_direction' => 'desc',
    ]));

    $descending->assertOk();
    $descending->assertSeeInOrder([
        $later->receipt_number,
        $middle->receipt_number,
        $earlier->receipt_number,
    ], false);
});

it('sorts ready for delivery by displayed receipt fallback and validates sort input', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $fallback = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'receipt_number' => null,
        'request_number' => 'REQ-FALLBACK-100',
        'completed_at' => now()->subDay(),
    ]);
    $fallback->forceFill(['receipt_number' => null])->saveQuietly();
    $fallback->refresh();

    $receipt = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'receipt_number' => 'RESI-FALLBACK-200',
        'request_number' => 'REQ-FALLBACK-200',
        'completed_at' => now()->subHours(6),
    ]);

    $ascending = $this->actingAs($user)->get(route('delivery.index', [
        'request_sort' => 'receipt_number',
        'request_direction' => 'asc',
    ]));

    $ascending->assertOk();
    $ascending->assertSeeInOrder([
        $fallback->fresh()->request_number,
        $receipt->receipt_number,
    ], false);

    $invalidSort = $this->actingAs($user)->get(route('delivery.index', [
        'request_sort' => 'suspect_name',
        'request_direction' => 'asc',
    ]));

    $invalidSort->assertOk();
    $invalidSort->assertSeeInOrder([
        $receipt->receipt_number,
        $fallback->fresh()->request_number,
    ], false);
});

it('preserves ready delivery sort parameters when sorting and filtering delivery history', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->get(route('delivery.index', [
        'request_sort' => 'receipt_number',
        'request_direction' => 'asc',
    ]));

    $response->assertOk();
    $response->assertSee('name="request_sort" value="receipt_number"', false);
    $response->assertSee('name="request_direction" value="asc"', false);
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

it('shows first suspect and additional suspect count on delivery index', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $request = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'suspect_name' => 'Legacy Tersangka',
    ]);

    Suspect::factory()->create([
        'test_request_id' => $request->id,
        'name' => 'BUDI SANTOSO',
        'order_no' => 1,
    ]);

    Suspect::factory()->create([
        'test_request_id' => $request->id,
        'name' => 'ANDI SAPUTRA',
        'order_no' => 2,
    ]);

    Suspect::factory()->create([
        'test_request_id' => $request->id,
        'name' => 'RIZKY PRATAMA',
        'order_no' => 3,
    ]);

    $response = $this->actingAs($user)->get(route('delivery.index'));

    $response->assertOk();
    $response->assertSee('BUDI SANTOSO');
    $response->assertSee('+2 tersangka lainnya');
    $response->assertDontSee('Legacy Tersangka');
});

it('falls back to legacy suspect name on delivery index when suspects relation is empty', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $request = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'suspect_name' => 'Tersangka Legacy Delivery',
    ]);

    $response = $this->actingAs($user)->get(route('delivery.index'));

    $response->assertOk();
    $response->assertSee('Tersangka Legacy Delivery');
});

it('searches delivery history by related suspect names', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $request = TestRequest::factory()->create([
        'status' => 'completed',
        'receipt_number' => 'RESI-SUSPECT-SEARCH',
        'suspect_name' => 'Nama Legacy Umum',
        'completed_at' => now(),
    ]);

    Suspect::factory()->create([
        'test_request_id' => $request->id,
        'name' => 'SITI AMINAH',
        'order_no' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('delivery.index', [
        'search' => 'SITI AMINAH',
    ]));

    $response->assertOk();
    $response->assertSee('RESI-SUSPECT-SEARCH');
    $response->assertSee('SITI AMINAH');
});
