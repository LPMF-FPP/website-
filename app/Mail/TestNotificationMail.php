<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(private readonly string $body)
    {
    }

    public function build(): self
    {
        return $this->subject('Tes Notifikasi LIMS')
            ->text('emails.test-notification')
            ->with([
                'body' => $this->body,
            ]);
    }
}
