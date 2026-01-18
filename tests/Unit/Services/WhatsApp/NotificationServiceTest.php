<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\WhatsApp\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_milestones_only_include_primary_notifications(): void
    {
        $service = new NotificationService();

        $this->assertSame([
            'REQUEST_RECEIVED',
            'REQUEST_REJECTED',
            'READY_FOR_PICKUP',
            'HANDOVER_COMPLETED',
        ], $service->getAvailableMilestones());
    }

    public function test_request_received_template_uses_formal_format(): void
    {
        $service = new NotificationService();

        $message = $service->getMilestoneMessage('REQUEST_RECEIVED', [
            'greetings' => 'Selamat Pagi',
            'pangkat' => 'AKP',
            'nama' => 'Siti Rahayu, S.H.',
            'nomor surat' => '2026-01-18-0017',
            'tersangka' => 'John Doe Test',
            'resi' => '2026-01-18-0019',
        ]);

        $expected = "Selamat Pagi, AKP Siti Rahayu, S.H..\n\nKami informasikan bahwa permintaan Anda dengan:\n\u{1F4C4} Nomor Surat: 2026-01-18-0017\n\u{1F464} Tersangka: John Doe Test\n\u{1F516} Kode Resi: 2026-01-18-0019\n\ntelah kami terima dan segera kami tindak lanjuti. \u{2705}\n\nTerima kasih atas kepercayaan Anda.\n\nSalam Presisi \u{1F64F}\nStaff Laboratorium Farmapol Pusdokkes Polri";

        $this->assertSame($expected, $message);
    }

    public function test_removed_milestones_return_null_template(): void
    {
        $service = new NotificationService();

        $message = $service->getMilestoneMessage('PREPARATION_DONE', [
            'resi' => 'A-001',
        ]);

        $this->assertNull($message);
    }
}
