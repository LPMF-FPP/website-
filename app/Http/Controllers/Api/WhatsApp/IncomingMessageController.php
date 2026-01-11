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
        try {
            // GOWA webhook payload structure
            $data = $request->all();

            Log::info('WhatsApp incoming webhook', ['payload' => $data]);

            // Extract message info from GOWA webhook format
            // GOWA can send multiple formats, handle them all:
            
            // Format 1: Nested in data.message (most common)
            if (isset($data['data']['message'])) {
                $msg = $data['data']['message'];
                $from = $msg['from'] ?? $msg['sender'] ?? null;
                $message = $msg['body'] ?? $msg['text'] ?? $msg['message'] ?? null;
            }
            // Format 2: Nested in message object
            elseif (isset($data['message']) && is_array($data['message'])) {
                $msg = $data['message'];
                $from = $msg['from'] ?? $msg['sender'] ?? null;
                $message = $msg['body'] ?? $msg['text'] ?? $msg['message'] ?? null;
            }
            // Format 3: Direct fields in root
            else {
                $from = $data['from'] ?? $data['sender'] ?? null;
                $message = $data['body'] ?? $data['text'] ?? $data['message'] ?? null;
            }

            if (! $from || ! $message) {
                Log::warning('WhatsApp webhook missing data', [
                    'from' => $from,
                    'message' => $message,
                    'payload' => $data
                ]);
                return response()->json(['status' => 'ignored', 'reason' => 'missing_data']);
            }

            // Normalize phone number
            $phoneE164 = PhoneNormalizer::fromJid($from);

            // Log incoming message
            $log = WhatsappCommandLog::create([
                'from_jid' => $from,
                'from_phone_e164' => $phoneE164,
                'message_text' => $message,
                'response_status' => 'processing',
            ]);

            // Dispatch command
            $result = $this->dispatcher->handle($from, $message);

            // Update log
            $log->update([
                'command' => $result['command'] ?? null,
                'params' => $result['params'] ?? null,
                'response_status' => $result['status'],
                'response_text' => $result['response'],
            ]);

            return response()->json([
                'status' => 'processed',
                'command' => $result['command'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
