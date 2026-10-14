<?php

namespace App\Services\AI;

use App\Services\Reminders\Handlers\CountdownHandler;
use App\Services\WhatsApp\TemplateService;

class AiPlaceholderCatalog
{
    public function keys(string $context): array
    {
        $templates = app(TemplateService::class);

        return match ($context) {
            'countdown', 'iso_countdown' => CountdownHandler::PLACEHOLDERS,
            'task' => array_values(array_unique(array_merge(...array_values($templates->getAllPlaceholders()['task'])))),
            'reminder' => ['date', 'time', 'temperature'],
            'general', 'broadcast' => array_values(array_unique(array_merge(...array_values($templates->getMagicVariables())))),
            default => throw new AiFailure('AI_CONTEXT_INVALID', 'Konteks editor AI tidak didukung.', 422),
        };
    }
}
