<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\Investigator;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\WhatsAppMessageLog;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MilestoneNotificationLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.enabled'], ['value' => true]);
        SystemSetting::updateOrCreate([
            'key' => 'notifications.whatsapp.enabled_milestones',
        ], [
            'value' => ['REQUEST_RECEIVED', 'HANDOVER_COMPLETED', 'REQUEST_REJECTED'],
        ]);
        settings_forget_cache();
    }

    public function test_request_received_notification_is_persisted_for_the_log_before_delivery(): void
    {
        Queue::fake();
        $investigator = Investigator::factory()->create(['phone' => '08123456789']);

        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
        ]);

        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_label' => 'Notifikasi Berita Acara Penerimaan',
            'status' => WhatsAppMessageLog::STATUS_PENDING,
            'transport' => WhatsAppMessageLog::TRANSPORT_GOWA,
        ]);
        $this->assertDatabaseHas('whatsapp_outbox', [
            'test_request_id' => $request->id,
            'milestone_key' => 'REQUEST_RECEIVED',
            'status' => 'queued',
        ]);
    }

    public function test_handover_completed_notification_is_persisted_for_the_log_before_delivery(): void
    {
        Queue::fake();
        $investigator = Investigator::factory()->create(['phone' => '08123456789']);
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
        ]);

        $request->update(['status' => 'completed']);

        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_label' => 'Notifikasi Berita Acara Penyerahan',
            'status' => WhatsAppMessageLog::STATUS_PENDING,
            'transport' => WhatsAppMessageLog::TRANSPORT_GOWA,
        ]);
        $this->assertDatabaseHas('whatsapp_outbox', [
            'test_request_id' => $request->id,
            'milestone_key' => 'HANDOVER_COMPLETED',
            'status' => 'queued',
        ]);
    }

    public function test_request_rejected_notification_is_persisted_for_the_log_before_delivery(): void
    {
        Queue::fake();
        $investigator = Investigator::factory()->create(['phone' => '08123456789']);
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'submitted',
        ]);

        $request->update([
            'status' => 'rejected',
            'rejected_reason' => 'Data pendukung belum lengkap.',
        ]);

        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_label' => 'Notifikasi permintaan ditolak',
            'status' => WhatsAppMessageLog::STATUS_PENDING,
            'transport' => WhatsAppMessageLog::TRANSPORT_GOWA,
        ]);
        $this->assertDatabaseHas('whatsapp_outbox', [
            'test_request_id' => $request->id,
            'milestone_key' => 'REQUEST_REJECTED',
            'status' => 'queued',
        ]);
    }
}
