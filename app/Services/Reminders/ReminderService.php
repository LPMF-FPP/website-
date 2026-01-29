<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Services\Reminders\Handlers\IsoCountdownHandler;
use App\Services\Reminders\Handlers\TemperatureReminderHandler;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    public function __construct(
        protected GowaClient $gowaClient,
        protected IsoCountdownHandler $isoHandler,
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

        // 3. Send Message
        foreach ($recipients as $recipient) {
            try {
                // Determine target (group or phone)
                $target = $recipient->recipient_value;
                $isGroup = $recipient->recipient_type === 'group';

                // Send via GowaClient
                // Note: GowaClient usually handles both individual and group if the ID is correct.
                // If special handling is needed for groups, we check GowaClient capabilities.
                // Assuming sendMessage works for both if JID is correct.

                // Format JID if it's a phone number (not a group)
                if (! $isGroup && ! str_contains($target, '@')) {
                    // Basic formatting, GowaClient might handle this but good to be safe
                    // Assuming Indonesian numbers
                    if (str_starts_with($target, '08')) {
                        $target = '62'.substr($target, 1);
                    }
                    if (! str_ends_with($target, '@s.whatsapp.net')) {
                        $target .= '@s.whatsapp.net';
                    }
                } elseif ($isGroup && ! str_ends_with($target, '@g.us')) {
                    $target .= '@g.us';
                }

                $this->gowaClient->sendMessage($target, $message);
                Log::info("Reminder sent to {$target}");

            } catch (\Exception $e) {
                Log::error("Failed to send reminder to {$recipient->recipient_value}: ".$e->getMessage());
            }
        }

        // 4. Update Last Run
        $reminder->update(['last_run_at' => now()]);
    }

    protected function buildMessage(Reminder $reminder): string
    {
        return match ($reminder->type) {
            'iso_countdown' => $this->isoHandler->handle($reminder),
            'temp_morning', 'temp_afternoon' => $this->tempHandler->handle($reminder),
            default => $reminder->message_template,
        };
    }
}
