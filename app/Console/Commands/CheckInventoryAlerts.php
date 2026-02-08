<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryAlertService;
use Illuminate\Console\Command;

class CheckInventoryAlerts extends Command
{
    protected $signature = 'inventory:check-alerts';

    protected $description = 'Check for low stock and expiring items and send alerts via WhatsApp';

    public function handle(InventoryAlertService $service)
    {
        $this->info('Checking inventory alerts...');

        $this->info('Checking low stock...');
        $service->checkLowStock();

        $this->info('Checking expiry dates...');
        $expiryDays = (int) settings('inventory.alert_expiry_days', 30);
        $service->checkExpiry($expiryDays);

        $this->info('Done.');
    }
}
