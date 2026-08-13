<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Models\WhatsAppMessageBatch;
use App\Services\Reminders\Handlers\CountdownHandler;
use App\Services\Reminders\Handlers\TemperatureReminderHandler;
use App\Services\WhatsApp\OutboundMessageService;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    public function __construct(
        protected OutboundMessageService $outboundMessageService,
        protected CountdownHandler $countdownHandler,
        protected TemperatureReminderHandler $tempHandler
    ) {}

    public function process(Reminder $reminder, string $deliveryKey): void
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

        // 4. Send Message
        foreach ($recipients as $recipient) {
            $target = $recipient->recipient_value;
            $isGroup = $recipient->recipient_type === 'group';

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

                $result = $this->outboundMessageService->sendText($target, $message, [
                    'batch_id' => $batch->id,
                    'recipient_name' => $target,
                    'recipient_type' => $recipient->recipient_type,
                    'source_type' => Reminder::class,
                    'source_id' => $reminder->id,
                    'source_label' => 'Pengingat WhatsApp',
                    'mentions' => $mentions,
                    'idempotency_key' => 'whatsapp-reminder:'.$reminder->id.':'.$deliveryKey.':recipient:'.$recipient->id,
                ]);

                if ($result['success']) {
                    Log::info("Reminder sent to {$target}");
                } else {
                    $errorMsg = $result['error'] ?? 'Unknown error';
                    Log::error("Failed to send reminder to {$target}: {$errorMsg}");
                }

            } catch (\Exception $e) {
                Log::error("Exception sending reminder to {$target}: {$e->getMessage()}");
            }

        }

        // 5. Update batch from the envelope states and preserve reminder schedule bookkeeping.
        $this->outboundMessageService->syncBatchStats($batch->id);

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
