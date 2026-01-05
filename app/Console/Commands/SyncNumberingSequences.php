<?php

namespace App\Console\Commands;

use App\Models\Sequence;
use App\Models\TestRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncNumberingSequences extends Command
{
    protected $signature = 'numbering:sync {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Sync numbering sequences based on existing records to prevent duplicate key violations';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Syncing numbering sequences...');

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
        }

        // Get current month/year for bucket calculation
        $now = now();
        $yearlyBucket = $now->format('Y');
        $monthlyBucket = $now->format('Y-m');

        // Sync BA sequence (request_number)
        $this->syncBaSequence($yearlyBucket, $monthlyBucket, $dryRun);

        // Sync Tracking sequence (receipt_number)
        $this->syncTrackingSequence($yearlyBucket, $monthlyBucket, $dryRun);

        $this->info('Sequence sync complete!');

        return Command::SUCCESS;
    }

    protected function syncBaSequence(string $yearlyBucket, string $monthlyBucket, bool $dryRun): void
    {
        $scope = 'ba';

        // Count existing requests with BA-RIM pattern for current month
        // Pattern: BA-RIM/{SEQ:3}/{RM}/{YYYY}/FPP
        $pattern = 'BA-RIM/%/' . $this->intToRoman((int) now()->format('m')) . '/' . now()->format('Y') . '/FPP';
        
        $maxSeq = TestRequest::where('request_number', 'like', $pattern)
            ->selectRaw("MAX(CAST(SPLIT_PART(request_number, '/', 2) AS INTEGER)) as max_seq")
            ->value('max_seq') ?? 0;

        $this->line("BA sequence: Found max sequence = {$maxSeq} for bucket '{$monthlyBucket}'");

        if (! $dryRun && $maxSeq > 0) {
            DB::transaction(function () use ($scope, $monthlyBucket, $maxSeq) {
                Sequence::updateOrCreate(
                    ['scope' => $scope, 'bucket' => $monthlyBucket],
                    ['current_value' => $maxSeq]
                );
            });
            $this->info("Updated {$scope} sequence to {$maxSeq}");
        }

        // Also check yearly bucket
        $yearlyMaxSeq = TestRequest::where('request_number', 'like', 'BA-RIM/%/%/' . now()->format('Y') . '/FPP')
            ->selectRaw("MAX(CAST(SPLIT_PART(request_number, '/', 2) AS INTEGER)) as max_seq")
            ->value('max_seq') ?? 0;

        $this->line("BA sequence (yearly): Found max sequence = {$yearlyMaxSeq} for bucket '{$yearlyBucket}'");

        if (! $dryRun && $yearlyMaxSeq > 0) {
            DB::transaction(function () use ($scope, $yearlyBucket, $yearlyMaxSeq) {
                Sequence::updateOrCreate(
                    ['scope' => $scope, 'bucket' => $yearlyBucket],
                    ['current_value' => $yearlyMaxSeq]
                );
            });
            $this->info("Updated {$scope} sequence (yearly) to {$yearlyMaxSeq}");
        }
    }

    protected function syncTrackingSequence(string $yearlyBucket, string $monthlyBucket, bool $dryRun): void
    {
        $scope = 'tracking';

        // Pattern: TR-LPMF{SEQ:3}{RM}{YYYY}
        // Extract sequence number from patterns like TR-LPMF001I2026
        $pattern = 'TR-LPMF%' . now()->format('Y');
        
        $maxSeq = TestRequest::where('receipt_number', 'like', $pattern)
            ->get()
            ->map(function ($request) {
                // Extract the 3-digit sequence from TR-LPMF{SEQ}{RM}{YYYY}
                if (preg_match('/TR-LPMF(\d{3})/', $request->receipt_number, $matches)) {
                    return (int) $matches[1];
                }
                return 0;
            })
            ->max() ?? 0;

        $this->line("Tracking sequence: Found max sequence = {$maxSeq} for bucket '{$monthlyBucket}'");

        if (! $dryRun && $maxSeq > 0) {
            DB::transaction(function () use ($scope, $monthlyBucket, $maxSeq) {
                Sequence::updateOrCreate(
                    ['scope' => $scope, 'bucket' => $monthlyBucket],
                    ['current_value' => $maxSeq]
                );
            });
            $this->info("Updated {$scope} sequence to {$maxSeq}");
        }

        // Also check yearly bucket
        if (! $dryRun && $maxSeq > 0) {
            DB::transaction(function () use ($scope, $yearlyBucket, $maxSeq) {
                Sequence::updateOrCreate(
                    ['scope' => $scope, 'bucket' => $yearlyBucket],
                    ['current_value' => $maxSeq]
                );
            });
            $this->info("Updated {$scope} sequence (yearly) to {$maxSeq}");
        }
    }

    protected function intToRoman(int $number): string
    {
        if ($number <= 0) {
            return '';
        }

        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];

        $result = '';
        foreach ($map as $value => $symbol) {
            while ($number >= $value) {
                $result .= $symbol;
                $number -= $value;
            }
        }

        return $result;
    }
}
