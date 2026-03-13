<?php

namespace Tests\Feature;

use App\Models\TestRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrackingReadyForDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ready_for_delivery_request_maps_to_penyerahan_in_public_tracking(): void
    {
        $request = TestRequest::factory()->create([
            'request_number' => 'REQ-TRACK-READY-001',
            'receipt_number' => 'RESI-TRACK-READY-001',
            'status' => 'ready_for_delivery',
            'submitted_at' => now()->subDays(4),
            'verified_at' => now()->subDays(3),
            'received_at' => now()->subDays(2),
            'ready_for_delivery_at' => now()->subHour(),
            'completed_at' => null,
        ]);

        $this->post(route('public.track'), [
            'tracking_number' => $request->receipt_number,
        ])
            ->assertOk()
            ->assertSee('Status Layanan Saat Ini', false)
            ->assertSee('Penyerahan');

        $this->getJson('/track/'.$request->receipt_number.'.json?nocache=1')
            ->assertOk()
            ->assertJsonPath('raw_status', 'penyerahan')
            ->assertJsonPath('current_stage_index', 3)
            ->assertJsonPath('progress_percent', 100);
    }
}
