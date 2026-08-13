<?php

namespace App\Http\Controllers;

use App\Models\InventoryAlertLog;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\Investigator;
use App\Models\Reminder;
use App\Models\StaffTask;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Models\WhatsAppMessageBatch;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappWhitelist;
use App\Services\AI\AiCommsService;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\NotificationService;
use App\Services\WhatsApp\OutboundMessageService;
use App\Services\WhatsApp\TemplateService;
use App\Services\WhatsApp\WhitelistService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;

class WhatsAppHubController extends Controller
{
    private GowaClient $gowaClient;

    private NotificationService $notificationService;

    private TemplateService $templateService;

    private OutboundMessageService $outboundMessageService;

    public function __construct(
        GowaClient $gowaClient,
        NotificationService $notificationService,
        TemplateService $templateService,
        OutboundMessageService $outboundMessageService
    ) {
        $this->gowaClient = $gowaClient;
        $this->notificationService = $notificationService;
        $this->templateService = $templateService;
        $this->outboundMessageService = $outboundMessageService;
    }

    public function index(): View
    {
        // $this->authorize('whatsapp.view'); // Temporarily disabled for dev/implementation phase

        $connectionStatus = $this->gowaClient->checkHealth();

        return view('whatsapp.index', [
            'initialConnectionStatus' => (bool) ($connectionStatus['connected'] ?? false)
                || (bool) ($connectionStatus['reachable'] ?? false),
        ]);
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
                'key' => 'message-'.$b->id,
                'type' => 'message',
                'title' => $b->title,
                'details' => "{$b->sent_count}/{$b->total_recipients} terkirim",
                'status' => $b->failed_count > 0 ? 'warning' : 'success',
                'time' => $b->created_at->diffForHumans(),
                'timestamp' => $b->created_at->timestamp,
            ]);

        $tasks = StaffTask::with('assignee')
            ->latest()->take(3)->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'key' => 'task-'.$t->id,
                'type' => 'task',
                'title' => $t->title,
                'details' => 'Ditugaskan ke '.($t->assignee?->name ?? 'Belum ditentukan'),
                'status' => $this->normalizeTaskActivityStatus((string) $t->status),
                'time' => $t->created_at->diffForHumans(),
                'timestamp' => $t->created_at->timestamp,
            ]);

        return $batches->merge($tasks)->sortByDesc('timestamp')->take(5)->values();
    }

    private function normalizeTaskActivityStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'pending' => 'warning',
            default => 'info',
        };
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
        if ($task->source_module === StaffTask::SOURCE_MODULE_QMH
            && $task->source_ref_type === StaffTask::SOURCE_REF_TYPE_QMH_REVISION) {
            return response()->json([
                'message' => 'Task workflow QMH hanya dapat diproses melalui command WhatsApp /qmh atau modul QMH.',
            ], 422);
        }

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

    public function storeReminder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:countdown,iso_countdown,temp_morning,temp_afternoon,custom',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'schedule_time' => 'required|date_format:H:i',
            'message_template' => 'required|string',
            'schedule_days' => 'required|array|min:1',
            'schedule_days.*' => 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'target_date' => 'nullable|date|required_if:type,countdown,iso_countdown',
            'event_name' => 'nullable|string|max:255|required_if:type,countdown',
            'event_emoji' => 'nullable|string|max:20',
            'milestones' => 'nullable|array',
            'milestones.*.days' => 'required|integer|min:0',
            'milestones.*.message' => 'required|string|max:500',
            'recipients' => 'nullable|array',
            'recipients.*.type' => 'required|in:phone,group',
            'recipients.*.value' => 'required|string',
            'mention_all' => 'boolean',
        ]);

        $metadata = $this->buildCountdownMetadata($validated, $validated['type']);

        $reminder = Reminder::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_enabled' => true,
            'schedule_time' => $validated['schedule_time'].':00',
            'schedule_days' => $validated['schedule_days'],
            'message_template' => $validated['message_template'],
            'metadata' => $metadata,
            'mention_all' => $request->boolean('mention_all'),
        ]);

        if (! empty($validated['recipients'])) {
            foreach ($validated['recipients'] as $recipient) {
                if (! empty($recipient['value'])) {
                    $reminder->recipients()->create([
                        'recipient_type' => $recipient['type'],
                        'recipient_value' => $recipient['value'],
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Reminder created',
            'reminder' => $reminder->fresh()->load('recipients'),
        ], 201);
    }

    public function updateReminder(Request $request, Reminder $reminder): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'sometimes|required|in:countdown,iso_countdown,temp_morning,temp_afternoon,custom',
            'schedule_time' => 'required|date_format:H:i',
            'is_enabled' => 'boolean',
            'message_template' => 'required|string',
            'target_date' => 'nullable|date',
            'event_name' => 'nullable|string|max:255',
            'event_emoji' => 'nullable|string|max:20',
            'milestones' => 'nullable|array',
            'milestones.*.days' => 'required|integer|min:0',
            'milestones.*.message' => 'required|string|max:500',
            'recipients' => 'nullable|array',
            'recipients.*.type' => 'required|in:phone,group',
            'recipients.*.value' => 'required|string',
            'mention_all' => 'boolean',
            'schedule_days' => 'required|array|min:1',
            'schedule_days.*' => 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
        ]);

        $type = $validated['type'] ?? $reminder->type;
        $metadata = $this->buildCountdownMetadata($validated, $type, is_array($reminder->metadata) ? $reminder->metadata : []);

        if (! in_array($type, ['countdown', 'iso_countdown'], true)) {
            $metadata = is_array($reminder->metadata) ? $reminder->metadata : null;
        }

        $reminder->update([
            'type' => $type,
            'name' => $validated['name'] ?? $reminder->name,
            'description' => $validated['description'] ?? $reminder->description,
            'schedule_time' => $validated['schedule_time'].':00',
            'is_enabled' => $validated['is_enabled'] ?? $reminder->is_enabled,
            'message_template' => $validated['message_template'],
            'metadata' => $metadata,
            'mention_all' => $request->boolean('mention_all'),
            'schedule_days' => $validated['schedule_days'],
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
        \App\Jobs\SendReminderJob::dispatch($reminder->id);

        return response()->json(['message' => 'Reminder triggered']);
    }

    public function deleteReminder(Reminder $reminder): JsonResponse
    {
        $reminder->delete();

        return response()->json(['message' => 'Reminder deleted']);
    }

    // --- Logs ---

    public function getLogs(Request $request): JsonResponse
    {
        $messages = WhatsAppMessageLog::query()
            ->withCount('attempts')
            ->orderBy('created_at', 'desc')
            ->paginate(25, ['*'], 'logs_page');

        $messages->getCollection()->transform(function (WhatsAppMessageLog $message): array {
            return $this->messageLogResponse($message);
        });

        return response()->json(['messages' => $messages]);
    }

    public function getLogDetail(WhatsAppMessageBatch $batch): JsonResponse
    {
        $logs = $batch->logs()
            ->with(['attempts' => fn ($query) => $query->orderByDesc('attempt_number')])
            ->paginate(50);

        $logs->getCollection()->transform(function (WhatsAppMessageLog $message): array {
            return $this->messageLogResponse($message, true);
        });

        return response()->json([
            'batch' => [
                'id' => $batch->id,
                'type' => $batch->type,
                'title' => $batch->title,
                'total_recipients' => $batch->total_recipients,
                'sent_count' => $batch->sent_count,
                'failed_count' => $batch->failed_count,
                'created_at' => $batch->created_at?->toISOString(),
            ],
            'messages' => $logs,
        ]);
    }

    public function getMessageAttempts(WhatsAppMessageLog $messageLog): JsonResponse
    {
        return response()->json([
            'message' => $this->messageLogResponse($messageLog, true),
        ]);
    }

    public function retryMessage(Request $request, WhatsAppMessageLog $messageLog): JsonResponse
    {
        if ($request->except('_token') !== []) {
            $this->auditRetryRequest($request, $messageLog, false, 'payload_not_allowed');

            return response()->json([
                'message' => 'Pengiriman ulang tidak menerima nomor, isi pesan, atau lampiran dari browser.',
            ], 422);
        }

        $before = [
            'status' => $messageLog->status,
            'attempt_count' => $messageLog->attempt_count,
        ];
        $queued = $this->outboundMessageService->retry($messageLog);
        $messageLog->refresh();

        $this->auditRetryRequest($request, $messageLog, $queued, null, $before);

        if (! $queued) {
            return response()->json([
                'message' => 'Pesan tidak dapat dikirim ulang.',
                'retry_block_reason' => $this->outboundMessageService->retryBlockReason($messageLog),
                'message_log' => $this->messageLogResponse($messageLog),
            ], 409);
        }

        return response()->json([
            'message' => 'Pengiriman ulang telah diantrikan.',
            'message_log' => $this->messageLogResponse($messageLog),
        ], 202);
    }

    // --- Inventory Alerts ---

    public function getInventoryAlerts(WhitelistService $whitelistService): JsonResponse
    {
        $expiryDays = (int) settings('inventory.alert_expiry_days', 30);

        $superAdminNumber = $whitelistService->normalizePhoneNumber(
            (string) settings('notifications.whatsapp.admin_number', '6285956592404')
        );

        $recipients = WhatsappWhitelist::query()
            ->select(['id', 'phone_number', 'name', 'receive_inventory_alerts'])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (WhatsappWhitelist $whitelist) use ($superAdminNumber, $whitelistService) {
                $normalizedPhoneNumber = $whitelistService->normalizePhoneNumber((string) $whitelist->phone_number);
                $isSuperAdmin = $normalizedPhoneNumber === $superAdminNumber;

                return [
                    'id' => $whitelist->id,
                    'phone_number' => $normalizedPhoneNumber,
                    'name' => $whitelist->name,
                    'receive_inventory_alerts' => $isSuperAdmin ? true : (bool) $whitelist->receive_inventory_alerts,
                    'is_super_admin' => $isSuperAdmin,
                ];
            })
            ->values();

        if ($recipients->firstWhere('is_super_admin', true) === null) {
            $recipients->prepend([
                'id' => null,
                'phone_number' => $superAdminNumber,
                'name' => 'Super Admin',
                'receive_inventory_alerts' => true,
                'is_super_admin' => true,
            ]);
        }

        $lowStockItems = InventoryItem::query()
            ->active()
            ->where('min_stock', '>', 0)
            ->belowMinStock()
            ->withSum('balances', 'on_hand_qty')
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'uom' => $item->uom,
                'total_on_hand' => (float) ($item->balances_sum_on_hand_qty ?? 0),
                'min_stock' => (float) $item->min_stock,
                'edit_url' => route('inventory.items.edit', $item),
            ]);

        $expiringLots = InventoryLot::query()
            ->with(['item'])
            ->nearExpiry($expiryDays)
            ->where('status', 'ACTIVE')
            ->whereHas('balances', fn ($q) => $q->where('on_hand_qty', '>', 0))
            ->orderBy('expiry_date')
            ->take(15)
            ->get()
            ->map(fn ($lot) => [
                'id' => $lot->id,
                'item_id' => $lot->item_id,
                'item_name' => $lot->item?->name,
                'uom' => $lot->item?->uom,
                'lot_no' => $lot->lot_no,
                'expiry_date' => optional($lot->expiry_date)->format('Y-m-d'),
            ]);

        $history = InventoryAlertLog::query()
            ->with(['item', 'lot'])
            ->latest()
            ->paginate(20);

        $historyData = $history->getCollection()->map(function (InventoryAlertLog $log) {
            $targetLabel = $log->item?->name ?? ($log->item_id ? 'Item #'.$log->item_id : '-');

            if ($log->alert_type === 'EXPIRY') {
                $lotNo = $log->lot?->lot_no ?? ($log->lot_id ? 'Lot #'.$log->lot_id : '-');
                $targetLabel = $targetLabel.' · '.$lotNo;
            }

            return [
                'id' => $log->id,
                'alert_type' => $log->alert_type,
                'target_label' => $targetLabel,
                'sent_count' => is_array($log->sent_to) ? count($log->sent_to) : 0,
                'failed_count' => is_array($log->failed_to) ? count($log->failed_to) : 0,
                'created_at_human' => optional($log->created_at)->format('Y-m-d H:i'),
            ];
        })->values();

        return response()->json([
            'expiry_days' => $expiryDays,
            'low_stock' => $lowStockItems,
            'expiring' => $expiringLots,
            'recipients' => $recipients,
            'history' => [
                'data' => $historyData,
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    // --- Groups & Settings ---

    public function getGroups(): JsonResponse
    {
        // Use getJoinedGroups() to get ALL groups the bot has joined (not just chats)
        $result = $this->gowaClient->getJoinedGroups();

        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Failed to fetch groups'], 500);
        }

        $groups = collect($result['groups'] ?? [])
            ->filter(fn ($group) => str_ends_with($group['JID'] ?? $group['jid'] ?? '', '@g.us'))
            ->map(fn ($group) => [
                'jid' => $group['JID'] ?? $group['jid'],
                'name' => $group['Name'] ?? $group['name'] ?? 'Unknown Group',
                'participant_count' => count($group['Participants'] ?? []),
            ])
            ->values();

        return response()->json(['groups' => $groups]);
    }

    public function getGroupParticipants(string $jid): JsonResponse
    {
        return response()->json($this->gowaClient->getGroupParticipants($jid));
    }

    public function getSettings(): JsonResponse
    {
        $aiProvider = (string) settings('notifications.whatsapp.ai.provider', 'openai');
        $aiBaseUrl = (string) settings('notifications.whatsapp.ai.base_url', config('services.ai.base_url', 'https://api.openai.com/v1'));
        $aiModel = (string) settings('notifications.whatsapp.ai.model', config('services.ai.model', 'gpt-4o-mini'));
        $hasAiApiKey = (bool) settings('notifications.whatsapp.ai.api_key') || ! empty((string) config('services.ai.key'));

        return response()->json([
            'base_url' => settings('notifications.whatsapp.base_url'),
            'basic_user' => settings('notifications.whatsapp.basic_user'),
            'device_id' => settings('notifications.whatsapp.device_id'),
            'inventory_alert_expiry_days' => (int) settings('inventory.alert_expiry_days', 30),
            'ai_provider' => $aiProvider,
            'ai_base_url' => $aiBaseUrl,
            'ai_model' => $aiModel,
            'ai_api_key_configured' => $hasAiApiKey,
            // Don't return password
        ]);
    }

    public function getWhitelist(WhitelistService $whitelistService): JsonResponse
    {
        $whitelist = $whitelistService->getAll()->map(fn (WhatsappWhitelist $item) => [
            'id' => $item->id,
            'phone_number' => $item->phone_number,
            'name' => $item->name,
            'added_by' => $item->added_by,
            'created_at_human' => $item->created_at?->diffForHumans(),
        ]);

        $superAdminNumber = $whitelistService->normalizePhoneNumber(
            (string) settings('notifications.whatsapp.admin_number', '6285956592404')
        );

        return response()->json([
            'whitelist' => $whitelist,
            'super_admin' => [
                'phone_number' => $superAdminNumber,
                'name' => 'Super Admin',
            ],
        ]);
    }

    public function storeWhitelist(Request $request, WhitelistService $whitelistService): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:8|max:25',
            'name' => 'nullable|string|max:100',
        ]);

        $normalized = $whitelistService->normalizePhoneNumber($validated['phone']);

        if ($whitelistService->isSuperAdmin($normalized)) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor ini adalah Super Admin dan tidak perlu ditambahkan ke whitelist.',
            ], 422);
        }

        if (WhatsappWhitelist::where('phone_number', $normalized)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor sudah terdaftar di whitelist.',
            ], 422);
        }

        $addedBy = $request->user()?->name ?? 'Web UI';

        $item = $whitelistService->add($normalized, $validated['name'] ?? null, $addedBy);

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil ditambahkan.',
            'item' => [
                'id' => $item->id,
                'phone_number' => $item->phone_number,
                'name' => $item->name,
                'added_by' => $item->added_by,
                'created_at_human' => $item->created_at?->diffForHumans() ?? 'baru saja',
            ],
        ], 201);
    }

    public function destroyWhitelist(WhatsappWhitelist $whitelist): JsonResponse
    {
        $whitelist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil dihapus.',
        ]);
    }

    public function updateWhitelistInventoryAlert(Request $request, WhatsappWhitelist $whitelist, WhitelistService $whitelistService): JsonResponse
    {
        $validated = $request->validate([
            'receive_inventory_alerts' => 'required|boolean',
        ]);

        $receiveInventoryAlerts = (bool) $validated['receive_inventory_alerts'];

        if (! $receiveInventoryAlerts && $whitelistService->isSuperAdmin((string) $whitelist->phone_number)) {
            if (! $whitelist->receive_inventory_alerts) {
                $whitelist->update(['receive_inventory_alerts' => true]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Super admin harus selalu menerima notifikasi inventory alert.',
            ], 422);
        }

        $whitelist->update([
            'receive_inventory_alerts' => $receiveInventoryAlerts,
        ]);

        $whitelist->refresh();

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $whitelist->id,
                'phone_number' => $whitelist->phone_number,
                'receive_inventory_alerts' => $whitelist->receive_inventory_alerts,
            ],
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_url' => 'required|url',
            'basic_user' => 'nullable|string',
            'basic_pass' => 'nullable|string',
            'device_id' => 'required|string',
            'inventory_alert_expiry_days' => 'nullable|integer|min:1|max:365',
            'ai_provider' => 'nullable|string|in:openai,openrouter,deepseek,custom',
            'ai_base_url' => 'nullable|url',
            'ai_model' => 'nullable|string|max:120',
            'ai_api_key' => 'nullable|string|max:500',
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

        if (array_key_exists('inventory_alert_expiry_days', $validated)) {
            SystemSetting::updateOrCreate(
                ['key' => 'inventory.alert_expiry_days'],
                ['value' => (int) ($validated['inventory_alert_expiry_days'] ?? 30)]
            );
        }

        if (array_key_exists('ai_provider', $validated) && ! empty($validated['ai_provider'])) {
            SystemSetting::updateOrCreate(
                ['key' => 'notifications.whatsapp.ai.provider'],
                ['value' => $validated['ai_provider']]
            );
        }

        if (array_key_exists('ai_base_url', $validated) && ! empty($validated['ai_base_url'])) {
            SystemSetting::updateOrCreate(
                ['key' => 'notifications.whatsapp.ai.base_url'],
                ['value' => rtrim($validated['ai_base_url'], '/')]
            );
        }

        if (array_key_exists('ai_model', $validated) && ! empty($validated['ai_model'])) {
            SystemSetting::updateOrCreate(
                ['key' => 'notifications.whatsapp.ai.model'],
                ['value' => trim($validated['ai_model'])]
            );
        }

        if (array_key_exists('ai_api_key', $validated) && ! empty($validated['ai_api_key']) && $validated['ai_api_key'] !== '••••••••') {
            SystemSetting::updateOrCreate(
                ['key' => 'notifications.whatsapp.ai.api_key'],
                ['value' => encrypt($validated['ai_api_key'])]
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

        $result = $this->outboundMessageService->sendText(
            $this->notificationService->formatJID($validated['phone']),
            $validated['message'],
            [
                'recipient_name' => $validated['phone'],
                'source_label' => 'Pesan uji Hub WhatsApp',
            ]
        );

        return response()->json($result, ($result['success'] ?? false) ? 200 : 502);
    }

    public function sendTestAi(Request $request, AiCommsService $aiService): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        try {
            $result = $aiService->generateMessage($validated['prompt']);

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjalankan AI test.',
            ], 500);
        }
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

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>|null
     */
    private function buildCountdownMetadata(array $validated, string $type, array $existing = []): ?array
    {
        if (! in_array($type, ['countdown', 'iso_countdown'], true)) {
            return null;
        }

        $metadata = $existing;

        if (isset($validated['target_date'])) {
            $metadata['target_date'] = $validated['target_date'];
        }

        if (isset($validated['event_name'])) {
            $metadata['event_name'] = $validated['event_name'];
        }

        if (isset($validated['event_emoji'])) {
            $metadata['event_emoji'] = $validated['event_emoji'];
        }

        if (isset($validated['milestones']) && is_array($validated['milestones'])) {
            $metadata['milestones'] = $this->normalizeMilestonesFromRequest($validated['milestones']);
        }

        return $metadata;
    }

    /**
     * @param  array<int, array<string, mixed>>  $milestones
     * @return array<string, string>
     */
    private function normalizeMilestonesFromRequest(array $milestones): array
    {
        $normalized = [];

        foreach ($milestones as $milestone) {
            $days = isset($milestone['days']) ? (int) $milestone['days'] : null;
            $message = trim((string) ($milestone['message'] ?? ''));

            if ($days === null || $days < 0 || $message === '') {
                continue;
            }

            $normalized[(string) $days] = $message;
        }

        krsort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function messageLogResponse(WhatsAppMessageLog $message, bool $includeAttempts = false): array
    {
        $response = [
            'id' => $message->id,
            'batch_id' => $message->batch_id,
            'recipient_jid' => $message->recipient_jid,
            'recipient_name' => $message->recipient_name,
            'recipient_type' => $message->recipient_type,
            'source_label' => $message->source_label,
            'status' => $message->status,
            'error_message' => $this->safeLogError($message->error_message),
            'attempt_count' => $message->attempt_count,
            'attempts_count' => $message->attempts_count ?? $message->attempt_count,
            'sent_at' => $message->sent_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
            'retry_available' => $message->canRetry() && $this->outboundMessageService->retryBlockReason($message) === null,
            'retry_block_reason' => $this->outboundMessageService->retryBlockReason($message),
        ];

        if ($includeAttempts) {
            $response['attempts'] = $message->attempts->map(fn ($attempt) => [
                'attempt_number' => $attempt->attempt_number,
                'status' => $attempt->status,
                'error_message' => $this->safeLogError($attempt->error_message),
                'started_at' => $attempt->started_at?->toISOString(),
                'completed_at' => $attempt->completed_at?->toISOString(),
            ])->values();
        }

        return $response;
    }

    private function safeLogError(?string $error): ?string
    {
        $error = trim((string) $error);
        if ($error === '') {
            return null;
        }

        $safePrefixes = [
            'Provider WhatsApp',
            'Koneksi ke provider WhatsApp',
            'Status pengiriman',
            'Worker berhenti',
            'Payload pengiriman',
            'Jenis payload',
            'Snapshot lampiran',
            'Lampiran sumber',
            'File tidak dapat',
            'Ukuran file',
            'Tipe file',
            'Gagal membuka file',
        ];

        foreach ($safePrefixes as $prefix) {
            if (str_starts_with($error, $prefix)) {
                return mb_strimwidth($error, 0, 300, '...');
            }
        }

        return 'Pengiriman gagal. Detail provider tidak ditampilkan.';
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    private function auditRetryRequest(
        Request $request,
        WhatsAppMessageLog $messageLog,
        bool $queued,
        ?string $reason = null,
        ?array $before = null
    ): void {
        ActivityLogger::log(
            'WHATSAPP_OUTBOUND_RETRY_REQUESTED',
            null,
            $messageLog,
            $before ?? [
                'status' => $messageLog->status,
                'attempt_count' => $messageLog->attempt_count,
            ],
            [
                'status' => $messageLog->status,
                'attempt_count' => $messageLog->attempt_count,
            ],
            array_filter([
                'queued' => $queued,
                'reason' => $reason,
            ], static fn ($value): bool => $value !== null),
            $request->user()?->id,
            $request
        );
    }
}
