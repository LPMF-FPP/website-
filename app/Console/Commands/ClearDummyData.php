<?php

namespace App\Console\Commands;

use App\Models\CustomerSurvey;
use App\Models\Document;
use App\Models\InventoryBalance;
use App\Models\InventoryLot;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
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

        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        // Clear in order of dependencies
        $this->line('Deleting customer surveys...');
        $deleted = CustomerSurvey::query()->delete();
        $this->info("  ✓ Deleted {$deleted} customer surveys");

        $this->line('Deleting documents...');
        $deleted = Document::query()->delete();
        $this->info("  ✓ Deleted {$deleted} documents");

        $this->line('Deleting samples...');
        $deleted = Sample::query()->delete();
        $this->info("  ✓ Deleted {$deleted} samples");

        $this->line('Deleting test requests...');
        $deleted = TestRequest::query()->delete();
        $this->info("  ✓ Deleted {$deleted} test requests");

        $this->line('Deleting investigators...');
        $deleted = Investigator::query()->delete();
        $this->info("  ✓ Deleted {$deleted} investigators");

        $this->line('Deleting inventory balances...');
        $deleted = InventoryBalance::query()->delete();
        $this->info("  ✓ Deleted {$deleted} inventory balances");

        $this->line('Deleting inventory lots...');
        $deleted = InventoryLot::query()->delete();
        $this->info("  ✓ Deleted {$deleted} inventory lots");

        $this->line('Deleting inventory items...');
        $deleted = InventoryItem::query()->delete();
        $this->info("  ✓ Deleted {$deleted} inventory items");

        $this->line('Deleting inventory locations...');
        $deleted = InventoryLocation::query()->delete();
        $this->info("  ✓ Deleted {$deleted} inventory locations");

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        $this->newLine();
        $this->info('✅ All dummy data cleared successfully!');

        return 0;
    }
}
