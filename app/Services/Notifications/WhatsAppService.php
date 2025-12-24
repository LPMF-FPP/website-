<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * WhatsApp notification service stub.
 * Replace with actual provider implementation when available.
 */
class WhatsAppService
{
    /**
     * Send a WhatsApp message.
     *
     * @param  string  $target  Phone number or WhatsApp ID
     * @param  string  $message  Message content
     * @return array{status: string, message: string, delivered_at?: string}
     */
    public function send(string $target, string $message): array
    {
        // Validate phone number format (basic validation)
        if (!$this->isValidPhoneNumber($target)) {
            return [
                'status' => 'error',
                'message' => 'Invalid phone number format. Expected format: +62xxx or 08xxx',
            ];
        }

        // STUB MODE: Replace with actual WhatsApp API integration in production
        // Options: Twilio, MessageBird, WhatsApp Business API, or Fonnte
        // For now, log the message and return success for testing
        Log::info('WhatsApp message stub', [
            'target' => $target,
            'message' => substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Simulate successful delivery
        return [
            'status' => 'delivered',
            'message' => 'WhatsApp notification logged (stub mode - check logs for actual integration)',
            'delivered_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Validate phone number format.
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        // Remove spaces and dashes
        $phone = preg_replace('/[\s\-]/', '', $phone);

        // Check Indonesian phone formats: +62xxx or 08xxx (at least 10 digits)
        return preg_match('/^(\+62|62|0)[0-9]{9,13}$/', $phone) === 1;
    }

    /**
     * Check if WhatsApp service is configured.
     * Returns true if WhatsApp API credentials are set in environment.
     */
    public function isConfigured(): bool
    {
        // Check if WhatsApp API credentials exist in config
        // Adjust these config keys based on your chosen provider
        return !empty(config('services.whatsapp.api_key')) 
            && !empty(config('services.whatsapp.api_url'));
    }

    /**
     * Get service status information.
     */
    public function getStatus(): array
    {
        return [
            'provider' => 'stub',
            'configured' => $this->isConfigured(),
            'mode' => 'development',
            'note' => 'Using stub implementation - messages are logged but not sent',
        ];
    }
}
