<?php

namespace Tests\Unit\Models;

use App\Models\MethodInstrumentRequirement;
use Tests\TestCase;

class MethodInstrumentRequirementTest extends TestCase
{
    public function test_it_has_available_methods_constant()
    {
        $this->assertTrue(defined(MethodInstrumentRequirement::class . '::AVAILABLE_METHODS'));
        $this->assertEquals(
            ['uv_vis', 'gc_ms', 'lc_ms'],
            MethodInstrumentRequirement::AVAILABLE_METHODS
        );
    }
}
