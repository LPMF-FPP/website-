<?php

namespace Tests\Unit\Services\Settings;

use App\Models\MethodInstrumentRequirement;
use App\Services\IkuService;
use App\Services\Settings\SettingsResponseBuilder;
use App\Services\WhatsApp\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SettingsResponseBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_methods_matches_model_constant()
    {
        $ikuService = Mockery::mock(IkuService::class);
        $notificationService = Mockery::mock(NotificationService::class);

        $builder = new SettingsResponseBuilder($ikuService, $notificationService);
        
        $data = $builder->getInstrumentRequirementsData();
        
        $this->assertEquals(
            MethodInstrumentRequirement::AVAILABLE_METHODS, 
            $data['available_methods']
        );
    }

    public function test_get_options_returns_expected_structure()
    {
        $builder = app(SettingsResponseBuilder::class);
        $options = $builder->getOptions();
        
        $this->assertArrayHasKey('timezones', $options);
        $this->assertArrayHasKey('date_formats', $options);
        $this->assertArrayHasKey('number_formats', $options);
        $this->assertArrayHasKey('languages', $options);
        $this->assertArrayHasKey('storage_drivers', $options);
        $this->assertArrayHasKey('document_types', $options);

        // Verify values are populated (sanity check)
        $this->assertContains('public', $options['storage_drivers']);
        $this->assertContains('Asia/Jakarta', $options['timezones']);
    }
}
