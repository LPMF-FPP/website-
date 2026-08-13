<?php

namespace App\Jobs;

use App\Models\Reminder;
use App\Services\Reminders\ReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $reminderId = null;

    // Supports jobs serialized before this class began storing only the reminder ID.
    public ?Reminder $reminder = null;

    public string $deliveryKey = '';

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $reminderId,
        ?string $deliveryKey = null
    ) {
        $this->reminderId = $reminderId;
        $this->deliveryKey = $deliveryKey ?? 'manual:'.Str::uuid();
    }

    public function uniqueId(): string
    {
        return ($this->reminderId ?? $this->reminder?->id ?? 'missing').':'.$this->deliveryKey;
    }

    /**
     * Execute the job.
     */
    public function handle(ReminderService $reminderService): void
    {
        $reminderId = $this->reminderId ?? $this->reminder?->id;
        $reminder = $reminderId ? Reminder::query()->find($reminderId) : null;
        if (! $reminder) {
            Log::warning('Reminder job skipped because the reminder no longer exists.', [
                'reminder_id' => $reminderId,
            ]);

            return;
        }

        $deliveryKey = $this->deliveryKey !== ''
            ? $this->deliveryKey
            : 'legacy:'.$reminder->id.':'.now()->toDateString();

        $reminderService->process($reminder, $deliveryKey);
    }
}
