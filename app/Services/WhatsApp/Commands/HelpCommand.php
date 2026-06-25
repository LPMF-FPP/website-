<?php

namespace App\Services\WhatsApp\Commands;

use App\Services\WhatsApp\TemplateService;
use App\Services\WhatsApp\WhitelistService;

class HelpCommand
{
    public function __construct(
        private TemplateService $templateService,
        private WhitelistService $whitelistService
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        $response = $this->templateService->render('command', 'HELP', [
            'nomor_resi' => $this->templateService->exampleTrackingNumber(),
        ]);

        // Admin Only Section: Show if user is Whitelisted
        if ($this->whitelistService->isAllowed($fromJid)) {
            $response .= $this->templateService->get('command', 'HELP_ADMIN');
        }

        return $response;
    }
}
