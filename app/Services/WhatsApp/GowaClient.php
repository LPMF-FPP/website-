<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GowaClient
{
    private string $baseUrl;

    private ?string $basicUser;

    private ?string $basicPass;

    private ?string $deviceId;

    public function __construct()
    {
        $this->baseUrl = settings('notifications.whatsapp.base_url') ?: env('WHATSAPP_API_URL', 'http://localhost:3000');
        $this->basicUser = settings('notifications.whatsapp.basic_user') ?: env('WHATSAPP_BASIC_USER', 'lpmf');
        $this->deviceId = settings('notifications.whatsapp.device_id') ?: env('WHATSAPP_DEVICE_ID', '03663e24-efdb-48fe-961d-456436bfb219');

        $encPass = settings('notifications.whatsapp.basic_pass');

        if ($encPass) {
            try {
                $this->basicPass = decrypt($encPass);
            } catch (\Throwable $e) {
                $this->basicPass = env('WHATSAPP_BASIC_PASS', 'lpmfjaya1');
            }
        } else {
            $this->basicPass = env('WHATSAPP_BASIC_PASS', 'lpmfjaya1');
        }
    }

    public function sendMessage(string $jid, string $message): array
    {
        try {
            $http = Http::timeout(30);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            if ($this->deviceId) {
                $http = $http->withHeaders([
                    'X-Device-Id' => $this->deviceId,
                ]);
            }

            $phone = str_replace('@s.whatsapp.net', '', $jid);

            $response = $http->post("{$this->baseUrl}/send/message", [
                'phone' => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('WhatsApp message sent successfully', [
                    'to' => $phone,
                    'message_id' => $data['results']['message_id'] ?? $data['message_id'] ?? $data['id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['results']['message_id'] ?? $data['message_id'] ?? $data['id'] ?? null,
                    'data' => $data,
                ];
            }

            Log::error('WhatsApp GOWA send failed', [
                'to' => $phone,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
            ];

        } catch (\Throwable $e) {
            Log::error('WhatsApp GOWA client error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function checkHealth(): array
    {
        try {
            $http = Http::timeout(10);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            $response = $http->get("{$this->baseUrl}/health");

            return [
                'reachable' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
            ];

        } catch (\Throwable $e) {
            return [
                'reachable' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List all devices from GOWA service
     */
    public function listDevices(): array
    {
        try {
            $http = Http::timeout(10);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            $response = $http->get("{$this->baseUrl}/devices");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'devices' => $data['results'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
                'devices' => [],
            ];

        } catch (\Throwable $e) {
            Log::warning('Failed to list GOWA devices', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'devices' => [],
            ];
        }
    }

    /**
     * List all devices from GOWA service using provided credentials
     */
    public function listDevicesWithCredentials(string $baseUrl, ?string $basicUser, ?string $basicPass): array
    {
        try {
            $http = Http::timeout(10);

            if ($basicUser && $basicPass) {
                $http = $http->withBasicAuth($basicUser, $basicPass);
            }

            $response = $http->get("{$baseUrl}/devices");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'devices' => $data['results'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
                'devices' => [],
            ];

        } catch (\Throwable $e) {
            Log::warning('Failed to list GOWA devices (custom credentials)', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'devices' => [],
            ];
        }
    }
}
