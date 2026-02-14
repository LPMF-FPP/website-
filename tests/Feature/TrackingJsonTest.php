<?php

namespace Tests\Feature;

use App\Models\TestRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// adjust if different namespace

class TrackingJsonTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_returns_404_for_unknown_tracking_number()
    {
        $this->getJson('/track/UNKNOWN-9999.json')
            ->assertStatus(404);
    }

    public function test_it_returns_condensed_payload_structure()
    {
        // Minimal factory assumptions; adjust field names to match actual schema
        $request = TestRequest::factory()->create([
            'request_number' => 'REQ-2025-0001',
            'status' => 'in_testing',
        ]);

        $this->getJson('/track/'.$request->request_number.'.json')
            ->assertOk()
            ->assertJsonStructure([
                'request_number',
                'raw_status',
                'current_stage_index',
                'progress_percent',
                'stages' => [
                    ['index', 'key', 'label', 'icon', 'status', 'timestamp'],
                    ['index', 'key', 'label', 'icon', 'status', 'timestamp'],
                    ['index', 'key', 'label', 'icon', 'status', 'timestamp'],
                    ['index', 'key', 'label', 'icon', 'status', 'timestamp'],
                ],
                'last_updated',
            ]);
    }
}
