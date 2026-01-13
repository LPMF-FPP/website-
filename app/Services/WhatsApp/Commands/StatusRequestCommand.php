<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\TestRequest;
use Illuminate\Support\Facades\DB;

class StatusRequestCommand
{
    public function execute(string $fromJid, array $params): string
    {
        $stats = TestRequest::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $total = array_sum($stats);
        
        $response = "📊 *STATISTIK PERMINTAAN*\n\n";
        $response .= "Total Permintaan: {$total}\n\n";
        
        if ($total > 0) {
            foreach ($stats as $status => $count) {
                $label = ucfirst(str_replace('_', ' ', $status ?: 'Unknown'));
                $response .= "• {$label}: {$count}\n";
            }
        } else {
            $response .= "Belum ada data permintaan.";
        }
        
        $response .= "\nKetik `/resi {nomor}` untuk cek detail.";

        return $response;
    }
}
