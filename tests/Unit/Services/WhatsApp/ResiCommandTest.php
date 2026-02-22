<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Models\Sample;
use App\Models\TestRequest;
use App\Services\WhatsApp\Commands\ResiCommand;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResiCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_resi_tracking_response_uses_stage_friendly_format(): void
    {
        $request = TestRequest::factory()->create([
            'receipt_number' => 'LPMF-RESI-2026-0001',
            'request_number' => 'REQ-2026-0001',
            'status' => 'in_testing',
            'submitted_at' => now()->subDays(3),
            'verified_at' => now()->subDays(2),
            'received_at' => now()->subDay(),
        ]);

        Sample::factory()->count(2)->create([
            'test_request_id' => $request->id,
        ]);

        $command = new ResiCommand(app(TemplateService::class));

        $message = $command->execute('6281111111111@s.whatsapp.net', ['LPMF-RESI-2026-0001']);

        $this->assertStringContainsString('📋 *PELACAKAN RESI PERMINTAAN*', $message);
        $this->assertStringContainsString('🧭 *Tahapan Proses (1-5)*', $message);
        $this->assertStringContainsString('✅ 1. Permintaan', $message);
        $this->assertStringContainsString('✅ 2. Kaji Ulang Permintaan', $message);
        $this->assertStringContainsString('🟡 3. Pengujian', $message);
        $this->assertStringContainsString('▪️ 3.1 Preparasi sampel: ✅ Selesai', $message);
        $this->assertStringContainsString('▪️ 3.2 Pengujian pada instrumen: *🟡 Sedang berjalan*', $message);
        $this->assertStringContainsString('▪️ 3.3 Interpretasi hasil: ⚪️ Menunggu', $message);
        $this->assertStringContainsString('🕒 Waktu mulai tahap 3:', $message);
        $this->assertStringContainsString('📌 *Status Terkini*', $message);
        $this->assertStringContainsString('🟡 Tahap 3 dari 5 - Pengujian sedang berjalan', $message);
        $this->assertStringContainsString('Keterangan: ✅ selesai | 🟡 sedang berjalan | ⚪️ menunggu', $message);
        $this->assertStringContainsString('🕒 Pada Tahap 3, waktu menunjukkan kapan tahap pengujian dimulai.', $message);
        $this->assertStringContainsString('*/resi LPMF-RESI-2026-0001*', $message);
    }
}
