<?php

namespace App\Observers;

use App\Jobs\SendQmhWorkflowTaskNotificationJob;
use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Support\Str;

class UserObserver
{
    public function updated(User $user): void
    {
        if (! $user->wasChanged('phone')) {
            return;
        }

        $phone = trim((string) $user->phone);
        if ($phone === '') {
            return;
        }

        $pendingTasks = StaffTask::query()
            ->where('assigned_to', $user->id)
            ->where('source_module', StaffTask::SOURCE_MODULE_QMH)
            ->where('source_ref_type', StaffTask::SOURCE_REF_TYPE_QMH_REVISION)
            ->whereIn('status', [StaffTask::STATUS_PENDING, StaffTask::STATUS_IN_PROGRESS])
            ->where('notification_sent', false)
            ->get();

        foreach ($pendingTasks as $task) {
            $actionCode = strtoupper(Str::random(10));

            $task->update([
                'action_token_hash' => hash('sha256', $actionCode),
                'action_expires_at' => now()->addMinutes(30),
                'token_consumed_at' => null,
            ]);

            SendQmhWorkflowTaskNotificationJob::dispatch((int) $task->id, $actionCode);
        }
    }
}
