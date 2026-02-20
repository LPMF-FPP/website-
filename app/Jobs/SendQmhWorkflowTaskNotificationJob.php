<?php

namespace App\Jobs;

use App\Models\QmhDocumentRevision;
use App\Models\StaffTask;
use App\Services\Quality\QmhRevisionDownloadService;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\NotificationService;
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

    public int $tries = 4;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $taskId,
        public string $actionCode
    ) {}

    public function handle(
        GowaClient $client,
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
                $sendFile = $client->sendFile($jid, $attachmentPath, $caption, $attachmentFilename);

                if ($sendFile['success'] ?? false) {
                    $task->update([
                        'notification_sent' => true,
                        'notification_sent_at' => now(),
                    ]);

                    return;
                }

                Log::warning('QMH WA attachment send failed, fallback to text', [
                    'task_id' => $task->id,
                    'status' => $sendFile['status'] ?? null,
                    'error' => $sendFile['error'] ?? null,
                ]);
            }

            $sendText = $client->sendMessage($jid, $message);
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
        $stage = $task->workflow_stage === StaffTask::WORKFLOW_STAGE_APPROVAL ? 'Approval' : 'Review';
        $dueAt = $task->due_at?->format('d M Y H:i') ?? '-';
        $docCode = (string) ($revision->document?->doc_code ?? '-');
        $version = (string) ($revision->version_label ?? '-');

        return "Yth. Tim terkait, berikut pemberitahuan workflow QMH Anda.\n\n"
            ."📌 *QMH Workflow Task*\n"
            ."Tahap: *{$stage}*\n"
            ."Dokumen: *{$docCode}* ({$version})\n"
            ."Deadline respon: *{$dueAt}*\n\n"
            ."Silakan pilih aksi dengan menyalin salah satu perintah berikut:\n"
            ."✅ Approve: `/qmh {$task->id} approve {$this->actionCode}`\n"
            ."🛑 Reject : `/qmh {$task->id} reject {$this->actionCode} alasan Anda`\n\n"
            ."Jika butuh daftar task/command terbaru, ketik: `/qmh inbox`\n"
            .'Terima kasih atas kolaborasinya 🙏';
    }

    private function buildCaption(StaffTask $task, QmhDocumentRevision $revision): string
    {
        $docCode = (string) ($revision->document?->doc_code ?? '-');

        return "Dokumen QMH {$docCode} terlampir. Ketik /qmh inbox untuk opsi aksi interaktif.";
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
}
