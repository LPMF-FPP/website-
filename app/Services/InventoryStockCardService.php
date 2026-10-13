<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use OverflowException;

class InventoryStockCardService
{
    public function __construct(private readonly InventoryLocationAccessService $access) {}

    public function report(array $filters, User $viewer): array
    {
        if (! $viewer->hasPermission('inventori.view')) {
            throw new AuthorizationException('Anda tidak memiliki izin melihat kartu stok.');
        }
        Validator::make($filters, [
            'item_id' => ['nullable', 'integer', 'min:1'], 'lot_id' => ['nullable', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'min:1'], 'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => array_merge(['nullable', 'date_format:Y-m-d'], ! empty($filters['date_from']) ? ['after_or_equal:date_from'] : []),
            'search' => ['nullable', 'string', 'max:200'],
        ])->validate();
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        unset($filters['page']);
        $lot = null;
        if (! empty($filters['lot_id'])) {
            $lot = $this->access->lotsForView(InventoryLot::query(), $viewer)->find($filters['lot_id']);
            if (! $lot || (! empty($filters['item_id']) && (int) $filters['item_id'] !== $lot->item_id)) {
                throw new AuthorizationException('Lot tidak tersedia untuk item dalam cakupan akses Anda.');
            }
            $filters['item_id'] = $lot->item_id;
        }
        $item = null;
        if (! empty($filters['item_id'])) {
            $item = $this->access->itemsForView(InventoryItem::query(), $viewer)->find($filters['item_id']);
            if (! $item) {
                throw new AuthorizationException('Item tidak tersedia dalam cakupan akses Anda.');
            }
        }
        $location = null;
        if (! empty($filters['location_id'])) {
            $location = $this->access->locationsForView(InventoryLocation::query(), $viewer)->find($filters['location_id']);
            if (! $location) {
                throw new AuthorizationException('Akses riwayat untuk lokasi ini tidak diberikan.');
            }
        }
        $visibleIds = $this->access->visibleLocationIds($viewer)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $query = InventoryMovement::query()->with([
            'item', 'lot',
            'fromLocation' => fn ($locations) => $this->access->locationsForView($locations, $viewer),
            'toLocation' => fn ($locations) => $this->access->locationsForView($locations, $viewer),
            'performedByUser',
        ])->whereIn('item_id', $this->access->itemsForView(InventoryItem::query(), $viewer)->select('id'));
        if ($location) {
            $query->atLocation($location->id);
        } else {
            // Aggregate the same authorized endpoint history available in individual location reports.
            $query->where(fn ($q) => $q->whereIn('from_location_id', $visibleIds)->orWhereIn('to_location_id', $visibleIds));
        }
        $query->when($item, fn ($q) => $q->where('item_id', $item->id))
            ->when($lot, fn ($q) => $q->where('lot_id', $lot->id));
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $from = ! empty($filters['date_from']) ? CarbonImmutable::parse($filters['date_from'], $timezone)->startOfDay() : null;
        $until = ! empty($filters['date_to']) ? CarbonImmutable::parse($filters['date_to'], $timezone)->addDay()->startOfDay() : null;
        $query->when($until, fn ($q) => $q->where('performed_at', '<', $until));
        $opening = 0;
        $running = 0;
        $incoming = 0;
        $outgoing = 0;
        $rows = [];
        foreach ($query->orderBy('performed_at')->orderBy('id')->get() as $movement) {
            $quantity = self::scaled((string) $movement->qty);
            $change = $location
                ? (($movement->to_location_id === $location->id ? $quantity : 0) - ($movement->from_location_id === $location->id ? $quantity : 0))
                : ((in_array($movement->to_location_id, $visibleIds, true) ? $quantity : 0) - (in_array($movement->from_location_id, $visibleIds, true) ? $quantity : 0));
            $running = self::add($running, $change);
            if ($from && $movement->performed_at->lt($from)) {
                $opening = $running;

                continue;
            }
            if ($change > 0) {
                $incoming = self::add($incoming, $change);
            } elseif ($change < 0) {
                $outgoing = self::add($outgoing, -$change);
            }
            $rows[] = ['movement' => $movement, 'change' => self::decimal($change), 'running_balance' => self::decimal($running)];
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            // Search narrows displayed rows, not the ledger period being reconciled.
            $rows = array_values(array_filter($rows, function ($row) use ($search): bool {
                $movement = $row['movement'];
                $text = implode(' ', [$movement->id, $movement->movement_type_label, $movement->lot?->lot_no, $movement->fromLocation?->name, $movement->toLocation?->name, $movement->notes, $movement->reference_type]);

                return mb_stripos($text, $search) !== false;
            }));
        }

        return [
            'rows' => $rows, 'filters' => $filters, 'item' => $item, 'lot' => $lot, 'location' => $location,
            'opening_balance' => self::decimal($opening), 'closing_balance' => self::decimal($running),
            'total_in' => self::decimal($incoming), 'total_out' => self::decimal($outgoing),
            'timezone' => $timezone, 'historical' => $until !== null && $until->lte(CarbonImmutable::now($timezone)),
        ];
    }

    private static function scaled(string $quantity): int
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private static function add(int $value, int $change): int
    {
        if (($change > 0 && $value > PHP_INT_MAX - $change) || ($change < 0 && $value < PHP_INT_MIN - $change)) {
            throw new OverflowException('Saldo laporan melampaui kapasitas perhitungan. Persempit cakupan laporan.');
        }

        return $value + $change;
    }

    private static function decimal(int $value): string
    {
        $absolute = abs($value);

        return ($value < 0 ? '-' : '').intdiv($absolute, 1000).'.'.str_pad((string) ($absolute % 1000), 3, '0', STR_PAD_LEFT);
    }

    public static function formatQuantity(string $quantity): string
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '000');
        $isNegative = str_starts_with($whole, '-');
        $whole = ltrim($whole, '-');

        return ($isNegative ? '-' : '').preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.str_pad($fraction, 3, '0');
    }
}
