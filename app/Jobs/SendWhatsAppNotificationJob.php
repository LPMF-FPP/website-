<?php

namespace App\Jobs;

use App\Models\WhatsappOutbox;
use App\Services\WhatsApp\MilestoneNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $outboxId
    ) {}

    public function handle(MilestoneNotificationService $milestoneNotificationService): void
    {
        $outbox = WhatsappOutbox::find($this->outboxId);

        if (! $outbox) {
            Log::warning('WhatsApp outbox record not found', ['id' => $this->outboxId]);

            return;
        }

        if ($outbox->status === 'sent') {
            Log::info('WhatsApp message already sent', ['outbox_id' => $this->outboxId]);

            return;
        }

        try {
            $messageLog = $milestoneNotificationService->queueExisting($outbox);

            Log::info('WhatsApp outbox persisted for delivery', [
                'outbox_id' => $this->outboxId,
                'message_log_id' => $messageLog->id,
                'state' => $messageLog->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp message envelope processing failed', [
                'outbox_id' => $this->outboxId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $outbox = WhatsappOutbox::find($this->outboxId);

        if ($outbox) {
            $outbox->status = 'failed';
            $outbox->last_error = 'Worker gagal sebelum status pengiriman dapat dipastikan.';
            $outbox->save();
        }

        Log::error('WhatsApp job permanently failed', [
            'outbox_id' => $this->outboxId,
            'error' => $exception->getMessage(),
        ]);
    }
}
