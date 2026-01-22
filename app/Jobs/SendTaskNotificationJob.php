<?php

namespace App\Jobs;

use App\Models\StaffTask;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\NotificationService;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTaskNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $taskId,
        public string $eventType = 'assigned'
    ) {}

    public function handle(GowaClient $client, NotificationService $notificationService): void
    {
        $task = StaffTask::with(['assignee', 'assigner', 'testRequest'])->find($this->taskId);

        if (! $task) {
            Log::warning("SendTaskNotificationJob: Task {$this->taskId} not found");

            return;
        }

        if (! $task->notify_whatsapp) {
            Log::info("SendTaskNotificationJob: WhatsApp notification disabled for task {$this->taskId}");

            return;
        }

        $assignee = $task->assignee;
        if (! $assignee || ! $assignee->phone) {
            Log::warning("SendTaskNotificationJob: Assignee or phone not found for task {$this->taskId}");

            return;
        }

        $message = $this->buildMessage($task, $this->eventType);
        $jid = $notificationService->formatJID($assignee->phone);

        try {
            $result = $client->sendMessage($jid, $message);

            if ($result['success']) {
                $task->update([
                    'notification_sent' => true,
                    'notification_sent_at' => now(),
                ]);
                Log::info("SendTaskNotificationJob: Notification sent for task {$this->taskId}");
            } else {
                Log::error("SendTaskNotificationJob: Failed to send notification for task {$this->taskId}", [
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            Log::error("SendTaskNotificationJob: Exception sending notification for task {$this->taskId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function buildMessage(StaffTask $task, string $eventType): string
    {
        $templateService = app(TemplateService::class);

        $replacements = [
            'greetings' => $this->getTimeBasedGreeting(),
            'assignee_name' => $task->assignee->name ?? 'Staff',
            'assigner_name' => $task->assigner->name ?? 'Admin',
            'title' => $task->title,
            'description' => $task->description ?? '',
            'priority' => $task->priority_label ?? $task->priority,
            'due_at' => $task->due_at?->format('d M Y H:i') ?? '-',
            'request_number' => $task->testRequest?->receipt_number ?? '-',
            'status' => $task->status_label ?? $task->status,
            'completed_at' => $task->completed_at?->format('d M Y H:i') ?? '-',
        ];

        $templateKey = $eventType === 'assigned' ? 'TASK_ASSIGNED' : 'TASK_STATUS_CHANGED';

        return $templateService->render('task', $templateKey, $replacements);
    }

    private function getTimeBasedGreeting(): string
    {
        $hour = (int) now()->format('H');

        if ($hour >= 5 && $hour < 11) {
            return 'Selamat Pagi';
        } elseif ($hour >= 11 && $hour < 15) {
            return 'Selamat Siang';
        } elseif ($hour >= 15 && $hour < 18) {
            return 'Selamat Sore';
        } else {
            return 'Selamat Malam';
        }
    }
}
