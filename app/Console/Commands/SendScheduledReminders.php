<?php

namespace App\Console\Commands;

use App\Jobs\SendReminderJob;
use App\Models\Reminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send {--force : Force send pending reminders ignoring time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled WhatsApp reminders that are due';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for due reminders...');

        $query = Reminder::query();

        if ($this->option('force')) {
            // For debugging: Just find enabled ones that haven't run today
            // This overrides the time check
            $query->enabled()
                ->where(function ($q) {
                    $q->whereNull('last_run_at')
                        ->orWhereDate('last_run_at', '<', now()->toDateString());
                });
        } else {
            $query->due();
        }

        $reminders = $query->get();

        if ($reminders->isEmpty()) {
            $this->info('No due reminders found.');

            return;
        }

        $this->info("Found {$reminders->count()} reminders due.");

        foreach ($reminders as $reminder) {
            $this->info("Dispatching reminder: {$reminder->name}");

            // Dispatch job
            SendReminderJob::dispatch($reminder);

            // Log it
            Log::info("Dispatched SendReminderJob for reminder ID: {$reminder->id}");
        }

        $this->info('All jobs dispatched.');
    }
}
