<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check queue configuration and required tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Checking queue configuration...');
        $this->newLine();

        $driver = Config::get('queue.default');
        $this->info("📋 Queue driver: {$driver}");

        if ($driver === 'sync') {
            $this->info('✅ Sync driver detected - no tables required');
            $this->newLine();
            $this->comment('💡 Jobs will run synchronously (not async)');
            return self::SUCCESS;
        }

        if ($driver === 'database') {
            return $this->checkDatabaseQueue();
        }

        $this->warn("⚠️  Queue driver '{$driver}' - skipping table checks");
        return self::SUCCESS;
    }

    protected function checkDatabaseQueue(): int
    {
        $this->info('📊 Checking database queue tables...');
        $this->newLine();

        $tables = [
            'jobs' => 'Stores pending jobs',
            'job_batches' => 'Stores batch job information',
            'failed_jobs' => 'Stores failed jobs for retry',
        ];

        $allExist = true;

        foreach ($tables as $table => $description) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->info("✅ {$table} - {$description} ({$count} records)");
            } else {
                $this->error("❌ {$table} - Missing! {$description}");
                $allExist = false;
            }
        }

        $this->newLine();

        if (!$allExist) {
            $this->error('❌ Queue tables are missing!');
            $this->newLine();
            $this->comment('🔧 Fix by running:');
            $this->line('   php artisan migrate');
            $this->newLine();
            $this->comment('📖 Or switch to sync queue in .env:');
            $this->line('   QUEUE_CONNECTION=sync');
            $this->newLine();
            return self::FAILURE;
        }

        $this->info('✅ All queue tables exist');
        $this->newLine();

        // Check for stuck jobs
        $stuckJobs = DB::table('jobs')
            ->where('reserved_at', '<', now()->subHours(1)->timestamp)
            ->whereNotNull('reserved_at')
            ->count();

        if ($stuckJobs > 0) {
            $this->warn("⚠️  {$stuckJobs} jobs may be stuck (reserved >1 hour ago)");
            $this->comment('   Consider restarting queue worker');
        }

        // Check pending jobs
        $pendingJobs = DB::table('jobs')->count();
        if ($pendingJobs > 0) {
            $this->warn("📋 {$pendingJobs} jobs in queue");
            $this->comment('   Make sure queue worker is running:');
            $this->line('   php artisan queue:work');
        } else {
            $this->info('📋 No pending jobs');
        }

        // Check failed jobs
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $this->warn("⚠️  {$failedJobs} failed jobs");
            $this->comment('   Review with: php artisan queue:failed');
            $this->comment('   Retry with: php artisan queue:retry all');
        } else {
            $this->info('✅ No failed jobs');
        }

        $this->newLine();
        $this->info('✅ Queue health check complete');

        return self::SUCCESS;
    }
}
