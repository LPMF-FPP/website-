<?php

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappCommandLog;
use App\Services\WhatsApp\CommandDispatcher;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IncomingMessageController extends Controller
{
    public function __construct(
        private CommandDispatcher $dispatcher
    ) {
    }

    public function webhook(Request $request)
    {
        $data = $request->all();
        
        Log::info('WhatsApp incoming webhook', ['payload' => $data]);

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

        $phoneE164 = PhoneNormalizer::fromJid($from);
        
        try {
            $log = WhatsappCommandLog::create([
                'from_jid' => $from,
                'from_phone_e164' => $phoneE164,
                'message_text' => $message,
                'response_status' => 'processing',
            ]);

            \App\Jobs\ProcessWhatsAppWebhook::dispatch($log->id, $from, $message);

            return response()->json(['status' => 'queued']);

        } catch (\Throwable $e) {
            Log::error('Webhook dispatch error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }
}
