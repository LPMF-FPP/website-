<?php

namespace App\Jobs;

use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600; // 1 hour for large broadcasts

    public function __construct(
        public int $broadcastId
    ) {}

    public function handle(GowaClient $client, NotificationService $notificationService): void
    {
        $broadcast = WhatsappBroadcast::find($this->broadcastId);

        if (! $broadcast) {
            Log::warning("SendBroadcastJob: Broadcast {$this->broadcastId} not found");

            return;
        }

        if ($broadcast->status === WhatsappBroadcast::STATUS_CANCELLED) {
            Log::info("SendBroadcastJob: Broadcast {$this->broadcastId} was cancelled");

            return;
        }

        $broadcast->markAsSending();

        $recipients = $broadcast->recipients()
            ->where('status', WhatsappBroadcastRecipient::STATUS_PENDING)
            ->get();

        $errors = [];

        foreach ($recipients as $recipient) {
            // Check if broadcast was cancelled during sending
            $broadcast->refresh();
            if ($broadcast->status === WhatsappBroadcast::STATUS_CANCELLED) {
                Log::info("SendBroadcastJob: Broadcast {$this->broadcastId} cancelled during sending");
                break;
            }

            try {
                $jid = $notificationService->formatJID($recipient->phone);
                $result = $client->sendMessage($jid, $broadcast->message);

                if ($result['success']) {
                    $recipient->markAsSent($result['message_id'] ?? '');
                    $broadcast->incrementSentCount();
                } else {
                    $errorMsg = $result['error'] ?? 'Unknown error';
                    $recipient->markAsFailed($errorMsg);
                    $broadcast->incrementFailedCount();
                    $errors[] = "{$recipient->name}: {$errorMsg}";
                }

                // Rate limiting: wait 1 second between messages to avoid blocking
                usleep(1000000);

            } catch (\Exception $e) {
                $recipient->markAsFailed($e->getMessage());
                $broadcast->incrementFailedCount();
                $errors[] = "{$recipient->name}: {$e->getMessage()}";
                Log::error("SendBroadcastJob: Error sending to {$recipient->phone}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update broadcast status
        $broadcast->refresh();
        if ($broadcast->status !== WhatsappBroadcast::STATUS_CANCELLED) {
            $broadcast->update([
                'status' => WhatsappBroadcast::STATUS_SENT,
                'completed_at' => now(),
                'error_log' => count($errors) > 0 ? implode("\n", array_slice($errors, 0, 50)) : null,
            ]);
        }

        Log::info("SendBroadcastJob: Broadcast {$this->broadcastId} completed", [
            'sent' => $broadcast->sent_count,
            'failed' => $broadcast->failed_count,
        ]);
    }
}
