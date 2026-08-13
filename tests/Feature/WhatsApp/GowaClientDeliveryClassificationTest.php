<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Services\WhatsApp\GowaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GowaClientDeliveryClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        settings_fake([
            'notifications.whatsapp.base_url' => 'https://gowa.test',
            'notifications.whatsapp.basic_user' => null,
            'notifications.whatsapp.basic_pass' => null,
            'notifications.whatsapp.device_id' => null,
        ], true);
    }

    public function test_non_success_response_is_a_confirmed_failure(): void
    {
        Http::fake([
            'https://gowa.test/send/message' => Http::response(['message' => 'rejected'], 429),
        ]);

        $result = app(GowaClient::class)->sendMessage('628123456789@s.whatsapp.net', 'Pesan');

        $this->assertSame('failed', $result['outcome']);
        $this->assertSame(429, $result['status']);
        $this->assertStringContainsString('HTTP 429', $result['error']);
    }

    public function test_connection_failure_is_unknown(): void
    {
        Http::fake([
            'https://gowa.test/send/message' => Http::failedConnection(),
        ]);

        $result = app(GowaClient::class)->sendMessage('628123456789@s.whatsapp.net', 'Pesan');

        $this->assertSame('unknown', $result['outcome']);
        $this->assertArrayNotHasKey('status', $result);
    }

    public function test_file_failure_is_confirmed_and_not_retried_inside_the_client(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'gowa-file-');
        file_put_contents($file, 'attachment body');

        Http::fake([
            'https://gowa.test/send/file' => Http::response(['message' => 'rejected'], 503),
        ]);

        try {
            $result = app(GowaClient::class)->sendFile('628123456789@s.whatsapp.net', $file, 'Lampiran', 'hasil.txt');
        } finally {
            @unlink($file);
        }

        $this->assertSame('failed', $result['outcome']);
        $this->assertSame(503, $result['status']);
        Http::assertSentCount(1);
    }
}
