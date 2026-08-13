<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\WhatsappOutbox;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\MilestoneNotificationService;
use App\Services\WhatsApp\NotificationService;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WhatsAppSettingsController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private GowaClient $client,
        private MilestoneNotificationService $milestoneNotificationService,
        private TemplateService $templateService
    ) {}

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'base_url' => 'required|string|url',
            'device_id' => 'nullable|string|max:255',
            'basic_user' => 'nullable|string|max:255',
            'basic_pass' => 'nullable|string|max:255',
            'enabled_milestones' => 'nullable|array',
            'enabled_milestones.*' => 'string|in:'.implode(',', $this->notificationService->getAvailableMilestones()),
            'templates' => 'nullable|array',
            'templates.*' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['templates']) && is_array($data['templates'])) {
            $allowed = $this->notificationService->getAvailableMilestones();
            $invalidKeys = array_diff(array_keys($data['templates']), $allowed);

            if (! empty($invalidKeys)) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'templates' => ['Invalid milestone keys: '.implode(', ', $invalidKeys)],
                    ],
                ], 422);
            }

            $data['templates'] = array_filter(
                array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data['templates']),
                fn ($value) => is_string($value) && $value !== ''
            );
        }

        $deviceId = $data['device_id'] ?? '';
        $basicUser = $data['basic_user'] ?? '';
        $basicPass = $data['basic_pass'] ?? '';

        if ($basicPass === '••••••••') {
            $basicPass = settings('notifications.whatsapp.basic_pass', '');
        } elseif ($basicPass !== '') {
            $basicPass = encrypt($basicPass);
        }

        \App\Models\SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.enabled'], ['value' => $data['enabled']]);
        \App\Models\SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.base_url'], ['value' => rtrim($data['base_url'], '/')]);
        \App\Models\SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.device_id'], ['value' => $deviceId]);
        \App\Models\SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.basic_user'], ['value' => $basicUser]);
        \App\Models\SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.basic_pass'], ['value' => $basicPass]);
        \App\Models\SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.enabled_milestones'], ['value' => $data['enabled_milestones'] ?? []]);
        \App\Models\SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.templates'], ['value' => $data['templates'] ?? []]);

        settings_forget_cache();

        return response()->json([
            'message' => 'WhatsApp settings saved successfully',
            'data' => [
                'enabled' => $data['enabled'],
                'base_url' => rtrim($data['base_url'], '/'),
                'device_id' => $data['device_id'] ?? null,
                'basic_user' => $data['basic_user'] ?? null,
                'enabled_milestones' => $data['enabled_milestones'] ?? [],
                'templates' => $data['templates'] ?? [],
            ],
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'message' => 'nullable|string|max:2000',
            'milestone' => 'nullable|string|in:'.implode(',', $this->notificationService->getAvailableMilestones()),
            'category' => 'nullable|string|in:'.implode(',', $this->templateService->getCategories()),
            'key' => 'nullable|string|max:50',
            'template' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');
        $milestone = $request->input('milestone');
        $category = $request->input('category');
        $key = $request->input('key');
        $customTemplate = $request->input('template');

        // If category + key specified, use template preview
        if ($category && $key) {
            $message = $this->templateService->preview($category, $key, $customTemplate);
        } elseif ($milestone) {
            $greetings = $this->notificationService->getTimeBasedGreeting();
            $testResi = 'TEST-'.date('Ymd-His');
            $message = $this->notificationService->getMilestoneMessage($milestone, [
                'greetings' => $greetings,
                'greeting' => $greetings.' Bapak/Ibu (Test)',
                'pangkat' => 'IPDA',
                'nama' => 'User Test',
                'nomor surat' => 'TEST/001',
                'tersangka' => 'Tersangka Test',
                'reason' => 'Data belum lengkap',
                'resi' => $testResi,
            ]);
        } else {
            $message = $request->input('message', 'Test message from LPMF LIMS');
        }

        $jid = $this->notificationService->formatJID($phone);

        try {
            $outbox = $this->milestoneNotificationService->queue(
                null,
                'TEST',
                $phone,
                $jid,
                $phone,
                $message
            );

            return response()->json([
                'message' => 'Test message queued successfully',
                'data' => [
                    'outbox_id' => $outbox->id,
                    'jid' => $jid,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to queue test message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function appInfo(): JsonResponse
    {
        try {
            $info = $this->client->getAppInfo();

            return response()->json($info, $info['success'] ? 200 : 502);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkHealth(): JsonResponse
    {
        try {
            $health = $this->client->checkHealth();

            return response()->json([
                'message' => $health['reachable'] ? 'Service is reachable' : 'Service is unreachable',
                'data' => $health,
            ], $health['reachable'] ? 200 : 503);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Health check failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOutboxLogs(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 50);
        $limit = min(max((int) $limit, 1), 200);

        $logs = WhatsappOutbox::with(['testRequest:id,receipt_number'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($outbox) {
                return [
                    'id' => $outbox->id,
                    'test_request_id' => $outbox->test_request_id,
                    'milestone_key' => $outbox->milestone_key,
                    'receipt_number' => $outbox->testRequest?->receipt_number ?? null,
                    'to_phone' => $outbox->to_phone_e164,
                    'to_jid' => $outbox->to_jid,
                    'message' => $outbox->message_text,
                    'status' => $outbox->status,
                    'attempts' => $outbox->attempts,
                    'provider_message_id' => $outbox->provider_message_id,
                    'last_error' => $outbox->last_error,
                    'created_at' => $outbox->created_at->toISOString(),
                ];
            });

        return response()->json([
            'data' => $logs,
            'meta' => [
                'count' => $logs->count(),
                'limit' => $limit,
            ],
        ]);
    }

    public function getTemplates(): JsonResponse
    {
        return response()->json([
            'data' => $this->notificationService->getAllTemplates(),
        ]);
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

        // Handle masked password if user didn't change it but wants to check connection
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

        $result = $this->client->listDevicesWithCredentials($baseUrl, $basicUser, $basicPass);

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

    public function getDevices(): JsonResponse
    {
        try {
            $result = $this->client->listDevices();

            return response()->json([
                'success' => $result['success'],
                'devices' => $result['devices'],
                'error' => $result['error'] ?? null,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'devices' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all templates grouped by category.
     */
    public function getAllTemplates(): JsonResponse
    {
        return response()->json([
            'data' => [
                'templates' => $this->templateService->getAll(),
                'labels' => $this->templateService->getTemplateLabels(),
                'categories' => $this->templateService->getCategoryLabels(),
                'placeholders' => $this->templateService->getAllPlaceholders(),
            ],
        ]);
    }

    /**
     * Get templates for a specific category.
     */
    public function getTemplateCategory(string $category): JsonResponse
    {
        $validCategories = $this->templateService->getCategories();

        if (! in_array($category, $validCategories)) {
            return response()->json([
                'message' => 'Invalid category',
                'valid_categories' => $validCategories,
            ], 422);
        }

        return response()->json([
            'data' => [
                'category' => $category,
                'templates' => $this->templateService->getCategory($category),
                'defaults' => $this->templateService->getDefaults($category),
                'labels' => $this->templateService->getTemplateLabels()[$category] ?? [],
                'placeholders' => $this->templateService->getAllPlaceholders()[$category] ?? [],
            ],
        ]);
    }

    /**
     * Update a single template.
     */
    public function updateTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|in:'.implode(',', $this->templateService->getCategories()),
            'key' => 'required|string|max:50',
            'template' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = $request->input('category');
        $key = $request->input('key');
        $template = $request->input('template');

        try {
            $this->templateService->update($category, $key, $template);

            return response()->json([
                'message' => 'Template updated successfully',
                'data' => [
                    'category' => $category,
                    'key' => $key,
                    'template' => $template,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update multiple templates in a category.
     */
    public function updateTemplateCategory(Request $request, string $category): JsonResponse
    {
        $validCategories = $this->templateService->getCategories();

        if (! in_array($category, $validCategories)) {
            return response()->json([
                'message' => 'Invalid category',
                'valid_categories' => $validCategories,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'templates' => 'required|array',
            'templates.*' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->templateService->updateCategory($category, $request->input('templates'));

            return response()->json([
                'message' => 'Templates updated successfully',
                'data' => [
                    'category' => $category,
                    'templates' => $this->templateService->getCategory($category),
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update templates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset a template to default.
     */
    public function resetTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|in:'.implode(',', $this->templateService->getCategories()),
            'key' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = $request->input('category');
        $key = $request->input('key');

        try {
            $defaultTemplate = $this->templateService->resetToDefault($category, $key);

            return response()->json([
                'message' => 'Template reset to default successfully',
                'data' => [
                    'category' => $category,
                    'key' => $key,
                    'template' => $defaultTemplate,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to reset template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview a template with sample data.
     */
    public function previewTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|in:'.implode(',', $this->templateService->getCategories()),
            'key' => 'required|string|max:50',
            'template' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = $request->input('category');
        $key = $request->input('key');
        $customTemplate = $request->input('template');

        try {
            $preview = $this->templateService->preview($category, $key, $customTemplate);

            return response()->json([
                'data' => [
                    'category' => $category,
                    'key' => $key,
                    'preview' => $preview,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to preview template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
