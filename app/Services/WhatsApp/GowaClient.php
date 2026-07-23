<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GowaClient
{
    private const DEFAULT_MAX_FILE_BYTES = 5_242_880; // 5 MB

    /**
     * @var array<int, string>
     */
    private const ALLOWED_FILE_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
        'text/plain',
    ];

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

    public function sendFile(string $jid, string $filePath, ?string $caption = null, ?string $filename = null): array
    {
        if (! is_readable($filePath)) {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'File tidak dapat dibaca.',
                'retryable' => false,
            ];
        }

        $fileSize = @filesize($filePath);
        if (is_int($fileSize) && $fileSize > $this->resolveMaxFileBytes()) {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Ukuran file melebihi batas maksimum pengiriman.',
                'retryable' => false,
            ];
        }

        $mimeType = $this->resolveMimeType($filePath);
        if (! in_array($mimeType, self::ALLOWED_FILE_MIME_TYPES, true)) {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Tipe file tidak diizinkan untuk pengiriman WhatsApp.',
                'retryable' => false,
            ];
        }

        try {
            $http = Http::timeout(45)
                ->retry(3, function (int $attempt): int {
                    $base = 200 * (2 ** max(0, $attempt - 1));

                    return min(5000, $base + random_int(0, 250));
                }, throw: false);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            if ($this->deviceId) {
                $http = $http->withHeaders([
                    'X-Device-Id' => $this->deviceId,
                ]);
            }

            $phone = str_replace('@s.whatsapp.net', '', $jid);
            $name = $filename ?? basename($filePath);
            $captionText = trim((string) $caption);

            $resource = fopen($filePath, 'rb');
            if ($resource === false) {
                return [
                    'success' => false,
                    'status' => 0,
                    'error' => 'Gagal membuka file attachment.',
                    'retryable' => false,
                ];
            }

            $response = $http
                ->attach('file', $resource, $name)
                ->post("{$this->baseUrl}/send/file", [
                    'phone' => $phone,
                    'caption' => $captionText,
                ]);

            if (is_resource($resource)) {
                fclose($resource);
            }

            $status = $response->status();
            $data = $response->json();
            $messageId = $data['results']['message_id'] ?? $data['message_id'] ?? $data['id'] ?? null;

            if ($response->successful()) {
                Log::info('WhatsApp file sent successfully', [
                    'to' => $phone,
                    'filename' => $name,
                    'message_id' => $messageId,
                ]);

                return [
                    'success' => true,
                    'status' => $status,
                    'message_id' => $messageId,
                    'data' => $data,
                ];
            }

            Log::warning('WhatsApp GOWA send file failed', [
                'to' => $phone,
                'filename' => $name,
                'status' => $status,
                'error' => $response->body(),
            ]);

            return [
                'success' => false,
                'status' => $status,
                'error' => $response->body(),
                'message_id' => $messageId,
                'retryable' => $this->isRetryableStatus($status),
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsApp GOWA send file exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 0,
                'error' => $e->getMessage(),
                'retryable' => true,
            ];
        }
    }

    private function extractPhoneFromJid(string $jid): string
    {
        return str_replace('@s.whatsapp.net', '', $jid);
    }

    private function isRetryableStatus(int $status): bool
    {
        return in_array($status, [408, 429], true) || $status >= 500;
    }

    private function resolveMaxFileBytes(): int
    {
        $configured = (int) settings('notifications.whatsapp.max_file_bytes', self::DEFAULT_MAX_FILE_BYTES);

        return max(1_024, $configured);
    }

    private function resolveMimeType(string $filePath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($filePath);

        return is_string($detected) ? $detected : 'application/octet-stream';
    }

    public function checkHealth(): array
    {
        try {
            $http = Http::timeout(15);

            $response = $http->get("{$this->baseUrl}/health");

            if ($response->successful()) {
                return [
                    'reachable' => true,
                    'connected' => true,
                    'status' => $response->status(),
                    'devices_count' => null,
                    'data' => null,
                ];
            }

            if ($response->status() === 404) {
                return $this->checkHealthFallback();
            }

            return [
                'reachable' => false,
                'connected' => false,
                'status' => $response->status(),
                'error' => $response->body(),
            ];

        } catch (\Throwable $e) {
            return $this->checkHealthFallback();
        }
    }

    private function checkHealthFallback(): array
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

            $response = $http->get("{$this->baseUrl}/devices");

            if ($response->successful()) {
                $data = $response->json();
                $devices = $data['results'] ?? [];

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

                // Response structure can vary: { "results": { "data": [...] } } or just { "data": [...] } or direct array
                $groups = $data['results']['data'] ?? $data['results'] ?? $data['data'] ?? $data ?? [];

                // Normalize if single object (rare but possible)
                if (isset($groups['JID']) || isset($groups['jid'])) {
                    $groups = [$groups];
                }

                return [
                    'success' => true,
                    'groups' => $groups,
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
     * Get GOWA server metadata (v9+).
     */
    public function getAppInfo(): array
    {
        try {
            $http = Http::timeout(10);

            if ($this->basicUser && $this->basicPass) {
                $http = $http->withBasicAuth($this->basicUser, $this->basicPass);
            }

            $response = $http->get("{$this->baseUrl}/app/info");

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['results'] ?? $data;

                return [
                    'success' => true,
                    'version' => $results['version'] ?? null,
                    'os' => $results['os'] ?? null,
                    'base_path' => $results['base_path'] ?? null,
                    'max_file_size' => $results['max_file_size'] ?? null,
                    'max_video_size' => $results['max_video_size'] ?? null,
                    'max_image_size' => $results['max_image_size'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
            ];

        } catch (\Throwable $e) {
            Log::warning('Failed to get GOWA app info', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get participants for a specific group via GOWA v9 /group/participants endpoint.
     */
    public function getGroupParticipants(string $groupId): array
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

            $response = $http->get("{$this->baseUrl}/group/participants", [
                'group_id' => $groupId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['results'] ?? [];

                return [
                    'success' => true,
                    'group_id' => $results['group_id'] ?? $groupId,
                    'name' => $results['name'] ?? null,
                    'participants' => $results['participants'] ?? [],
                    'count' => count($results['participants'] ?? []),
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
                'count' => 0,
            ];

        } catch (\Throwable $e) {
            Log::warning('Failed to get GOWA group participants', [
                'group_id' => $groupId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'count' => 0,
            ];
        }
    }
}
