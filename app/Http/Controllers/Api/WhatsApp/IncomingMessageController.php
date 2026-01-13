<?php

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCommandLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class IncomingMessageController extends Controller
{
    public function webhook(Request $request)
    {
        // 1. Verify Signature
        $signature = $request->header('X-Hub-Signature-256');
        $secret = Config::get('services.whatsapp.webhook_secret', env('WHATSAPP_WEBHOOK_SECRET'));

        // If secret is configured, enforce signature verification
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
        $data = $request->all();
        
        Log::info('WhatsApp incoming webhook', ['payload' => $data]);

        // Standardize payload extraction (support both old and new structures if needed)
        if (isset($data['payload']) && is_array($data['payload'])) {
            $msg = $data['payload'];
            $from = $msg['from'] ?? $msg['sender'] ?? null;
            $message = $msg['body'] ?? $msg['text'] ?? $msg['message'] ?? null;
        } elseif (isset($data['data']['message'])) {
            $msg = $data['data']['message'];
            $from = $msg['from'] ?? $msg['sender'] ?? null;
            $message = $msg['body'] ?? $msg['text'] ?? $msg['message'] ?? null;
        } elseif (isset($data['message']) && is_array($data['message'])) {
            $msg = $data['message'];
            $from = $msg['from'] ?? $msg['sender'] ?? null;
            $message = $msg['body'] ?? $msg['text'] ?? $msg['message'] ?? null;
        } else {
            $from = $data['from'] ?? $data['sender'] ?? null;
            $message = $data['body'] ?? $data['text'] ?? $data['message'] ?? null;
        }

        if (! $from || ! $message) {
            return response()->json(['status' => 'ignored', 'reason' => 'missing_data']);
        }

        // 3. Log to Database
        try {
            $log = WhatsappCommandLog::create([
                'from_jid' => $from,
                'from_phone_e164' => $from, // Normalize if needed
                'message_text' => $message,
                'params' => json_encode($data), // Store full payload
                'response_status' => 'received', 
            ]);

            // Dispatch job if needed (keeping existing logic)
            // \App\Jobs\ProcessWhatsAppWebhook::dispatch($log->id, $from, $message);

            return response()->json(['status' => 'ok']);

        } catch (\Throwable $e) {
            Log::error('Webhook dispatch error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }
}
