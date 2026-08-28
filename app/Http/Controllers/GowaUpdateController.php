<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WhatsApp\RequestGowaUpdate;
use App\Http\Requests\WhatsApp\RetryGowaUpdate;
use App\Models\GowaUpdateOperation;
use App\Services\WhatsApp\GowaUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class GowaUpdateController extends Controller
{
    public function __construct(private readonly GowaUpdateService $service) {}

    public function status(Request $request): JsonResponse
    {
        $data = $this->service->status();
        $permissions = [
            'can_request' => $request->user()?->hasPermission('gowa-update.request') === true,
            'can_retry' => $request->user()?->hasPermission('gowa-update.retry') === true,
            'can_detail' => $request->user()?->hasPermission('gowa-update.detail') === true,
        ];
        if ($data['latest_operation'] !== null) {
            $operation = GowaUpdateOperation::query()->find($data['latest_operation']['id']);
            $data['latest_operation'] = $operation === null ? null : $this->service->operationProjection($operation, $permissions);
        }
        $data['can_request'] = $permissions['can_request'];
        $data['can_retry'] = $permissions['can_retry'] && (bool) ($data['latest_operation']['can_retry'] ?? false);
        $data['can_detail'] = $permissions['can_detail'];

        return response()->json(['data' => $data, 'message' => 'Status pembaruan GOWA tersedia.']);
    }

    public function detail(Request $request, GowaUpdateOperation $operation): JsonResponse
    {
        $this->ensureGowaScope($operation);

        return response()->json(['data' => $this->service->operationProjection($operation, [
            'can_request' => $request->user()?->hasPermission('gowa-update.request') === true,
            'can_retry' => $request->user()?->hasPermission('gowa-update.retry') === true,
            'can_detail' => true,
        ]), 'message' => 'Detail operasi tersedia.']);
    }

    public function requestUpdate(RequestGowaUpdate $request): JsonResponse
    {
        try {
            $operation = $this->service->create($request->string('release_id')->toString(), $request->string('action_uuid')->toString(), (int) $request->user()->id);
        } catch (RuntimeException $exception) {
            $code = $this->safeCode($exception->getMessage());

            return response()->json(['message' => 'Pembaruan GOWA belum dapat dimulai.', 'code' => $code], $code === 'privileged_runner_unavailable' ? 503 : 409);
        }

        return response()->json(['data' => $operation->safeProjection(), 'message' => 'Pembaruan GOWA masuk antrean.'], 202);
    }

    public function retry(RetryGowaUpdate $request, GowaUpdateOperation $operation): JsonResponse
    {
        $this->ensureGowaScope($operation);

        try {
            $retry = $this->service->retry($operation, (int) $request->user()->id);
        } catch (RuntimeException $exception) {
            $code = $this->safeCode($exception->getMessage());

            return response()->json(['message' => 'Operasi belum dapat diulang.', 'code' => $code], $code === 'privileged_runner_unavailable' ? 503 : 409);
        }

        return response()->json(['data' => $retry->safeProjection(), 'message' => 'Percobaan ulang masuk antrean.'], 202);
    }

    public function audit(Request $request, GowaUpdateOperation $operation): JsonResponse
    {
        $this->ensureGowaScope($operation);

        return response()->json(['data' => $operation->events()->latest('occurred_at')->limit(50)->get()->map(fn ($event): array => [
            'code' => $event->code,
            'from' => $event->from_state,
            'to' => $event->to_state,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'meta' => $event->safe_meta,
        ]), 'message' => 'Riwayat operasi tersedia.']);
    }

    private function ensureGowaScope(GowaUpdateOperation $operation): void
    {
        abort_unless($operation->scope === 'gowa', 404);
    }

    private function safeCode(string $code): string
    {
        return in_array($code, [
            'release_not_allowed', 'update_already_active', 'idempotency_payload_mismatch',
            'privileged_runner_unavailable', 'runtime_evidence_unavailable', 'operation_not_retryable',
        ], true) ? $code : 'update_unavailable';
    }
}
