<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use App\Support\QmhAnswerSanitizer;
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

        $watermark = $copyType === 'controlled' ? 'SALINAN TERKENDALI' : 'SALINAN TIDAK TERKENDALI';

        $binary = $this->renderPdfBinary($revision, $watermark);

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

    public function buildWatermarkedHtml(QmhDocumentRevision $revision, string $watermarkText, ?int $resolvedPageCount = null): string
    {
        $revision->loadMissing(['document.parentSop', 'document.pairedIk', 'template', 'createdBy', 'reviewedBy', 'approvedBy']);

        $schema = $this->resolveFormSchema($revision);
        $answers = QmhAnswerSanitizer::sanitizeAnswersJson($revision->answers_json);

        return view('pdf.qmh-document', [
            'revision' => $revision,
            'schema' => $schema,
            'answers' => $answers,
            'watermarkText' => $watermarkText,
            'resolvedPageCount' => $resolvedPageCount,
        ])->render();
    }

    public function renderPdfBinary(QmhDocumentRevision $revision, string $watermarkText, bool $remoteEnabled = false): string
    {
        $probeHtml = $this->buildWatermarkedHtml($revision, $watermarkText);

        $probePdf = Pdf::loadHTML($probeHtml)
            ->setPaper('a4')
            ->setWarnings(false)
            ->setOption('isRemoteEnabled', $remoteEnabled)
            ->setOption('isHtml5ParserEnabled', true);

        $pageCount = 1;
        try {
            $probePdf->render();

            $dompdf = $probePdf->getDomPDF();
            if (is_object($dompdf) && method_exists($dompdf, 'getCanvas')) {
                $canvas = $dompdf->getCanvas();
                if (is_object($canvas) && method_exists($canvas, 'get_page_count')) {
                    $pageCount = max(1, (int) $canvas->get_page_count());
                }
            }
        } catch (\Throwable) {
            $pageCount = 1;
        }

        $finalHtml = $this->buildWatermarkedHtml($revision, $watermarkText, $pageCount);

        return Pdf::loadHTML($finalHtml)
            ->setPaper('a4')
            ->setWarnings(false)
            ->setOption('isRemoteEnabled', $remoteEnabled)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFormSchema(QmhDocumentRevision $revision): array
    {
        $docType = (string) ($revision->document?->doc_type ?? '');
        $templateMeta = is_array($revision->template?->metadata) ? $revision->template->metadata : [];

        $revisionSchema = $revision->form_schema_json ?? null;
        if (is_array($revisionSchema)) {
            return $revisionSchema;
        }

        $schema = $templateMeta['form_schema'] ?? null;
        if (is_array($schema)) {
            return $schema;
        }

        return $this->defaultFormSchema($docType);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormSchema(string $docType): array
    {
        if ($docType === 'ik') {
            return [
                'version' => 1,
                'doc_type' => 'ik',
                'questions' => [
                    ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                    ['id' => 'scope', 'label' => 'Ruang Lingkup', 'type' => 'textarea', 'required' => true],
                    ['id' => 'responsibilities', 'label' => 'Tanggung Jawab', 'type' => 'textarea', 'required' => false],
                    ['id' => 'reference', 'label' => 'Acuan', 'type' => 'textarea', 'required' => false],
                    ['id' => 'instructions', 'label' => 'Instruksi Kerja', 'type' => 'textarea', 'required' => true],
                    ['id' => 'required_docs', 'label' => 'Dokumentasi Yang Diperlukan', 'type' => 'list', 'required' => false],
                    ['id' => 'closing', 'label' => 'Penutup', 'type' => 'textarea', 'required' => false],
                ],
            ];
        }

        if ($docType !== 'sop') {
            return [
                'version' => 1,
                'doc_type' => $docType,
                'questions' => [],
            ];
        }

        return [
            'version' => 1,
            'doc_type' => 'sop',
            'questions' => [
                ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                ['id' => 'scope', 'label' => 'Ruang Lingkup', 'type' => 'textarea', 'required' => true],
                ['id' => 'definitions', 'label' => 'Definisi', 'type' => 'list', 'required' => false],
                ['id' => 'references', 'label' => 'Referensi', 'type' => 'list', 'required' => false],
                ['id' => 'procedure', 'label' => 'Prosedur', 'type' => 'textarea', 'required' => true],
                ['id' => 'records', 'label' => 'Rekaman / Form Terkait', 'type' => 'list', 'required' => false],
                ['id' => 'responsibilities', 'label' => 'Tanggung Jawab', 'type' => 'textarea', 'required' => false],
                ['id' => 'attachments', 'label' => 'Lampiran', 'type' => 'list', 'required' => false],
            ],
        ];
    }

    private function generatePdfBinary(QmhDocumentRevision $revision, string $watermarkText): string
    {
        return $this->renderPdfBinary($revision, $watermarkText);
    }
}
