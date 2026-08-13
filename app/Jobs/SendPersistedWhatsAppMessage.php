<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WhatsAppMessageLog;
use App\Services\WhatsApp\MilestoneNotificationService;
use App\Services\WhatsApp\OutboundMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPersistedWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $messageLogId) {}

    public function handle(
        OutboundMessageService $outboundMessageService,
        MilestoneNotificationService $milestoneNotificationService
    ): void {
        $outboundMessageService->deliver($this->messageLogId);

        $messageLog = WhatsAppMessageLog::query()->find($this->messageLogId);
        if ($messageLog !== null) {
            $milestoneNotificationService->syncOutboxForMessageLog($messageLog);
        }
    }

    public function failed(\Throwable $exception): void
    {
        app(OutboundMessageService::class)->markUnknownIfSending($this->messageLogId);

        $messageLog = WhatsAppMessageLog::query()->find($this->messageLogId);
        if ($messageLog !== null) {
            app(MilestoneNotificationService::class)->syncOutboxForMessageLog($messageLog);
        }
    }
}
