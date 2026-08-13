<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappOutbox;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MilestoneNotificationService
{
    public function __construct(private readonly OutboundMessageService $outboundMessageService) {}

    public function queue(
        ?int $testRequestId,
        string $milestone,
        string $phone,
        string $recipientJid,
        string $recipientName,
        string $message,
        bool $forceNewDelivery = false
    ): WhatsappOutbox {
        return DB::transaction(function () use ($testRequestId, $milestone, $phone, $recipientJid, $recipientName, $message, $forceNewDelivery): WhatsappOutbox {
            $attributes = [
                'test_request_id' => $testRequestId,
                'milestone_key' => $milestone,
                'to_phone_e164' => PhoneNormalizer::toE164($phone),
                'to_jid' => $recipientJid,
                'message_text' => $message,
                'status' => 'queued',
                'attempts' => 0,
                'last_error' => null,
            ];

            if ($testRequestId === null) {
                $outbox = WhatsappOutbox::query()->create($attributes);
            } else {
                $outbox = WhatsappOutbox::query()
                    ->where('test_request_id', $testRequestId)
                    ->where('milestone_key', $milestone)
                    ->lockForUpdate()
                    ->first();

                if ($outbox === null) {
                    $outbox = WhatsappOutbox::query()->create($attributes);
                }
            }

            $messageLog = $this->latestMessageLogFor($outbox);
            $createsSuccessor = false;
            if ($forceNewDelivery && $messageLog !== null) {
                if ($this->isInFlight($messageLog)) {
                    $this->syncOutbox($outbox, $messageLog);

                    return $outbox->fresh();
                }

                if ($messageLog->status === WhatsAppMessageLog::STATUS_UNKNOWN) {
                    throw new \LogicException('Status pengiriman sebelumnya tidak pasti. Verifikasi penerima sebelum membuat pengiriman baru.');
                }

                if ($messageLog->status === WhatsAppMessageLog::STATUS_FAILED) {
                    if ($messageLog->transport === WhatsAppMessageLog::TRANSPORT_LEGACY_OUTBOX) {
                        $messageLog = null;
                        $createsSuccessor = true;
                    } elseif (! $messageLog->canRetry() || ! $this->outboundMessageService->retry($messageLog)) {
                        throw new \LogicException('Pengiriman sebelumnya tidak dapat diulang dengan aman. Lihat Log Pengiriman WhatsApp untuk detail.');
                    } else {
                        $this->syncOutbox($outbox, $messageLog->fresh());

                        return $outbox->fresh();
                    }
                }

                if ($messageLog?->status === WhatsAppMessageLog::STATUS_BLOCKED) {
                    throw new \LogicException('Pengiriman sebelumnya diblokir. Lihat Log Pengiriman WhatsApp untuk detail.');
                }

                $createsSuccessor = $createsSuccessor || $messageLog !== null;
            }

            $createNewDelivery = $messageLog === null
                || ($forceNewDelivery && ! $this->isInFlight($messageLog));
            if ($createNewDelivery) {
                $outbox->fill($attributes)->save();
            }

            if ($createNewDelivery || $messageLog !== null) {
                $this->queueExisting($outbox, $recipientName, $messageLog, $createsSuccessor);
            }

            return $outbox->fresh();
        }, attempts: 3);
    }

    public function queueExisting(
        WhatsappOutbox $outbox,
        ?string $recipientName = null,
        ?WhatsAppMessageLog $messageLog = null,
        bool $forceNewDelivery = false
    ): WhatsAppMessageLog {
        $existing = $messageLog ?? $this->latestMessageLogFor($outbox);
        if (! $forceNewDelivery && $existing !== null) {
            $this->syncOutbox($outbox, $existing);

            return $existing;
        }

        $messageLog = $this->outboundMessageService->queueText(
            (string) $outbox->to_jid,
            (string) $outbox->message_text,
            [
                'recipient_name' => $recipientName ?: (string) $outbox->to_phone_e164,
                'recipient_type' => str_ends_with((string) $outbox->to_jid, '@g.us') ? 'group' : 'individual',
                'source_type' => WhatsappOutbox::class,
                'source_id' => $outbox->id,
                'source_label' => $this->sourceLabel((string) $outbox->milestone_key),
                'idempotency_key' => $forceNewDelivery
                    ? 'whatsapp-outbox:'.$outbox->id.':'.Str::uuid()
                    : 'whatsapp-outbox:'.$outbox->id,
            ]
        );

        $this->syncOutbox($outbox, $messageLog);

        return $messageLog;
    }

    public function syncOutboxForMessageLog(WhatsAppMessageLog $messageLog): void
    {
        if ($messageLog->source_type !== WhatsappOutbox::class || ! $messageLog->source_id) {
            return;
        }

        $outbox = WhatsappOutbox::query()->find($messageLog->source_id);
        if ($outbox === null) {
            return;
        }

        $this->syncOutbox($outbox, $messageLog);
    }

    private function latestMessageLogFor(WhatsappOutbox $outbox): ?WhatsAppMessageLog
    {
        return WhatsAppMessageLog::query()
            ->where('source_type', WhatsappOutbox::class)
            ->where('source_id', $outbox->id)
            ->latest('id')
            ->first();
    }

    private function syncOutbox(WhatsappOutbox $outbox, WhatsAppMessageLog $messageLog): void
    {
        if ($this->latestMessageLogFor($outbox)?->id !== $messageLog->id) {
            return;
        }

        $isQueued = in_array($messageLog->status, [
            WhatsAppMessageLog::STATUS_PREPARING,
            WhatsAppMessageLog::STATUS_PENDING,
            WhatsAppMessageLog::STATUS_SENDING,
        ], true);
        $isSent = $messageLog->status === WhatsAppMessageLog::STATUS_SENT;

        $outbox->forceFill([
            'status' => $isSent ? 'sent' : ($isQueued ? 'queued' : 'failed'),
            'provider_message_id' => $isSent ? $messageLog->message_id : null,
            'attempts' => $messageLog->attempt_count,
            'last_error' => $isSent || $isQueued ? null : $this->safeOutboxError($messageLog),
        ])->save();
    }

    private function safeOutboxError(WhatsAppMessageLog $messageLog): string
    {
        return $messageLog->status === WhatsAppMessageLog::STATUS_UNKNOWN
            ? 'Status pengiriman tidak dapat dipastikan. Lihat Log Pengiriman WhatsApp untuk detail aman.'
            : 'Pengiriman gagal. Lihat Log Pengiriman WhatsApp untuk detail aman.';
    }

    private function isInFlight(?WhatsAppMessageLog $messageLog): bool
    {
        return $messageLog !== null && in_array($messageLog->status, [
            WhatsAppMessageLog::STATUS_PREPARING,
            WhatsAppMessageLog::STATUS_PENDING,
            WhatsAppMessageLog::STATUS_SENDING,
        ], true);
    }

    private function sourceLabel(string $milestone): string
    {
        return match ($milestone) {
            'REQUEST_RECEIVED' => 'Notifikasi Berita Acara Penerimaan',
            'READY_FOR_PICKUP' => 'Notifikasi siap diambil',
            'HANDOVER_COMPLETED' => 'Notifikasi Berita Acara Penyerahan',
            'REQUEST_REJECTED' => 'Notifikasi permintaan ditolak',
            'TEST' => 'Pesan uji WhatsApp',
            default => 'Notifikasi milestone',
        };
    }
}
