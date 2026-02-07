<?php

namespace App\Services\Reminders\Handlers;

use App\Models\Reminder;
use Carbon\Carbon;

class CountdownHandler
{
    public function handle(Reminder $reminder): string
    {
        $metadata = is_array($reminder->metadata) ? $reminder->metadata : [];

        $targetDate = Carbon::parse($metadata['target_date'] ?? '2026-08-15');
        $daysRemaining = (int) Carbon::now()->diffInDays($targetDate, false);

        $eventName = $metadata['event_name'] ?? $reminder->name ?? 'Event';
        $eventEmoji = $metadata['event_emoji'] ?? '📅';

        $milestones = $this->normalizeMilestones($metadata['milestones'] ?? []);
        $milestoneMessage = $this->resolveMilestoneMessage($daysRemaining, $milestones);

        $message = (string) $reminder->message_template;
        $message = str_replace('{target_date}', $targetDate->translatedFormat('d F Y'), $message);
        $message = str_replace('{days_remaining}', (string) $daysRemaining, $message);
        $message = str_replace('{event_name}', $eventName, $message);
        $message = str_replace('{event_emoji}', $eventEmoji, $message);
        $message = str_replace('{milestone_message}', $milestoneMessage, $message);
        $message = str_replace('{motivation_message}', $milestoneMessage, $message);

        return $message;
    }

    /**
     * @param  array<int|string, mixed>  $rawMilestones
     * @return array<int, string>
     */
    private function normalizeMilestones(array $rawMilestones): array
    {
        $normalized = [];

        foreach ($rawMilestones as $key => $value) {
            if (is_array($value) && isset($value['days'], $value['message'])) {
                $days = (int) $value['days'];
                $message = trim((string) $value['message']);

                if ($days >= 0 && $message !== '') {
                    $normalized[$days] = $message;
                }

                continue;
            }

            if ((is_int($key) || is_string($key)) && is_string($value)) {
                $days = (int) $key;
                $message = trim($value);

                if ($days >= 0 && $message !== '') {
                    $normalized[$days] = $message;
                }
            }
        }

        if ($normalized === []) {
            return $this->defaultMilestones();
        }

        krsort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function defaultMilestones(): array
    {
        return [
            100 => 'Masih ada waktu panjang, persiapkan dengan santai tapi pasti.',
            60 => 'Dua bulan lagi. Mulai cek kelengkapan dokumen dan rencana kerja.',
            30 => 'Satu bulan tersisa. Pastikan semua poin persiapan sudah on-track.',
            14 => 'Dua minggu lagi. Fokus ke detail final dan koordinasi tim.',
            7 => 'Satu minggu terakhir. Final check sebelum hari H.',
            0 => 'HARI INI. Jalankan agenda sesuai rencana dan tetap tenang.',
        ];
    }

    /**
     * @param  array<int, string>  $milestones
     */
    private function resolveMilestoneMessage(int $daysRemaining, array $milestones): string
    {
        foreach ($milestones as $threshold => $message) {
            if ($daysRemaining >= $threshold) {
                return $message;
            }
        }

        return 'Event telah berlalu. Evaluasi hasil dan tindak lanjut perbaikan.';
    }
}
