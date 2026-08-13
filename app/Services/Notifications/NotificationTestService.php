<?php

namespace App\Services\Notifications;

use App\Mail\TestNotificationMail;
use App\Services\WhatsApp\NotificationService;
use App\Services\WhatsApp\OutboundMessageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationTestService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly OutboundMessageService $outboundMessageService
    ) {}

    /**
     * Send a test notification via the specified channel.
     *
     * @return array{status: string, message: string, delivered_at?: string, message_log_id?: int}
     */
    public function send(string $channel, string $target, ?string $message = null): array
    {
        return match ($channel) {
            'email' => $this->sendEmail($target, $message),
            'whatsapp' => $this->sendWhatsApp($target, $message),
            default => [
                'status' => 'failed',
                'message' => 'Unsupported notification channel: '.$channel,
            ],
        };
    }

    /**
     * Send test email notification.
     */
    private function sendEmail(string $target, ?string $message): array
    {
        try {
            $body = $message ?: 'Tes notifikasi LIMS - Email berfungsi dengan baik.';

            Mail::to($target)->send(new TestNotificationMail($body));

            // Fallback to log if mail driver is 'log'
            if (config('mail.default') === 'log') {
                Log::info('Test email notification (log driver)', [
                    'to' => $target,
                    'body' => $body,
                ]);
            }

            return [
                'status' => 'delivered',
                'message' => sprintf('Email berhasil dikirim ke %s', $target),
                'delivered_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Email notification test failed', [
                'target' => $target,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'message' => 'Gagal mengirim email: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Send test WhatsApp notification.
     */
    private function sendWhatsApp(string $target, ?string $message): array
    {
        try {
            $text = $message ?: '*[LIMS]* Tes notifikasi - WhatsApp berfungsi dengan baik.';

            $messageLog = $this->outboundMessageService->queueText(
                $this->notificationService->formatJID($target),
                $text,
                [
                    'recipient_name' => $target,
                    'source_label' => 'Pesan uji pengaturan',
                ]
            );

            return [
                'status' => 'queued',
                'message' => 'Pesan uji WhatsApp telah diantrikan.',
                'message_log_id' => $messageLog->id,
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsApp notification test failed', [
                'target' => $target,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'message' => 'Gagal mengirim WhatsApp: '.$e->getMessage(),
            ];
        }
    }
}
