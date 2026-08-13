<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Events\NumberIssued;
use App\Listeners\SendIssueNotification;
use App\Models\SystemSetting;
use App\Models\WhatsAppMessageLog;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NumberIssuedNotificationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_number_issue_notification_is_persisted_for_the_log_before_delivery(): void
    {
        $this->seed(SystemSettingSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'notifications'], ['value' => [
            'whatsapp' => [
                'enabled' => true,
                'default_target' => '08123456789',
                'message' => 'Nomor {NUMBER} untuk {REQ} telah diterbitkan.',
            ],
        ]]);
        settings_forget_cache();
        Queue::fake();

        app(SendIssueNotification::class)->handle(new NumberIssued('ba', 'BA/001', [
            'request_short' => 'REQ-001',
        ]));

        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_type' => NumberIssued::class,
            'source_label' => 'Notifikasi nomor terbit',
            'recipient_jid' => '628123456789@s.whatsapp.net',
            'status' => WhatsAppMessageLog::STATUS_PENDING,
            'transport' => WhatsAppMessageLog::TRANSPORT_GOWA,
        ]);
    }
}
