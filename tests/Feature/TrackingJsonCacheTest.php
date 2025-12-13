<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TrackingJsonCacheTest extends TestCase
{
    /** @test */
    public function json_endpoint_sets_cached_flag_and_reuses_cache()
    {
        Cache::flush();
        $id = 'REQ-2025-0001';

        // First request - should populate cache (_cached true because response includes flag after caching)
        $first = $this->getJson("/track/{$id}.json");
        $first->assertOk();
        $first->assertJsonStructure(['request_number', 'progress_percent', 'stages', 'last_updated', '_cached']);
        $firstCached = $first->json('_cached');
        $this->assertTrue($firstCached, 'First response expected _cached true after storing.');

        // Second request without nocache still cached
        $second = $this->getJson("/track/{$id}.json");
        $second->assertOk();
        $this->assertTrue($second->json('_cached'));

        // Request with nocache=1 should bypass and return _cached false
        $bypass = $this->getJson("/track/{$id}.json?nocache=1");
        $bypass->assertOk();
        $this->assertFalse($bypass->json('_cached'), 'Bypass request should have _cached false');

        // Next normal request after bypass should be cached again
        $after = $this->getJson("/track/{$id}.json");
        $after->assertOk();
        $this->assertTrue($after->json('_cached'));
    }
}
