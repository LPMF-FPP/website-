<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WhatsApp\RequestGowaPreparation;
use App\Http\Requests\WhatsApp\RequestGowaUpdate;
use App\Http\Requests\WhatsApp\RetryGowaUpdate;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdatePreparation;
use App\Services\WhatsApp\GowaUpdatePreparationService;
use App\Services\WhatsApp\GowaUpdateService;
use App\Services\WhatsApp\GowaUpstreamReleaseChecker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class GowaUpdateController extends Controller
{
    public function __construct(
        private readonly GowaUpdateService $service,
        private readonly GowaUpdatePreparationService $preparations,
        private readonly GowaUpstreamReleaseChecker $releaseChecker,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $data = $this->service->status();
        $permissions = [
            'can_request' => $request->user()?->hasPermission('gowa-update.request') === true,
            'can_retry' => $request->user()?->hasPermission('gowa-update.retry') === true,
            'can_detail' => $request->user()?->hasPermission('gowa-update.detail') === true,
        ];
        if ($data['latest_operation'] !== null) {
            $operation = GowaUpdateOperation::query()->where('scope', GowaUpdateOperation::SCOPE)->find($data['latest_operation']['id']);
            $data['latest_operation'] = $operation === null ? null : $this->service->operationProjection($operation, $permissions);
        }
        $data['can_request'] = $permissions['can_request'];
        $data['can_retry'] = $permissions['can_retry'] && (bool) ($data['latest_operation']['can_retry'] ?? false);
        $data['can_detail'] = $permissions['can_detail'];
        $preparation = GowaUpdatePreparation::query()
            ->where('requested_by', $request->user()?->id)
            ->latest('created_at')
            ->first();
        $data['latest_preparation'] = $preparation?->safeProjection();
        $runtime = $data['runtime'] ?? [];
        $preparationMatchesRuntime = is_array($data['latest_preparation'])
            && ($data['latest_preparation']['ready'] ?? false) === true
            && ($runtime['digest'] ?? null) === $preparation?->runtime_digest
            && ($runtime['container_identity'] ?? null) === $preparation?->container_identity;
        $data['can_install'] = $permissions['can_request']
            && (bool) $data['available']
            && $preparationMatchesRuntime;

        return response()->json(['data' => $data, 'message' => 'Status pembaruan GOWA tersedia.']);
    }

    public function check(): JsonResponse
    {
        try {
            $data = $this->releaseChecker->check(true);
        } catch (ConnectionException|RuntimeException) {
            return response()->json([
                'message' => 'Pemeriksaan pembaruan gagal. Coba lagi beberapa saat.',
                'code' => 'upstream_release_unavailable',
            ], 503);
        }

        return response()->json(['data' => $data, 'message' => 'Pemeriksaan pembaruan selesai.']);
    }

    public function prepare(RequestGowaPreparation $request): JsonResponse
    {
        try {
            $preparation = $this->preparations->startLatest(
                $request->string('action_uuid')->toString(),
                (int) $request->user()->id,
            );
        } catch (RuntimeException $exception) {
            $code = $this->safeCode($exception->getMessage());

            return response()->json(['message' => 'Paket pembaruan belum dapat disiapkan.', 'code' => $code], in_array($code, ['preparation_runner_unavailable', 'upstream_release_unavailable'], true) ? 503 : 409);
        }

        return response()->json(['data' => $preparation->safeProjection(), 'message' => 'Persiapan rilis GOWA masuk antrean.'], 202);
    }

    public function preparation(Request $request, string $preparation): JsonResponse
    {
        $record = $this->preparations->forUser($preparation, (int) $request->user()->id);

        return response()->json(['data' => $record->safeProjection(), 'message' => 'Status persiapan rilis tersedia.']);
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
            $preparation = $this->preparations->forUser($request->string('preparation_id')->toString(), (int) $request->user()->id);
            $operation = $this->service->create(
                (string) $preparation->release_id,
                $request->string('action_uuid')->toString(),
                (int) $request->user()->id,
                preparationId: $request->string('preparation_id')->toString(),
            );
        } catch (RuntimeException $exception) {
            $code = $this->safeCode($exception->getMessage());

            return response()->json(['message' => 'Pembaruan GOWA belum dapat dimulai.', 'code' => $code], in_array($code, ['privileged_runner_unavailable', 'upstream_release_unavailable'], true) ? 503 : 409);
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
            'release_not_allowed', 'release_not_latest', 'upstream_release_unavailable', 'update_already_active', 'idempotency_payload_mismatch',
            'privileged_runner_unavailable', 'runtime_evidence_unavailable', 'runtime_evidence_stale', 'preparation_not_ready',
            'preparation_runner_unavailable', 'preparation_failed', 'preparation_result_invalid',
            'preparation_evidence_mismatch', 'preparation_already_active', 'no_update_available', 'operation_not_retryable',
            'preparation_queue_unavailable',
        ], true) ? $code : 'update_unavailable';
    }
}
