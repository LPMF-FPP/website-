<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryLotTrackingPolicyEvent;
use App\Services\InventoryLocationAccessService;
use App\Services\InventoryLotTrackingPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryLotTrackingPolicyController extends Controller
{
    public function __construct(
        private readonly InventoryLocationAccessService $locationAccess,
        private readonly InventoryLotTrackingPolicyService $policyService,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(): View
    {
        $policies = InventoryLotTrackingPolicy::query()->with('decider:id,name')->orderBy('item_type')->get()->keyBy('item_type');
        $activeCounts = $this->locationAccess
            ->itemsForView(InventoryItem::query()->active(), request()->user())
            ->selectRaw('item_type, COUNT(*) AS item_count')
            ->groupBy('item_type')
            ->pluck('item_count', 'item_type');
        $events = InventoryLotTrackingPolicyEvent::query()
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.lot-policies.index', [
            'policies' => $policies,
            'activeCounts' => $activeCounts,
            'itemTypes' => InventoryItem::ITEM_TYPES,
            'events' => $events,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'policies' => ['required', 'array'],
            'policies.*' => ['nullable', 'in:lot_tracked,untracked'],
            'evidence_reference' => ['required', 'string', 'max:255'],
        ], [
            'evidence_reference.required' => 'Cantumkan referensi persetujuan pemilik inventori.',
        ]);
        abort_unless(array_diff(array_keys($validated['policies']), array_keys(InventoryItem::ITEM_TYPES)) === [], 422);

        $updatedCount = $this->policyService->updateMatrix(
            $request->user(),
            $validated['policies'],
            $validated['evidence_reference'],
        );

        return redirect()
            ->route('inventory.baseline.policies')
            ->with('success', $updatedCount > 0
                ? "Kebijakan pelacakan lot diperbarui ({$updatedCount} tipe item)."
                : 'Kebijakan tidak berubah; tidak ada event audit baru.');
    }
}
