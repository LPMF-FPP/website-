<?php

namespace App\Listeners;

use App\Events\NumberIssued;
use App\Services\WhatsApp\OutboundMessageService;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendIssueNotification implements ShouldQueue
{
    public function __construct(protected OutboundMessageService $outboundMessageService) {}

    public function handle(NumberIssued $event): void
    {
        $config = settings('notifications');
        if (! $config) {
            return;
        }

        $emailConfig = $config['email'] ?? [];
        $whatsappConfig = $config['whatsapp'] ?? [];

        $replace = static fn (string $value): string => strtr($value, [
            '{SCOPE}' => strtoupper($event->scope),
            '{NUMBER}' => $event->number,
            '{REQ}' => (string) ($event->ctx['request_short'] ?? '-'),
        ]);

        if (! empty($emailConfig['enabled'])) {
            $subject = $replace($emailConfig['subject'] ?? 'Nomor {NUMBER}');
            $body = $replace($emailConfig['body'] ?? 'Nomor {NUMBER} telah diterbitkan.');
            $recipient = $emailConfig['default_recipient'] ?? config('mail.to.address', config('mail.from.address'));

            if ($recipient) {
                Mail::raw($body, function ($message) use ($subject, $recipient) {
                    $message->to($recipient)->subject($subject);
                });
            }
        }

        if (! empty($whatsappConfig['enabled'])) {
            $message = $replace($whatsappConfig['message'] ?? '{SCOPE} {NUMBER} issued');
            $recipient = $whatsappConfig['default_target'] ?? null;

            if (! $recipient) {
                Log::warning('[LIMS] WhatsApp recipient not configured');

                return;
            }

            $this->outboundMessageService->queueText(
                PhoneNormalizer::toJid(PhoneNormalizer::toE164((string) $recipient)),
                $message,
                [
                    'recipient_name' => (string) $recipient,
                    'source_type' => NumberIssued::class,
                    'source_label' => 'Notifikasi nomor terbit',
                    'idempotency_key' => 'number-issued:'.strtolower($event->scope).':'.$event->number.':'.PhoneNormalizer::toCanonicalDigits((string) $recipient),
                ]
            );
        }
    }
}
