<?php

namespace App\Console\Commands;

use App\Models\Sample;
use App\Models\Sequence;
use App\Models\TestRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixNumberingSequence extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:numbering
                            {--delete= : Delete request by ID or suspect name (partial match)}
                            {--renumber : Renumber all requests sequentially}
                            {--reset-counters : Reset sequence counters to match actual data}
                            {--dry-run : Show what would be done without making changes}
                            {--year= : Target year bucket (default: current year)}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix numbering issues: delete problematic requests, renumber sequentially, and reset counters';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = $this->option('year') ?? now()->year;
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Show current state
        $this->showCurrentState($year);

        // Handle deletion if requested
        if ($deleteTarget = $this->option('delete')) {
            if (! $this->deleteRequest($deleteTarget, $dryRun, $force)) {
                return self::FAILURE;
            }
        }

        // Handle renumbering if requested
        if ($this->option('renumber')) {
            $this->renumberRequests($year, $dryRun, $force);
        }

        // Handle counter reset if requested
        if ($this->option('reset-counters')) {
            $this->resetCounters($year, $dryRun, $force);
        }

        // If no specific action, show help
        if (! $this->option('delete') && ! $this->option('renumber') && ! $this->option('reset-counters')) {
            $this->info('Available actions:');
            $this->line('  --delete=<id|name>   Delete a request by ID or suspect name');
            $this->line('  --renumber           Renumber all requests sequentially');
            $this->line('  --reset-counters     Reset sequence counters to match actual data');
            $this->line('  --dry-run            Preview changes without applying them');
            $this->line('  --force              Skip confirmation prompts');
            $this->newLine();
            $this->info('Example usage:');
            $this->line('  php artisan fix:numbering --delete=SABRI --renumber --reset-counters');
            $this->line('  php artisan fix:numbering --delete=145 --reset-counters --force');
            $this->line('  php artisan fix:numbering --renumber --reset-counters --dry-run');
        }

        // Show final state
        if ($this->option('delete') || $this->option('renumber') || $this->option('reset-counters')) {
            $this->newLine();
            $this->showCurrentState($year, 'Final State');
        }

        return self::SUCCESS;
    }

    /**
     * Show current state of requests and sequences
     */
    protected function showCurrentState(int $year, string $title = 'Current State'): void
    {
        $this->info("=== $title (Year: $year) ===");
        $this->newLine();

        // Show requests
        $requests = TestRequest::orderBy('id')->get();
        $this->info('Test Requests (' . $requests->count() . ' total):');

        $tableData = [];
        foreach ($requests as $r) {
            $tableData[] = [
                $r->id,
                $r->receipt_number,
                $r->request_number,
                \Illuminate\Support\Str::limit($r->suspect_name, 30),
            ];
        }

        if ($tableData) {
            $this->table(['ID', 'Resi', 'BA Number', 'Tersangka'], $tableData);
        } else {
            $this->line('  No requests found.');
        }

        // Show samples
        $samples = Sample::orderBy('id')->get();
        $this->info('Samples (' . $samples->count() . ' total):');
        $codes = $samples->pluck('sample_code')->filter()->values();
        if ($codes->isNotEmpty()) {
            $this->line('  ' . $codes->implode(', '));
        } else {
            $this->line('  No samples found.');
        }

        // Show sequence counters
        $this->newLine();
        $this->info('Sequence Counters:');
        $sequences = Sequence::where('bucket', (string) $year)->get();
        foreach ($sequences as $seq) {
            $this->line("  {$seq->scope}: {$seq->current_value}");
        }

        $this->newLine();
    }

    /**
     * Delete a request by ID or suspect name
     */
    protected function deleteRequest(string $target, bool $dryRun, bool $force): bool
    {
        // Find the request
        $request = null;

        if (is_numeric($target)) {
            $request = TestRequest::find((int) $target);
        }

        if (! $request) {
            $request = TestRequest::where('suspect_name', 'LIKE', "%{$target}%")->first();
        }

        if (! $request) {
            $this->error("Request not found: {$target}");

            return false;
        }

        $this->warn("Found request to delete:");
        $this->table(
            ['ID', 'Resi', 'BA Number', 'Tersangka'],
            [[$request->id, $request->receipt_number, $request->request_number, $request->suspect_name]]
        );

        // Count related records
        $docCount = DB::table('documents')->where('test_request_id', $request->id)->count();
        $sampleIds = DB::table('samples')->where('test_request_id', $request->id)->pluck('id');
        $sampleCount = $sampleIds->count();
        $stpCount = DB::table('sample_test_processes')->whereIn('sample_id', $sampleIds)->count();
        $suspectCount = DB::table('suspects')->where('test_request_id', $request->id)->count();

        $this->line("  Related records: {$docCount} documents, {$sampleCount} samples, {$stpCount} test processes, {$suspectCount} suspects");

        if ($dryRun) {
            $this->info('  [DRY RUN] Would delete this request and all related records.');

            return true;
        }

        if (! $force && ! $this->confirm('Are you sure you want to delete this request?')) {
            $this->line('Deletion cancelled.');

            return false;
        }

        DB::beginTransaction();
        try {
            // Delete in correct order (children first)
            DB::table('documents')->where('test_request_id', $request->id)->delete();
            DB::table('sample_test_processes')->whereIn('sample_id', $sampleIds)->delete();
            DB::table('samples')->where('test_request_id', $request->id)->delete();
            DB::table('suspects')->where('test_request_id', $request->id)->delete();
            DB::table('recent_requests')->where('test_request_id', $request->id)->delete();
            DB::table('survey_responses')->where('test_request_id', $request->id)->delete();
            DB::table('customer_surveys')->where('test_request_id', $request->id)->delete();
            DB::table('test_requests')->where('id', $request->id)->delete();

            DB::commit();
            $this->info("✓ Deleted request ID {$request->id} and all related records.");

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to delete: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Renumber all requests sequentially
     */
    protected function renumberRequests(int $year, bool $dryRun, bool $force): void
    {
        $requests = TestRequest::orderBy('id')->get();

        if ($requests->isEmpty()) {
            $this->info('No requests to renumber.');

            return;
        }

        $this->info('Renumbering ' . $requests->count() . ' requests...');

        // Get pattern from settings
        $numbering = app(\App\Services\NumberingService::class);
        $baPattern = $this->getPattern($numbering, 'ba');
        $trackingPattern = $this->getPattern($numbering, 'tracking');

        $changes = [];
        $seq = 1;

        foreach ($requests as $request) {
            $oldBa = $request->request_number;
            $oldResi = $request->receipt_number;

            // Generate new numbers
            $newBa = $this->renderPattern($baPattern, $seq, $year);
            $newResi = $this->renderPattern($trackingPattern, $seq, $year);

            if ($oldBa !== $newBa || $oldResi !== $newResi) {
                $changes[] = [
                    'id' => $request->id,
                    'old_ba' => $oldBa,
                    'new_ba' => $newBa,
                    'old_resi' => $oldResi,
                    'new_resi' => $newResi,
                ];
            }

            $seq++;
        }

        if (empty($changes)) {
            $this->info('  All requests are already numbered correctly.');

            return;
        }

        $this->table(
            ['ID', 'Old BA', 'New BA', 'Old Resi', 'New Resi'],
            array_map(fn ($c) => [$c['id'], $c['old_ba'], $c['new_ba'], $c['old_resi'], $c['new_resi']], $changes)
        );

        if ($dryRun) {
            $this->info('[DRY RUN] Would apply ' . count($changes) . ' renumbering changes.');

            return;
        }

        if (! $force && ! $this->confirm('Apply these renumbering changes?')) {
            $this->line('Renumbering cancelled.');

            return;
        }

        DB::beginTransaction();
        try {
            $seq = 1;
            foreach ($requests as $request) {
                $request->request_number = $this->renderPattern($baPattern, $seq, $year);
                $request->receipt_number = $this->renderPattern($trackingPattern, $seq, $year);
                $request->save();
                $seq++;
            }
            DB::commit();
            $this->info('✓ Renumbered ' . count($changes) . ' requests.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to renumber: ' . $e->getMessage());
        }
    }

    /**
     * Reset sequence counters to match actual data
     */
    protected function resetCounters(int $year, bool $dryRun, bool $force): void
    {
        $this->info('Resetting sequence counters...');

        $requestCount = TestRequest::count();
        $sampleCount = Sample::count();

        $updates = [
            'tracking' => $requestCount,
            'ba' => $requestCount,
            'sample_code' => $sampleCount,
        ];

        $changes = [];
        foreach ($updates as $scope => $expectedValue) {
            $seq = Sequence::where('scope', $scope)->where('bucket', (string) $year)->first();
            $currentValue = $seq?->current_value ?? 0;

            if ($currentValue !== $expectedValue) {
                $changes[] = [
                    'scope' => $scope,
                    'current' => $currentValue,
                    'expected' => $expectedValue,
                ];
            }
        }

        if (empty($changes)) {
            $this->info('  All counters are already correct.');

            return;
        }

        $this->table(['Scope', 'Current', 'Expected'], $changes);

        if ($dryRun) {
            $this->info('[DRY RUN] Would reset ' . count($changes) . ' counters.');

            return;
        }

        if (! $force && ! $this->confirm('Reset these counters?')) {
            $this->line('Counter reset cancelled.');

            return;
        }

        foreach ($updates as $scope => $value) {
            Sequence::where('scope', $scope)
                ->where('bucket', (string) $year)
                ->update(['current_value' => $value]);
        }

        $this->info('✓ Reset ' . count($changes) . ' sequence counters.');
    }

    /**
     * Get numbering pattern for a scope
     */
    protected function getPattern($numbering, string $scope): string
    {
        try {
            $reflection = new \ReflectionMethod($numbering, 'getConfig');
            $reflection->setAccessible(true);
            $config = $reflection->invoke($numbering, $scope);

            return $config['pattern'] ?? '{SEQ:3}';
        } catch (\Exception $e) {
            return $scope === 'ba' ? 'BA-RIM/{SEQ:3}/{RM}/{YYYY}/FPP' : 'TR-LPMF{SEQ:3}{RM}{YYYY}';
        }
    }

    /**
     * Render a pattern with given sequence number and year
     */
    protected function renderPattern(string $pattern, int $seq, int $year): string
    {
        $month = now()->month;
        $romanMonths = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        $replacements = [
            '{YYYY}' => $year,
            '{YY}' => substr($year, -2),
            '{MM}' => str_pad($month, 2, '0', STR_PAD_LEFT),
            '{RM}' => $romanMonths[$month - 1] ?? 'I',
        ];

        $result = str_replace(array_keys($replacements), array_values($replacements), $pattern);

        // Handle {SEQ:N} pattern
        if (preg_match('/\{SEQ:(\d+)\}/', $result, $matches)) {
            $padLength = (int) $matches[1];
            $result = preg_replace('/\{SEQ:\d+\}/', str_pad($seq, $padLength, '0', STR_PAD_LEFT), $result);
        }

        return $result;
    }
}
