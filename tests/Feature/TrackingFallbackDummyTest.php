<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TrackingFallbackDummyTest extends TestCase
{
    public function test_dummy_receipt_tracking_json_resolves_from_fallback_dataset(): void
    {
        Cache::flush();

        $this->getJson('/track/DUMMY-RECEIPT-002.json?nocache=1')
            ->assertOk()
            ->assertJsonPath('request_number', 'DUMMY-PENGUJIAN-002')
            ->assertJsonPath('raw_status', 'interpretasi_hasil')
            ->assertJsonPath('current_stage_index', 2);
    }

    public function test_dummy_request_tracking_page_renders_from_fallback_dataset(): void
    {
        $this->post(route('public.track'), [
            'tracking_number' => 'DUMMY-PENGUJIAN-001',
        ])
            ->assertOk()
            ->assertSee('DUMMY-PENGUJIAN-001')
            ->assertSee('DUMMY-RECEIPT-001')
            ->assertSee('Dummy Pengujian A');
    }
}
