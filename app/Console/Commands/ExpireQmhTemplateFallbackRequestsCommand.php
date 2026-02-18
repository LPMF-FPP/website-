<?php

namespace App\Console\Commands;

use App\Services\Quality\QmhTemplateFallbackService;
use Illuminate\Console\Command;

class ExpireQmhTemplateFallbackRequestsCommand extends Command
{
    protected $signature = 'qmh:fallback:expire';

    protected $description = 'Expire overdue QMH template fallback requests';

    public function handle(QmhTemplateFallbackService $service): int
    {
        $total = $service->expirePendingRequests();

        $this->info("Expired fallback requests: {$total}");

        return self::SUCCESS;
    }
}
