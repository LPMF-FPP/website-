<?php

namespace App\Jobs;

use App\Services\WhatsApp\OutboundMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $phone,
        public string $message,
        public ?int $batchId = null,
        public ?string $attachmentPath = null,
        public ?string $attachmentFilename = null,
        ?string $idempotencyKey = null
    ) {
        $this->idempotencyKey = $idempotencyKey;
    }

    public ?string $idempotencyKey;

    /**
     * Execute the job.
     */
    public function handle(OutboundMessageService $outboundMessageService): void
    {
        $phone = $this->normalizePhone($this->phone);
        $jid = $phone.'@s.whatsapp.net';

        Log::info("Job sending WA to {$phone}");

        try {
            if (is_string($this->attachmentPath) && trim($this->attachmentPath) !== '') {
                $outboundMessageService->sendFile($jid, $this->attachmentPath, $this->message, $this->attachmentFilename, [
                    'batch_id' => $this->batchId,
                    'recipient_name' => $phone,
                    'recipient_type' => 'individual',
                    'source_label' => 'Notifikasi laporan gabungan',
                    'idempotency_key' => $this->idempotencyKey,
                ]);
            } else {
                $outboundMessageService->sendText($jid, $this->message, [
                    'batch_id' => $this->batchId,
                    'recipient_name' => $phone,
                    'recipient_type' => 'individual',
                    'source_label' => 'Notifikasi laporan gabungan',
                    'idempotency_key' => $this->idempotencyKey,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SendWhatsAppMessage failed', [
                'phone' => $phone,
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);

            // OutboundMessageService owns the durable state when persistence succeeds.
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($phone !== '' && str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        return $phone;
    }
}
