<?php

namespace App\Http\Controllers;

use App\Models\Investigator;
use App\Models\Reminder;
use App\Models\StaffTask;
use App\Models\User;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Models\WhatsAppMessageBatch;
use App\Models\WhatsAppMessageLog;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\NotificationService;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class WhatsAppHubController extends Controller
{
    private GowaClient $gowaClient;

    private NotificationService $notificationService;

    private TemplateService $templateService;

    public function __construct(
        GowaClient $gowaClient,
        NotificationService $notificationService,
        TemplateService $templateService
    ) {
        $this->gowaClient = $gowaClient;
        $this->notificationService = $notificationService;
        $this->templateService = $templateService;
    }

    public function index(): View
    {
        // $this->authorize('whatsapp.view'); // Temporarily disabled for dev/implementation phase

        return view('whatsapp.index');
    }

    // --- Overview ---

    public function getOverviewData(): JsonResponse
    {
        $today = now()->startOfDay();

        $stats = [
            'sent_today' => WhatsAppMessageLog::where('status', 'sent')
                ->where('created_at', '>=', $today)
                ->count(),
            'pending_tasks' => StaffTask::where('status', 'pending')->count(),
            'scheduled' => WhatsappBroadcast::where('status', 'scheduled')->count(),
            'failed_today' => WhatsAppMessageLog::where('status', 'failed')
                ->where('created_at', '>=', $today)
                ->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'recent_activity' => $this->getRecentActivity(),
        ]);
    }

    private function getRecentActivity(): Collection
    {
        // Mix batches, tasks, broadcasts
        $batches = WhatsAppMessageBatch::with('creator')
            ->latest()->take(3)->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'type' => 'message',
                'title' => $b->title,
                'subtitle' => "{$b->sent_count}/{$b->total_recipients} sent",
                'status' => $b->failed_count > 0 ? 'warning' : 'success',
                'time' => $b->created_at->diffForHumans(),
                'timestamp' => $b->created_at->timestamp,
            ]);

        $tasks = StaffTask::with('assignee')
            ->latest()->take(3)->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => 'task',
                'title' => $t->title,
                'subtitle' => "Assigned to {$t->assignee->name}",
                'status' => $t->status,
                'time' => $t->created_at->diffForHumans(),
                'timestamp' => $t->created_at->timestamp,
            ]);

        return $batches->merge($tasks)->sortByDesc('timestamp')->take(5)->values();
    }

    // --- Tasks ---

    public function getTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $filter = $request->input('filter', 'my_tasks');
        $status = $request->input('status', 'active');

        $query = StaffTask::with(['assignee', 'assigner', 'testRequest']);

        // Apply ownership filter
        if ($filter === 'my_tasks') {
            $query->where('assigned_to', $user->id);
        } elseif ($filter === 'assigned_by_me') {
            $query->where('assigned_by', $user->id);
        }

        // Apply status filter
        if ($status !== 'all') {
            if ($status === 'active') {
                $query->active();
            } else {
                $query->where('status', $status);
            }
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'tasks' => $tasks,
            'stats' => [
                'pending' => StaffTask::where('status', 'pending')->count(),
                'in_progress' => StaffTask::where('status', 'in_progress')->count(),
                'overdue' => StaffTask::overdue()->count(),
                'completed_today' => StaffTask::where('status', 'completed')
                    ->whereDate('completed_at', today())->count(),
            ],
            'users' => User::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'assigned_to' => 'required|exists:users,id',
            'test_request_id' => 'nullable|exists:test_requests,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'due_at' => 'nullable|date|after_or_equal:today',
            'notify_whatsapp' => 'boolean',
        ]);

        $validated['assigned_by'] = $request->user()->id;
        $validated['status'] = StaffTask::STATUS_PENDING;
        $validated['notify_whatsapp'] = $validated['notify_whatsapp'] ?? true;

        $task = StaffTask::create($validated);

        if ($task->notify_whatsapp) {
            dispatch(new \App\Jobs\SendTaskNotificationJob($task->id, 'assigned'));
        }

        return response()->json([
            'message' => 'Task created successfully',
            'task' => $task->load(['assignee', 'assigner', 'testRequest']),
        ], 201);
    }

    public function updateTask(Request $request, StaffTask $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'assigned_to' => 'sometimes|required|exists:users,id',
            'test_request_id' => 'nullable|exists:test_requests,id',
            'priority' => 'sometimes|required|in:low,normal,high,urgent',
            'due_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'notify_whatsapp' => 'boolean',
        ]);

        $task->update($validated);

        return response()->json([
            'message' => 'Task updated successfully',
            'task' => $task->fresh(['assignee', 'assigner', 'testRequest']),
        ]);
    }

    public function updateTaskStatus(Request $request, StaffTask $task): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'notes' => 'nullable|string|max:2000',
        ]);

        $oldStatus = $task->status;
        $newStatus = $validated['status'];

        $updates = ['status' => $newStatus];

        if (isset($validated['notes'])) {
            $updates['notes'] = $validated['notes'];
        }

        if ($newStatus === StaffTask::STATUS_IN_PROGRESS && $oldStatus === StaffTask::STATUS_PENDING) {
            $updates['started_at'] = now();
        } elseif ($newStatus === StaffTask::STATUS_COMPLETED) {
            $updates['completed_at'] = now();
        }

        $task->update($updates);

        if ($task->notify_whatsapp && in_array($newStatus, ['completed', 'in_progress'])) {
            dispatch(new \App\Jobs\SendTaskNotificationJob($task->id, 'status_changed'));
        }

        return response()->json([
            'message' => 'Task status updated',
            'task' => $task->fresh(['assignee', 'assigner', 'testRequest']),
        ]);
    }

    public function destroyTask(StaffTask $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }

    // --- Templates & Settings ---

    public function getTemplates(): JsonResponse
    {
        return response()->json([
            'categories' => $this->templateService->getCategoryLabels(),
            'templates' => $this->templateService->getAll(),
            'labels' => $this->templateService->getTemplateLabels(),
            'placeholders' => $this->templateService->getAllPlaceholders(),
            'milestones' => $this->notificationService->getAvailableMilestones(),
        ]);
    }

    public function saveTemplates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'templates' => 'required|array',
        ]);

        $templates = $validated['templates'];

        // Save by category using TemplateService updateCategory
        foreach ($templates as $category => $items) {
            try {
                // Filter out empty values or nulls just in case
                $cleanItems = array_filter($items, fn ($v) => ! is_null($v));
                if (! empty($cleanItems)) {
                    $this->templateService->updateCategory($category, $cleanItems);
                }
            } catch (\InvalidArgumentException $e) {
                // Ignore invalid categories
                continue;
            }
        }

        settings_forget_cache();

        return response()->json(['message' => 'Templates saved successfully']);
    }

    public function resetTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'key' => 'required|string',
        ]);

        $category = $validated['category'];
        $key = $validated['key'];

        $default = $this->templateService->resetToDefault($category, $key);

        return response()->json([
            'message' => 'Template reset to default',
            'template' => $default,
        ]);
    }

    public function previewTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'key' => 'required|string',
            'template' => 'nullable|string',
        ]);

        $preview = $this->templateService->preview(
            $validated['category'],
            $validated['key'],
            $validated['template']
        );

        return response()->json(['preview' => $preview]);
    }

    public function checkConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'base_url' => 'required|string|url',
            'basic_user' => 'nullable|string|max:255',
            'basic_pass' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $baseUrl = rtrim($request->input('base_url'), '/');
        $basicUser = $request->input('basic_user');
        $basicPass = $request->input('basic_pass');

        if ($basicPass === '••••••••') {
            $basicPass = settings('notifications.whatsapp.basic_pass');
            if ($basicPass) {
                try {
                    $basicPass = decrypt($basicPass);
                } catch (\Throwable $e) {
                    $basicPass = env('WHATSAPP_BASIC_PASS');
                }
            } else {
                $basicPass = env('WHATSAPP_BASIC_PASS');
            }
        }

        $result = $this->gowaClient->listDevicesWithCredentials($baseUrl, $basicUser, $basicPass);

        if ($result['success']) {
            return response()->json([
                'message' => 'Connection successful',
                'devices' => $result['devices'],
            ]);
        }

        return response()->json([
            'message' => 'Connection failed',
            'error' => $result['error'] ?? 'Unknown error',
            'status' => $result['status'] ?? 500,
        ], 400);
    }

    public function getConnectionStatus(): JsonResponse
    {
        return response()->json($this->gowaClient->checkHealth());
    }

    // --- Broadcasts ---

    public function getBroadcasts(Request $request): JsonResponse
    {
        $query = WhatsappBroadcast::with('creator')->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json([
            'broadcasts' => $query->paginate(20),
            'statuses' => WhatsappBroadcast::statuses(),
        ]);
    }

    public function getBroadcast(WhatsappBroadcast $broadcast): JsonResponse
    {
        return response()->json([
            'broadcast' => $broadcast->load('creator'),
            'recipients_count' => $broadcast->recipients()->count(),
        ]);
    }

    public function storeBroadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'target_type' => 'required|in:investigators,users,custom',
            'target_filters' => 'nullable|array',
            'recipient_ids' => 'nullable|array',
            'scheduled_at' => 'nullable|date|after:now',
            'mention_all' => 'boolean',
        ]);

        // Logic similar to old WhatsappBroadcastController
        $broadcast = WhatsappBroadcast::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'target_type' => $validated['target_type'],
            'target_filters' => $validated['target_filters'] ?? null,
            'recipient_ids' => $validated['recipient_ids'] ?? null,
            'created_by' => $request->user()->id,
            'status' => isset($validated['scheduled_at']) ? WhatsappBroadcast::STATUS_SCHEDULED : WhatsappBroadcast::STATUS_DRAFT,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            // We might want to store mention_all in broadcast table too, or pass it when sending
        ]);

        $this->buildRecipients($broadcast);

        return response()->json(['message' => 'Broadcast created', 'broadcast' => $broadcast], 201);
    }

    public function updateBroadcast(Request $request, WhatsappBroadcast $broadcast): JsonResponse
    {
        if (! $broadcast->canEdit()) {
            return response()->json(['message' => 'Cannot edit broadcast'], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'message' => 'sometimes|required|string|max:2000',
            'target_type' => 'sometimes|required|in:investigators,users,custom',
            'target_filters' => 'nullable|array',
            'recipient_ids' => 'nullable|array',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $broadcast->update($validated);

        if (isset($validated['target_type']) || isset($validated['target_filters']) || isset($validated['recipient_ids'])) {
            $broadcast->recipients()->delete();
            $this->buildRecipients($broadcast);
        }

        return response()->json(['message' => 'Broadcast updated', 'broadcast' => $broadcast]);
    }

    public function sendBroadcast(Request $request, WhatsappBroadcast $broadcast): JsonResponse
    {
        if (! $broadcast->canSend()) {
            return response()->json(['message' => 'Cannot send broadcast'], 422);
        }

        $mentionAll = $request->boolean('mention_all');

        // Dispatch job
        dispatch(new \App\Jobs\SendBroadcastJob($broadcast->id, $mentionAll));

        $broadcast->update([
            'status' => WhatsappBroadcast::STATUS_SENDING,
            'started_at' => now(),
        ]);

        return response()->json(['message' => 'Broadcast sending started']);
    }

    public function cancelBroadcast(WhatsappBroadcast $broadcast): JsonResponse
    {
        if (! $broadcast->canCancel()) {
            return response()->json(['message' => 'Cannot cancel broadcast'], 422);
        }

        $broadcast->markAsCancelled();

        return response()->json(['message' => 'Broadcast cancelled']);
    }

    public function deleteBroadcast(WhatsappBroadcast $broadcast): JsonResponse
    {
        if (! $broadcast->canEdit()) {
            return response()->json(['message' => 'Cannot delete broadcast'], 422);
        }

        $broadcast->delete();

        return response()->json(['message' => 'Broadcast deleted']);
    }

    public function previewRecipients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:investigators,users,custom',
            'target_filters' => 'nullable|array',
            'recipient_ids' => 'nullable|array',
        ]);

        $recipients = $this->getRecipientsPreview(
            $validated['target_type'],
            $validated['target_filters'] ?? [],
            $validated['recipient_ids'] ?? []
        );

        return response()->json([
            'count' => $recipients->count(),
            'recipients' => $recipients->take(20),
        ]);
    }

    // --- Reminders ---

    public function getReminders(): JsonResponse
    {
        $reminders = Reminder::with('recipients')->orderBy('schedule_time')->get();

        return response()->json(['reminders' => $reminders]);
    }

    public function getReminder(Reminder $reminder): JsonResponse
    {
        return response()->json(['reminder' => $reminder->load('recipients')]);
    }

    public function updateReminder(Request $request, Reminder $reminder): JsonResponse
    {
        $validated = $request->validate([
            'schedule_time' => 'required|date_format:H:i',
            'is_enabled' => 'boolean',
            'message_template' => 'required|string',
            'target_date' => 'nullable|date',
            'recipients' => 'nullable|array',
            'recipients.*.type' => 'required|in:phone,group',
            'recipients.*.value' => 'required|string',
            'mention_all' => 'boolean',
        ]);

        $metadata = $reminder->metadata;
        if ($reminder->type === 'iso_countdown' && isset($validated['target_date'])) {
            $metadata['target_date'] = $validated['target_date'];
        }

        $reminder->update([
            'schedule_time' => $validated['schedule_time'].':00',
            'is_enabled' => $request->has('is_enabled'),
            'message_template' => $validated['message_template'],
            'metadata' => $metadata,
            'mention_all' => $request->boolean('mention_all'),
        ]);

        $reminder->recipients()->delete();
        if (! empty($validated['recipients'])) {
            foreach ($validated['recipients'] as $r) {
                if (! empty($r['value'])) {
                    $reminder->recipients()->create([
                        'recipient_type' => $r['type'],
                        'recipient_value' => $r['value'],
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Reminder updated', 'reminder' => $reminder->fresh()]);
    }

    public function toggleReminder(Reminder $reminder): JsonResponse
    {
        $reminder->update(['is_enabled' => ! $reminder->is_enabled]);

        return response()->json(['message' => 'Reminder toggled', 'is_enabled' => $reminder->is_enabled]);
    }

    public function triggerReminder(Reminder $reminder): JsonResponse
    {
        \App\Jobs\SendReminderJob::dispatch($reminder);

        return response()->json(['message' => 'Reminder triggered']);
    }

    // --- Logs ---

    public function getLogs(Request $request): JsonResponse
    {
        $batches = WhatsAppMessageBatch::with(['creator', 'logs'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json(['logs' => $batches]);
    }

    public function getLogDetail(WhatsAppMessageBatch $batch): JsonResponse
    {
        $logs = $batch->logs()->paginate(50);

        return response()->json(['batch' => $batch, 'messages' => $logs]);
    }

    // --- Groups & Settings ---

    public function getGroups(): JsonResponse
    {
        // Try to get groups from GOWA
        $result = $this->gowaClient->listChats();

        if (! $result['success']) {
            return response()->json(['error' => $result['error']], 500);
        }

        $groups = collect($result['chats'] ?? [])
            ->filter(fn ($chat) => str_ends_with($chat['jid'] ?? '', '@g.us'))
            ->map(fn ($chat) => [
                'jid' => $chat['jid'],
                'name' => $chat['name'] ?? 'Unknown Group',
                // Metadata might not be available here, frontend will handle participant fetching if needed
            ])
            ->values();

        return response()->json(['groups' => $groups]);
    }

    public function getGroupParticipants(string $jid): JsonResponse
    {
        // GOWA doesn't expose easy participants count in listChats
        // We might need to rely on the client or ignore for now if not supported
        // But we implemented a placeholder in GowaClient
        return response()->json($this->gowaClient->getGroupParticipants($jid));
    }

    public function getSettings(): JsonResponse
    {
        return response()->json([
            'base_url' => settings('notifications.whatsapp.base_url'),
            'basic_user' => settings('notifications.whatsapp.basic_user'),
            'device_id' => settings('notifications.whatsapp.device_id'),
            // Don't return password
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_url' => 'required|url',
            'basic_user' => 'nullable|string',
            'basic_pass' => 'nullable|string',
            'device_id' => 'required|string',
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.base_url'],
            ['value' => $validated['base_url']]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.basic_user'],
            ['value' => $validated['basic_user']]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.device_id'],
            ['value' => $validated['device_id']]
        );

        if (! empty($validated['basic_pass'])) {
            SystemSetting::updateOrCreate(
                ['key' => 'notifications.whatsapp.basic_pass'],
                ['value' => encrypt($validated['basic_pass'])]
            );
        }

        // Clear cache so changes take effect immediately
        if (function_exists('settings_forget_cache')) {
            settings_forget_cache();
        } else {
            cache()->forget('sys_settings_all');
        }

        return response()->json(['message' => 'Settings saved']);
    }

    public function getDevices(): JsonResponse
    {
        return response()->json($this->gowaClient->listDevices());
    }

    public function sendTestMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $result = $this->gowaClient->sendMessage($validated['phone'], $validated['message']);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    // --- Private Helpers (reused from old controller) ---

    private function buildRecipients(WhatsappBroadcast $broadcast): void
    {
        $recipients = $this->getRecipientsPreview(
            $broadcast->target_type,
            $broadcast->target_filters ?? [],
            $broadcast->recipient_ids ?? []
        );

        $recipientRecords = [];

        foreach ($recipients as $recipient) {
            $recipientRecords[] = [
                'broadcast_id' => $broadcast->id,
                'recipient_type' => $recipient['type'],
                'recipient_id' => $recipient['id'],
                'phone' => $recipient['phone'],
                'name' => $recipient['name'],
                'status' => WhatsappBroadcastRecipient::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (count($recipientRecords) > 0) {
            WhatsappBroadcastRecipient::insert($recipientRecords);
        }

        $broadcast->update([
            'total_recipients' => count($recipientRecords),
        ]);
    }

    private function getRecipientsPreview(string $targetType, array $filters, array $customIds): \Illuminate\Support\Collection
    {
        $recipients = collect();

        if ($targetType === WhatsappBroadcast::TARGET_INVESTIGATORS) {
            $query = Investigator::whereNotNull('phone')->where('phone', '!=', '');
            if (! empty($filters['jurisdiction'])) {
                $query->where('jurisdiction', $filters['jurisdiction']);
            }
            foreach ($query->get() as $inv) {
                $recipients->push(['type' => 'investigator', 'id' => $inv->id, 'name' => $inv->rank.' '.$inv->name, 'phone' => $inv->phone]);
            }
        } elseif ($targetType === WhatsappBroadcast::TARGET_USERS) {
            $query = User::where('is_active', true)->whereNotNull('phone')->where('phone', '!=', '');
            if (! empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }
            foreach ($query->get() as $user) {
                $recipients->push(['type' => 'user', 'id' => $user->id, 'name' => $user->name, 'phone' => $user->phone]);
            }
        } elseif ($targetType === WhatsappBroadcast::TARGET_CUSTOM && ! empty($customIds)) {
            foreach ($customIds as $customId) {
                if (is_string($customId) && str_contains($customId, ':')) {
                    [$type, $id] = explode(':', $customId, 2);
                    if ($type === 'inv') {
                        $inv = Investigator::find($id);
                        if ($inv && $inv->phone) {
                            $recipients->push(['type' => 'investigator', 'id' => $inv->id, 'name' => $inv->rank.' '.$inv->name, 'phone' => $inv->phone]);
                        }
                    } elseif ($type === 'user') {
                        $user = User::find($id);
                        if ($user && $user->phone) {
                            $recipients->push(['type' => 'user', 'id' => $user->id, 'name' => $user->name, 'phone' => $user->phone]);
                        }
                    }
                }
            }
        }

        return $recipients;
    }
}
