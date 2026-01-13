<?php

namespace App\Services\WhatsApp\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RestartWorkerCommand
{
    public function execute(string $fromJid, array $params): string
    {
        $senderNumber = explode('@', $fromJid)[0];
        
        $adminNumber = settings('notifications.whatsapp.admin_number', '6285956592404');

        if ($senderNumber !== $adminNumber) {
            return "⛔ *Akses Ditolak*\nCommand ini hanya untuk Administrator.";
        }

        try {
            Artisan::call('queue:restart');
            
            Artisan::call('cache:clear');

            Log::info("Queue worker restart triggered via WhatsApp by {$senderNumber}");

            return "🔄 *Sistem Restarted*\n\nQueue worker telah di-restart.\nCache aplikasi telah dibersihkan.\n\nSilakan tunggu 10-20 detik sebelum mencoba command lain.";
        } catch (\Exception $e) {
            Log::error("Failed to restart worker via WhatsApp: " . $e->getMessage());
            return "❌ Gagal melakukan restart: " . $e->getMessage();
        }
    }
}
