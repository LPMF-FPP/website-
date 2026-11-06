<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsappCommandLog;
use App\Services\WhatsApp\PrivateMessageNotification;
use App\Support\PhoneNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // 1. Fail-closed signature policy
        $signature = $request->header('X-Hub-Signature-256');
        $secret = trim((string) Config::get('services.whatsapp.webhook_secret', env('WHATSAPP_WEBHOOK_SECRET')));

        if ($secret === '') {
            Log::error('WhatsApp webhook secret is not configured. Request denied.');

            return response()->json([
                'status' => 'error',
                'message' => 'Webhook secret not configured',
            ], 503);
        }

        if (! $signature) {
            return response()->json(['status' => 'error', 'message' => 'Missing signature'], 403);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        // 2. Parse payload
        $data = $request->all();
        if (empty($data)) {
            $rawContent = $request->getContent();
            $data = json_decode($rawContent, true) ?? [];
        }

        $commandMessage = null;
        if (isset($data['event'])) {
            $incoming = $data['payload'] ?? null;
            $chat = is_array($incoming) ? ($incoming['chat_id'] ?? null) : null;
            $sender = is_array($incoming) ? ($incoming['from'] ?? null) : null;
            if (is_string($sender) && preg_match('/^\d+@lid$/', $sender)
                && is_string($chat) && preg_match('/^\d{7,15}@s\.whatsapp\.net$/', $chat)
                && ($incoming['chat_lid'] ?? null) === $sender) {
                $data['payload']['from'] = $chat;
                $sender = $chat;
            }
            $unresolvedLid = is_string($sender) && preg_match('/^\d+@lid$/', $sender) && $chat === $sender;
            if ($data['event'] !== 'message' || ! is_array($incoming)
                || ($incoming['is_from_me'] ?? null) !== false
                || ! is_string($chat) || (! str_ends_with($chat, '@s.whatsapp.net') && ! str_ends_with($chat, '@lid'))
                || ! is_string($sender) || (! preg_match('/^\d{7,15}@s\.whatsapp\.net$/', $sender) && ! $unresolvedLid)
                || (str_ends_with($chat, '@s.whatsapp.net') && $chat !== $sender)) {
                if (($data['event'] ?? null) === 'message') {
                    $reason = match (true) {
                        ! is_array($incoming) => 'missing_payload',
                        ($incoming['is_from_me'] ?? null) === true => 'self_message',
                        ($incoming['is_from_me'] ?? null) !== false => 'missing_sender_direction',
                        is_string($chat) && str_ends_with($chat, '@g.us') => 'group_message',
                        ! is_string($chat) || (! str_ends_with($chat, '@s.whatsapp.net') && ! str_ends_with($chat, '@lid')) => 'unsupported_chat',
                        ! is_string($sender) || ! preg_match('/^\d{7,15}@s\.whatsapp\.net$/', $sender) => 'unresolved_sender',
                        default => 'sender_chat_mismatch',
                    };
                    Log::info('WhatsApp webhook message ignored', ['reason' => $reason]);
                }

                return response()->json(['status' => 'ignored', 'reason' => 'unsupported_event']);
            }

            foreach (['image' => 'Foto', 'document' => 'Dokumen', 'video' => 'Video', 'audio' => 'Audio', 'sticker' => 'Stiker'] as $field => $label) {
                if (isset($incoming[$field])) {
                    $caption = $incoming['body'] ?? '';
                    if (! is_string($caption) || trim($caption) === '') {
                        $caption = $incoming['caption'] ?? '';
                    }
                    $commandMessage = is_string($caption) ? $caption : '';
                    $safeCaption = is_string($caption) ? $this->sanitizeMessageForStorage($caption) : '';
                    $data['payload']['body'] = 'Lampiran: '.$label.($safeCaption !== '' ? "\n".$safeCaption : '');
                    if (isset($data['payload']['caption'])) {
                        $data['payload']['caption'] = $safeCaption;
                    }
                    break;
                }
            }
        }

        $messagePayload = $this->extractMessagePayload($data);
        $fromRaw = $messagePayload['from'] ?? null;
        $messageRaw = $messagePayload['message'] ?? null;
        $providerMessageId = $this->extractProviderMessageId($data);

        if (! is_string($fromRaw) || ! is_string($messageRaw) || trim($fromRaw) === '' || trim($messageRaw) === '') {
            Log::info('WhatsApp webhook message ignored', ['reason' => 'missing_data']);

            return response()->json(['status' => 'ignored', 'reason' => 'missing_data']);
        }

        if ($unresolvedLid ?? false) {
            $safeMessage = $this->sanitizeMessageForStorage($messageRaw);
            $fingerprint = $providerMessageId === null ? $this->buildEventFingerprint($data, $fromRaw, $safeMessage) : null;
            try {
                app(PrivateMessageNotification::class)->queue($data, $safeMessage, $providerMessageId, $fingerprint);
            } catch (\Throwable $e) {
                Log::error('Webhook dispatch error', ['exception' => $e::class]);

                return response()->json(['status' => 'error'], 500);
            }

            Log::info('WhatsApp private message has no verified phone', ['reason' => 'unresolved_sender']);

            return response()->json(['status' => 'ok']);
        }

        $fromPhone = PhoneNormalizer::toE164($fromRaw);
        $fromJid = PhoneNormalizer::toJid($fromPhone);
        $sanitizedMessage = $this->sanitizeMessageForStorage($messageRaw);
        $sanitizedPayload = $this->sanitizePayloadForStorage($data);
        if (isset($data['event']) && is_array($sanitizedPayload['payload'] ?? null)) {
            foreach (['image', 'document', 'video', 'audio', 'sticker'] as $field) {
                if (isset($sanitizedPayload['payload'][$field])) {
                    $sanitizedPayload['payload'][$field] = true;
                }
            }
        }
        $messageFingerprint = $providerMessageId === null
            ? (isset($data['event'])
                ? $this->buildEventFingerprint($data, $fromJid, $sanitizedMessage)
                : $this->buildMessageFingerprint($fromJid, $sanitizedMessage))
            : null;

        if ($providerMessageId !== null) {
            $alreadyProcessedByProviderId = WhatsappCommandLog::query()
                ->where('provider_message_id', $providerMessageId)
                ->exists();

            if ($alreadyProcessedByProviderId) {
                Log::info('WhatsApp webhook message deduplicated', ['reason' => 'provider_message_id']);

                return response()->json(['status' => 'ok', 'dedupe' => 'provider_message_id']);
            }
        }

        if ($messageFingerprint !== null && WhatsappCommandLog::query()
            ->where('message_fingerprint', $messageFingerprint)
            ->exists()) {
            Log::info('WhatsApp webhook message deduplicated', ['reason' => 'message_fingerprint']);

            return response()->json(['status' => 'ok', 'dedupe' => 'message_fingerprint']);
        }

        try {
            DB::transaction(function () use ($fromJid, $fromPhone, $sanitizedMessage, $providerMessageId, $messageFingerprint, $sanitizedPayload, $data, $messageRaw, $commandMessage): void {
                $log = WhatsappCommandLog::create([
                    'from_jid' => $fromJid,
                    'from_phone_e164' => $fromPhone,
                    'message_text' => $sanitizedMessage,
                    'provider_message_id' => $providerMessageId,
                    'message_fingerprint' => $messageFingerprint,
                    'params' => $sanitizedPayload,
                    'response_status' => 'received',
                ]);

                app(PrivateMessageNotification::class)->queue($data, $sanitizedMessage, $providerMessageId, $messageFingerprint);
                ProcessWhatsAppWebhook::dispatch($log->id, $fromJid, $commandMessage ?? $messageRaw)->afterCommit();
            });

            return response()->json(['status' => 'ok']);
        } catch (QueryException $e) {
            if ($this->isDuplicateKeyViolation($e) && (
                ($providerMessageId !== null && WhatsappCommandLog::query()->where('provider_message_id', $providerMessageId)->exists())
                || ($messageFingerprint !== null && WhatsappCommandLog::query()->where('message_fingerprint', $messageFingerprint)->exists())
            )) {
                return response()->json(['status' => 'ok', 'dedupe' => 'race']);
            }

            Log::error('Webhook dispatch error', ['exception' => $e::class]);

            return response()->json(['status' => 'error'], 500);
        } catch (\Throwable $e) {
            Log::error('Webhook dispatch error', ['exception' => $e::class]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{from: string|null, message: string|null}
     */
    private function extractMessagePayload(array $payload): array
    {
        $flatFrom = $payload['from'] ?? $payload['sender'] ?? null;
        $flatMessage = $payload['body'] ?? $payload['text'] ?? $payload['message'] ?? null;

        if (is_string($flatFrom) && is_string($flatMessage)) {
            return [
                'from' => $flatFrom,
                'message' => $flatMessage,
            ];
        }

        if (isset($payload['payload']) && is_array($payload['payload'])) {
            $msg = $payload['payload'];

            return [
                'from' => is_string($msg['from'] ?? null) ? $msg['from'] : (is_string($msg['sender'] ?? null) ? $msg['sender'] : null),
                'message' => is_string($msg['body'] ?? null) ? $msg['body'] : (is_string($msg['text'] ?? null) ? $msg['text'] : (is_string($msg['message'] ?? null) ? $msg['message'] : null)),
            ];
        }

        if (isset($payload['data']['message']) && is_array($payload['data']['message'])) {
            $msg = $payload['data']['message'];

            return [
                'from' => is_string($msg['from'] ?? null) ? $msg['from'] : (is_string($msg['sender'] ?? null) ? $msg['sender'] : null),
                'message' => is_string($msg['body'] ?? null) ? $msg['body'] : (is_string($msg['text'] ?? null) ? $msg['text'] : (is_string($msg['message'] ?? null) ? $msg['message'] : null)),
            ];
        }

        if (isset($payload['message']) && is_array($payload['message'])) {
            $msg = $payload['message'];

            return [
                'from' => is_string($msg['from'] ?? null) ? $msg['from'] : (is_string($msg['sender'] ?? null) ? $msg['sender'] : null),
                'message' => is_string($msg['body'] ?? null) ? $msg['body'] : (is_string($msg['text'] ?? null) ? $msg['text'] : (is_string($msg['message'] ?? null) ? $msg['message'] : null)),
            ];
        }

        return [
            'from' => null,
            'message' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractProviderMessageId(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'message_id'),
            data_get($payload, 'id'),
            data_get($payload, 'payload.message_id'),
            data_get($payload, 'payload.id'),
            data_get($payload, 'data.message.id'),
            data_get($payload, 'message.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }

            if (is_numeric($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function sanitizeMessageForStorage(string $message): string
    {
        return preg_replace('/(\/qmh\s+\S+\s+\S+\s+)\S+/i', '$1[REDACTED]', trim($message)) ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayloadForStorage(array $payload): array
    {
        $sanitized = $payload;

        $flatBody = $sanitized['body'] ?? $sanitized['text'] ?? $sanitized['message'] ?? null;
        if (is_string($flatBody)) {
            $redacted = $this->sanitizeMessageForStorage($flatBody);
            if (isset($sanitized['body']) && is_string($sanitized['body'])) {
                $sanitized['body'] = $redacted;
            }
            if (isset($sanitized['text']) && is_string($sanitized['text'])) {
                $sanitized['text'] = $redacted;
            }
            if (isset($sanitized['message']) && is_string($sanitized['message'])) {
                $sanitized['message'] = $redacted;
            }
        }

        foreach (['payload', 'data.message', 'message'] as $path) {
            $nested = data_get($sanitized, $path);
            if (! is_array($nested)) {
                continue;
            }

            foreach (['body', 'text', 'message'] as $field) {
                if (isset($nested[$field]) && is_string($nested[$field])) {
                    $nested[$field] = $this->sanitizeMessageForStorage($nested[$field]);
                }
            }

            data_set($sanitized, $path, $nested);
        }

        return $sanitized;
    }

    private function buildMessageFingerprint(string $fromJid, string $message): string
    {
        $canonicalSender = PhoneNormalizer::toCanonicalDigits($fromJid);
        $canonicalMessage = strtolower(trim((string) preg_replace('/\s+/', ' ', $message)));
        $timeBucket = (string) intdiv(now()->timestamp, 120);

        return hash('sha256', implode('|', [$canonicalSender, $canonicalMessage, $timeBucket]));
    }

    /** @param array<string, mixed> $data */
    private function buildEventFingerprint(array $data, string $fromJid, string $message): string
    {
        return hash('sha256', json_encode([
            'device' => data_get($data, 'device_id'),
            'sender' => $fromJid,
            'chat' => data_get($data, 'payload.chat_id'),
            'timestamp' => data_get($data, 'payload.timestamp'),
            'message' => $message,
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function isDuplicateKeyViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505';
    }
}
