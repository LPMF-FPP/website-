<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QmhRevisionDownloadService
{
    /**
     * @return array{binary: string, filename: string, watermark_text: string}
     */
    public function generateAndLogDownload(
        QmhDocumentRevision $revision,
        int $actorId,
        string $copyType,
        ?string $reason,
        ?string $distributionTarget,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $revision->loadMissing('document');

        if ($copyType === 'controlled' && $revision->status !== 'published') {
            throw ValidationException::withMessages([
                'copy_type' => 'Controlled copy hanya bisa diunduh dari revisi berstatus published.',
            ]);
        }

        if ($copyType === 'uncontrolled' && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan download wajib diisi untuk uncontrolled copy.',
            ]);
        }

        $watermark = $copyType === 'controlled' ? 'CONTROLLED COPY' : 'UNCONTROLLED COPY';

        $binary = $this->generatePdfBinary($revision, $watermark);

        $fileHash = hash('sha256', $binary);

        DB::transaction(function () use ($revision, $actorId, $copyType, $reason, $distributionTarget, $watermark, $fileHash, $ipAddress, $userAgent) {
            QmhDocumentDownloadLog::query()->create([
                'document_id' => $revision->document_id,
                'revision_id' => $revision->id,
                'edition_number' => $revision->edition_number,
                'revision_number' => $revision->revision_number,
                'copy_type' => $copyType,
                'downloaded_by' => $actorId,
                'downloaded_at' => now(),
                'reason' => $reason,
                'distribution_target' => $distributionTarget,
                'watermark_text' => $watermark,
                'file_hash' => $fileHash,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            QmhWorkflowEvent::query()->create([
                'revision_id' => $revision->id,
                'event_type' => 'download',
                'actor_id' => $actorId,
                'payload_json' => [
                    'copy_type' => $copyType,
                    'reason' => $reason,
                    'watermark_text' => $watermark,
                    'file_hash' => $fileHash,
                ],
            ]);
        });

        $safeDocCode = preg_replace('/[^A-Za-z0-9._-]+/', '_', $revision->document->doc_code ?? 'qmh-document');
        $filename = sprintf('%s-%s-%s.pdf', $safeDocCode, $revision->version_label, $copyType);

        return [
            'binary' => $binary,
            'filename' => $filename,
            'watermark_text' => $watermark,
        ];
    }

    public function buildWatermarkedHtml(QmhDocumentRevision $revision, string $watermarkText): string
    {
        $revision->loadMissing('document');

        $bodyHtml = $revision->content_html ?: '<p>Tidak ada konten.</p>';

        return view('pdf.qmh-document', [
            'revision' => $revision,
            'watermarkText' => $watermarkText,
            'contentHtml' => $bodyHtml,
        ])->render();
    }

    private function generatePdfBinary(QmhDocumentRevision $revision, string $watermarkText): string
    {
        $html = $this->buildWatermarkedHtml($revision, $watermarkText);

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setWarnings(false)
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }
}
