<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationsSecurityRequest;
use App\Http\Requests\Settings\NotificationTestRequest;
use App\Services\Notifications\NotificationTestService;
use App\Services\Settings\SettingsResponseBuilder;
use App\Services\Settings\SettingsWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class NotificationsController extends Controller
{
    public function __construct(
        private readonly SettingsWriter $writer,
        private readonly SettingsResponseBuilder $builder
    ) {}

    public function update(NotificationsSecurityRequest $request): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validated();

        $payload = [];

        if (isset($data['notifications'])) {
            $payload['notifications'] = $data['notifications'];
        }

        if (isset($data['smtp'])) {
            // Store SMTP config in settings (excluding password from plain storage)
            $smtp = $data['smtp'];
            $payload['smtp.host'] = $smtp['host'] ?? null;
            $payload['smtp.port'] = $smtp['port'] ?? null;
            $payload['smtp.username'] = $smtp['username'] ?? null;
            $payload['smtp.from_address'] = $smtp['from_address'] ?? null;
            $payload['smtp.from_name'] = $smtp['from_name'] ?? null;

            // Only store password if provided (non-empty)
            if (! empty($smtp['password'])) {
                $payload['smtp.password'] = encrypt($smtp['password']);
            }
        }

        if (isset($data['security']['roles'])) {
            $payload['security.roles'] = $data['security']['roles'];
        }

        $this->writer->put($payload, 'UPDATE_NOTIFICATIONS_SECURITY', $request->user());

        $snapshot = $this->builder->build();

        return response()->json([
            'notifications' => Arr::get($snapshot, 'notifications', []),
            'smtp' => Arr::get($snapshot, 'smtp', []),
            'security' => Arr::get($snapshot, 'security', []),
        ]);
    }

    public function test(NotificationTestRequest $request, NotificationTestService $service): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validated();

        $result = $service->send(
            $data['channel'],
            $data['target'],
            $data['message']
        );

        $status = $result['status'] === 'delivered' ? 200 : 422;

        return response()->json($result, $status);
    }
}
