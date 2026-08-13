<?php

namespace App\Jobs;

use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Models\WhatsAppMessageBatch;
use App\Services\WhatsApp\NotificationService;
use App\Services\WhatsApp\OutboundMessageService;
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
        public int $broadcastId,
        public bool $mentionAll = false
    ) {}

    public function handle(OutboundMessageService $outboundMessageService, NotificationService $notificationService): void
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

        // Create Batch for Hub Logs
        $batch = WhatsAppMessageBatch::create([
            'type' => 'broadcast',
            'source_type' => WhatsappBroadcast::class,
            'source_id' => $broadcast->id,
            'title' => $broadcast->title,
            'message_preview' => substr($broadcast->message, 0, 1000),
            'total_recipients' => $broadcast->total_recipients,
            'mention_all' => $this->mentionAll,
            'started_at' => now(),
            'created_by' => $broadcast->created_by,
        ]);

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

            $errorMsg = null;
            $msgId = null;
            $jid = '';

            try {
                $jid = $notificationService->formatJID($recipient->phone);

                // Mentions (only useful if sending to group JID)
                $mentions = [];
                if ($this->mentionAll) {
                    $mentions[] = '@everyone';
                }

                $result = $outboundMessageService->sendText($jid, $broadcast->message, [
                    'batch_id' => $batch->id,
                    'recipient_name' => $recipient->name,
                    'recipient_type' => $recipient->recipient_type,
                    'source_type' => WhatsappBroadcast::class,
                    'source_id' => $broadcast->id,
                    'source_label' => 'Broadcast WhatsApp',
                    'mentions' => $mentions,
                    'idempotency_key' => 'whatsapp-broadcast:'.$broadcast->id.':recipient:'.$recipient->id,
                ]);

                if ($result['success']) {
                    $msgId = $result['message_id'] ?? '';

                    $recipient->markAsSent($msgId);
                    $broadcast->incrementSentCount();
                } else {
                    $errorMsg = $result['error'] ?? 'Unknown error';

                    $recipient->markAsFailed($errorMsg);
                    $broadcast->incrementFailedCount();
                    $errors[] = "{$recipient->name}: {$errorMsg}";
                }

                // Rate limiting
                usleep(1000000); // 1s

            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();

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

        $outboundMessageService->syncBatchStats($batch->id);

        Log::info("SendBroadcastJob: Broadcast {$this->broadcastId} completed", [
            'sent' => $broadcast->sent_count,
            'failed' => $broadcast->failed_count,
        ]);
    }
}
