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

            dispatch(function () {
                \Illuminate\Support\Facades\Log::warning("KILLING QUEUE WORKER PID: " . getmypid());
                posix_kill(getmypid(), SIGKILL);
            })->afterCommit();

            Log::info("Queue worker restart triggered via WhatsApp by {$senderNumber}");

            return "🔄 *Sistem Restarted (Hard Kill)*\n\nWorker akan dimatikan paksa setelah pesan ini terkirim.\nSystemd akan menghidupkannya kembali otomatis.\n\nTunggu ~10 detik.";
        } catch (\Exception $e) {
            Log::error("Failed to restart worker via WhatsApp: " . $e->getMessage());
            return "❌ Gagal melakukan restart: " . $e->getMessage();
        }
    }
}
