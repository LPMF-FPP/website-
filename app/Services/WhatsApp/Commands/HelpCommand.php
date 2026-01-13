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

        $response .= "📝 *INPUT MANUAL*\n";
        $response .= "🌡️ *Suhu*: `/suhu {lokasi} {nilai} {pagi/siang}`\n";
        $response .= "   Contoh: /suhu R01 24.5 pagi\n";
        $response .= "📦 *Stok*: `/stok {masuk/keluar} {nama} {jml}`\n";
        $response .= "   Contoh: /stok masuk alkohol 5\n\n";

        $response .= "📊 *INFORMASI*\n";
        $response .= "🔍 `/stok` (tanpa parameter): Lihat daftar stok item.\n";
        $response .= "🌡️ `/suhu` (tanpa parameter): Lihat daftar sensor.\n";
        $response .= "📈 `/status` : Lihat statistik permintaan.\n\n";

        $response .= "🔔 *FITUR OTOMATIS*\n";
        $response .= "Bot ini juga akan mengirim notifikasi untuk:\n";
        $response .= "• 🌡️ *Peringatan Suhu* (Real-time)\n";
        $response .= "• 📦 *Stok Menipis & Kadaluarsa* (Setiap 08:00)\n\n";

        // Admin Only Section
        $senderNumber = explode('@', $fromJid)[0];
        $adminNumber = settings('notifications.whatsapp.admin_number', '6285956592404');
        
        if ($senderNumber === $adminNumber) {
            $response .= "🛠️ *ADMIN TOOLS*\n";
            $response .= "`/restart` : Restart System/Queue\n\n";
        }

        $response .= "─────────────────\n";
        $response .= "💬 Hubungi kami jika butuh bantuan lebih lanjut.";

        return $response;
    }
}
