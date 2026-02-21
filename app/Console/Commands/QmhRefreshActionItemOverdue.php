<?php

namespace App\Console\Commands;

use App\Models\QmhRapatActionItem;
use App\Services\Quality\AuditTrailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class QmhRefreshActionItemOverdue extends Command
{
    protected $signature = 'qmh:action-items:refresh-overdue';

    protected $description = 'Refresh status overdue untuk action item QMH';

    public function handle(): int
    {
        $lock = Cache::lock('qmh-action-items-refresh-overdue', 300);
        if (! $lock->get()) {
            $this->warn('Refresh overdue sedang berjalan pada proses lain.');

            return self::SUCCESS;
        }

        try {
            $affected = 0;
            $auditTrailService = app(AuditTrailService::class);

            QmhRapatActionItem::query()
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereIn('status', [
                    QmhRapatActionItem::STATUS_OPEN,
                    QmhRapatActionItem::STATUS_IN_PROGRESS,
                    QmhRapatActionItem::STATUS_RESOLVED,
                ])
                ->orderBy('id')
                ->chunkById(200, function ($items) use (&$affected, $auditTrailService): void {
                    foreach ($items as $item) {
                        $before = $item->toArray();
                        $item->status = QmhRapatActionItem::STATUS_OVERDUE;
                        $item->save();

                        $auditTrailService->log(
                            tableName: 'qmh_rapat_action_items',
                            recordId: $item->id,
                            action: 'STATE_CHANGE',
                            oldValues: $before,
                            newValues: $item->fresh()?->toArray(),
                            source: 'scheduler',
                            reason: 'Auto overdue refresh'
                        );

                        $affected++;
                    }
                });

            $this->info(sprintf('Refresh overdue selesai. %d item diperbarui.', $affected));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
