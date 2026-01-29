<?php

namespace App\Services\Reminders\Handlers;

use App\Models\Reminder;

class TemperatureReminderHandler
{
    public function handle(Reminder $reminder): string
    {
        // For now, it just returns the template as is,
        // but could be expanded to include date or shift info.
        return $reminder->message_template;
    }
}
