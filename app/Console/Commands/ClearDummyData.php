<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDummyData extends Command
{
    protected $signature = 'dummy:clear {--force : Skip confirmation} {--keep-users : Keep all users, not just admin}';

    protected $description = 'Clear all dummy data but preserve settings and admin user';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete ALL test requests, samples, documents, surveys, and inventory data (but preserve settings and admin user). Continue?')) {
            $this->info('Cancelled.');

            return 0;
        }

        $this->info('Clearing dummy data (preserving settings & admin)...');

        // Tables to truncate completely (no data to preserve)
        $tablesToTruncate = [
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
            'number_sequences', // Reset numbering sequences so they start from 1
        ];

        // Tables to clear selectively (preserve certain records)
        // - users: preserve admin users
        // - settings: preserve all (not touched)

        DB::beginTransaction();

        try {
            // 1. Truncate tables that can be fully cleared
            foreach ($tablesToTruncate as $table) {
                $this->line("Truncating {$table}...");
                DB::statement("TRUNCATE TABLE {$table} CASCADE");
                $this->info("  ✓ Truncated {$table}");
            }

            // 2. Clear non-admin users (preserve admin)
            if (! $this->option('keep-users')) {
                $this->line('Clearing non-admin users...');
                $deletedUsers = User::where('role', '!=', 'admin')->delete();
                $this->info("  ✓ Deleted {$deletedUsers} non-admin users");
            }

            // 3. Settings table is NOT touched - preserved automatically
            $this->info('  ✓ Settings preserved (not touched)');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to clear data: '.$e->getMessage());

            return 1;
        }

        $this->newLine();
        $this->info('✅ All dummy data cleared successfully!');
        $this->info('   Preserved: settings, admin user(s)');

        return 0;
    }
}
