<?php

namespace App\Services\Inventory;

use App\Models\InventoryAlertLog;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\WhitelistService;
use Illuminate\Support\Facades\Log;

class InventoryAlertService
{
    public function __construct(
        protected GowaClient $whatsapp,
        protected WhitelistService $whitelistService
    ) {}

    public function checkLowStock(): void
    {
        $items = InventoryItem::where('is_active', true)
            ->where('min_stock', '>', 0)
            ->get();

        foreach ($items as $item) {
            $currentBalance = InventoryBalance::where('item_id', $item->id)->sum('on_hand_qty');

            if ($currentBalance <= $item->min_stock) {
                $this->sendLowStockAlert($item, $currentBalance);
            }
        }
    }

    public function checkExpiry(int $daysThreshold = 30): void
    {
        $thresholdDate = now()->addDays($daysThreshold)->toDateString();

        $lots = InventoryLot::where('status', 'ACTIVE')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $thresholdDate)
            ->where('expiry_date', '>=', now()->toDateString())
            ->get();

        foreach ($lots as $lot) {
            $this->sendExpiryAlert($lot);
        }
    }

    protected function sendLowStockAlert(InventoryItem $item, float $currentBalance): void
    {
        $message = "⚠️ *LOW STOCK ALERT*\n\n";
        $message .= "Item: {$item->name}\n";
        $message .= 'Code: '.($item->code ?? $item->name)."\n";
        $message .= "Current Balance: {$currentBalance} {$item->uom}\n";
        $message .= "Min Stock: {$item->min_stock} {$item->uom}\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

        $result = $this->sendNotification($message);

        try {
            InventoryAlertLog::create([
                'alert_type' => 'LOW_STOCK',
                'item_id' => $item->id,
                'lot_id' => null,
                'message' => $message,
                'recipients' => $result['recipients'],
                'sent_to' => $result['sent_to'],
                'failed_to' => $result['failed_to'],
                'meta' => [
                    'current_balance' => (float) $currentBalance,
                    'min_stock' => (float) $item->min_stock,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store inventory alert log: '.$e->getMessage());
        }
    }

    protected function sendExpiryAlert(InventoryLot $lot): void
    {
        $item = $lot->item;
        $expiryDate = $lot->expiry_date instanceof \DateTime ? $lot->expiry_date->format('Y-m-d') : $lot->expiry_date;
        $daysUntil = now()->diffInDays($lot->expiry_date, false);

        $message = "⚠️ *EXPIRY ALERT*\n\n";
        $message .= "Item: {$item->name}\n";
        $message .= "Lot No: {$lot->lot_no}\n";
        $message .= "Expiry Date: {$expiryDate}\n";
        $message .= 'Days Remaining: '.(int) $daysUntil." days\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

        $result = $this->sendNotification($message);

        try {
            InventoryAlertLog::create([
                'alert_type' => 'EXPIRY',
                'item_id' => $item?->id,
                'lot_id' => $lot->id,
                'message' => $message,
                'recipients' => $result['recipients'],
                'sent_to' => $result['sent_to'],
                'failed_to' => $result['failed_to'],
                'meta' => [
                    'expiry_date' => $expiryDate,
                    'days_remaining' => (int) $daysUntil,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store inventory alert log: '.$e->getMessage());
        }
    }

    /**
     * @return array{recipients: array<int,string>, sent_to: array<int,string>, failed_to: array<int,string>}
     */
    protected function sendNotification(string $message): array
    {
        $recipients = $this->whitelistService->getAdminPhoneNumbers();

        $sentTo = [];
        $failedTo = [];

        foreach ($recipients as $adminNumber) {
            try {
                $result = $this->whatsapp->sendMessage($adminNumber.'@s.whatsapp.net', $message);

                if (($result['success'] ?? false) === true) {
                    $sentTo[] = $adminNumber;
                } else {
                    $failedTo[] = $adminNumber;
                }
            } catch (\Exception $e) {
                Log::error('Failed to send inventory alert to '.$adminNumber.': '.$e->getMessage());
                $failedTo[] = $adminNumber;
            }
        }

        return [
            'recipients' => $recipients,
            'sent_to' => array_values(array_unique($sentTo)),
            'failed_to' => array_values(array_unique($failedTo)),
        ];
    }
}
