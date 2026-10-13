<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Services\InventoryLocationAccessService;
use App\Services\InventoryLotIdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LotController extends Controller
{
    public function __construct(
        private readonly InventoryLocationAccessService $locationAccess,
        private readonly InventoryLotIdentityService $lotIdentity,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(InventoryItem $item): View
    {
        abort_unless($this->locationAccess->itemsForView(InventoryItem::query(), request()->user())->whereKey($item->id)->exists(), 404);

        $lots = InventoryLot::query()
            ->where('item_id', $item->id)
            ->tap(fn ($lots) => $this->locationAccess->lotsForView($lots, request()->user()))
            ->with(['balances' => fn ($balances) => $balances->whereIn('location_id', $this->locationAccess->visibleLocationIds(request()->user()))->with('location')])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('inventory.items.lots', [
            'item' => $item,
            'lots' => $lots,
            'statuses' => InventoryLot::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_no' => ['required', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'received_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        // Check if item requires expiry
        $item = InventoryItem::findOrFail($validated['item_id']);
        if (! $item->is_active) {
            return back()->withInput()->withErrors(['item_id' => 'Item sudah diarsipkan; lot baru tidak dapat ditambahkan.']);
        }
        if ($item->requiresExpiry() && empty($validated['expiry_date'])) {
            return back()
                ->withInput()
                ->withErrors(['expiry_date' => 'Tanggal kadaluarsa wajib diisi untuk jenis item ini.']);
        }

        $validated['status'] = 'ACTIVE';

        try {
            DB::transaction(function () use ($validated): void {
                $item = InventoryItem::query()->whereKey($validated['item_id'])->lockForUpdate()->firstOrFail();
                if (! $item->is_active) {
                    throw ValidationException::withMessages([
                        'item_id' => 'Item sudah diarsipkan; lot baru tidak dapat ditambahkan.',
                    ]);
                }

                if (InventoryLotIdentityService::canonicalize($validated['lot_no']) === '') {
                    throw ValidationException::withMessages([
                        'lot_no' => 'Nomor lot harus berisi karakter selain spasi.',
                    ]);
                }

                $collision = $this->lotIdentity->findForItem($item->id, $validated['lot_no']);
                if ($collision !== null) {
                    throw ValidationException::withMessages([
                        'lot_no' => $this->lotIdentity->duplicateLotGuidance($item->id, $validated['lot_no']),
                    ]);
                }

                if ($item->requiresExpiry() && empty($validated['expiry_date'])) {
                    throw ValidationException::withMessages([
                        'expiry_date' => 'Tanggal kadaluarsa wajib diisi untuk jenis item ini.',
                    ]);
                }

                InventoryLot::create($validated);
            });
        } catch (QueryException $exception) {
            if ($this->lotIdentity->isIdentityConflict($exception)) {
                throw ValidationException::withMessages([
                    'lot_no' => $this->lotIdentity->duplicateLotGuidance($validated['item_id'], $validated['lot_no']),
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('inventory.items.lots', $item)
            ->with('success', 'Lot berhasil ditambahkan.');
    }
}
