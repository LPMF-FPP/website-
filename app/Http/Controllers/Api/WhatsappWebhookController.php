<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCommandLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Verify HMAC Signature
        $signature = $request->header('X-Hub-Signature-256');
        $secret = Config::get('services.whatsapp.webhook_secret', env('WHATSAPP_WEBHOOK_SECRET'));

        if ($secret) {
            if (!$signature) {
                return response()->json(['status' => 'error', 'message' => 'Missing signature'], 403);
            }

            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

            if (!hash_equals($expected, $signature)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
            }
        }

        // 2. Parse Payload
        // Handle both application/json (auto-parsed) and raw JSON string
        $data = $request->all();
        if (empty($data)) {
            $rawContent = $request->getContent();
            $data = json_decode($rawContent, true) ?? [];
        }
        
        Log::info('WhatsApp incoming webhook', ['payload' => $data]);

        // Support both flat structure (tests) and nested structure (production webhooks)
        $from = $data['from'] ?? $data['sender'] ?? null;
        $message = $data['body'] ?? $data['text'] ?? $data['message'] ?? null;

        if (!$from || !$message) {
            if (isset($data['payload']) && is_array($data['payload'])) {
                $msg = $data['payload'];
                $from = $from ?? ($msg['from'] ?? $msg['sender'] ?? null);
                $message = $message ?? ($msg['body'] ?? $msg['text'] ?? $msg['message'] ?? null);
            } elseif (isset($data['data']['message'])) {
                $msg = $data['data']['message'];
                $from = $from ?? ($msg['from'] ?? $msg['sender'] ?? null);
                $message = $message ?? ($msg['body'] ?? $msg['text'] ?? $msg['message'] ?? null);
            } elseif (isset($data['message']) && is_array($data['message'])) {
                $msg = $data['message'];
                $from = $from ?? ($msg['from'] ?? $msg['sender'] ?? null);
                $message = $message ?? ($msg['body'] ?? $msg['text'] ?? $msg['message'] ?? null);
            }
        }

        if (!$from || !$message) {
            return response()->json(['status' => 'ignored', 'reason' => 'missing_data'], 200);
        }

        // 3. Log to Database
        try {
            $log = WhatsappCommandLog::create([
                'from_jid' => $from,
                'from_phone_e164' => $from,
                'message_text' => $message,
                'params' => $data, // FIX: Model casts to array, no json_encode needed
                'response_status' => 'received', 
            ]);

            \App\Jobs\ProcessWhatsAppWebhook::dispatch($log->id, $from, $message);

            return response()->json(['status' => 'ok']);

        } catch (\Throwable $e) {
            Log::error('Webhook dispatch error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }
}
