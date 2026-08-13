<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE_TYPE = 'App\\Models\\WhatsappOutbox';

    private const TRANSPORT = 'legacy_outbox';

    public function up(): void
    {
        DB::table('whatsapp_outbox')
            ->orderBy('id')
            ->chunkById(100, function ($outboxes): void {
                $outboxIds = $outboxes->pluck('id');
                $existingSourceIds = DB::table('whatsapp_message_logs')
                    ->where('source_type', self::SOURCE_TYPE)
                    ->whereIn('source_id', $outboxIds)
                    ->pluck('source_id')
                    ->all();
                $existingSourceIds = array_flip(array_map('intval', $existingSourceIds));
                $records = [];

                foreach ($outboxes as $outbox) {
                    if (isset($existingSourceIds[(int) $outbox->id])) {
                        continue;
                    }

                    [$status, $error, $retryBlockReason, $sentAt, $completedAt] = $this->legacyState($outbox);
                    $timestamp = $outbox->updated_at ?? $outbox->created_at ?? now();
                    $recipientJid = (string) ($outbox->to_jid ?? '');

                    $records[] = [
                        'batch_id' => null,
                        'recipient_jid' => $recipientJid,
                        'recipient_name' => (string) ($outbox->to_phone_e164 ?? $recipientJid),
                        'recipient_type' => str_ends_with($recipientJid, '@g.us') ? 'group' : 'individual',
                        'status' => $status,
                        'error_message' => $error,
                        'message_id' => $status === 'sent' ? $outbox->provider_message_id : null,
                        'sent_at' => $sentAt,
                        'transport' => self::TRANSPORT,
                        'payload_encrypted' => Crypt::encryptString(json_encode([
                            'kind' => 'text',
                            'recipient_jid' => $recipientJid,
                            'message' => (string) ($outbox->message_text ?? ''),
                            'mentions' => [],
                        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)),
                        'source_type' => self::SOURCE_TYPE,
                        'source_id' => $outbox->id,
                        'source_label' => $this->sourceLabel((string) $outbox->milestone_key),
                        'idempotency_key' => 'legacy-whatsapp-outbox:'.$outbox->id,
                        'retryable' => false,
                        'retry_block_reason' => $retryBlockReason,
                        'attempt_count' => max(0, (int) ($outbox->attempts ?? 0)),
                        'claimed_at' => null,
                        'completed_at' => $completedAt,
                        'created_at' => $outbox->created_at ?? $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($records !== []) {
                    DB::table('whatsapp_message_logs')->insertOrIgnore($records);
                }
            }, 'id');
    }

    public function down(): void
    {
        DB::table('whatsapp_message_logs')
            ->where('source_type', self::SOURCE_TYPE)
            ->where('idempotency_key', 'like', 'legacy-whatsapp-outbox:%')
            ->delete();
    }

    private function legacyState(object $outbox): array
    {
        $timestamp = $outbox->updated_at ?? $outbox->created_at ?? now();

        return match ((string) $outbox->status) {
            'sent', 'delivered', 'read' => [
                'sent',
                null,
                'Pesan telah terkirim dan tidak dapat diulang.',
                $timestamp,
                $timestamp,
            ],
            'failed' => [
                'failed',
                'Pengiriman gagal pada outbox sebelum audit pengiriman aman tersedia.',
                'Log ini diimpor dari outbox sebelum fitur retry aman aktif. Pengiriman ulang diblokir untuk mencegah pesan ganda.',
                null,
                $timestamp,
            ],
            default => [
                'unknown',
                'Status pengiriman pada outbox lama tidak dapat dipastikan.',
                'Log ini diimpor dari outbox sebelum fitur retry aman aktif. Pengiriman ulang diblokir untuk mencegah pesan ganda.',
                null,
                $timestamp,
            ],
        };
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
};
