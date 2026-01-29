<?php

namespace App\Services\Reminders\Handlers;

use App\Models\Reminder;
use Carbon\Carbon;

class IsoCountdownHandler
{
    public function handle(Reminder $reminder): string
    {
        $targetDate = Carbon::parse($reminder->metadata['target_date'] ?? '2026-08-15');
        $now = Carbon::now();
        $daysRemaining = (int) $now->diffInDays($targetDate, false);

        // Simple motivation logic based on days remaining
        $motivation = match (true) {
            $daysRemaining > 100 => 'Masih ada waktu panjang, persiapkan dokumen dengan santai tapi pasti! 🐢',
            $daysRemaining > 60 => 'Dua bulan lagi! Mulai cek kelengkapan dokumen teknis. 🧐',
            $daysRemaining > 30 => 'Satu bulan tersisa! Rapatkan barisan dan finalisasi temuan internal audit. 💪',
            $daysRemaining > 14 => 'Dua minggu lagi! Fokus pada detail dan kebersihan lab. 🧹',
            $daysRemaining > 7 => 'Satu minggu terakhir! Pastikan semua personil siap. ⚡',
            $daysRemaining > 0 => "H-{$daysRemaining}! Semangat tim LPMF! 🔥",
            $daysRemaining === 0 => 'HARI INI! Good luck untuk asesmennya! 🌟',
            default => 'Surveillance telah selesai. Evaluasi hasil dan tindak lanjut perbaikan.',
        };

        // Replace placeholders
        $message = $reminder->message_template;
        $message = str_replace('{target_date}', $targetDate->translatedFormat('d F Y'), $message);
        $message = str_replace('{days_remaining}', (string) $daysRemaining, $message);
        $message = str_replace('{motivation_message}', $motivation, $message);

        return $message;
    }
}
