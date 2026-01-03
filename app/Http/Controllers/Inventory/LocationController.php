<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(): View
    {
        $locations = InventoryLocation::query()
            ->withCount('balances')
            ->orderBy('name')
            ->paginate(20);

        return view('inventory.locations.index', [
            'locations' => $locations,
            'locationTypes' => InventoryLocation::LOCATION_TYPES,
        ]);
    }

    public function create(): View
    {
        return view('inventory.locations.form', [
            'location' => new InventoryLocation,
            'locationTypes' => InventoryLocation::LOCATION_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_type' => ['required', 'in:warehouse,lab,cold_storage'],
            'is_restricted' => ['boolean'],
        ]);

        InventoryLocation::create($validated);

        return redirect()
            ->route('inventory.locations.index')
            ->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function edit(InventoryLocation $location): View
    {
        return view('inventory.locations.form', [
            'location' => $location,
            'locationTypes' => InventoryLocation::LOCATION_TYPES,
        ]);
    }

    public function update(Request $request, InventoryLocation $location): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_type' => ['required', 'in:warehouse,lab,cold_storage'],
            'is_restricted' => ['boolean'],
        ]);

        $location->update($validated);

        return redirect()
            ->route('inventory.locations.index')
            ->with('success', 'Lokasi berhasil diperbarui.');
    }
}
