<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GowaUpdateOperation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public const ACTIVE_STATUSES = ['queued', 'preparing', 'updating', 'verifying', 'reconciling'];

    public const TERMINAL_STATUSES = ['succeeded', 'failed', 'rolled_back', 'degraded'];

    public const SCOPE = 'gowa';

    public const FAILURE_CODES = [
        'privileged_runner_unavailable', 'runtime_evidence_unavailable', 'runtime_evidence_stale',
        'release_not_allowed', 'claim_payload_mismatch', 'claim_rejected', 'claim_unauthorized',
        'claim_replay', 'update_already_active', 'idempotency_payload_mismatch', 'operation_not_retryable',
        'quiescence_unproven', 'attestation_mismatch', 'attestation_conflict', 'attestation_rejected',
        'evidence_rejected', 'evidence_unavailable', 'evidence_key_unavailable', 'evidence_signature_invalid',
        'evidence_sequence_gap', 'evidence_transition_rejected', 'reconciliation_failed', 'unexpected_failure',
    ];

    protected $fillable = [
        'id', 'scope', 'release_id', 'requested_version', 'requested_digest',
        'previous_version', 'previous_digest', 'status', 'idempotency_key',
        'fencing_token', 'checkpoint', 'root_authority_generation', 'heartbeat_at',
        'lease_expires_at', 'retry_of_id', 'requested_by', 'failure_code',
        'failure_message_key', 'preservation_snapshot', 'feature_snapshot', 'client_action_uuid',
    ];

    protected $casts = [
        'heartbeat_at' => 'datetime', 'lease_expires_at' => 'datetime',
        'preservation_snapshot' => 'array', 'feature_snapshot' => 'array',
        'fencing_token' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'retry_of_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GowaUpdateEvent::class, 'operation_id');
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(GowaUpdateAttestation::class, 'operation_id');
    }

    public function dispatchClaim(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GowaUpdateDispatchClaim::class, 'operation_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isStale(): bool
    {
        return ! $this->isTerminal() && $this->lease_expires_at?->isPast() === true;
    }

    /** @return array<string, mixed> */
    public function safeProjection(): array
    {
        $failureCode = $this->failure_code === null ? null : self::safeFailureCode($this->failure_code);

        return [
            'id' => (string) $this->id,
            'status' => $this->status,
            'release_id' => $this->release_id,
            'version' => $this->requested_version,
            'digest' => $this->requested_digest,
            'fencing_token' => $this->fencing_token,
            'checkpoint' => $this->checkpoint,
            'failure_code' => $failureCode,
            'message' => $this->safeFailureMessage(),
            'retry_of_id' => $this->retry_of_id,
            'heartbeat_at' => $this->heartbeat_at?->toIso8601String(),
            'lease_expires_at' => $this->lease_expires_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'stale' => $this->lease_expires_at?->isPast() ?? false,
        ];
    }

    public function safeFailureMessage(): ?string
    {
        $code = $this->failure_code === null ? match ($this->failure_message_key) {
            'gowa_update.reconciliation_failed' => 'reconciliation_failed',
            default => null,
        } : self::safeFailureCode($this->failure_code);

        return match ($code) {
            'privileged_runner_unavailable' => 'Jalur pemeliharaan terproteksi belum tersedia. Hubungi administrator sistem.',
            'runtime_evidence_unavailable', 'runtime_evidence_stale' => 'Bukti runtime belum cukup baru untuk menjalankan pembaruan. Tunggu pemeriksaan berikutnya.',
            'release_not_allowed', 'claim_payload_mismatch' => 'Rilis yang dipilih tidak lagi disetujui. Muat ulang halaman dan pilih rilis yang tersedia.',
            'update_already_active' => 'Pembaruan lain sedang berjalan. Tunggu sampai statusnya selesai.',
            'operation_not_retryable', 'quiescence_unproven' => 'Percobaan ulang ditahan sampai layanan, kunci, permintaan, dan bukti operasi benar-benar tenang.',
            'attestation_mismatch' => 'Hasil belum dapat diverifikasi dengan aman. Jangan ulangi sebelum pemeriksaan operasional selesai.',
            'reconciliation_failed', 'unexpected_failure' => 'Hasil pembaruan belum dapat dipastikan. Hubungi administrator sistem untuk pemeriksaan manual.',
            default => $this->failure_code === null ? null : 'Pembaruan belum dapat dinyatakan aman. Hubungi administrator sistem.',
        };
    }

    public static function safeFailureCode(string $code): string
    {
        return in_array($code, self::FAILURE_CODES, true) ? $code : 'unexpected_failure';
    }
}
