<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Services\InventoryLocationAccessService;
use App\Services\InventoryLocationConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(
        private readonly InventoryLocationAccessService $locationAccess,
        private readonly InventoryLocationConfigurationService $configurationService,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(): View
    {
        $user = request()->user();
        $visibleLocationIdsQuery = $this->locationAccess->visibleLocationIds($user);
        $query = InventoryLocation::query();
        if (! $user->hasPermission('inventori.location-access.manage')) {
            $this->locationAccess->locationsForView($query, $user);
        }

        $locations = $query
            ->withCount(['balances' => fn ($query) => $query->whereIn('location_id', clone $visibleLocationIdsQuery)])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);
        $visibleLocationIds = $this->locationAccess->locationsForView(
            InventoryLocation::query()->whereIn('id', $locations->getCollection()->pluck('id')),
            $user,
        )->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return view('inventory.locations.index', [
            'locations' => $locations,
            'locationTypes' => InventoryLocation::LOCATION_TYPES,
            'visibleLocationIds' => $visibleLocationIds,
        ]);
    }

    public function create(): View
    {
        return view('inventory.locations.form', [
            'location' => new InventoryLocation,
            'locationTypes' => InventoryLocation::LOCATION_TYPES,
            'canRestrictLocations' => request()->user()->hasPermission('inventori.location-access.manage'),
            'configurationEvents' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_type' => ['required', 'string', Rule::in(array_keys(InventoryLocation::LOCATION_TYPES))],
            'is_restricted' => ['boolean'],
        ], [
            'is_restricted.boolean' => 'Pilih status akses lokasi yang valid.',
        ]);

        $this->configurationService->create($request->user(), $validated);

        return redirect()
            ->route($request->user()->hasPermission('inventori.view') ? 'inventory.locations.index' : 'inventory.locations.create')
            ->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function edit(InventoryLocation $location): View
    {
        abort_unless($this->locationAccess->canViewLocation(request()->user(), $location), 403);

        return view('inventory.locations.form', [
            'location' => $location,
            'locationTypes' => InventoryLocation::LOCATION_TYPES,
            'canRestrictLocations' => request()->user()->hasPermission('inventori.location-access.manage'),
            'configurationEvents' => $location->configurationEvents()
                ->with('actor:id,name')
                ->latest('occurred_at')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function update(Request $request, InventoryLocation $location): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_type' => ['required', 'string', Rule::in(array_keys(InventoryLocation::LOCATION_TYPES))],
        ]);

        $this->configurationService->update($request->user(), $location->id, $validated);

        return redirect()
            ->route('inventory.locations.index')
            ->with('success', 'Lokasi berhasil diperbarui.');
    }

    public function access(InventoryLocation $location): View
    {
        return view('inventory.locations.access', $this->locationAccess->managementData(request()->user(), $location->id));
    }

    public function updateAccess(Request $request, InventoryLocation $location): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'can_view' => ['nullable', 'boolean'],
            'can_transact' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'user_id.exists' => 'Pengguna tidak ditemukan. Segarkan daftar akses lokasi.',
        ]);

        $this->locationAccess->changeGrant(
            $request->user(),
            (int) $validated['user_id'],
            $location->id,
            (bool) ($validated['can_view'] ?? false),
            (bool) ($validated['can_transact'] ?? false),
            $validated['reason'] ?? null,
            ['surface' => 'master-location'],
        );

        return redirect()->route('inventory.locations.access', $location)->with('success', 'Akses lokasi berhasil diperbarui.');
    }
}
