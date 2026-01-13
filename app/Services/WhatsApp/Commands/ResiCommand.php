<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\TestRequest;
use Carbon\Carbon;

class ResiCommand
{
    public function execute(string $fromJid, array $params): string
    {
        if (empty($params[0])) {
            return "❌ Format salah!\n\nGunakan: /resi {nomor_resi}\n\nContoh: /resi LPMF/001/2026";
        }

        $receiptNumber = $params[0];

        // Find test request
        $testRequest = TestRequest::with(['investigator', 'samples'])
            ->where('receipt_number', $receiptNumber)
            ->first();

        if (! $testRequest) {
            return "❌ Nomor resi tidak ditemukan: {$receiptNumber}\n\nPastikan nomor resi benar.";
        }

        // Build tracking response
        return $this->buildTrackingResponse($testRequest);
    }

    private function buildTrackingResponse(TestRequest $testRequest): string
    {
        $response = "📋 *TRACKING PERMINTAAN PENGUJIAN*\n\n";
        $response .= "📝 Resi: *{$testRequest->receipt_number}*\n";
        $response .= "📄 No. Permintaan: {$testRequest->request_number}\n\n";

        // Investigator info
        if ($testRequest->investigator) {
            $response .= "👤 Penyidik: {$testRequest->investigator->name}\n";
        }

        // Status timeline
        $response .= "\n📍 *STATUS PERJALANAN:*\n\n";

        $milestones = $this->getMilestones($testRequest);

        foreach ($milestones as $milestone) {
            if ($milestone['completed']) {
                $icon = '✅';
                $statusText = '';
            } elseif ($milestone['current'] ?? false) {
                $icon = '▶️'; // Sedang berjalan
                $statusText = ' (PROSES)';
            } else {
                $icon = '⚪'; // Belum
                $statusText = '';
            }
            
            $response .= "{$icon} *{$milestone['label']}*{$statusText}\n";

            if (!empty($milestone['timestamp'])) {
                $response .= "   🕒 {$milestone['timestamp']}\n";
            }

            $response .= "\n";
        }

        // Current status summary
        $currentStatus = $this->getCurrentStatusText($testRequest->status);
        $response .= "\n🔔 Status Saat Ini:\n*{$currentStatus}*\n";

        // Sample count
        $sampleCount = $testRequest->samples->count();
        if ($sampleCount > 0) {
            $response .= "\n📦 Jumlah Sampel: {$sampleCount}\n";
        }

        // Footer
        $response .= "\n─────────────────\n";
        $response .= "💬 Butuh bantuan? Ketik /help";

        return $response;
    }

    private function getMilestones(TestRequest $testRequest): array
    {
        $tz = settings('locale.timezone', 'Asia/Jakarta');

        // 1. Permintaan
        $milestones = [
            [
                'label' => '1. Permintaan',
                'completed' => $testRequest->submitted_at !== null,
                'timestamp' => $testRequest->submitted_at ?
                    Carbon::parse($testRequest->submitted_at)->timezone($tz)->format('d M Y, H:i') : null,
            ],
        ];

        // 2. Kaji Ulang Permintaan
        $milestones[] = [
            'label' => '2. Kaji Ulang Permintaan',
            'completed' => $testRequest->verified_at !== null,
            'timestamp' => $testRequest->verified_at ?
                Carbon::parse($testRequest->verified_at)->timezone($tz)->format('d M Y, H:i') : null,
        ];

        // 3. Pengujian (With substeps visualization)
        $isTestingStarted = $testRequest->received_at !== null;
        $isTestingDone = $testRequest->completed_at !== null || $testRequest->status === 'completed';
        
        $substeps = $isTestingStarted 
            ? "\n      a. Preparasi sampel\n      b. Pengujian pada instrumen\n      c. Interpretasi hasil" 
            : "";

        $milestones[] = [
            'label' => '3. Pengujian' . $substeps,
            'completed' => $isTestingDone,
            'current' => $isTestingStarted && !$isTestingDone, // Flag custom untuk icon 'sedang jalan'
            'timestamp' => $testRequest->received_at ?
                Carbon::parse($testRequest->received_at)->timezone($tz)->format('d M Y, H:i') : null,
        ];

        // 4. Siap Diserahkan
        $milestones[] = [
            'label' => '4. Siap Diserahkan',
            'completed' => $testRequest->completed_at !== null,
            'timestamp' => $testRequest->completed_at ?
                Carbon::parse($testRequest->completed_at)->timezone($tz)->format('d M Y, H:i') : null,
        ];

        // 5. Selesai (Asumsi delivered jika ada flag/status tertentu, atau manual check)
        // Untuk saat ini kita anggap 'Selesai' jika status final, atau sama dengan completed jika belum ada fitur delivery tracking
        // Kita cek status == 'delivered' jika ada, atau gunakan completed_at sebagai proxy sementara
        $isDelivered = $testRequest->status === 'delivered'; 
        
        $milestones[] = [
            'label' => '5. Selesai',
            'completed' => $isDelivered,
            'timestamp' => null, // Tambahkan delivered_at jika kolom ada
        ];

        return $milestones;
    }

    private function getCurrentStatusText(string $status): string
    {
        return match ($status) {
            'draft' => '1. Permintaan (Draft)',
            'submitted' => '1. Permintaan (Disubmit)',
            'pending_verification' => '1. Permintaan (Menunggu Verifikasi)',
            'verified' => '2. Kaji Ulang Permintaan (Selesai)',
            'pending_review' => '2. Kaji Ulang Permintaan (Sedang Review)',
            'ready_for_test' => '3. Pengujian (Siap)',
            'in_testing' => '3. Pengujian (Sedang Berjalan)',
            'processing' => '3. Pengujian (Proses)',
            'completed' => '4. Siap Diserahkan',
            'delivered' => '5. Selesai',
            default => 'Status: ' . ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
