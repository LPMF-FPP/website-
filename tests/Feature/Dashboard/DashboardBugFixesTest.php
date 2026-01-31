<?php

use App\Models\CustomerSurvey;
use App\Models\Delivery;
use App\Models\TestRequest;
use App\Services\DisposisiTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('satisfaction trend shows new direction when no previous month data', function () {
    // Arrange: Create survey data ONLY for current month
    $user = \App\Models\User::factory()->create();

    // Create survey for this month
    $request = TestRequest::factory()->create(['submitted_at' => now()]);
    CustomerSurvey::create([
        'test_request_id' => $request->id,
        'respondent_name' => 'Test',
        'respondent_institution' => 'Test',
        'respondent_job_category' => 'Swasta',
        'request_type' => 'Kimia - Fisika',
        'score_avg' => 4.0,
        'submitted_at' => now(),
        'submitted_by' => $user->id,
        'voluntary_statement' => true,
        'answers' => [],
        'suggestion' => 'Good',
    ]);

    // Act
    $response = $this->actingAs($user)->get(route('dashboard'));

    // Assert
    $response->assertOk();
    // customer_satisfaction is a top-level key in view data, not inside stats
    $satisfaction = $response->viewData('customer_satisfaction');

    expect($satisfaction['trend_direction'])->toBe('new');
    expect($satisfaction['trend'])->toBeNull();
});

test('hasil column shows delivery created_at when delivery exists', function () {
    // Arrange
    $user = \App\Models\User::factory()->create();
    $deliveryDate = now()->subDays(2);
    $request = TestRequest::factory()->create();

    $delivery = Delivery::create([
        'request_id' => $request->id,
        'delivered_by' => $user->id,
        'status' => \App\Enums\DeliveryStatus::PENDING,
        'delivery_date' => now(),
    ]);

    // Force update created_at manually to ensure it's different from now()
    $delivery->created_at = $deliveryDate;
    $delivery->save(['timestamps' => false]); // Prevent updated_at update

    // Act
    $service = new DisposisiTableService;
    // getTableData executes a fresh query, so setRelation is not needed
    $data = $service->getTableData(['search' => $request->suspect_name]);
    $row = $data->first();

    // Assert
    expect($row['hasil']->toDateTimeString())->toBe($deliveryDate->toDateTimeString());
});

test('speed calculation handles corrupted dates gracefully', function () {
    $user = \App\Models\User::factory()->create();

    TestRequest::factory()->create([
        'status' => 'completed',
        'submitted_at' => now(),
        'completed_at' => now()->subDays(5),
        'created_at' => now()->subDays(5),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();
});

test('hasil column is null when no delivery exists', function () {
    // Arrange
    $request = TestRequest::factory()->create([
        'status' => 'submitted', // Valid DB status (not in_progress)
        'completed_at' => null,
    ]);

    // Act
    $service = new DisposisiTableService;
    $data = $service->getTableData(['search' => $request->suspect_name]);
    $row = $data->first();

    // Assert
    expect($row['hasil'])->toBeNull();
});

test('ambil column only shows when status is completed', function () {
    // Arrange
    $completedDate = now()->subDay();
    $request = TestRequest::factory()->create([
        'status' => 'completed',
        'completed_at' => $completedDate,
        'updated_at' => $completedDate,
    ]);

    // Act
    $service = new DisposisiTableService;
    $data = $service->getTableData(['search' => $request->suspect_name]);
    $row = $data->first();

    // Assert
    expect($row['ambil']->toDateTimeString())->toBe($completedDate->toDateTimeString());
});

test('ambil column is null for ready_for_delivery status', function () {
    // Arrange
    $request = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'completed_at' => now(),
        'updated_at' => now(),
    ]);

    // Act
    $service = new DisposisiTableService;
    $data = $service->getTableData(['search' => $request->suspect_name]);
    $row = $data->first();

    // Assert
    expect($row['ambil'])->toBeNull();
});

test('status is red when stuck for more than 14 days', function () {
    // Arrange: Submitted 15 days ago, no further updates
    $request = TestRequest::factory()->create([
        'status' => 'submitted',
        'submitted_at' => now()->subDays(15),
        'created_at' => now()->subDays(15),
        'verified_at' => null,
        'completed_at' => null,
    ]);

    // Act
    $service = new DisposisiTableService;
    $data = $service->getTableData(['search' => $request->suspect_name]);
    $row = $data->first();

    // Assert
    expect($row['status'])->toBe('stuck_14_days');
});

test('status is yellow when stuck for more than 7 days but less than 14', function () {
    // Arrange: Submitted 8 days ago, no further updates
    $request = TestRequest::factory()->create([
        'status' => 'submitted',
        'submitted_at' => now()->subDays(8),
        'created_at' => now()->subDays(8),
        'verified_at' => null,
        'completed_at' => null,
    ]);

    // Act
    $service = new DisposisiTableService;
    $data = $service->getTableData(['search' => $request->suspect_name]);
    $row = $data->first();

    // Assert
    expect($row['status'])->toBe('stuck_7_days');
});

test('status is in_progress when stuck for less than 7 days', function () {
    // Arrange: Submitted 3 days ago
    $request = TestRequest::factory()->create([
        'status' => 'submitted',
        'submitted_at' => now()->subDays(3),
        'created_at' => now()->subDays(3),
    ]);

    // Act
    $service = new DisposisiTableService;
    $data = $service->getTableData(['search' => $request->suspect_name]);
    $row = $data->first();

    // Assert
    expect($row['status'])->toBe('in_progress');
});

test('status calculation uses latest activity date', function () {
    // Arrange: Submitted 20 days ago (Red), BUT Verified 3 days ago (Green/White)
    // Should be in_progress because last update was 3 days ago
    $request = TestRequest::factory()->create([
        'status' => 'verified',
        'submitted_at' => now()->subDays(20),
        'created_at' => now()->subDays(20),
        'verified_at' => now()->subDays(3),
    ]);

    // Act
    $service = new DisposisiTableService;
    $data = $service->getTableData(['search' => $request->suspect_name]);
    $row = $data->first();

    // Assert
    expect($row['status'])->toBe('in_progress');
});
