<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WhatsApp\GowaUpdateReconciler;
use Illuminate\Console\Command;

final class ReconcileGowaUpdates extends Command
{
    protected $signature = 'gowa-updater:reconcile';

    protected $description = 'Reconcile stale GOWA update operations without repeating mutation.';

    public function handle(GowaUpdateReconciler $reconciler): int
    {
        $this->info('Reconciled '.$reconciler->reconcile().' GOWA operation(s).');

        return self::SUCCESS;
    }
}
