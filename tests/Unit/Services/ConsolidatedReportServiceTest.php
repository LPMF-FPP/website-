<?php

namespace Tests\Unit\Services;

use App\Repositories\SettingsRepository;
use App\Services\ActiveSubstanceService;
use App\Services\ConsolidatedReportService;
use App\Services\IkuService;
use App\Services\WhatsApp\TemplateService;
use Mockery;
use Tests\TestCase;

class ConsolidatedReportServiceTest extends TestCase
{
    public function test_default_signers_structure_constant_exists_with_correct_structure(): void
    {
        $structure = ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE;

        $this->assertIsArray($structure);
        $this->assertCount(3, $structure);

        $this->assertEquals('Pembuat', $structure[0]['role']);
        $this->assertEquals('Pemeriksa', $structure[1]['role']);
        $this->assertEquals('Pengesah', $structure[2]['role']);

        foreach ($structure as $signer) {
            $this->assertArrayHasKey('role', $signer);
            $this->assertArrayHasKey('name', $signer);
            $this->assertArrayHasKey('position', $signer);
            $this->assertArrayHasKey('nip', $signer);
        }
    }

    public function test_get_default_signers_returns_constant_when_settings_null(): void
    {
        $mockSettings = Mockery::mock(SettingsRepository::class);
        $mockSettings->shouldReceive('get')
            ->with('consolidated_report.default_signers', null)
            ->andReturn(null);

        $service = new ConsolidatedReportService(
            Mockery::mock(ActiveSubstanceService::class),
            Mockery::mock(IkuService::class),
            $mockSettings,
            Mockery::mock(TemplateService::class)
        );

        $this->assertEquals(ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE, $service->getDefaultSigners());
    }

    public function test_get_default_signers_returns_constant_when_settings_empty_array(): void
    {
        $mockSettings = Mockery::mock(SettingsRepository::class);
        $mockSettings->shouldReceive('get')
            ->with('consolidated_report.default_signers', null)
            ->andReturn([]);

        $service = new ConsolidatedReportService(
            Mockery::mock(ActiveSubstanceService::class),
            Mockery::mock(IkuService::class),
            $mockSettings,
            Mockery::mock(TemplateService::class)
        );

        $this->assertEquals(ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE, $service->getDefaultSigners());
    }

    public function test_get_default_signers_returns_saved_settings_when_present(): void
    {
        $savedSigners = [
            ['role' => 'Pembuat', 'name' => 'John Doe', 'position' => 'Analyst', 'nip' => '123'],
            ['role' => 'Pemeriksa', 'name' => 'Jane Doe', 'position' => 'Supervisor', 'nip' => '456'],
            ['role' => 'Pengesah', 'name' => 'Boss Man', 'position' => 'Director', 'nip' => '789'],
        ];

        $mockSettings = Mockery::mock(SettingsRepository::class);
        $mockSettings->shouldReceive('get')
            ->with('consolidated_report.default_signers', null)
            ->andReturn($savedSigners);

        $service = new ConsolidatedReportService(
            Mockery::mock(ActiveSubstanceService::class),
            Mockery::mock(IkuService::class),
            $mockSettings,
            Mockery::mock(TemplateService::class)
        );

        $signers = $service->getDefaultSigners();

        $this->assertEquals($savedSigners, $signers);
        $this->assertEquals('John Doe', $signers[0]['name']);
    }
}
