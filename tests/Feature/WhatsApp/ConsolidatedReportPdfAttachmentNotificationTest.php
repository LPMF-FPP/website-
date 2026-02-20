<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendWhatsAppMessage;
use App\Models\ConsolidatedReport;
use App\Services\ConsolidatedReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConsolidatedReportPdfAttachmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_pdf_attachment_when_report_pdf_exists(): void
    {
        Queue::fake();
        Storage::fake('local');

        settings_fake([
            'consolidated_report.notify_on_generate' => true,
            'consolidated_report.notify_phone' => '+6285956592404',
        ], true);

        $pdfPath = 'reports/consolidated/laporan-monthly-20260201.pdf';
        Storage::disk('local')->put($pdfPath, 'dummy pdf');

        $report = ConsolidatedReport::create([
            'period_type' => 'monthly',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'period_label' => 'Bulan Februari 2026',
            'report_data' => [
                'statistics' => [
                    'total_requests_received' => 12,
                    'total_samples_received' => 24,
                ],
            ],
            'comparison_data' => [],
            'narrative_sections' => ['opening' => '', 'closing' => ''],
            'signers' => [
                ['role' => 'Pembuat', 'name' => '', 'position' => '', 'nip' => ''],
                ['role' => 'Pemeriksa', 'name' => '', 'position' => '', 'nip' => ''],
                ['role' => 'Pengesah', 'name' => '', 'position' => '', 'nip' => ''],
            ],
            'pdf_path' => $pdfPath,
            'generated_at' => now(),
            'is_auto_generated' => true,
        ]);

        $service = app(ConsolidatedReportService::class);
        $dispatched = $service->sendGenerationNotification($report);

        $this->assertSame(1, $dispatched);

        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) {
            return $job->attachmentPath !== null
                && is_string($job->attachmentPath)
                && str_ends_with($job->attachmentPath, '.pdf')
                && $job->attachmentFilename === 'laporan-monthly-20260201.pdf';
        });
    }
}
