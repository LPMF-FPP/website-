<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageBatch;
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

    public int $tries = 1;

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
        $phone = $this->normalizePhone($this->phone);
        $jid = $phone.'@s.whatsapp.net';

        Log::info("Job sending WA to {$phone}");

        $log = null;
        if ($this->batchId) {
            $log = WhatsAppMessageLog::query()
                ->where('batch_id', $this->batchId)
                ->where('recipient_jid', $jid)
                ->latest('id')
                ->first();

            if ($log?->status === 'sent') {
                $this->syncBatchStats($this->batchId);

                return;
            }

            if (! $log) {
                $log = WhatsAppMessageLog::create([
                    'batch_id' => $this->batchId,
                    'recipient_jid' => $jid,
                    'recipient_name' => $phone,
                    'recipient_type' => 'individual',
                    'status' => 'pending',
                ]);
            }

            $claimed = WhatsAppMessageLog::query()
                ->whereKey($log->id)
                ->whereIn('status', ['pending', 'failed'])
                ->update([
                    'status' => 'processing',
                    'error_message' => null,
                ]);

            if ($claimed === 0) {
                return;
            }

            $log->refresh();
        }

        try {
            $result = $client->sendMessage($jid, $this->message);
            $isSent = (bool) ($result['success'] ?? false);

            if ($log) {
                $log->update([
                    'status' => $isSent ? 'sent' : 'failed',
                    'message_id' => $result['message_id'] ?? null,
                    'error_message' => $isSent ? null : ($result['error'] ?? 'Unknown error'),
                    'sent_at' => $isSent ? now() : null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SendWhatsAppMessage failed', [
                'phone' => $phone,
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);

            if ($log) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        if ($this->batchId) {
            $this->syncBatchStats($this->batchId);
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

    private function syncBatchStats(int $batchId): void
    {
        $batch = WhatsAppMessageBatch::find($batchId);
        if (! $batch) {
            return;
        }

        $sentCount = WhatsAppMessageLog::query()
            ->where('batch_id', $batchId)
            ->where('status', 'sent')
            ->count();
        $failedCount = WhatsAppMessageLog::query()
            ->where('batch_id', $batchId)
            ->where('status', 'failed')
            ->count();

        $batch->update([
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'completed_at' => ($sentCount + $failedCount) >= (int) $batch->total_recipients ? now() : $batch->completed_at,
        ]);
    }
}
