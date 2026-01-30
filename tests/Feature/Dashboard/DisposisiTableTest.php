<?php

namespace Tests\Feature\Dashboard;

use App\Models\Delivery;
use App\Models\Sample;
use App\Models\Suspect;
use App\Models\TestRequest;
use App\Services\DisposisiTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisposisiTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_returns_paginated_disposisi_data(): void
    {
        $request = TestRequest::factory()->create([
            'submitted_at' => now()->subDays(10),
            'verified_at' => now()->subDays(5),
            'completed_at' => null,
        ]);
        Suspect::factory()->create([
            'test_request_id' => $request->id,
            'name' => 'JOHN DOE',
        ]);
        Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'LS 01 | 2026',
        ]);

        $service = app(DisposisiTableService::class);
        $result = $service->getTableData();

        $this->assertCount(1, $result);
        $this->assertEquals('JOHN DOE', $result[0]['nama_tsk']);
        $this->assertNotNull($result[0]['masuk']);
        $this->assertNotNull($result[0]['urmin']);
        $this->assertNull($result[0]['hasil']);
    }

    public function test_service_detects_stuck_urmin_status(): void
    {
        $request = TestRequest::factory()->create([
            'submitted_at' => now()->subDays(20),
            'verified_at' => null,
        ]);
        Suspect::factory()->create(['test_request_id' => $request->id]);

        $service = app(DisposisiTableService::class);
        $result = $service->getTableData();

        $this->assertEquals('stuck_urmin', $result[0]['status']);
    }

    public function test_service_detects_stuck_hasil_status(): void
    {
        $request = TestRequest::factory()->create([
            'submitted_at' => now()->subDays(20),
            'verified_at' => now()->subDays(10),
            'completed_at' => null,
        ]);
        Suspect::factory()->create(['test_request_id' => $request->id]);

        $service = app(DisposisiTableService::class);
        $result = $service->getTableData();

        $this->assertEquals('stuck_hasil', $result[0]['status']);
    }

    public function test_service_detects_completed_status(): void
    {
        $request = TestRequest::factory()->create([
            'submitted_at' => now()->subDays(30),
            'verified_at' => now()->subDays(25),
            'completed_at' => now()->subDays(10),
        ]);
        Suspect::factory()->create(['test_request_id' => $request->id]);
        Delivery::factory()->collected()->create(['request_id' => $request->id]);

        $service = app(DisposisiTableService::class);
        $result = $service->getTableData();

        $this->assertEquals('completed', $result[0]['status']);
    }

    public function test_service_includes_sp_date_from_delivery(): void
    {
        $request = TestRequest::factory()->create();
        Suspect::factory()->create(['test_request_id' => $request->id]);
        Delivery::factory()->withSuratPengantar()->create(['request_id' => $request->id]);

        $service = app(DisposisiTableService::class);
        $result = $service->getTableData();

        $this->assertNotNull($result[0]['sp']);
    }

    public function test_service_returns_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $request = TestRequest::factory()->create();
            Suspect::factory()->create(['test_request_id' => $request->id]);
        }

        $service = app(DisposisiTableService::class);
        $result = $service->getPaginatedTableData(filters: [], perPage: 15);

        $this->assertEquals(15, $result->count());
        $this->assertEquals(20, $result->total());
        $this->assertEquals(2, $result->lastPage());
    }
}
