<?php

namespace Tests\Unit\Services;

use App\Services\ConsolidatedReportService;
use Tests\TestCase;

class ConsolidatedReportServiceTest extends TestCase
{
    public function test_default_signers_structure_constant_exists()
    {
        $this->assertTrue(defined(ConsolidatedReportService::class.'::DEFAULT_SIGNERS_STRUCTURE'));

        $structure = ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE;
        $this->assertIsArray($structure);
        $this->assertCount(3, $structure);
        $this->assertEquals('Pembuat', $structure[0]['role']);
        $this->assertEquals('Pemeriksa', $structure[1]['role']);
        $this->assertEquals('Pengesah', $structure[2]['role']);
    }

    public function test_get_default_signers_returns_constant_when_settings_empty()
    {
        // Mock dependencies if needed, or just instantiate if no complex deps in constructor for this method
        // Service has dependencies, so we resolve from container or mock

        // Let's rely on the constant test first, then manually verify usage
        // Or better, mock the SettingsRepository to return null

        $mockSettings = \Mockery::mock(\App\Repositories\SettingsRepository::class);
        $mockSettings->shouldReceive('get')
            ->with('consolidated_report.default_signers', null)
            ->andReturn(null);

        // We need to construct service with mocks
        $service = new ConsolidatedReportService(
            $this->createMock(\App\Services\ActiveSubstanceService::class),
            $this->createMock(\App\Services\IkuService::class),
            $mockSettings
        );

        $signers = $service->getDefaultSigners();

        $this->assertEquals(ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE, $signers);
    }
}
