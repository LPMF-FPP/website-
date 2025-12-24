<?php

namespace App\Listeners;

use App\Events\NumberIssued;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendIssueNotification implements ShouldQueue
{
    public function __construct(protected WhatsAppService $whatsapp)
    {
    }

    public function handle(NumberIssued $event): void
    {
        $config = settings('notifications');
        if (!$config) {
            return;
        }

        $emailConfig = $config['email'] ?? [];
        $whatsappConfig = $config['whatsapp'] ?? [];

        $replace = static fn (string $value): string => strtr($value, [
            '{SCOPE}' => strtoupper($event->scope),
            '{NUMBER}' => $event->number,
            '{REQ}' => (string) ($event->ctx['request_short'] ?? '-'),
        ]);

        if (!empty($emailConfig['enabled'])) {
            $subject = $replace($emailConfig['subject'] ?? 'Nomor {NUMBER}');
            $body = $replace($emailConfig['body'] ?? 'Nomor {NUMBER} telah diterbitkan.');
            $recipient = $emailConfig['default_recipient'] ?? config('mail.to.address', config('mail.from.address'));

            if ($recipient) {
                Mail::raw($body, function ($message) use ($subject, $recipient) {
                    $message->to($recipient)->subject($subject);
                });
            }
        }

        if (!empty($whatsappConfig['enabled'])) {
            $message = $replace($whatsappConfig['message'] ?? '{SCOPE} {NUMBER} issued');
            $recipient = $whatsappConfig['default_target'] ?? null;

            if (!$recipient) {
                Log::warning('[LIMS] WhatsApp recipient not configured');
                return;
            }

            if ($this->whatsapp->isConfigured()) {
                $this->whatsapp->send($recipient, $message);
            } else {
                Log::warning('[LIMS] WhatsApp service not configured', [
                    'message' => $message,
                    'recipient' => $recipient,
                ]);
            }
        }
    }
}
