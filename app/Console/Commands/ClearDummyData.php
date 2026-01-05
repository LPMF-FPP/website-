<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDummyData extends Command
{
    protected $signature = 'dummy:clear {--force : Skip confirmation}';

    protected $description = 'Clear all dummy data created by DummyDataSeeder';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('This will delete ALL test requests, samples, documents, surveys, and inventory data. Continue?')) {
            $this->info('Cancelled.');
            return 0;
        }

        $this->info('Clearing dummy data...');

        // Get list of tables to truncate (in dependency order, children first)
        $tables = [
            'customer_surveys',
            'remaining_units',
            'evidence_units', 
            'sample_test_processes',
            'documents',
            'samples',
            'suspects',
            'test_requests',
            'investigators',
            'inventory_movements',
            'inventory_balances',
            'inventory_lots',
            'inventory_items',
            'inventory_locations',
            'sequences', // Reset numbering sequences so they start from 1
        ];

        DB::beginTransaction();

        try {
            foreach ($tables as $table) {
                $this->line("Truncating {$table}...");
                DB::statement("TRUNCATE TABLE {$table} CASCADE");
                $this->info("  ✓ Truncated {$table}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to clear data: " . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('✅ All dummy data cleared successfully!');

        return 0;
    }
}
