<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Models\WhatsAppMessageBatch;
use App\Models\WhatsAppMessageLog;
use App\Services\Reminders\Handlers\CountdownHandler;
use App\Services\Reminders\Handlers\TemperatureReminderHandler;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    public function __construct(
        protected GowaClient $gowaClient,
        protected CountdownHandler $countdownHandler,
        protected TemperatureReminderHandler $tempHandler
    ) {}

    public function process(Reminder $reminder): void
    {
        Log::info("Processing reminder: {$reminder->name} (ID: {$reminder->id})");

        // 1. Build Message
        $message = $this->buildMessage($reminder);

        // 2. Get Recipients
        $recipients = $reminder->recipients;

        if ($recipients->isEmpty()) {
            Log::warning("No recipients found for reminder: {$reminder->name}");

            return;
        }

        // 3. Create Batch
        $batch = WhatsAppMessageBatch::create([
            'type' => 'reminder',
            'source_type' => Reminder::class,
            'source_id' => $reminder->id,
            'title' => $reminder->name,
            'message_preview' => substr($message, 0, 1000),
            'total_recipients' => $recipients->count(),
            'mention_all' => $reminder->mention_all ?? false,
            'started_at' => now(),
        ]);

        $sentCount = 0;
        $failedCount = 0;

        // 4. Send Message
        foreach ($recipients as $recipient) {
            $target = $recipient->recipient_value;
            $isGroup = $recipient->recipient_type === 'group';
            $errorMsg = null;
            $msgId = null;
            $status = 'pending';

            // Format JID
            if (! $isGroup && ! str_contains($target, '@')) {
                if (str_starts_with($target, '08')) {
                    $target = '62'.substr($target, 1);
                }
                if (! str_ends_with($target, '@s.whatsapp.net')) {
                    $target .= '@s.whatsapp.net';
                }
            } elseif ($isGroup && ! str_ends_with($target, '@g.us')) {
                $target .= '@g.us';
            }

            try {
                // Prepare mentions
                $mentions = [];
                if ($isGroup && $reminder->mention_all) {
                    $mentions[] = '@everyone'; // or whatever keyword GOWA supports, assuming GowaClient handles translation or pass direct
                }

                $result = $this->gowaClient->sendMessage($target, $message, $mentions);

                if ($result['success']) {
                    $status = 'sent';
                    $msgId = $result['message_id'];
                    $sentCount++;
                    Log::info("Reminder sent to {$target}");
                } else {
                    $status = 'failed';
                    $errorMsg = $result['error'] ?? 'Unknown error';
                    $failedCount++;
                    Log::error("Failed to send reminder to {$target}: {$errorMsg}");
                }

            } catch (\Exception $e) {
                $status = 'failed';
                $errorMsg = $e->getMessage();
                $failedCount++;
                Log::error("Exception sending reminder to {$target}: {$e->getMessage()}");
            }

            // Create Log
            WhatsAppMessageLog::create([
                'batch_id' => $batch->id,
                'recipient_jid' => $target,
                'recipient_name' => $target, // We don't have name stored in recipient table, use value
                'recipient_type' => $recipient->recipient_type,
                'status' => $status,
                'error_message' => $errorMsg,
                'message_id' => $msgId,
                'sent_at' => $status === 'sent' ? now() : null,
            ]);
        }

        // 5. Update Batch and Reminder
        $batch->update([
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'completed_at' => now(),
        ]);

        $reminder->update(['last_run_at' => now()]);
    }

    protected function buildMessage(Reminder $reminder): string
    {
        return match ($reminder->type) {
            'iso_countdown', 'countdown' => $this->countdownHandler->handle($reminder),
            'temp_morning', 'temp_afternoon' => $this->tempHandler->handle($reminder),
            default => $reminder->message_template,
        };
    }
}
