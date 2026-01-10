<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GowaClient
{
    private string $baseUrl;
    private ?string $basicUser;
    private ?string $basicPass;

    public function __construct()
    {
        $this->baseUrl = settings('notifications.whatsapp.base_url', 'http://localhost:3000');
        $this->basicUser = settings('notifications.whatsapp.basic_user');
        $this->basicPass = settings('notifications.whatsapp.basic_pass');
        
        if ($this->basicPass) {
            try {
                $this->basicPass = decrypt($this->basicPass);
            } catch (\Throwable $e) {
                Log::warning('Failed to decrypt WhatsApp basic password', ['error' => $e->getMessage()]);
                $this->basicPass = null;
            }
        }
    }

    public function sendMessage(string $jid, string $message): array
    {
        try {
            $http = Http::timeout(30);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            // Extract phone number from JID (remove @s.whatsapp.net suffix)
            $phone = str_replace('@s.whatsapp.net', '', $jid);

            $response = $http->post("{$this->baseUrl}/send/message", [
                'phone' => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['results']['message_id'] ?? $data['message_id'] ?? $data['id'] ?? null,
                    'data' => $data,
                ];
            }

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
}
