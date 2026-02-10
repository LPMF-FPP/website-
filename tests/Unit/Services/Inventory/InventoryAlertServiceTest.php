<?php

namespace Tests\Unit\Services\Inventory;

use App\Models\InventoryAlertLog;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\SystemSetting;
use App\Models\WhatsappWhitelist;
use App\Services\Inventory\InventoryAlertService;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\WhitelistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InventoryAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_alert_is_sent_to_all_whitelisted_admins_and_super_admin_fallback(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.admin_number'],
            ['value' => '628999000111']
        );

        $whitelist = new WhitelistService;
        $whitelist->add('08123456789', 'Admin 1');
        $whitelist->add('628111222333', 'Admin 2');

        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::factory()->create([
            'min_stock' => 10,
            'is_active' => true,
            'uom' => 'pcs',
        ]);

        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);

        $expectedJids = [
            '628123456789@s.whatsapp.net',
            '628111222333@s.whatsapp.net',
            '628999000111@s.whatsapp.net',
        ];

        $sent = [];
        $gowa = Mockery::mock(GowaClient::class);
        $gowa->shouldReceive('sendMessage')
            ->times(count($expectedJids))
            ->andReturnUsing(function (string $jid, string $message) use (&$sent): array {
                $sent[] = $jid;
                $this->assertStringContainsString('*LOW STOCK ALERT*', $message);

                return ['success' => true];
            });

        $service = new InventoryAlertService($gowa, $whitelist);
        $service->checkLowStock();

        $this->assertSame(1, InventoryAlertLog::query()->count());
        $log = InventoryAlertLog::query()->firstOrFail();
        $this->assertSame('LOW_STOCK', $log->alert_type);
        $this->assertSame($item->id, $log->item_id);
        $this->assertNotEmpty($log->recipients);

        sort($sent);
        sort($expectedJids);

        $this->assertSame($expectedJids, $sent);
    }

    public function test_low_stock_alert_is_not_sent_to_whitelisted_admins_who_opted_out_of_inventory_alerts(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.admin_number'],
            ['value' => '628999000111']
        );

        $whitelist = new WhitelistService;
        $whitelist->add('08123456789', 'Admin 1');
        $whitelist->add('628111222333', 'Admin 2');
        $whitelist->add('628777888999', 'Opted Out Admin');

        WhatsappWhitelist::where('phone_number', '628777888999')
            ->update(['receive_inventory_alerts' => false]);

        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::factory()->create([
            'min_stock' => 10,
            'is_active' => true,
            'uom' => 'pcs',
        ]);

        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);

        $expectedJids = [
            '628123456789@s.whatsapp.net',
            '628111222333@s.whatsapp.net',
            '628999000111@s.whatsapp.net',
        ];

        $sent = [];
        $gowa = Mockery::mock(GowaClient::class);
        $gowa->shouldReceive('sendMessage')
            ->andReturnUsing(function (string $jid, string $message) use (&$sent): array {
                $sent[] = $jid;
                $this->assertStringContainsString('*LOW STOCK ALERT*', $message);

                return ['success' => true];
            });

        $service = new InventoryAlertService($gowa, $whitelist);
        $service->checkLowStock();

        $this->assertSame(1, InventoryAlertLog::query()->count());

        $optedOutJid = '628777888999@s.whatsapp.net';
        $this->assertFalse(in_array($optedOutJid, $sent, true), 'Opted-out admin should not receive inventory alerts.');

        sort($sent);
        sort($expectedJids);

        $this->assertSame($expectedJids, $sent);
    }
}
