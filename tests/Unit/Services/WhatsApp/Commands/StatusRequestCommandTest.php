<?php

namespace Tests\Unit\Services\WhatsApp\Commands;

use App\Services\NumberingService;
use App\Services\WhatsApp\Commands\StatusRequestCommand;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusRequestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_report_uses_current_tracking_number_format(): void
    {
        $command = new StatusRequestCommand(new TemplateService);

        $response = $command->execute('628123456789@s.whatsapp.net', []);

        $this->assertStringContainsString(
            app(NumberingService::class)->example('tracking'),
            $response
        );
    }
}
