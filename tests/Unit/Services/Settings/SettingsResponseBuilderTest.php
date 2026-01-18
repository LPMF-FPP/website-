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
}
