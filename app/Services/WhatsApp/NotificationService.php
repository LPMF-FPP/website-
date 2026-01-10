<?php

namespace App\Services\WhatsApp;

use App\Support\PhoneNormalizer;

class NotificationService
{
    private const MILESTONE_TEMPLATES = [
        'REQUEST_RECEIVED' => 'Permintaan Anda telah diterima. Resi: {resi}.',
        'REVIEW_DONE_READY_FOR_TEST' => 'Permintaan {resi} telah selesai dikaji ulang dan siap dilakukan pengujian.',
        'PREPARATION_DONE' => 'Permintaan {resi} telah selesai dipreparasi sampel.',
        'INSTRUMENTATION_DONE' => 'Permintaan {resi} telah selesai diuji instrumen.',
        'INTERPRETATION_DONE' => 'Permintaan {resi} telah selesai dilakukan interpretasi hasil.',
        'READY_FOR_PICKUP' => 'Permintaan {resi} siap diambil.',
        'HANDOVER_COMPLETED' => 'Permintaan {resi} telah diambil dan serah terima telah dicatat.',
    ];

    public function getMilestoneMessage(string $milestone, array $replacements = []): ?string
    {
        $templates = settings('notifications.whatsapp.templates', []);

        if (!is_array($templates) || empty($templates)) {
            $templates = self::MILESTONE_TEMPLATES;
        }

        $template = $templates[$milestone] ?? self::MILESTONE_TEMPLATES[$milestone] ?? null;

        if (!is_string($template) || trim($template) === '') {
            return null;
        }

        foreach ($replacements as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    public function shouldNotify(string $milestone): bool
    {
        $enabledMilestones = settings('notifications.whatsapp.enabled_milestones', []);

        if (!is_array($enabledMilestones)) {
            return false;
        }

        return in_array($milestone, $enabledMilestones, true);
    }

    public function formatJID(string $phone): string
    {
        return PhoneNormalizer::toJid(PhoneNormalizer::toE164($phone));
    }

    public function isWhatsAppEnabled(): bool
    {
        return (bool) settings('notifications.whatsapp.enabled', false);
    }

    public function getAvailableMilestones(): array
    {
        return array_keys(self::MILESTONE_TEMPLATES);
    }

    public function getAllTemplates(): array
    {
        return self::MILESTONE_TEMPLATES;
    }
}
