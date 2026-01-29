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

    public function sendMessage(string $jid, string $message, array $mentions = []): array
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

            $payload = [
                'phone' => $phone,
                'message' => $message,
            ];

            if (! empty($mentions)) {
                $payload['mentions'] = $mentions;
            }

            $response = $http->post("{$this->baseUrl}/send/message", $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('WhatsApp message sent successfully', [
                    'to' => $phone,
                    'message_id' => $data['results']['message_id'] ?? $data['message_id'] ?? $data['id'] ?? null,
                    'has_mentions' => ! empty($mentions),
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

    private function extractPhoneFromJid(string $jid): string
    {
        return str_replace('@s.whatsapp.net', '', $jid);
    }

    public function checkHealth(): array
    {
        try {
            $http = Http::timeout(10);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            // Use /devices as /health endpoint is not available in current GOWA version
            $response = $http->get("{$this->baseUrl}/devices");

            if ($response->successful()) {
                $data = $response->json();
                $devices = $data['results'] ?? [];

                // Check if any device is logged in
                $hasLoggedInDevice = collect($devices)->contains(function ($device) {
                    return ($device['state'] ?? '') === 'logged_in';
                });

                return [
                    'reachable' => true,
                    'connected' => $hasLoggedInDevice,
                    'status' => $response->status(),
                    'devices_count' => count($devices),
                    'data' => $data,
                ];
            }

            return [
                'reachable' => false,
                'connected' => false,
                'status' => $response->status(),
                'error' => $response->body(),
            ];

        } catch (\Throwable $e) {
            return [
                'reachable' => false,
                'connected' => false,
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
                $rawDevices = $data['results'] ?? [];

                // Normalize device structure for frontend compatibility
                $devices = collect($rawDevices)->map(function ($device) {
                    return [
                        // Original fields
                        'id' => $device['id'] ?? null,
                        'display_name' => $device['display_name'] ?? null,
                        'state' => $device['state'] ?? 'unknown',
                        'jid' => $device['jid'] ?? null,
                        'created_at' => $device['created_at'] ?? null,

                        // Alias fields for frontend compatibility
                        'device_id' => $device['id'] ?? null,
                        'name' => $device['display_name'] ?? null,
                        'phone' => $this->extractPhoneFromJid($device['jid'] ?? ''),
                        'connected' => ($device['state'] ?? '') === 'logged_in',
                    ];
                })->all();

                return [
                    'success' => true,
                    'devices' => $devices,
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

    /**
     * List all chats from GOWA service (includes groups)
     */
    public function listChats(int $limit = 100): array
    {
        try {
            $http = Http::timeout(10);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            if ($this->deviceId) {
                $http = $http->withHeaders([
                    'X-Device-Id' => $this->deviceId,
                ]);
            }

            $response = $http->get("{$this->baseUrl}/chats", [
                'limit' => $limit,
                'offset' => 0,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'chats' => $data['results']['data'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
                'chats' => [],
            ];

        } catch (\Throwable $e) {
            Log::warning('Failed to list GOWA chats', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'chats' => [],
            ];
        }
    }

    /**
     * Get joined groups (returns groups user has joined)
     * Limit of 500 groups due to WhatsApp protocol limitation
     */
    public function getJoinedGroups(): array
    {
        try {
            $http = Http::timeout(15);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            if ($this->deviceId) {
                $http = $http->withHeaders([
                    'X-Device-Id' => $this->deviceId,
                ]);
            }

            $response = $http->get("{$this->baseUrl}/user/my/groups");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'groups' => $data['results']['data'] ?? $data['results'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
                'groups' => [],
            ];

        } catch (\Throwable $e) {
            Log::warning('Failed to list GOWA joined groups', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'groups' => [],
            ];
        }
    }

    /**
     * Get participants for a specific group
     * Currently GOWA doesn't expose a direct endpoint for this in docs,
     * but we can try to fetch group metadata if available.
     *
     * Based on OpenAPI, there is no direct endpoint to get participants count easily
     * without fetching group details.
     *
     * For now, we'll return a placeholder or empty list if not supported,
     * or implement if a specific endpoint is found.
     *
     * NOTE: Since we don't have a direct endpoint documented for just participants,
     * we will rely on the user/my/groups endpoint which might return participant count in metadata,
     * or we assume we can't get exact count yet.
     */
    public function getGroupParticipants(string $groupId): array
    {
        // For now, returning empty as we don't have a confirmed endpoint for this
        // in the OpenAPI spec provided.
        // If needed, we might need to parse it from the chats list if it contains metadata.
        return [
            'success' => false,
            'message' => 'Not implemented yet',
            'count' => 0,
        ];
    }
}
