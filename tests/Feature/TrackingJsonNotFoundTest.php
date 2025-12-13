<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrackingJsonNotFoundTest extends TestCase
{
    /** @test */
    public function not_found_tracking_number_returns_404_without_cached_flag()
    {
        $resp = $this->getJson('/track/REQ-1999-9999.json');
        $resp->assertStatus(404);
        $this->assertArrayNotHasKey('_cached', $resp->json(), '404 payload must not contain _cached flag');
    }
}
