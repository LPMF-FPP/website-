<?php

namespace App\Services\WhatsApp\Commands;

use App\Services\WhatsApp\TemplateService;
use App\Services\WhatsApp\WhitelistService;

class WhitelistCommand
{
    public function __construct(
        private WhitelistService $whitelistService,
        private TemplateService $templateService
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        // Security Check: Only Super Admin can manage whitelist
        if (! $this->whitelistService->isSuperAdmin($fromJid)) {
            return $this->templateService->get('command', 'WHITELIST_UNAUTHORIZED');
        }

        $action = strtolower($params[0] ?? 'list');

        return match ($action) {
            'add' => $this->handleAdd($fromJid, $params),
            'del', 'delete', 'remove', 'rm' => $this->handleRemove($params),
            default => $this->handleList(),
        };
    }

    private function handleList(): string
    {
        $whitelist = $this->whitelistService->getAll();
        $superAdmin = settings('notifications.whatsapp.admin_number', '6285956592404');

        $lines = ["📋 *DAFTAR ADMIN WHITELIST*\n"];

        // Always show Super Admin first
        $lines[] = "👑 {$superAdmin} (SUPER ADMIN)";

        if ($whitelist->isNotEmpty()) {
            foreach ($whitelist as $index => $item) {
                $name = $item->name ? "({$item->name})" : '';
                $lines[] = "{$index}. {$item->phone_number} {$name}";
            }
        } else {
            $lines[] = "\n_Belum ada admin tambahan._";
        }

        $lines[] = "\nTotal: ".($whitelist->count() + 1).' admin';
        $lines[] = "\n─────────────────";
        $lines[] = 'Gunakan:';
        $lines[] = '/whitelist add 08xxx [nama]';
        $lines[] = '/whitelist del 08xxx';

        return implode("\n", $lines);
    }

    private function handleAdd(string $fromJid, array $params): string
    {
        $phone = $params[1] ?? null;
        if (! $phone) {
            return "❌ Format salah!\nGunakan: /whitelist add 08xxx [nama]";
        }

        // Combine remaining params as name
        $name = isset($params[2]) ? implode(' ', array_slice($params, 2)) : null;
        $addedBy = $this->whitelistService->normalizePhoneNumber($fromJid);

        try {
            $item = $this->whitelistService->add($phone, $name, $addedBy);

            return $this->templateService->render('command', 'WHITELIST_ADDED', [
                'phone' => $item->phone_number,
                'name' => $item->name ?? 'Admin',
            ]);
        } catch (\Exception $e) {
            return '❌ Gagal menambahkan nomor: '.$e->getMessage();
        }
    }

    private function handleRemove(array $params): string
    {
        $phone = $params[1] ?? null;
        if (! $phone) {
            return "❌ Format salah!\nGunakan: /whitelist del 08xxx";
        }

        if ($this->whitelistService->remove($phone)) {
            return $this->templateService->render('command', 'WHITELIST_REMOVED', [
                'phone' => $phone,
            ]);
        }

        return $this->templateService->render('command', 'WHITELIST_NOT_FOUND', [
            'phone' => $phone,
        ]);
    }
}
