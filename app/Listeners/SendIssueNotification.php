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
        $config = settings('automation.notify_on_issue');
        if (!$config) {
            return;
        }

        $replace = static fn (string $value): string => strtr($value, [
            '{SCOPE}' => strtoupper($event->scope),
            '{NUMBER}' => $event->number,
            '{REQ}' => (string) ($event->ctx['request_short'] ?? '-'),
        ]);

        if (!empty($config['email'])) {
            $subject = $replace(data_get($config, 'templates.subject', 'Number {NUMBER}'));
            $body = $replace(data_get($config, 'templates.body', 'Number {NUMBER} issued'));

            Mail::raw($body, function ($message) use ($subject) {
                $message->to(config('mail.to.address', config('mail.from.address')))
                    ->subject($subject);
            });
        }

        if (!empty($config['whatsapp'])) {
            $message = $replace(data_get($config, 'templates.whatsapp', '{SCOPE} {NUMBER} issued'));
            $recipient = settings('automation.whatsapp_recipient');

            if (empty($recipient)) {
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

