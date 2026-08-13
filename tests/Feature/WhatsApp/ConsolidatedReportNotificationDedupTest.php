<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendPersistedWhatsAppMessage;
use App\Models\ConsolidatedReport;
use App\Services\ConsolidatedReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConsolidatedReportNotificationDedupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_consolidated_notification_only_once_for_same_period_and_recipient(): void
    {
        Queue::fake();

        settings_fake([
            'consolidated_report.notify_on_generate' => true,
            'consolidated_report.notify_phone' => '+6285956592404',
        ], true);

        $report = ConsolidatedReport::create([
            'period_type' => 'biweekly',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-15',
            'period_label' => 'Bi-weekly 01-15 Februari 2026',
            'report_data' => [
                'statistics' => [
                    'total_requests_received' => 10,
                    'total_samples_received' => 20,
                ],
            ],
            'comparison_data' => [],
            'narrative_sections' => ['opening' => '', 'closing' => ''],
            'signers' => [
                ['role' => 'Pembuat', 'name' => '', 'position' => '', 'nip' => ''],
                ['role' => 'Pemeriksa', 'name' => '', 'position' => '', 'nip' => ''],
                ['role' => 'Pengesah', 'name' => '', 'position' => '', 'nip' => ''],
            ],
            'generated_at' => now(),
            'is_auto_generated' => true,
        ]);

        $duplicateReport = ConsolidatedReport::create([
            'period_type' => 'biweekly',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-15',
            'period_label' => 'Bi-weekly 01-15 Februari 2026',
            'report_data' => [
                'statistics' => [
                    'total_requests_received' => 11,
                    'total_samples_received' => 21,
                ],
            ],
            'comparison_data' => [],
            'narrative_sections' => ['opening' => '', 'closing' => ''],
            'signers' => [
                ['role' => 'Pembuat', 'name' => '', 'position' => '', 'nip' => ''],
                ['role' => 'Pemeriksa', 'name' => '', 'position' => '', 'nip' => ''],
                ['role' => 'Pengesah', 'name' => '', 'position' => '', 'nip' => ''],
            ],
            'generated_at' => now(),
            'is_auto_generated' => true,
        ]);

        $service = app(ConsolidatedReportService::class);

        $firstDispatch = $service->sendGenerationNotification($report);
        $secondDispatch = $service->sendGenerationNotification($report);
        $thirdDispatch = $service->sendGenerationNotification($duplicateReport);

        $this->assertSame(1, $firstDispatch);
        $this->assertSame(0, $secondDispatch);
        $this->assertSame(0, $thirdDispatch);

        Queue::assertPushed(SendPersistedWhatsAppMessage::class, 1);
        $this->assertDatabaseCount('whatsapp_message_batches', 1);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'recipient_jid' => '6285956592404@s.whatsapp.net',
            'status' => 'pending',
        ]);
    }
}
