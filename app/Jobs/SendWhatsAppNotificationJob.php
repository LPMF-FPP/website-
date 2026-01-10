<?php

namespace App\Jobs;

use App\Models\WhatsappOutbox;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 60;
    public int $timeout = 120;

    public function __construct(
        public int $outboxId
    ) {
    }

    public function handle(GowaClient $client): void
    {
        $outbox = WhatsappOutbox::find($this->outboxId);

        if (!$outbox) {
            Log::warning('WhatsApp outbox record not found', ['id' => $this->outboxId]);
            return;
        }

        if ($outbox->status === 'sent') {
            Log::info('WhatsApp message already sent', ['outbox_id' => $this->outboxId]);
            return;
        }

        $outbox->increment('attempts');

        try {
            $result = $client->sendMessage($outbox->to_jid, $outbox->message_text);

            if ($result['success']) {
                $outbox->status = 'sent';
                $outbox->provider_message_id = $result['message_id'];
                $outbox->last_error = null;
                $outbox->save();

                Log::info('WhatsApp message sent successfully', [
                    'outbox_id' => $this->outboxId,
                    'message_id' => $result['message_id'],
                ]);
            } else {
                throw new \RuntimeException($result['error'] ?? 'Unknown error');
            }

        } catch (\Throwable $e) {
            $outbox->status = 'failed';
            $outbox->last_error = $e->getMessage();
            $outbox->save();

            Log::error('WhatsApp message send failed', [
                'outbox_id' => $this->outboxId,
                'attempt' => $outbox->attempts,
                'error' => $e->getMessage(),
            ]);

            if ($outbox->attempts < $this->tries) {
                throw $e;
            }

            Log::error('WhatsApp message failed after max retries', [
                'outbox_id' => $this->outboxId,
                'attempts' => $outbox->attempts,
            ]);
        }
    }

    public function backoff(): array
    {
        return [60, 120, 240, 480, 960];
    }

    public function failed(\Throwable $exception): void
    {
        $outbox = WhatsappOutbox::find($this->outboxId);

        if ($outbox) {
            $outbox->status = 'failed';
            $outbox->last_error = $exception->getMessage();
            $outbox->save();
        }

        Log::error('WhatsApp job permanently failed', [
            'outbox_id' => $this->outboxId,
            'error' => $exception->getMessage(),
        ]);
    }
}
