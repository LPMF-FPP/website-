<?php

namespace App\Jobs;

use App\Models\Reminder;
use App\Services\Reminders\ReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Reminder $reminder
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReminderService $reminderService): void
    {
        $reminderService->process($this->reminder);
    }
}
