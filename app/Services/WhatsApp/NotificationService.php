<?php

namespace App\Services\WhatsApp;

use App\Support\PhoneNormalizer;
use Carbon\Carbon;

class NotificationService
{
    private const MILESTONE_TEMPLATES = [
        'REQUEST_RECEIVED' => 'Permintaan Anda telah diterima. Resi: {resi}.',
        'REVIEW_DONE_READY_FOR_TEST' => 'Permintaan {resi} telah selesai dikaji ulang dan siap dilakukan pengujian.',
        'REQUEST_REJECTED' => '{greeting}, permintaan {resi} ditolak setelah kaji ulang. Alasan: {reason}.',
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

    /**
     * Generate time-based greeting
     */
    public function getTimeBasedGreeting(): string
    {
        $hour = Carbon::now(settings('locale.timezone', 'Asia/Jakarta'))->hour;

        if ($hour >= 5 && $hour < 11) {
            return 'Selamat Pagi';
        } elseif ($hour >= 11 && $hour < 15) {
            return 'Selamat Siang';
        } elseif ($hour >= 15 && $hour < 19) {
            return 'Selamat Sore';
        } else {
            return 'Selamat Malam';
        }
    }

    /**
     * Get proper salutation for investigator
     * - For POLRI members (is_polri = true): Use rank
     * - For non-POLRI: Use Bapak/Ibu
     */
    public function getSalutation($investigator): string
    {
        if (!$investigator) {
            return 'Bapak/Ibu';
        }

        // Check if investigator is POLRI member
        if (isset($investigator->is_polri) && $investigator->is_polri) {
            // Use rank if available
            return $investigator->rank ?? 'Bapak/Ibu';
        }

        // For non-POLRI, use Bapak/Ibu
        return 'Bapak/Ibu';
    }

    /**
     * Get complete greeting with name
     */
    public function getGreeting($investigator): string
    {
        $timeGreeting = $this->getTimeBasedGreeting();
        $salutation = $this->getSalutation($investigator);
        
        if (!$investigator || !isset($investigator->name)) {
            return $timeGreeting . ' ' . $salutation;
        }

        return $timeGreeting . ' ' . $salutation . ' ' . $investigator->name;
    }
}
