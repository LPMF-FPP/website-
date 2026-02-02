<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\TestRequest;
use Illuminate\Console\Command;

class BackfillReadyForDeliveryAtCommand extends Command
{
    protected $signature = 'app:backfill-ready-for-delivery-at 
                            {--dry-run : Show what would be updated without making changes}
                            {--force-estimate : For requests without activity log, use completed_at as estimate}';

    protected $description = 'Backfill ready_for_delivery_at for existing test requests using Activity Log';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $forceEstimate = $this->option('force-estimate');

        $this->info('=== Backfill ready_for_delivery_at ===');
        $this->newLine();

        // STEP 1: Status ready_for_delivery - gunakan completed_at (masih akurat)
        $this->info('Step 1: Processing ready_for_delivery requests...');
        $readyRequests = TestRequest::where('status', 'ready_for_delivery')
            ->whereNull('ready_for_delivery_at')
            ->whereNotNull('completed_at')
            ->get();

        $this->line("  Found {$readyRequests->count()} requests");

        foreach ($readyRequests as $request) {
            $this->line("  ✓ {$request->request_number}: {$request->completed_at->format('Y-m-d H:i:s')} (from completed_at)");
            if (! $dryRun) {
                $request->update(['ready_for_delivery_at' => $request->completed_at]);
            }
        }

        // STEP 2: Status completed - cari dari Activity Log
        $this->newLine();
        $this->info('Step 2: Processing completed requests (checking Activity Log)...');

        $completedRequests = TestRequest::where('status', 'completed')
            ->whereNull('ready_for_delivery_at')
            ->get();

        $this->line("  Found {$completedRequests->count()} requests");

        $recovered = 0;
        $notRecoverable = [];

        foreach ($completedRequests as $request) {
            // Cari di Activity Log kapan status berubah ke ready_for_delivery
            $log = ActivityLog::where('subject_id', $request->id)
                ->where('subject_type', TestRequest::class)
                ->get()
                ->first(function ($log) {
                    $after = is_array($log->after) ? $log->after : json_decode($log->after, true);

                    return ($after['status'] ?? null) === 'ready_for_delivery';
                });

            if ($log) {
                $this->line("  ✓ {$request->request_number}: {$log->created_at->format('Y-m-d H:i:s')} (from Activity Log)");
                if (! $dryRun) {
                    $request->update(['ready_for_delivery_at' => $log->created_at]);
                }
                $recovered++;
            } else {
                $notRecoverable[] = $request;
            }
        }

        // STEP 3: Handle requests tanpa Activity Log
        $this->newLine();
        $notRecoverableCount = count($notRecoverable);

        if ($notRecoverableCount > 0) {
            $this->warn("Step 3: {$notRecoverableCount} requests have NO Activity Log:");

            foreach ($notRecoverable as $request) {
                if ($forceEstimate && $request->completed_at) {
                    $this->line("  ⚠ {$request->request_number}: {$request->completed_at->format('Y-m-d H:i:s')} (ESTIMATE from completed_at)");
                    if (! $dryRun) {
                        $request->update(['ready_for_delivery_at' => $request->completed_at]);
                    }
                } else {
                    $this->line("  ✗ {$request->request_number}: SKIPPED (no accurate data available)");
                }
            }

            if (! $forceEstimate) {
                $this->newLine();
                $this->warn('Use --force-estimate to use completed_at as fallback (not accurate!)');
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== Summary ===');
        $this->line("  Ready for delivery: {$readyRequests->count()} (from completed_at)");
        $this->line("  Completed + Activity Log: {$recovered} (accurate)");
        $this->line('  Completed + No Log: '.count($notRecoverable).' ('.($forceEstimate ? 'estimated' : 'skipped').')');

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN - no changes made');
        }

        return Command::SUCCESS;
    }
}
