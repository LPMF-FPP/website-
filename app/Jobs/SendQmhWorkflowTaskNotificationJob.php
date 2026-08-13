<?php

namespace App\Jobs;

use App\Models\QmhDocumentRevision;
use App\Models\StaffTask;
use App\Services\Quality\QmhRevisionDownloadService;
use App\Services\WhatsApp\NotificationService;
use App\Services\WhatsApp\OutboundMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendQmhWorkflowTaskNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $taskId,
        public string $actionCode
    ) {}

    public function handle(
        OutboundMessageService $outboundMessageService,
        NotificationService $notificationService,
        QmhRevisionDownloadService $downloadService
    ): void {
        if (! $notificationService->isWhatsAppEnabled()) {
            return;
        }

        $task = StaffTask::query()->with('assignee')->find($this->taskId);
        if (! $task) {
            return;
        }

        if ($task->notification_sent) {
            return;
        }

        if ($task->source_module !== StaffTask::SOURCE_MODULE_QMH || $task->source_ref_type !== StaffTask::SOURCE_REF_TYPE_QMH_REVISION) {
            return;
        }

        $assignee = $task->assignee;
        if (! $assignee || ! is_string($assignee->phone) || trim($assignee->phone) === '') {
            Log::warning('QMH WA notification skipped: assignee phone missing', ['task_id' => $task->id]);

            return;
        }

        $jid = $notificationService->formatJID($assignee->phone);
        $revision = QmhDocumentRevision::query()->with('document')->find((int) $task->source_ref_id);
        if (! $revision) {
            Log::warning('QMH WA notification skipped: revision missing', ['task_id' => $task->id]);

            return;
        }

        $message = $this->buildMessage($task, $revision);
        $caption = $this->buildCaption($task, $revision);

        $attachmentPath = null;
        $attachmentFilename = sprintf('QMH-%s-%s.pdf', $revision->document?->doc_code ?? 'DOC', $revision->version_label ?? 'draft');

        try {
            $attachmentPath = $this->prepareAttachment($revision, $downloadService);

            if ($attachmentPath !== null) {
                $sendFile = $outboundMessageService->sendFile($jid, $attachmentPath, $caption, $attachmentFilename, [
                    'recipient_name' => $assignee->name,
                    'source_type' => StaffTask::class,
                    'source_id' => $task->id,
                    'source_label' => 'Notifikasi workflow QMH',
                    'idempotency_key' => $this->deliveryKey($task->id, 'attachment'),
                ]);

                if ($sendFile['success'] ?? false) {
                    $task->update([
                        'notification_sent' => true,
                        'notification_sent_at' => now(),
                    ]);

                    return;
                }

                if (($sendFile['state'] ?? null) === 'unknown') {
                    Log::warning('QMH WA attachment delivery is uncertain; text fallback suppressed', [
                        'task_id' => $task->id,
                    ]);

                    return;
                }

                Log::warning('QMH WA attachment send failed, fallback to text', [
                    'task_id' => $task->id,
                    'status' => $sendFile['status'] ?? null,
                    'error' => $sendFile['error'] ?? null,
                ]);
            }

            $sendText = $outboundMessageService->sendText($jid, $message, [
                'recipient_name' => $assignee->name,
                'source_type' => StaffTask::class,
                'source_id' => $task->id,
                'source_label' => 'Notifikasi workflow QMH',
                'idempotency_key' => $this->deliveryKey($task->id, 'text'),
            ]);
            if ($sendText['success'] ?? false) {
                $task->update([
                    'notification_sent' => true,
                    'notification_sent_at' => now(),
                ]);

                return;
            }

            Log::error('QMH WA text fallback failed', [
                'task_id' => $task->id,
                'error' => $sendText['error'] ?? null,
                'status' => $sendText['status'] ?? null,
            ]);
        } finally {
            if ($attachmentPath !== null && is_file($attachmentPath)) {
                @unlink($attachmentPath);
            }
        }
    }

    private function buildMessage(StaffTask $task, QmhDocumentRevision $revision): string
    {
        $stage = $task->workflow_stage === StaffTask::WORKFLOW_STAGE_APPROVAL ? '✅ Approval' : '🔎 Review';
        $dueAt = $task->due_at?->format('d M Y H:i') ?? '-';
        $docCode = (string) ($revision->document?->doc_code ?? '-');
        $version = (string) ($revision->version_label ?? '-');

        return "👋 Yth. Bapak/Ibu, berikut notifikasi tugas workflow QMH Anda.\n\n"
            ."🔔 *QMH Workflow Task*\n"
            ."📄 *Dokumen* : {$docCode} ({$version})\n"
            ."🧭 *Tahap*   : {$stage}\n"
            ."⏰ *Batas respon* : {$dueAt}\n\n"
            ."Silakan pilih aksi berikut (copy-paste):\n"
            ."✅ *Setujui*\n"
            ."`/qmh {$task->id} approve {$this->actionCode}`\n"
            ."🛑 *Kembalikan / Reject*\n"
            ."`/qmh {$task->id} reject {$this->actionCode} alasan Anda`\n\n"
            ."ℹ️ Bantuan cepat:\n"
            ."• `/qmh inbox` untuk daftar task aktif\n"
            ."• `/qmh bantuan` untuk panduan command\n\n"
            .'Terima kasih atas kerja sama profesionalnya. 🙏';
    }

    private function buildCaption(StaffTask $task, QmhDocumentRevision $revision): string
    {
        $docCode = (string) ($revision->document?->doc_code ?? '-');

        return "📎 Dokumen QMH {$docCode} terlampir. Ketik /qmh inbox untuk aksi cepat approve/reject.";
    }

    private function prepareAttachment(QmhDocumentRevision $revision, QmhRevisionDownloadService $downloadService): ?string
    {
        $sourcePath = trim((string) ($revision->source_pdf_path ?? ''));
        $sourceDisk = trim((string) ($revision->source_pdf_disk ?? ''));

        if ($sourcePath !== '' && $sourceDisk !== '' && Storage::disk($sourceDisk)->exists($sourcePath)) {
            $binary = Storage::disk($sourceDisk)->get($sourcePath);
            if ($binary !== '') {
                return $this->writeTempPdf($binary);
            }
        }

        try {
            $binary = $downloadService->renderPdfBinary($revision, 'SALINAN TERKENDALI');

            return $this->writeTempPdf($binary);
        } catch (\Throwable $e) {
            Log::warning('QMH WA attachment render fallback failed', [
                'revision_id' => $revision->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function writeTempPdf(string $binary): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'qmh-wa-');
        if (! is_string($tmp) || $tmp === '') {
            return null;
        }

        $pdfPath = $tmp.'.pdf';
        @unlink($tmp);

        $written = @file_put_contents($pdfPath, $binary);
        if ($written === false) {
            return null;
        }

        return $pdfPath;
    }

    private function deliveryKey(int $taskId, string $kind): string
    {
        return hash_hmac(
            'sha256',
            implode('|', ['qmh-workflow-task', $taskId, $kind, $this->actionCode]),
            (string) config('app.key')
        );
    }
}
