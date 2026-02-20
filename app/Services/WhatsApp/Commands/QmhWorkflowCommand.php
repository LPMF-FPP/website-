<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\QmhDocumentRevision;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\Quality\QmhRevisionApprovalService;
use App\Services\Quality\QmhRevisionRejectionService;
use App\Services\Quality\QmhRevisionTransitionService;
use App\Support\PhoneNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QmhWorkflowCommand
{
    public function __construct(
        private QmhRevisionTransitionService $transitionService,
        private QmhRevisionApprovalService $approvalService,
        private QmhRevisionRejectionService $rejectionService
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        $firstParam = strtolower((string) ($params[0] ?? ''));

        if ($firstParam === '' || in_array($firstParam, ['help', 'bantuan'], true)) {
            return $this->usageMessage();
        }

        if (in_array($firstParam, ['inbox', 'list', 'daftar'], true)) {
            return $this->buildInboxResponse($fromJid);
        }

        if (in_array($firstParam, ['approve', 'reject'], true)) {
            $reason = trim(implode(' ', array_slice($params, 1)));

            return $this->executeShortcut($fromJid, $firstParam, $reason);
        }

        if (count($params) < 3) {
            return "⚠️ Format command belum lengkap.\n"
                .'Gunakan: /qmh {task_id} {approve|reject} {action_code} [reason]\n'
                .'Atau ketik /qmh inbox untuk daftar command siap pakai.';
        }

        $taskId = (int) ($params[0] ?? 0);
        $action = strtolower((string) ($params[1] ?? ''));
        $actionCode = trim((string) ($params[2] ?? ''));
        $reason = trim(implode(' ', array_slice($params, 3)));
        $senderIdentity = PhoneNormalizer::toCanonicalDigits($fromJid);
        $rateLimitKey = sprintf('qmh-wa-action:%s:%d', $senderIdentity !== '' ? $senderIdentity : 'unknown', $taskId);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return '⏳ Permintaan Anda terlalu sering dalam waktu singkat. Mohon coba lagi beberapa menit lagi.';
        }

        if ($taskId <= 0 || ! in_array($action, ['approve', 'reject'], true) || $actionCode === '') {
            return '⚠️ Format command tidak valid. Cek kembali parameter /qmh Anda, atau ketik /qmh inbox untuk bantuan cepat.';
        }

        try {
            return DB::transaction(function () use ($fromJid, $taskId, $action, $actionCode, $reason, $rateLimitKey): string {
                $task = StaffTask::query()
                    ->with('assignee')
                    ->lockForUpdate()
                    ->find($taskId);

                if ($task === null) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '⚠️ Task QMH tidak ditemukan.';
                }

                if ($task->source_module !== StaffTask::SOURCE_MODULE_QMH || $task->source_ref_type !== StaffTask::SOURCE_REF_TYPE_QMH_REVISION) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '⚠️ Task ini bukan task workflow QMH.';
                }

                if (! in_array($task->status, [StaffTask::STATUS_PENDING, StaffTask::STATUS_IN_PROGRESS], true)) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '⚠️ Task ini sudah tidak aktif.';
                }

                if ($task->token_consumed_at !== null) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '⚠️ Action code sudah digunakan. Minta task terbaru dari sistem.';
                }

                if ($task->action_expires_at === null || $task->action_expires_at->isPast()) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '⚠️ Action code sudah kedaluwarsa. Minta task terbaru dari sistem.';
                }

                $expectedHash = (string) ($task->action_token_hash ?? '');
                $incomingHash = hash('sha256', $actionCode);
                if ($expectedHash === '' || ! hash_equals($expectedHash, $incomingHash)) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '⚠️ Action code tidak valid. Silakan ketik /qmh inbox untuk mendapatkan command terbaru.';
                }

                $assignee = $task->assignee;
                if (! $assignee instanceof User || ! $assignee->is_active) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '⚠️ Assignee task tidak valid atau tidak aktif. Mohon hubungi admin.';
                }

                if (! PhoneNormalizer::isSameIdentity($fromJid, (string) $assignee->phone)) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '🔒 Nomor pengirim tidak berwenang untuk task ini. Pastikan Anda menggunakan nomor yang terdaftar.';
                }

                if (! $assignee->hasPermission('qmh.create')) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '🔒 Anda tidak memiliki izin workflow QMH. Mohon hubungi admin untuk verifikasi akses.';
                }

                $revisionId = (int) ($task->source_ref_id ?? 0);
                $revision = QmhDocumentRevision::query()->lockForUpdate()->find($revisionId);
                if (! $revision instanceof QmhDocumentRevision) {
                    RateLimiter::hit($rateLimitKey, 300);

                    return '⚠️ Revisi QMH tidak ditemukan. Mohon ketik /qmh inbox untuk sinkronisasi task terbaru.';
                }

                if ($task->workflow_stage === StaffTask::WORKFLOW_STAGE_REVIEW) {
                    $message = $this->executeReviewStage($task, $revision, $assignee, $action, $reason);
                } elseif ($task->workflow_stage === StaffTask::WORKFLOW_STAGE_APPROVAL) {
                    $message = $this->executeApprovalStage($task, $revision, $assignee, $action, $reason);
                } else {
                    return '⚠️ Tahap workflow task tidak dikenali. Mohon hubungi admin.';
                }

                $task->status = StaffTask::STATUS_COMPLETED;
                $task->completed_at = now();
                $task->token_consumed_at = now();
                $task->notes = trim((string) $task->notes.'\nAksi WA: '.$action.' oleh user #'.$assignee->id.' @ '.now()->toDateTimeString());
                $task->save();

                RateLimiter::clear($rateLimitKey);

                return $message;
            });
        } catch (ValidationException|AuthorizationException) {
            RateLimiter::hit($rateLimitKey, 300);

            return '⚠️ Aksi belum dapat diproses. Pastikan status revisi, assignment, dan command masih valid, lalu coba lagi.';
        } catch (\Throwable) {
            RateLimiter::hit($rateLimitKey, 300);

            return '🚧 Terjadi kendala internal saat memproses aksi QMH. Silakan coba lagi sesaat lagi.';
        }
    }

    private function usageMessage(): string
    {
        return "👋 Halo, berikut panduan singkat command QMH.\n\n"
            ."📘 *Format command QMH*\n"
            ."1) /qmh inbox\n"
            ."2) /qmh {task_id} approve {action_code}\n"
            ."3) /qmh {task_id} reject {action_code} alasan\n\n"
            ."⚡ *Shortcut* (jika hanya ada 1 task aktif):\n"
            ."- /qmh approve\n"
            ."- /qmh reject alasan\n\n"
            .'💡 Tip: ketik /qmh inbox kapan saja untuk mendapatkan command siap copy-paste.';
    }

    private function executeShortcut(string $fromJid, string $action, string $reason): string
    {
        if ($action === 'reject' && $reason === '') {
            return '⚠️ Alasan reject wajib diisi agar tim dapat melakukan perbaikan. Contoh: /qmh reject perlu revisi format tabel.';
        }

        $assignee = $this->resolveAssigneeBySender($fromJid);
        if (! $assignee instanceof User) {
            return '⚠️ Nomor pengirim belum terdaftar sebagai assignee task QMH aktif.';
        }

        $activeTasks = $this->getActiveTasksForAssignee((int) $assignee->id);
        if ($activeTasks->count() === 0) {
            return 'ℹ️ Saat ini tidak ada task QMH aktif untuk nomor Anda.';
        }

        if ($activeTasks->count() > 1) {
            return 'ℹ️ Ada lebih dari satu task aktif. Ketik /qmh inbox untuk melihat daftar command siap pakai.';
        }

        /** @var StaffTask $task */
        $task = $activeTasks->first();
        $actionCode = $this->issueActionCode($task);
        $params = [(string) $task->id, $action, $actionCode];

        if ($action === 'reject') {
            $params[] = $reason;
        }

        return $this->execute($fromJid, $params);
    }

    private function buildInboxResponse(string $fromJid): string
    {
        $assignee = $this->resolveAssigneeBySender($fromJid);
        if (! $assignee instanceof User) {
            return '⚠️ Nomor pengirim belum terdaftar sebagai assignee workflow QMH aktif.';
        }

        $activeTasks = $this->getActiveTasksForAssignee((int) $assignee->id);
        if ($activeTasks->count() === 0) {
            return 'ℹ️ Saat ini tidak ada task QMH aktif untuk nomor Anda.';
        }

        $lines = [
            '📋 *Inbox Task QMH*',
            'Terima kasih, berikut daftar task Anda hari ini:',
            'Silakan copy-paste command berikut untuk aksi cepat:',
        ];

        foreach ($activeTasks as $index => $task) {
            $actionCode = $this->issueActionCode($task);
            $stageLabel = $this->taskStageLabel($task);
            $docCode = $this->taskDocCode($task);
            $dueAt = $task->due_at?->format('d M H:i') ?? '-';

            $lines[] = sprintf('%d) #%d [%s] %s (deadline %s)', $index + 1, $task->id, $stageLabel, $docCode, $dueAt);
            $lines[] = sprintf('   ✅ Approve: /qmh %d approve %s', $task->id, $actionCode);
            $lines[] = sprintf('   🛑 Reject : /qmh %d reject %s alasan', $task->id, $actionCode);
        }

        if ($activeTasks->count() === 1) {
            $lines[] = '⚡ Shortcut tersedia: /qmh approve atau /qmh reject alasan';
        }

        $lines[] = 'Jika ada kendala, kirim /qmh bantuan.';

        return implode("\n", $lines);
    }

    private function resolveAssigneeBySender(string $fromJid): ?User
    {
        /** @var User|null $assignee */
        $assignee = User::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->get()
            ->first(fn (User $user) => PhoneNormalizer::isSameIdentity($fromJid, (string) $user->phone));

        if (! $assignee instanceof User) {
            return null;
        }

        if (! $assignee->hasPermission('qmh.create')) {
            return null;
        }

        return $assignee;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, StaffTask>
     */
    private function getActiveTasksForAssignee(int $assigneeId)
    {
        return StaffTask::query()
            ->qmhWorkflow()
            ->where('assigned_to', $assigneeId)
            ->whereIn('status', [StaffTask::STATUS_PENDING, StaffTask::STATUS_IN_PROGRESS])
            ->orderBy('due_at')
            ->orderBy('created_at')
            ->get();
    }

    private function issueActionCode(StaffTask $task): string
    {
        $actionCode = strtoupper(Str::random(10));

        $task->action_token_hash = hash('sha256', $actionCode);
        $task->action_expires_at = now()->addMinutes(30);
        $task->token_consumed_at = null;
        $task->save();

        return $actionCode;
    }

    private function taskStageLabel(StaffTask $task): string
    {
        return $task->workflow_stage === StaffTask::WORKFLOW_STAGE_APPROVAL ? 'APPROVAL' : 'REVIEW';
    }

    private function taskDocCode(StaffTask $task): string
    {
        $context = is_array($task->context_json) ? $task->context_json : [];
        $docCode = trim((string) ($context['doc_code'] ?? ''));

        return $docCode !== '' ? $docCode : ('REV#'.$task->source_ref_id);
    }

    private function executeReviewStage(
        StaffTask $task,
        QmhDocumentRevision $revision,
        User $assignee,
        string $action,
        string $reason
    ): string {
        if ($action === 'reject') {
            if ($reason === '') {
                return '⚠️ Alasan reject wajib diisi untuk tahap review agar pembuat dokumen dapat menindaklanjuti.';
            }

            $this->transitionService->returnToDraft($revision, (int) $assignee->id, $reason);

            return '🛑 Review ditolak. Revisi dikembalikan ke draft. Terima kasih, catatan Anda sudah diteruskan ke pembuat dokumen.';
        }

        $context = is_array($task->context_json) ? $task->context_json : [];
        $approverId = (int) ($context['approver_id'] ?? $revision->disahkan_oleh ?? 0);
        if ($approverId <= 0) {
            return '⚠️ Approver belum ditetapkan untuk melanjutkan ke tahap approval. Mohon hubungi admin.';
        }

        $this->transitionService->passReview($revision, (int) $assignee->id, $approverId);

        return '✅ Review disetujui. Revisi diteruskan ke tahap approval. Terima kasih atas verifikasinya.';
    }

    private function executeApprovalStage(
        StaffTask $task,
        QmhDocumentRevision $revision,
        User $assignee,
        string $action,
        string $reason
    ): string {
        if ($action === 'reject') {
            if ($reason === '') {
                return '⚠️ Alasan reject wajib diisi untuk tahap approval agar revisi dapat diperbaiki dengan tepat.';
            }

            $this->rejectionService->rejectToDraft($revision, (int) $assignee->id, $reason);

            return '🛑 Approval ditolak. Revisi dikembalikan ke draft. Catatan perbaikan Anda sudah dicatat.';
        }

        $context = is_array($task->context_json) ? $task->context_json : [];
        $promoteToNewEdition = (bool) ($context['promote_to_new_edition'] ?? false);
        $promoteReason = $promoteToNewEdition ? (string) ($context['promote_reason'] ?? 'Promote via WhatsApp action') : null;

        $this->approvalService->approve(
            $revision,
            (int) $assignee->id,
            $promoteToNewEdition,
            $promoteReason,
            null,
            null,
            null,
            null,
            null
        );

        return '✅ Approval disetujui. Revisi berhasil dipublish. Terima kasih, proses dokumen sudah selesai dengan baik.';
    }
}
