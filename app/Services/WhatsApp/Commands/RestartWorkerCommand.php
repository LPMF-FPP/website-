<?php

namespace App\Services\WhatsApp\Commands;

use App\Services\WhatsApp\TemplateService;
use App\Services\WhatsApp\WhitelistService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RestartWorkerCommand
{
    public function __construct(
        private TemplateService $templateService,
        private WhitelistService $whitelistService
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        // Double check permissions (already checked in Dispatcher)
        if (! $this->whitelistService->isAllowed($fromJid)) {
            return $this->templateService->get('command', 'RESTART_UNAUTHORIZED');
        }

        $senderNumber = explode('@', $fromJid)[0];

        try {
            Artisan::call('queue:restart');
            Artisan::call('cache:clear');

            dispatch(function () {
                \Illuminate\Support\Facades\Log::warning('KILLING QUEUE WORKER PID: '.getmypid());
                posix_kill(getmypid(), SIGKILL);
            })->afterCommit();

            Log::info("Queue worker restart triggered via WhatsApp by {$senderNumber}");

            return $this->templateService->get('command', 'RESTART_SUCCESS');
        } catch (\Exception $e) {
            Log::error('Failed to restart worker via WhatsApp: '.$e->getMessage());

            return '❌ Gagal melakukan restart: '.$e->getMessage();
        }
    }
}
