<?php

namespace App\Services\WhatsApp\Commands;

use App\Services\WhatsApp\TemplateService;

class HelpCommand
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        $response = $this->templateService->get('command', 'HELP');

        // Admin Only Section
        $senderNumber = explode('@', $fromJid)[0];
        $adminNumber = settings('notifications.whatsapp.admin_number', '6285956592404');

        if ($senderNumber === $adminNumber) {
            $response .= $this->templateService->get('command', 'HELP_ADMIN');
        }

        return $response;
    }
}
