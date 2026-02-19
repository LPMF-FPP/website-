<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsappCommandLog;
use App\Support\PhoneNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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

        $messagePayload = $this->extractMessagePayload($data);
        $fromRaw = $messagePayload['from'] ?? null;
        $messageRaw = $messagePayload['message'] ?? null;
        $providerMessageId = $this->extractProviderMessageId($data);

        if (! is_string($fromRaw) || ! is_string($messageRaw) || trim($fromRaw) === '' || trim($messageRaw) === '') {
            return response()->json(['status' => 'ignored', 'reason' => 'missing_data']);
        }

        $fromPhone = PhoneNormalizer::toE164($fromRaw);
        $fromJid = PhoneNormalizer::toJid($fromPhone);
        $sanitizedMessage = $this->sanitizeMessageForStorage($messageRaw);
        $sanitizedPayload = $this->sanitizePayloadForStorage($data);
        $messageFingerprint = $this->buildMessageFingerprint($fromJid, $sanitizedMessage);

        if ($providerMessageId !== null) {
            $alreadyProcessedByProviderId = WhatsappCommandLog::query()
                ->where('provider_message_id', $providerMessageId)
                ->exists();

            if ($alreadyProcessedByProviderId) {
                return response()->json(['status' => 'ok', 'dedupe' => 'provider_message_id']);
            }
        }

        $alreadyProcessedByFingerprint = WhatsappCommandLog::query()
            ->where('message_fingerprint', $messageFingerprint)
            ->exists();

        if ($alreadyProcessedByFingerprint) {
            return response()->json(['status' => 'ok', 'dedupe' => 'message_fingerprint']);
        }

        try {
            $log = WhatsappCommandLog::create([
                'from_jid' => $fromJid,
                'from_phone_e164' => $fromPhone,
                'message_text' => $sanitizedMessage,
                'provider_message_id' => $providerMessageId,
                'message_fingerprint' => $messageFingerprint,
                'params' => $sanitizedPayload,
                'response_status' => 'received',
            ]);

            ProcessWhatsAppWebhook::dispatch($log->id, $fromJid, $messageRaw);

            return response()->json(['status' => 'ok']);
        } catch (QueryException $e) {
            if ($this->isDuplicateKeyViolation($e)) {
                return response()->json(['status' => 'ok', 'dedupe' => 'race']);
            }

            Log::error('Webhook dispatch error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 500);
        } catch (\Throwable $e) {
            Log::error('Webhook dispatch error', [
                'error' => $e->getMessage(),
            ]);

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
        $trimmed = trim($message);
        if (! str_starts_with(strtolower($trimmed), '/qmh ')) {
            return $trimmed;
        }

        $parts = preg_split('/\s+/', $trimmed);
        if (! is_array($parts) || count($parts) < 4) {
            return $trimmed;
        }

        $parts[3] = '[REDACTED]';

        return implode(' ', $parts);
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

    private function isDuplicateKeyViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505';
    }
}
