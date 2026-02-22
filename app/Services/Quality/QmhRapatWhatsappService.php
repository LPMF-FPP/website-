<?php

namespace App\Services\Quality;

use App\Models\QmhRapat;
use App\Models\User;
use App\Models\WhatsAppMessageBatch;
use App\Models\WhatsAppMessageLog;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use InvalidArgumentException;

class QmhRapatWhatsappService
{
    public function __construct(
        private readonly GowaClient $gowaClient,
        private readonly NotificationService $notificationService,
    ) {}

    public function renderSummaryPdf(QmhRapat $rapat): string
    {
        $rapat->loadMissing([
            'creator',
            'pesertas.user',
            'notulensis.creator',
            'actionItems.assignee',
        ]);

        return Pdf::loadView('pdf.qmh-rapat-summary', [
            'rapat' => $rapat,
        ])
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->output();
    }

    /**
     * @param  array{recipient_type: string, recipient_value: string, message?: string|null}  $payload
     * @return array{success: bool, error?: string, batch_id: int|null, recipient_jid?: string}
     */
    public function sendSummaryPdf(QmhRapat $rapat, User $actor, array $payload): array
    {
        [$recipientJid, $recipientType, $recipientLabel] = $this->resolveRecipient(
            (string) $payload['recipient_type'],
            (string) $payload['recipient_value']
        );

        $caption = trim((string) ($payload['message'] ?? ''));
        if ($caption === '') {
            $caption = 'Dokumen hasil rapat terlampir. Mohon ditinjau.';
        }

        $batch = WhatsAppMessageBatch::query()->create([
            'type' => 'qmh_rapat_summary',
            'source_type' => QmhRapat::class,
            'source_id' => $rapat->id,
            'title' => 'Hasil Rapat QMH: '.$rapat->title,
            'message_preview' => mb_substr($caption, 0, 1000),
            'total_recipients' => 1,
            'sent_count' => 0,
            'failed_count' => 0,
            'mention_all' => false,
            'started_at' => now(),
            'created_by' => $actor->id,
        ]);

        $log = WhatsAppMessageLog::query()->create([
            'batch_id' => $batch->id,
            'recipient_jid' => $recipientJid,
            'recipient_name' => $recipientLabel,
            'recipient_type' => $recipientType,
            'status' => 'pending',
        ]);

        $pdfPath = null;
        try {
            $pdfPath = $this->writeTempPdf($this->renderSummaryPdf($rapat));

            $result = $this->gowaClient->sendFile(
                $recipientJid,
                $pdfPath,
                $caption,
                $this->buildFilename($rapat)
            );

            $isSuccess = (bool) ($result['success'] ?? false);
            $log->update([
                'status' => $isSuccess ? 'sent' : 'failed',
                'message_id' => $result['message_id'] ?? null,
                'error_message' => $isSuccess ? null : ((string) ($result['error'] ?? 'Gagal mengirim file WhatsApp.')),
                'sent_at' => $isSuccess ? now() : null,
            ]);

            $batch->update([
                'sent_count' => $isSuccess ? 1 : 0,
                'failed_count' => $isSuccess ? 0 : 1,
                'completed_at' => now(),
            ]);

            if (! $isSuccess) {
                return [
                    'success' => false,
                    'error' => (string) ($result['error'] ?? 'Gagal mengirim file WhatsApp.'),
                    'batch_id' => $batch->id,
                    'recipient_jid' => $recipientJid,
                ];
            }

            return [
                'success' => true,
                'batch_id' => $batch->id,
                'recipient_jid' => $recipientJid,
            ];
        } finally {
            if (is_string($pdfPath) && $pdfPath !== '' && is_file($pdfPath)) {
                @unlink($pdfPath);
            }
        }
    }

    /**
     * @return array{string, string, string}
     */
    private function resolveRecipient(string $recipientType, string $recipientValue): array
    {
        $recipientType = trim($recipientType);
        $recipientValue = trim($recipientValue);

        if ($recipientType === 'group') {
            if ($recipientValue === '') {
                throw new InvalidArgumentException('Tujuan grup WhatsApp wajib diisi.');
            }

            $jid = str_ends_with($recipientValue, '@g.us')
                ? $recipientValue
                : $recipientValue.'@g.us';

            return [$jid, 'group', $jid];
        }

        if ($recipientType !== 'individual') {
            throw new InvalidArgumentException('Tipe penerima WhatsApp tidak valid.');
        }

        if ($recipientValue === '') {
            throw new InvalidArgumentException('Nomor WhatsApp penerima wajib diisi.');
        }

        $normalized = preg_replace('/[^0-9]/', '', $recipientValue) ?? '';
        if ($normalized === '') {
            throw new InvalidArgumentException('Nomor WhatsApp penerima tidak valid.');
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        $jid = $this->notificationService->formatJID($normalized);

        return [$jid, 'individual', $normalized];
    }

    private function writeTempPdf(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'qmh-rapat-');
        if (! is_string($tmp) || $tmp === '') {
            throw new InvalidArgumentException('Gagal menyiapkan file PDF sementara.');
        }

        $pdfPath = $tmp.'.pdf';
        @unlink($tmp);

        $written = @file_put_contents($pdfPath, $binary);
        if ($written === false) {
            throw new InvalidArgumentException('Gagal menulis file PDF sementara.');
        }

        return $pdfPath;
    }

    private function buildFilename(QmhRapat $rapat): string
    {
        $slug = Str::slug($rapat->title ?: 'hasil-rapat', '-');

        return 'hasil-rapat-'.($slug !== '' ? $slug : 'qmh').'-'.$rapat->id.'.pdf';
    }
}
