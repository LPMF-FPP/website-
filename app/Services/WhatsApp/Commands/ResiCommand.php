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
            $icon = $milestone['completed'] ? '✅' : '⏳';
            $status = $milestone['completed'] ? 'SELESAI' : 'PENDING';
            $timestamp = $milestone['timestamp'] ?? null;

            $response .= "{$icon} {$milestone['label']} - *{$status}*\n";

            if ($timestamp) {
                $response .= "   └ {$timestamp}\n";
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

        $milestones = [
            [
                'label' => '1️⃣ Permintaan Disubmit',
                'completed' => $testRequest->submitted_at !== null,
                'timestamp' => $testRequest->submitted_at ?
                    Carbon::parse($testRequest->submitted_at)->timezone($tz)->format('d M Y, H:i') : null,
            ],
            [
                'label' => '2️⃣ Permintaan Diverifikasi',
                'completed' => $testRequest->verified_at !== null,
                'timestamp' => $testRequest->verified_at ?
                    Carbon::parse($testRequest->verified_at)->timezone($tz)->format('d M Y, H:i') : null,
            ],
            [
                'label' => '3️⃣ Permintaan Diterima',
                'completed' => $testRequest->received_at !== null,
                'timestamp' => $testRequest->received_at ?
                    Carbon::parse($testRequest->received_at)->timezone($tz)->format('d M Y, H:i') : null,
            ],
        ];

        // Add completed milestone if status is completed
        if ($testRequest->status === 'completed') {
            $milestones[] = [
                'label' => '4️⃣ Serah Terima Selesai',
                'completed' => true,
                'timestamp' => $testRequest->completed_at ?
                    Carbon::parse($testRequest->completed_at)->timezone($tz)->format('d M Y, H:i') : null,
            ];
        } else {
            $milestones[] = [
                'label' => '4️⃣ Serah Terima Selesai',
                'completed' => false,
                'timestamp' => null,
            ];
        }

        return $milestones;
    }

    private function getCurrentStatusText(string $status): string
    {
        return match ($status) {
            'draft' => '📝 Draft - Belum disubmit',
            'pending_verification' => '🔍 Menunggu Verifikasi',
            'verified' => '✅ Terverifikasi - Menunggu Penerimaan',
            'pending_review' => '🔍 Menunggu Kajian Ulang',
            'ready_for_test' => '🧪 Siap Dilakukan Pengujian',
            'in_testing' => '⚗️ Sedang Dalam Pengujian',
            'completed' => '✅ Selesai - Sudah Diserahterimakan',
            default => '❓ Status: '.$status,
        };
    }
}
