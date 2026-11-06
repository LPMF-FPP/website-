<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class PrivateMessageNotification
{
    public function __construct(private readonly OutboundMessageService $outbound) {}

    /** @param array<string, mixed> $data */
    public function queue(array $data, string $safeMessage, ?string $providerMessageId, ?string $messageFingerprint): void
    {
        $group = config('services.whatsapp.incoming_group');
        $payload = $data['payload'] ?? null;
        if (! is_string($group) || $group === '') {
            return;
        }
        if (! preg_match('/^\d+(@g\.us)?$/', $group)) {
            Log::warning('WhatsApp incoming notification unavailable', ['reason' => 'invalid_group_configuration']);

            return;
        }
        if (($data['event'] ?? null) !== 'message' || ! is_array($payload)
            || ($payload['is_from_me'] ?? null) !== false) {
            return;
        }

        $sender = $payload['from'] ?? null;
        $chat = $payload['chat_id'] ?? null;
        $id = $providerMessageId !== null ? 'provider:'.$providerMessageId : ($messageFingerprint !== null ? 'fingerprint:'.$messageFingerprint : null);
        $phoneMatches = [];
        $hasPhone = is_string($sender) && preg_match('/^(\d{7,15})@s\.whatsapp\.net$/', $sender, $phoneMatches);
        $hasLid = is_string($sender) && preg_match('/^\d+@lid$/', $sender) && $sender === $chat;
        if ((! $hasPhone && ! $hasLid)
            || ! is_string($chat) || (! str_ends_with($chat, '@s.whatsapp.net') && ! str_ends_with($chat, '@lid'))
            || ! is_string($id) || trim($id) === '') {
            Log::warning('WhatsApp incoming notification unavailable', ['reason' => 'missing_verified_identity_or_message_id']);

            return;
        }

        $number = $hasPhone ? $phoneMatches[1] : null;
        $name = $payload['sender_display_name'] ?? $payload['from_name'] ?? null;
        $lines = ['📩 *Pesan WhatsApp Masuk*'];
        if (is_string($name) && trim($name) !== '') {
            $lines[] = '*Dari:* '.str_replace(["\r", "\n"], ' ', trim($name));
        }
        $lines[] = '*Nomor:* '.($number ?? 'Tidak tersedia');
        try {
            if (is_string($payload['timestamp'] ?? null)) {
                $lines[] = '*Waktu:* '.CarbonImmutable::parse($payload['timestamp'])->setTimezone(config('app.timezone'))->format('d-m-Y H:i');
            }
        } catch (\Throwable) {
        }
        $lines[] = '';
        $lines[] = '*Pesan:*';
        $lines[] = $safeMessage;
        if ($number !== null) {
            $lines[] = '';
            $lines[] = 'Balas langsung: https://wa.me/'.$number;
        }

        $this->outbound->queueText(
            str_ends_with($group, '@g.us') ? $group : $group.'@g.us',
            implode("\n", $lines),
            [
                'source_type' => 'incoming_private_message',
                'idempotency_key' => 'incoming:'.hash('sha256', (string) ($data['device_id'] ?? '').'|'.$id),
            ]
        );
    }
}
