<?php

namespace App\Jobs;

use App\Models\WhatsappCommandLog;
use App\Services\WhatsApp\CommandDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $logId,
        public string $from,
        public string $message
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CommandDispatcher $dispatcher): void
    {
        try {
            $log = WhatsappCommandLog::query()->find($this->logId);
            if ($log === null) {
                Log::warning('WhatsApp command log not found for processing', [
                    'log_id' => $this->logId,
                ]);

                return;
            }

            if ($log->processed_at !== null) {
                return;
            }

            $result = $dispatcher->handle($this->from, $this->message);

            // Only update command-related fields, preserve original params
            $updates = [
                'command' => $result['command'] ?? null,
                'response_status' => $result['status'],
                'response_text' => $result['response'],
                'processed_at' => now(),
            ];

            // Only update params if dispatcher provides new params (for commands)
            if (isset($result['params'])) {
                $updates['params'] = $result['params'];
            }

            $log->update($updates);
        } catch (\Throwable $e) {
            Log::error('Job command processing error', [
                'log_id' => $this->logId,
                'error' => $e->getMessage(),
            ]);

            WhatsappCommandLog::where('id', $this->logId)->update([
                'response_status' => 'error',
                'response_text' => 'Terjadi kesalahan internal saat memproses command.',
                'processed_at' => now(),
            ]);
        }
    }
}
