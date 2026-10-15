<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\WhatsApp\NotificationService;
use Tests\TestCase;

class NotificationServiceTaglineTest extends TestCase
{
    public function test_milestone_defaults_use_the_updated_tagline(): void
    {
        $templates = (new NotificationService)->getAllTemplates();

        foreach ($templates as $template) {
            $this->assertStringContainsString('Polri Bersama Masyarakat', $template);
            $this->assertStringNotContainsString('Salam Presisi', $template);
        }
    }

    public function test_saved_milestone_template_uses_the_migrated_tagline(): void
    {
        settings_fake([
            'notifications.whatsapp.templates' => [
                'REQUEST_RECEIVED' => 'Polri Bersama Masyarakat 🙏',
            ],
        ], replace: true);

        $message = (new NotificationService)->getMilestoneMessage('REQUEST_RECEIVED');

        $this->assertSame('Polri Bersama Masyarakat 🙏', $message);
    }
}
