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
            $result = $dispatcher->handle($this->from, $this->message);
            
            // Only update command-related fields, preserve original params
            $updates = [
                'command' => $result['command'] ?? null,
                'response_status' => $result['status'],
                'response_text' => $result['response'],
            ];
            
            // Only update params if dispatcher provides new params (for commands)
            if (isset($result['params'])) {
                $updates['params'] = $result['params'];
            }
            
            WhatsappCommandLog::where('id', $this->logId)->update($updates);
        } catch (\Throwable $e) {
            Log::error('Job command processing error', [
                'log_id' => $this->logId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
