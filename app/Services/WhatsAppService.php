<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.base_url', '');
        $this->apiKey = config('services.whatsapp.api_key');
    }

    /**
     * Send WhatsApp message via Narawa API
     *
     * @param  string  $to  Phone number with country code (e.g., 628123456789)
     * @param  string  $message  Message content
     * @return bool
     */
    public function send(string $to, string $message): bool
    {
        if (empty($this->baseUrl)) {
            Log::warning('[WhatsApp] Base URL not configured');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->when($this->apiKey, fn ($http) => $http->withHeaders(['Authorization' => 'Bearer '.$this->apiKey]))
                ->post("{$this->baseUrl}/send", [
                    'to' => $this->formatPhoneNumber($to),
                    'message' => $message,
                ]);

            if ($response->successful()) {
                Log::info('[WhatsApp] Message sent successfully', [
                    'to' => $to,
                    'response' => $response->json(),
                ]);

                return true;
            }

            Log::error('[WhatsApp] Failed to send message', [
                'to' => $to,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('[WhatsApp] Exception while sending message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Format phone number to international format (remove leading 0, add country code if needed)
     *
     * @param  string  $phone
     * @return string
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        if (!str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        return $phone;
    }

    /**
     * Check if WhatsApp service is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl);
    }
}
