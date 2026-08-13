<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappOutbox;
use App\Services\WhatsApp\OutboundMessageService;
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

    public function handle(OutboundMessageService $outboundMessageService): void
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
            $result = $outboundMessageService->sendText((string) $outbox->to_jid, (string) $outbox->message_text, [
                'recipient_name' => $outbox->to_phone_e164,
                'source_type' => WhatsappOutbox::class,
                'source_id' => $outbox->id,
                'source_label' => 'Notifikasi milestone',
                'idempotency_key' => 'whatsapp-outbox:'.$outbox->id,
            ]);
            $messageLog = isset($result['message_log_id'])
                ? WhatsAppMessageLog::query()->find((int) $result['message_log_id'])
                : null;

            if (($result['success'] ?? false) === true) {
                $outbox->status = 'sent';
                $outbox->provider_message_id = $result['message_id'] ?? null;
                $outbox->last_error = null;
                $outbox->attempts = $messageLog?->attempt_count ?? $outbox->attempts;
                $outbox->save();

                Log::info('WhatsApp message sent successfully', [
                    'outbox_id' => $this->outboxId,
                    'message_id' => $result['message_id'] ?? null,
                ]);

                return;
            }

            // The legacy outbox cannot represent `unknown`; the central envelope remains authoritative.
            $outbox->status = 'failed';
            $outbox->last_error = $result['error'] ?? 'Status pengiriman tidak dapat dipastikan.';
            $outbox->attempts = $messageLog?->attempt_count ?? $outbox->attempts;
            $outbox->save();

            Log::warning('WhatsApp message was not sent', [
                'outbox_id' => $this->outboxId,
                'state' => $result['state'] ?? null,
                'attempts' => $outbox->attempts,
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
