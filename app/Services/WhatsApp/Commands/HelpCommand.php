<?php

namespace App\Services\WhatsApp\Commands;

class HelpCommand
{
    public function execute(string $fromJid, array $params): string
    {
        $response = "🤖 *BOT LPMF TRACKING*\n\n";
        $response .= "Daftar command yang tersedia:\n\n";

        $response .= "📦 */resi {nomor_resi}*\n";
        $response .= "   Cek status perjalanan permintaan pengujian\n";
        $response .= "   Contoh: /resi LPMF/001/2026\n\n";

        $response .= "❓ */help* atau */bantuan*\n";
        $response .= "   Tampilkan menu bantuan ini\n\n";

        $response .= "─────────────────\n";
        $response .= "💬 Hubungi kami jika butuh bantuan lebih lanjut.";

        return $response;
    }
}
