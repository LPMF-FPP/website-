<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Services\InventoryDashboardQueryService;
use App\Services\InventoryLocationAccessService;
use App\Services\SampleDisposalEligibilityService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InventoryLocationAccessService $locationAccess,
        private readonly InventoryDashboardQueryService $alerts,
        private readonly SampleDisposalEligibilityService $sampleEligibility,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View|Response
    {
        try {
            return $this->renderDashboard($request);
        } catch (QueryException $exception) {
            report($exception);

            return response()->view('inventory.unavailable', [
                'title' => 'Dashboard inventori belum tersedia',
                'retryUrl' => route('inventory.dashboard', $request->only('tab')),
            ], 503);
        }
    }

    private function renderDashboard(Request $request): View
    {
        $validated = $request->validate(['tab' => ['nullable', 'in:low_stock,near_expiry,expired']]);
        $user = request()->user();
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($user);
        // Low stock items
        $lowStockItemsQuery = $this->alerts->query('low_stock', $user);
        $lowStockCount = (clone $lowStockItemsQuery)->count();
        $lowStockItems = $lowStockItemsQuery->take(10)->get();

        // Calculate usage trend for low stock items
        foreach ($lowStockItems as $item) {
            // monthly_usage is already loaded via withSum
            $monthlyUsage = $item->monthly_usage ?? 0;

            if ($item->min_stock > 0 && $monthlyUsage > ($item->min_stock * 2)) {
                $item->trend = 'high';
            } elseif ($monthlyUsage > 0) {
                $item->trend = 'moderate';
            } else {
                $item->trend = 'low';
            }
        }

        // Near expiry lots (30 days)
        $nearExpiry30Query = $this->alerts->query('near_expiry', $user);
        $nearExpiry30Count = (clone $nearExpiry30Query)->count();
        $nearExpiry30 = $nearExpiry30Query
            ->with(['item', 'balances' => fn ($query) => $query->whereIn('location_id', $visibleLocationIds)->with('location')])
            ->orderBy('expiry_date')
            ->take(10)
            ->get();

        // Near expiry lots (60 days)
        $nearExpiry60 = InventoryLot::query()
            ->with(['item', 'balances' => fn ($query) => $query->whereIn('location_id', $visibleLocationIds)->with('location')])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', Carbon::today()->addDays(30))
            ->where('expiry_date', '<=', Carbon::today()->addDays(60))
            ->where('status', '!=', 'DISPOSED')
            ->whereHas('balances', fn ($q) => $q->where('on_hand_qty', '>', 0)->whereIn('location_id', $visibleLocationIds))
            ->orderBy('expiry_date')
            ->take(10)
            ->get();

        // Expired lots with stock
        $expiredLotsQuery = $this->alerts->query('expired', $user);
        $expiredCount = (clone $expiredLotsQuery)->count();
        $expiredLots = $expiredLotsQuery
            ->with(['item', 'balances' => fn ($query) => $query->whereIn('location_id', $visibleLocationIds)->with('location')])
            ->orderBy('expiry_date')
            ->take(10)
            ->get();

        // Summary stats
        $totalItems = $this->locationAccess->itemsForView(InventoryItem::query(), $user)->active()->count();
        $totalLots = InventoryLot::where('status', 'ACTIVE')->whereHas('balances', fn ($query) => $query->whereIn('location_id', $visibleLocationIds))->count();
        $canViewDisposal = $request->user()->hasPermission('sample-disposal.view');
        $eligibleSamplesCount = $canViewDisposal ? $this->sampleEligibility->query()->count() : null;

        $finishedSamplesCount = $canViewDisposal ? Sample::query()
            ->whereNotNull('testing_completed_at')
            ->whereIn('disposal_status', ['pending', 'eligible'])
            ->count() : null;

        $disposedThisMonthCount = $canViewDisposal ? Sample::query()
            ->where('disposal_status', 'disposed')
            ->whereNotNull('disposed_at')
            ->where('disposed_at', '>=', now()->startOfMonth())
            ->count() : null;

        $recentSampleDisposals = $canViewDisposal ? SampleDisposal::query()
            ->withCount('samples')
            ->latest('executed_at')
            ->take(5)
            ->get() : collect();

        $topMovers = InventoryMovement::query()
            ->where('movement_type', 'ISSUE')
            ->where('performed_at', '>=', now()->subDays(7))
            ->whereIn('from_location_id', $visibleLocationIds)
            ->selectRaw('item_id, SUM(qty) as total_qty')
            ->groupBy('item_id')
            ->orderByDesc('total_qty')
            ->with('item:id,item_code,name,uom')
            ->take(5)
            ->get();

        return view('inventory.dashboard', [
            'initialAlertTab' => $validated['tab'] ?? 'low_stock',
            'lowStockItems' => $lowStockItems,
            'nearExpiry30' => $nearExpiry30,
            'nearExpiry60' => $nearExpiry60,
            'expiredLots' => $expiredLots,
            'nearExpiry30Count' => $nearExpiry30Count,
            'lowStockCount' => $lowStockCount,
            'expiredCount' => $expiredCount,
            'stats' => [
                'total_items' => $totalItems,
                'total_lots' => $totalLots,
                'low_stock' => $lowStockCount,
                'expired' => $expiredCount,
            ],
            'eligibleSamplesCount' => $eligibleSamplesCount,
            'finishedSamplesCount' => $finishedSamplesCount,
            'disposedThisMonthCount' => $disposedThisMonthCount,
            'recentSampleDisposals' => $recentSampleDisposals,
            'topMovers' => $topMovers,
        ]);
    }

    public function alerts(Request $request): View|Response
    {
        $validated = $request->validate([
            'category' => ['nullable', 'in:low_stock,near_expiry,expired'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $category = $validated['category'] ?? 'low_stock';

        try {
            return view('inventory.alerts.index', [
                'category' => $category,
                'categories' => InventoryDashboardQueryService::CATEGORIES,
                'records' => $this->alerts->query($category, $request->user())->paginate(20)->withQueryString(),
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return response()->view('inventory.unavailable', [
                'title' => 'Daftar peringatan belum tersedia',
                'retryUrl' => route('inventory.alerts.index', ['category' => $category]),
            ], 503);
        }
    }

    public function ajaxSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $canIssue = $request->user()?->hasPermission('inventori.issue.create') ?? false;

        $urlsFor = static function (int $itemId, ?int $lotId = null) use ($canIssue): array {
            return [
                'issue_url' => $canIssue
                    ? route('inventory.transaction.issue', array_filter(['item_id' => $itemId, 'lot_id' => $lotId]))
                    : null,
                'view_url' => route('inventory.stock-card', array_filter(['item_id' => $itemId, 'lot_id' => $lotId])),
            ];
        };

        if ($q === '') {
            return response()->json([
                'query' => $q,
                'exact_match' => null,
                'results' => [],
            ]);
        }

        $qLower = strtolower($q);

        $exactLots = InventoryLot::query()
            ->with('item')
            ->tap(fn ($lots) => $this->locationAccess->lotsForView($lots, $request->user()))
            ->whereRaw('LOWER(lot_no) = ?', [$qLower])
            ->where('status', '!=', 'DISPOSED')
            ->limit(2)
            ->get();

        $exactMatch = null;
        if ($exactLots->count() === 1) {
            $lot = $exactLots->first();

            $exactMatch = [
                'type' => 'lot',
                'id' => $lot->id,
                'item_id' => $lot->item_id,
                'lot_id' => $lot->id,
                'label' => trim(($lot->item?->item_code ? $lot->item->item_code.' · ' : '').($lot->item?->name ?? 'Item').' · '.$lot->lot_no),
                'meta' => $lot->item?->uom,
                ...$urlsFor($lot->item_id, $lot->id),
            ];
        }

        if ($exactMatch === null) {
            $exactItems = $this->locationAccess->itemsForView(InventoryItem::query(), $request->user())
                ->active()
                ->where(function ($items) use ($qLower): void {
                    $items->whereRaw('LOWER(name) = ?', [$qLower])
                        ->orWhereRaw('LOWER(item_code) = ?', [$qLower]);
                })
                ->limit(2)
                ->get();

            if ($exactItems->count() === 1) {
                $item = $exactItems->first();
                $exactMatch = [
                    'type' => 'item',
                    'id' => $item->id,
                    'item_id' => $item->id,
                    'lot_id' => null,
                    'label' => trim(($item->item_code ? $item->item_code.' · ' : '').$item->name),
                    'meta' => $item->uom,
                    ...$urlsFor($item->id),
                ];
            }
        }

        $lots = InventoryLot::query()
            ->with('item')
            ->tap(fn ($lots) => $this->locationAccess->lotsForView($lots, $request->user()))
            ->whereRaw('LOWER(lot_no) LIKE ?', ['%'.$qLower.'%'])
            ->where('status', '!=', 'DISPOSED')
            ->orderBy('lot_no')
            ->limit(10)
            ->get()
            ->map(fn ($lot) => [
                'type' => 'lot',
                'id' => $lot->id,
                'item_id' => $lot->item_id,
                'lot_id' => $lot->id,
                'label' => trim(($lot->item?->item_code ? $lot->item->item_code.' · ' : '').($lot->item?->name ?? 'Item').' · '.$lot->lot_no),
                'meta' => trim(collect([$lot->item?->uom, $lot->expiry_date ? 'exp '.$lot->expiry_date->format('Y-m-d') : null])->filter()->join(' · ')),
                ...$urlsFor($lot->item_id, $lot->id),
            ])
            ->values();

        $items = $this->locationAccess->itemsForView(InventoryItem::query(), $request->user())
            ->active()
            ->where(function ($items) use ($qLower): void {
                $items->whereRaw('LOWER(name) LIKE ?', ['%'.$qLower.'%'])
                    ->orWhereRaw('LOWER(item_code) LIKE ?', ['%'.$qLower.'%']);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'item_code', 'name', 'uom'])
            ->map(fn ($item) => [
                'type' => 'item',
                'id' => $item->id,
                'item_id' => $item->id,
                'lot_id' => null,
                'label' => trim(($item->item_code ? $item->item_code.' · ' : '').$item->name),
                'meta' => $item->uom,
                ...$urlsFor($item->id),
            ])
            ->values();

        return response()->json([
            'query' => $q,
            'exact_match' => $exactMatch,
            'results' => $lots->concat($items)->values(),
        ]);
    }

    public function ajaxOverview(Request $request): JsonResponse
    {
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($request->user());
        $query = $this->locationAccess->itemsForView(InventoryItem::query(), $request->user())
            ->active()
            ->withSum(['balances as total_stock' => fn ($balances) => $balances->whereIn('location_id', $visibleLocationIds)], 'on_hand_qty')
            ->with(['balances' => fn ($balances) => $balances->whereIn('location_id', $visibleLocationIds)->with('location'), 'lots' => function ($q) use ($request) {
                $q->active()
                    ->tap(fn ($lots) => $this->locationAccess->lotsForView($lots, $request->user()))
                    ->orderBy('expiry_date');
            }]);

        if ($request->has('q') && $request->q !== '') {
            $q = strtolower($request->q);
            $query->where(function ($items) use ($q): void {
                $items->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%'])
                    ->orWhereRaw('LOWER(item_code) LIKE ?', ['%'.$q.'%']);
            });
        }

        // Sort by total stock descending by default so users see what they HAVE first
        // COALESCE is important because items with no balances return NULL for total_stock,
        // which sorts unexpectedly depending on DB driver. We want 0 at the bottom.
        $query->orderByRaw('COALESCE((SELECT SUM(on_hand_qty) FROM inventory_balances WHERE item_id = inventory_items.id AND location_id IN ('.(clone $visibleLocationIds)->toSql().')), 0) DESC', (clone $visibleLocationIds)->getBindings())
            ->orderBy('name');

        $items = $query->paginate(10)->appends($request->only('q'));

        // Append status field
        $items->getCollection()->transform(function ($item) {
            $item->status = 'ok';
            $item->category = $item->item_type_label;
            $totalStock = (float) $item->total_stock;

            if ($totalStock <= 0) {
                $item->status = 'empty';
            } elseif ($totalStock < $item->min_stock) {
                $item->status = 'critical';
            }

            // Explicitly set total_stock
            $item->total_stock = $totalStock;

            return $item;
        });

        return response()->json($items);
    }

    public function ajaxFastMoving(Request $request): JsonResponse
    {
        $query = InventoryMovement::query()
            ->where('movement_type', 'ISSUE')
            ->where('performed_at', '>=', now()->subDays(30))
            ->whereIn('from_location_id', $this->locationAccess->visibleLocationIds($request->user()))
            ->selectRaw('item_id, SUM(qty) as total_out')
            ->groupBy('item_id')
            ->orderByDesc('total_out')
            ->with('item:id,item_code,name,item_type,uom');

        $items = $query->paginate(10);
        $items->getCollection()->each(function ($movement): void {
            if ($movement->item) {
                $movement->item->category = $movement->item->item_type_label;
            }
        });

        return response()->json($items);
    }
}
