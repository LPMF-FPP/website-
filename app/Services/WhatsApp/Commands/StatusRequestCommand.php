<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\Sample;
use App\Models\TestRequest;
use Illuminate\Support\Facades\DB;

class StatusRequestCommand
{
    public function execute(string $fromJid, array $params): string
    {
        $kajiUlangStatuses = ['submitted', 'pending_verification', 'verified', 'pending_review'];
        $pengujianStatuses = ['ready_for_test', 'in_testing', 'processing'];
        $siapDiserahkanStatuses = ['ready_for_delivery', 'completed'];

        $ongoingStatuses = array_merge($kajiUlangStatuses, $pengujianStatuses, $siapDiserahkanStatuses);

        $requests = TestRequest::select('status', DB::raw('count(*) as total'))
            ->whereIn('status', $ongoingStatuses)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $kajiUlangCount = 0;
        $pengujianCount = 0;
        $siapDiserahkanCount = 0;

        foreach ($requests as $status => $count) {
            if (in_array($status, $kajiUlangStatuses)) {
                $kajiUlangCount += $count;
            }
            if (in_array($status, $pengujianStatuses)) {
                $pengujianCount += $count;
            }
            if (in_array($status, $siapDiserahkanStatuses)) {
                $siapDiserahkanCount += $count;
            }
        }

        $totalOngoing = $kajiUlangCount + $pengujianCount + $siapDiserahkanCount;

        $sampleQuery = Sample::whereHas('testRequest', function ($q) use ($ongoingStatuses) {
            $q->whereIn('status', $ongoingStatuses);
        });

        $totalSamples = $sampleQuery->count();

        $activeSubstances = $sampleQuery->whereNotNull('active_substance')
            ->where('active_substance', '!=', '')
            ->distinct()
            ->pluck('active_substance')
            ->toArray();

        $response = "📊 *STATISTIK PERMINTAAN*\n\n";
        $response .= "🔢 Total Ongoing: *{$totalOngoing}*\n";
        $response .= "   ├ 📝 Kaji Ulang: {$kajiUlangCount}\n";
        $response .= "   ├ ⚗️ Pengujian: {$pengujianCount}\n";
        $response .= "   └ 📦 Siap Diserahkan: {$siapDiserahkanCount}\n\n";

        $response .= "🧪 Jumlah Sampel: *{$totalSamples}*\n";

        if (! empty($activeSubstances)) {
            $substList = implode(', ', array_slice($activeSubstances, 0, 10));
            if (count($activeSubstances) > 10) {
                $substList .= ', dll...';
            }
            $response .= "💊 Zat Aktif: {$substList}\n";
        } else {
            $response .= "💊 Zat Aktif: -\n";
        }

        $response .= "\n─────────────────\n";
        $response .= 'Ketik `/resi {nomor}` untuk detail.';

        return $response;
    }
}
