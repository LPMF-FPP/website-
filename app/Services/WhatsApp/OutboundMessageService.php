<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Jobs\SendPersistedWhatsAppMessage;
use App\Models\WhatsAppMessageAttempt;
use App\Models\WhatsAppMessageBatch;
use App\Models\WhatsAppMessageLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OutboundMessageService
{
    private const PRIVATE_ATTACHMENT_DISK = 'local';

    public function __construct(private readonly GowaClient $gowaClient) {}

    /**
     * Persist a text envelope and schedule its delivery after any active transaction commits.
     *
     * @param  array<string, mixed>  $options
     */
    public function queueText(string $recipientJid, string $message, array $options = []): WhatsAppMessageLog
    {
        $log = DB::transaction(function () use ($recipientJid, $message, $options): WhatsAppMessageLog {
            $log = $this->persistText($recipientJid, $message, $options);

            $this->queueIfPending($log);

            return $log;
        }, attempts: 3);

        return $log;
    }

    /**
     * Persist a file envelope and schedule its delivery after any active transaction commits.
     *
     * @param  array<string, mixed>  $options
     */
    public function queueFile(
        string $recipientJid,
        string $filePath,
        ?string $caption = null,
        ?string $filename = null,
        array $options = []
    ): WhatsAppMessageLog {
        $log = DB::transaction(function () use ($recipientJid, $filePath, $caption, $filename, $options): WhatsAppMessageLog {
            $log = $this->persistFile($recipientJid, $filePath, $caption, $filename, $options);

            $this->queueIfPending($log);

            return $log;
        }, attempts: 3);

        if ($log->status !== WhatsAppMessageLog::STATUS_PENDING) {
            $this->syncBatchStats($log->batch_id);
        }

        return $log;
    }

    /**
     * Persist a text envelope before calling the provider. This preserves synchronous source behavior.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendText(string $recipientJid, string $message, array $options = []): array
    {
        $log = $this->persistText($recipientJid, $message, $options);

        return $this->deliver($log->id);
    }

    /**
     * Persist a file envelope before calling the provider. This preserves synchronous source behavior.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendFile(
        string $recipientJid,
        string $filePath,
        ?string $caption = null,
        ?string $filename = null,
        array $options = []
    ): array {
        $log = DB::transaction(function () use ($recipientJid, $filePath, $caption, $filename, $options): WhatsAppMessageLog {
            return $this->persistFile($recipientJid, $filePath, $caption, $filename, $options);
        }, attempts: 3);

        if ($log->status !== WhatsAppMessageLog::STATUS_PENDING) {
            $this->syncBatchStats($log->batch_id);

            return $this->resultForLog($log);
        }

        return $this->deliver($log->id);
    }

    /**
     * Atomically claim a pending envelope and deliver exactly one provider attempt.
     *
     * @return array<string, mixed>
     */
    public function deliver(int $messageLogId): array
    {
        $claim = $this->claim($messageLogId);

        if ($claim === null) {
            $log = WhatsAppMessageLog::query()->find($messageLogId);

            return $log ? $this->resultForLog($log) : $this->missingResult();
        }

        [$log, $attempt] = $claim;

        try {
            $payload = $this->decryptPayload($log);
            if ($payload === null) {
                return $this->finalize(
                    $log,
                    $attempt,
                    WhatsAppMessageLog::STATUS_BLOCKED,
                    'Payload pengiriman tidak dapat dipulihkan dengan aman.',
                    null,
                    null
                );
            }

            if (($payload['kind'] ?? null) === 'file') {
                return $this->deliverFile($log, $attempt, $payload);
            }

            if (($payload['kind'] ?? null) !== 'text') {
                return $this->finalize(
                    $log,
                    $attempt,
                    WhatsAppMessageLog::STATUS_BLOCKED,
                    'Jenis payload pengiriman tidak didukung.',
                    null,
                    null
                );
            }

            $result = $this->gowaClient->sendMessage(
                (string) ($payload['recipient_jid'] ?? ''),
                (string) ($payload['message'] ?? ''),
                $this->normalizeMentions($payload['mentions'] ?? [])
            );

            return $this->finalizeTransportResult($log, $attempt, $result);
        } catch (\Throwable $exception) {
            return $this->finalize(
                $log,
                $attempt,
                WhatsAppMessageLog::STATUS_UNKNOWN,
                'Koneksi ke provider terputus; status pengiriman tidak dapat dipastikan.',
                null,
                null
            );
        }
    }

    /**
     * Queue exactly one retry only when the last provider outcome was a confirmed failure.
     */
    public function retry(WhatsAppMessageLog $messageLog): bool
    {
        $log = DB::transaction(function () use ($messageLog): ?WhatsAppMessageLog {
            $current = WhatsAppMessageLog::query()
                ->lockForUpdate()
                ->find($messageLog->id);

            if (! $current || ! $current->canRetry()) {
                return null;
            }

            if ($this->decryptPayload($current) === null) {
                $this->blockWithoutSync($current, 'Payload pengiriman tidak dapat dipulihkan dengan aman.');

                return null;
            }

            if (! $this->hasRetryableAttachment($current)) {
                $this->blockWithoutSync($current, 'Snapshot lampiran privat tidak tersedia untuk pengulangan.');

                return null;
            }

            $updated = WhatsAppMessageLog::query()
                ->whereKey($current->id)
                ->where('status', WhatsAppMessageLog::STATUS_FAILED)
                ->where('retryable', true)
                ->whereNotNull('payload_encrypted')
                ->update([
                    'status' => WhatsAppMessageLog::STATUS_PENDING,
                    'error_message' => null,
                    'retryable' => false,
                    'retry_block_reason' => 'Pesan sedang menunggu pengiriman ulang.',
                    'claimed_at' => null,
                    'completed_at' => null,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                return null;
            }

            $queued = $current->fresh();
            SendPersistedWhatsAppMessage::dispatch($queued->id)->afterCommit();

            return $queued;
        }, attempts: 3);

        if (! $log) {
            $this->syncBatchStats($messageLog->fresh()?->batch_id);

            return false;
        }

        $this->syncBatchStats($log->batch_id);

        return true;
    }

    /**
     * Marks an interrupted worker execution as uncertain rather than permitting a resend.
     */
    public function markUnknownIfSending(int $messageLogId): void
    {
        $batchId = DB::transaction(function () use ($messageLogId): ?int {
            $log = WhatsAppMessageLog::query()->lockForUpdate()->find($messageLogId);

            if (! $log || $log->status !== WhatsAppMessageLog::STATUS_SENDING) {
                return null;
            }

            $log->update([
                'status' => WhatsAppMessageLog::STATUS_UNKNOWN,
                'error_message' => 'Worker berhenti sebelum status pengiriman dapat dipastikan.',
                'retryable' => false,
                'retry_block_reason' => 'Status pengiriman tidak pasti; verifikasi provider sebelum tindakan lanjutan.',
                'completed_at' => now(),
            ]);

            $log->attempts()
                ->where('status', WhatsAppMessageLog::STATUS_SENDING)
                ->latest('attempt_number')
                ->first()
                ?->update([
                    'status' => WhatsAppMessageLog::STATUS_UNKNOWN,
                    'error_message' => 'Worker berhenti sebelum status pengiriman dapat dipastikan.',
                    'completed_at' => now(),
                ]);

            return $log->batch_id;
        }, attempts: 3);

        $this->syncBatchStats($batchId);
    }

    public function retryBlockReason(WhatsAppMessageLog $messageLog): ?string
    {
        if ($messageLog->canRetry() && $this->hasRetryableAttachment($messageLog)) {
            return null;
        }

        if ($messageLog->transport === WhatsAppMessageLog::TRANSPORT_LEGACY_OUTBOX) {
            return $messageLog->status === WhatsAppMessageLog::STATUS_SENT
                ? 'Pesan telah terkirim dan tidak dapat diulang.'
                : 'Log ini diimpor dari outbox sebelum fitur retry aman aktif. Pengiriman ulang diblokir untuk mencegah pesan ganda.';
        }

        if ($messageLog->retry_block_reason) {
            return $messageLog->retry_block_reason;
        }

        if ($messageLog->status === WhatsAppMessageLog::STATUS_FAILED
            && (! is_string($messageLog->payload_encrypted) || $messageLog->payload_encrypted === '')) {
            return $messageLog->transport === null
                ? 'Log ini dibuat sebelum fitur retry aman aktif. Payload asli tidak tersedia; kirim ulang dari sumber pesan untuk membuat log baru yang dapat diulang bila gagal.'
                : 'Payload pesan tersimpan tidak tersedia. Pengiriman ulang diblokir untuk mencegah isi pesan berubah atau terkirim ganda.';
        }

        return match ($messageLog->status) {
            WhatsAppMessageLog::STATUS_SENT => 'Pesan telah terkirim dan tidak dapat diulang.',
            WhatsAppMessageLog::STATUS_SENDING => 'Pengiriman sedang berlangsung.',
            WhatsAppMessageLog::STATUS_UNKNOWN => 'Status pengiriman tidak pasti; verifikasi provider sebelum tindakan lanjutan.',
            WhatsAppMessageLog::STATUS_BLOCKED => 'Pesan diblokir dan tidak dapat diulang.',
            WhatsAppMessageLog::STATUS_FAILED => 'Riwayat lama tidak menyimpan payload untuk pengulangan.',
            default => 'Pesan belum dapat diulang.',
        };
    }

    /**
     * Returns a redacted, display-safe preview without exposing the encrypted payload itself.
     */
    public function auditPreview(WhatsAppMessageLog $messageLog): ?string
    {
        $payload = $this->decryptPayload($messageLog);
        if ($payload === null) {
            return null;
        }

        $preview = match ($payload['kind'] ?? null) {
            'text' => is_scalar($payload['message'] ?? null) ? (string) $payload['message'] : null,
            'file' => filled($payload['caption'] ?? null)
                ? (string) $payload['caption']
                : 'Lampiran dikirim tanpa pesan tambahan.',
            default => null,
        };

        return $this->sanitizeAuditPreview($preview);
    }

    public function sanitizeAuditPreview(?string $preview): ?string
    {
        $preview = trim((string) $preview);
        if ($preview === '') {
            return null;
        }

        $preview = preg_replace(
            '/(\/qmh\s+(?:\d+\s+)?(?:approve|reject)\s+)\S+/i',
            '$1[REDACTED]',
            $preview
        ) ?? $preview;
        $preview = preg_replace(
            '/(\baction[\s_-]*code\s*[:=]?\s*)[A-Z0-9_-]{6,}\b/i',
            '$1[REDACTED]',
            $preview
        ) ?? $preview;
        $preview = preg_replace(
            '/(\b(?:password|api[\s_-]*key|basic[\s_-]*pass|secret|token)\s*[:=]\s*)\S+/i',
            '$1[REDACTED]',
            $preview
        ) ?? $preview;

        return mb_strimwidth($preview, 0, 500, '...');
    }

    public function syncBatchStats(?int $batchId): void
    {
        if (! $batchId) {
            return;
        }

        DB::transaction(function () use ($batchId): void {
            $batch = WhatsAppMessageBatch::query()->lockForUpdate()->find($batchId);
            if (! $batch) {
                return;
            }

            $sentCount = WhatsAppMessageLog::query()
                ->where('batch_id', $batchId)
                ->where('status', WhatsAppMessageLog::STATUS_SENT)
                ->count();
            $notSentCount = WhatsAppMessageLog::query()
                ->where('batch_id', $batchId)
                ->whereIn('status', [
                    WhatsAppMessageLog::STATUS_FAILED,
                    WhatsAppMessageLog::STATUS_UNKNOWN,
                    WhatsAppMessageLog::STATUS_BLOCKED,
                ])
                ->count();
            $terminalCount = $sentCount + $notSentCount;
            $isComplete = $batch->total_recipients > 0 && $terminalCount >= $batch->total_recipients;

            $batch->update([
                'sent_count' => $sentCount,
                'failed_count' => $notSentCount,
                'completed_at' => $isComplete ? now() : null,
            ]);
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function persistText(string $recipientJid, string $message, array $options): WhatsAppMessageLog
    {
        return $this->persistEnvelope([
            'kind' => 'text',
            'recipient_jid' => $recipientJid,
            'message' => $message,
            'mentions' => $this->normalizeMentions($options['mentions'] ?? []),
        ], $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function persistFile(
        string $recipientJid,
        string $filePath,
        ?string $caption,
        ?string $filename,
        array $options
    ): WhatsAppMessageLog {
        $options['initial_status'] = WhatsAppMessageLog::STATUS_PREPARING;
        $log = $this->persistEnvelope([
            'kind' => 'file',
            'recipient_jid' => $recipientJid,
            'caption' => trim((string) $caption),
        ], $options);

        if ($log->status !== WhatsAppMessageLog::STATUS_PREPARING || $log->attachment_path) {
            return $log;
        }

        $validationError = $this->attachmentSnapshotError($filePath);
        if ($validationError !== null) {
            $this->blockWithoutSync($log, $validationError);

            return $log->fresh();
        }

        $snapshot = $this->snapshotAttachment($filePath, $filename);
        if ($snapshot === null) {
            $this->blockWithoutSync($log, 'Lampiran sumber tidak dapat disalin ke penyimpanan privat.');

            return $log->fresh();
        }

        $log->update(array_merge($snapshot, [
            'status' => WhatsAppMessageLog::STATUS_PENDING,
            'retry_block_reason' => 'Pesan sedang menunggu pengiriman.',
        ]));

        return $log->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    private function persistEnvelope(array $payload, array $options): WhatsAppMessageLog
    {
        $idempotencyKey = $this->idempotencyKey($options['idempotency_key'] ?? null);
        if ($idempotencyKey !== null) {
            $existing = WhatsAppMessageLog::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $recipientJid = (string) ($payload['recipient_jid'] ?? '');
        $initialStatus = $options['initial_status'] ?? WhatsAppMessageLog::STATUS_PENDING;
        if (! in_array($initialStatus, [
            WhatsAppMessageLog::STATUS_PENDING,
            WhatsAppMessageLog::STATUS_PREPARING,
        ], true)) {
            $initialStatus = WhatsAppMessageLog::STATUS_PENDING;
        }
        $attributes = [
            'batch_id' => isset($options['batch_id']) ? (int) $options['batch_id'] : null,
            'recipient_jid' => $recipientJid,
            'recipient_name' => $this->nullableString($options['recipient_name'] ?? null) ?? $recipientJid,
            'recipient_type' => $this->nullableString($options['recipient_type'] ?? null)
                ?? (str_ends_with($recipientJid, '@g.us') ? 'group' : 'individual'),
            'status' => $initialStatus,
            'transport' => WhatsAppMessageLog::TRANSPORT_GOWA,
            'payload_encrypted' => $this->encryptPayload($payload),
            'source_type' => $this->nullableString($options['source_type'] ?? null),
            'source_id' => isset($options['source_id']) && $options['source_id'] !== null
                ? (int) $options['source_id']
                : null,
            'source_label' => $this->nullableString($options['source_label'] ?? null),
            'idempotency_key' => $idempotencyKey,
            'retryable' => false,
            'retry_block_reason' => $initialStatus === WhatsAppMessageLog::STATUS_PREPARING
                ? 'Lampiran sedang disiapkan di penyimpanan privat.'
                : 'Pesan sedang menunggu pengiriman.',
            'attempt_count' => 0,
        ];

        try {
            return WhatsAppMessageLog::query()->create($attributes);
        } catch (QueryException $exception) {
            if ($idempotencyKey === null) {
                throw $exception;
            }

            return WhatsAppMessageLog::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }
    }

    /**
     * @return array{0: WhatsAppMessageLog, 1: WhatsAppMessageAttempt}|null
     */
    private function claim(int $messageLogId): ?array
    {
        return DB::transaction(function () use ($messageLogId): ?array {
            $updated = WhatsAppMessageLog::query()
                ->whereKey($messageLogId)
                ->where('status', WhatsAppMessageLog::STATUS_PENDING)
                ->update([
                    'status' => WhatsAppMessageLog::STATUS_SENDING,
                    'retryable' => false,
                    'retry_block_reason' => 'Pengiriman sedang berlangsung.',
                    'claimed_at' => now(),
                    'attempt_count' => DB::raw('attempt_count + 1'),
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                return null;
            }

            $log = WhatsAppMessageLog::query()->lockForUpdate()->findOrFail($messageLogId);
            $attempt = $log->attempts()->create([
                'attempt_number' => $log->attempt_count,
                'status' => WhatsAppMessageLog::STATUS_SENDING,
                'started_at' => now(),
            ]);

            return [$log, $attempt];
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function deliverFile(WhatsAppMessageLog $log, WhatsAppMessageAttempt $attempt, array $payload): array
    {
        if (! $this->hasRetryableAttachment($log)) {
            return $this->finalize(
                $log,
                $attempt,
                WhatsAppMessageLog::STATUS_BLOCKED,
                'Snapshot lampiran privat tidak tersedia untuk pengiriman.',
                null,
                null
            );
        }

        try {
            $path = Storage::disk((string) $log->attachment_disk)->path((string) $log->attachment_path);
        } catch (\Throwable) {
            return $this->finalize(
                $log,
                $attempt,
                WhatsAppMessageLog::STATUS_BLOCKED,
                'Snapshot lampiran privat tidak tersedia untuk pengiriman.',
                null,
                null
            );
        }

        $result = $this->gowaClient->sendFile(
            (string) ($payload['recipient_jid'] ?? ''),
            $path,
            (string) ($payload['caption'] ?? ''),
            $log->attachment_filename
        );

        return $this->finalizeTransportResult($log, $attempt, $result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function finalizeTransportResult(
        WhatsAppMessageLog $log,
        WhatsAppMessageAttempt $attempt,
        array $result
    ): array {
        $state = $this->transportState($result);
        $error = $state === WhatsAppMessageLog::STATUS_SENT
            ? null
            : (string) ($result['error'] ?? $this->defaultErrorFor($state));
        $providerStatus = isset($result['status']) && is_numeric($result['status'])
            ? (int) $result['status']
            : null;
        $messageId = isset($result['message_id']) && is_scalar($result['message_id'])
            ? (string) $result['message_id']
            : null;

        return $this->finalize($log, $attempt, $state, $error, $providerStatus, $messageId);
    }

    /**
     * @return array<string, mixed>
     */
    private function finalize(
        WhatsAppMessageLog $log,
        WhatsAppMessageAttempt $attempt,
        string $state,
        ?string $error,
        ?int $providerStatus,
        ?string $messageId
    ): array {
        $isSent = $state === WhatsAppMessageLog::STATUS_SENT;
        $isRetryable = $state === WhatsAppMessageLog::STATUS_FAILED;
        $blockReason = match ($state) {
            WhatsAppMessageLog::STATUS_SENT => 'Pesan telah terkirim dan tidak dapat diulang.',
            WhatsAppMessageLog::STATUS_PREPARING => 'Lampiran sedang disiapkan di penyimpanan privat.',
            WhatsAppMessageLog::STATUS_FAILED => null,
            WhatsAppMessageLog::STATUS_UNKNOWN => 'Status pengiriman tidak pasti; verifikasi provider sebelum tindakan lanjutan.',
            default => $error ?? 'Pesan tidak dapat dikirim ulang.',
        };

        DB::transaction(function () use ($log, $attempt, $state, $messageId, $error, $isSent, $isRetryable, $blockReason, $providerStatus): void {
            $lockedLog = WhatsAppMessageLog::query()->lockForUpdate()->findOrFail($log->id);
            $lockedAttempt = WhatsAppMessageAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            $lockedLog->update([
                'status' => $state,
                'message_id' => $messageId,
                'error_message' => $error,
                'sent_at' => $isSent ? now() : null,
                'retryable' => $isRetryable,
                'retry_block_reason' => $blockReason,
                'completed_at' => now(),
            ]);

            $lockedAttempt->update([
                'status' => $state,
                'provider_status' => $providerStatus,
                'provider_message_id' => $messageId,
                'error_message' => $error,
                'completed_at' => now(),
            ]);
        }, attempts: 3);

        $log->refresh();

        $this->syncBatchStats($log->batch_id);

        return [
            'success' => $isSent,
            'state' => $state,
            'status' => $providerStatus,
            'message_id' => $messageId,
            'error' => $error,
            'message_log_id' => $log->id,
            'retryable' => $isRetryable,
        ];
    }

    private function block(WhatsAppMessageLog $log, string $reason): void
    {
        $this->blockWithoutSync($log, $reason);

        $this->syncBatchStats($log->batch_id);
    }

    private function blockWithoutSync(WhatsAppMessageLog $log, string $reason): void
    {
        $log->update([
            'status' => WhatsAppMessageLog::STATUS_BLOCKED,
            'error_message' => $reason,
            'retryable' => false,
            'retry_block_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    private function queueIfPending(WhatsAppMessageLog $log): void
    {
        if ($log->status !== WhatsAppMessageLog::STATUS_PENDING) {
            return;
        }

        SendPersistedWhatsAppMessage::dispatch($log->id)->afterCommit();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decryptPayload(WhatsAppMessageLog $log): ?array
    {
        if (! is_string($log->payload_encrypted) || $log->payload_encrypted === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($log->payload_encrypted), true, 512, JSON_THROW_ON_ERROR);

            return is_array($payload) ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encryptPayload(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotAttachment(string $filePath, ?string $filename): ?array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            return null;
        }

        $name = trim((string) $filename);
        if ($name === '') {
            $name = basename($filePath);
        }

        $safeName = Str::slug(pathinfo($name, PATHINFO_FILENAME), '-');
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $storedName = ($safeName !== '' ? $safeName : 'attachment').($extension !== '' ? '.'.$extension : '');
        $path = 'whatsapp-outbound/'.now()->format('Y/m').'/'.Str::uuid().'-'.$storedName;
        $resource = fopen($filePath, 'rb');

        if ($resource === false) {
            return null;
        }

        try {
            $disk = Storage::disk(self::PRIVATE_ATTACHMENT_DISK);
            if (! $disk->put($path, $resource)) {
                return null;
            }

            return [
                'attachment_disk' => self::PRIVATE_ATTACHMENT_DISK,
                'attachment_path' => $path,
                'attachment_filename' => $name,
                'attachment_mime' => $this->attachmentMimeType($filePath),
                'attachment_size' => $this->attachmentSize($filePath),
            ];
        } catch (\Throwable) {
            try {
                Storage::disk(self::PRIVATE_ATTACHMENT_DISK)->delete($path);
            } catch (\Throwable) {
                // The envelope is blocked even when storage cleanup is unavailable.
            }

            return null;
        } finally {
            fclose($resource);
        }
    }

    private function hasRetryableAttachment(WhatsAppMessageLog $log): bool
    {
        if (! $log->attachment_path && ! $log->attachment_disk) {
            return true;
        }

        if (! is_string($log->attachment_disk)
            || $log->attachment_disk === ''
            || ! is_string($log->attachment_path)
            || $log->attachment_path === '') {
            return false;
        }

        try {
            return Storage::disk($log->attachment_disk)->exists($log->attachment_path);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function transportState(array $result): string
    {
        if (($result['success'] ?? false) === true) {
            return WhatsAppMessageLog::STATUS_SENT;
        }

        $outcome = $result['outcome'] ?? $result['state'] ?? null;
        if (in_array($outcome, [
            WhatsAppMessageLog::STATUS_FAILED,
            WhatsAppMessageLog::STATUS_UNKNOWN,
            WhatsAppMessageLog::STATUS_BLOCKED,
        ], true)) {
            return $outcome;
        }

        // A received HTTP response confirms that GOWA did not accept the send request.
        if (isset($result['status']) && is_numeric($result['status']) && (int) $result['status'] > 0) {
            return WhatsAppMessageLog::STATUS_FAILED;
        }

        return WhatsAppMessageLog::STATUS_UNKNOWN;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMentions(mixed $mentions): array
    {
        if (! is_array($mentions)) {
            return [];
        }

        return array_values(array_filter($mentions, static fn ($mention): bool => is_string($mention) && $mention !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function resultForLog(WhatsAppMessageLog $log): array
    {
        return [
            'success' => $log->status === WhatsAppMessageLog::STATUS_SENT,
            'state' => $log->status,
            'status' => null,
            'message_id' => $log->message_id,
            'error' => $log->error_message,
            'message_log_id' => $log->id,
            'retryable' => $log->canRetry(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function missingResult(): array
    {
        return [
            'success' => false,
            'state' => WhatsAppMessageLog::STATUS_UNKNOWN,
            'status' => null,
            'message_id' => null,
            'error' => 'Envelope pengiriman tidak ditemukan.',
            'retryable' => false,
        ];
    }

    private function defaultErrorFor(string $state): string
    {
        return match ($state) {
            WhatsAppMessageLog::STATUS_FAILED => 'Provider menolak pengiriman.',
            WhatsAppMessageLog::STATUS_UNKNOWN => 'Status pengiriman ke provider tidak dapat dipastikan.',
            default => 'Pesan tidak dapat dikirim.',
        };
    }

    private function idempotencyKey(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), 128, '');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function attachmentMimeType(string $filePath): string
    {
        try {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($filePath);

            return is_string($mime) ? $mime : 'application/octet-stream';
        } catch (\Throwable) {
            return 'application/octet-stream';
        }
    }

    private function attachmentSnapshotError(string $filePath): ?string
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            return 'File tidak dapat dibaca.';
        }

        $size = $this->attachmentSize($filePath);
        if ($size === null) {
            return 'File tidak dapat dibaca.';
        }

        $maxBytes = max(1_024, (int) settings('notifications.whatsapp.max_file_bytes', GowaClient::DEFAULT_MAX_FILE_BYTES));
        if ($size > $maxBytes) {
            return 'Ukuran file melebihi batas maksimum pengiriman.';
        }

        if (! in_array($this->attachmentMimeType($filePath), GowaClient::ALLOWED_FILE_MIME_TYPES, true)) {
            return 'Tipe file tidak diizinkan untuk pengiriman WhatsApp.';
        }

        return null;
    }

    private function attachmentSize(string $filePath): ?int
    {
        try {
            $size = filesize($filePath);
        } catch (\Throwable) {
            return null;
        }

        return is_int($size) ? $size : null;
    }
}
