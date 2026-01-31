<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageLog;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $phone,
        public string $message,
        public ?int $batchId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GowaClient $client): void
    {
        // Normalize phone (strip + or 0, ensure 62 prefix if needed)
        // This is a basic normalization, can be improved
        $phone = $this->phone;
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }
        $phone = preg_replace('/[^0-9]/', '', $phone);

        $jid = $phone.'@s.whatsapp.net';

        Log::info("Job sending WA to {$this->phone}");

        $result = $client->sendMessage($jid, $this->message);

        // Log result
        if ($this->batchId) {
            WhatsAppMessageLog::create([
                'batch_id' => $this->batchId,
                'recipient' => $this->phone,
                'status' => $result['success'] ? 'sent' : 'failed',
                'message_id' => $result['message_id'] ?? null,
                'error_message' => $result['error'] ?? null,
            ]);
        }
    }
}
