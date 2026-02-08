<div
    x-data="inventoryQuickActions({
        items: @js(($quickActionItems ?? collect())->values()),
        locations: @js(($locations ?? collect())->values()),
        defaultLocationId: @js(($locations ?? collect())->first()?->id),
    })"
>
    <div class="border-b border-gray-200 mb-4">
        <nav class="-mb-px flex space-x-4 overflow-x-auto text-sm" aria-label="Tabs">
            <button 
                @click="activeTab = 'issue'"
                :class="{ 'border-primary-500 text-primary-600 font-semibold': activeTab === 'issue', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'issue' }"
                class="whitespace-nowrap pb-2 px-1 border-b-2 transition-colors"
            >
                Keluar
            </button>
            <button 
                @click="activeTab = 'receipt'"
                :class="{ 'border-primary-500 text-primary-600 font-semibold': activeTab === 'receipt', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'receipt' }"
                class="whitespace-nowrap pb-2 px-1 border-b-2 transition-colors"
            >
                Masuk
            </button>
             <button 
                @click="activeTab = 'transfer'"
                :class="{ 'border-primary-500 text-primary-600 font-semibold': activeTab === 'transfer', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'transfer' }"
                class="whitespace-nowrap pb-2 px-1 border-b-2 transition-colors"
            >
                Transfer
            </button>
        </nav>
    </div>

    <!-- Quick Issue Form -->
    <div x-show="activeTab === 'issue'" class="space-y-3">
        <form method="POST" action="{{ route('inventory.transaction.issue') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="reference_type" value="MANUAL" />

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Item</label>
                <select name="item_id" x-model.number="issue.item_id" @change="loadLots('issue')" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" required>
                    <option value="">Pilih item...</option>
                    <template x-for="it in items" :key="it.id">
                        <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                    </template>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Lot (opsional)</label>
                    <select name="lot_id" x-model.number="issue.lot_id" @change="loadBalance('issue')" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua lot</option>
                        <template x-for="lot in lots.issue" :key="lot.id">
                            <option :value="lot.id" :disabled="!lot.can_issue" x-text="lot.lot_no"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Lokasi</label>
                    <select name="location_id" x-model.number="issue.location_id" @change="loadBalance('issue')" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" required>
                        <option value="">Pilih...</option>
                        <template x-for="loc in locations" :key="loc.id">
                            <option :value="loc.id" x-text="loc.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Jumlah</label>
                <div class="relative rounded-md shadow-sm">
                    <input name="qty" x-model.number="issue.qty" type="number" step="0.001" min="0.001" required class="block w-full rounded-md border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="0.00" />
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                         <span class="text-gray-500 sm:text-xs" x-text="items.find(i => i.id == issue.item_id)?.uom || ''"></span>
                    </div>
                </div>
                <p class="mt-1 text-[10px] text-gray-500" x-show="balance.issue !== null">
                    Stok: <span class="font-mono font-medium text-gray-700" x-text="balance.issue"></span>
                </p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Catatan</label>
                <input name="notes" x-model="issue.notes" type="text" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Opsional..." />
            </div>

            <x-primary-button type="submit" class="w-full justify-center">Simpan Pengeluaran</x-primary-button>
        </form>
    </div>
    
    <!-- Quick Receipt Form -->
    <div x-show="activeTab === 'receipt'" class="space-y-3" style="display: none;">
        <form method="POST" action="{{ route('inventory.transaction.receipt') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="reference_type" value="MANUAL" />

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Item</label>
                <select name="item_id" x-model.number="receipt.item_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" required>
                    <option value="">Pilih item...</option>
                    <template x-for="it in items" :key="it.id">
                        <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                    </template>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Lokasi</label>
                    <select name="location_id" x-model.number="receipt.location_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" required>
                        <option value="">Pilih...</option>
                        <template x-for="loc in locations" :key="loc.id">
                            <option :value="loc.id" x-text="loc.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Jumlah</label>
                    <input name="qty" x-model.number="receipt.qty" type="number" step="0.001" min="0.001" required class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="0.00" />
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Catatan</label>
                <input name="notes" x-model="receipt.notes" type="text" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Opsional..." />
            </div>

            <x-primary-button type="submit" class="w-full justify-center">Simpan Penerimaan</x-primary-button>
        </form>
    </div>

    <!-- Quick Transfer Form -->
    <div x-show="activeTab === 'transfer'" class="space-y-3" style="display: none;">
        <form method="POST" action="{{ route('inventory.transaction.transfer') }}" class="space-y-3">
            @csrf

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Item</label>
                <select name="item_id" x-model.number="transfer.item_id" @change="loadLots('transfer')" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" required>
                    <option value="">Pilih item...</option>
                    <template x-for="it in items" :key="it.id">
                        <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Lot (opsional)</label>
                <select name="lot_id" x-model.number="transfer.lot_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="">Semua lot</option>
                    <template x-for="lot in lots.transfer" :key="lot.id">
                        <option :value="lot.id" x-text="lot.lot_no"></option>
                    </template>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Dari</label>
                    <select name="from_location_id" x-model.number="transfer.from_location_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" required>
                        <option value="">Pilih...</option>
                        <template x-for="loc in locations" :key="loc.id">
                            <option :value="loc.id" x-text="loc.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Ke</label>
                    <select name="to_location_id" x-model.number="transfer.to_location_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" required>
                        <option value="">Pilih...</option>
                        <template x-for="loc in locations" :key="loc.id">
                            <option :value="loc.id" x-text="loc.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Jumlah</label>
                <input name="qty" x-model.number="transfer.qty" type="number" step="0.001" min="0.001" required class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="0.00" />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Catatan</label>
                <input name="notes" x-model="transfer.notes" type="text" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Opsional..." />
            </div>

            <x-primary-button type="submit" class="w-full justify-center">Transfer</x-primary-button>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        function inventoryQuickActions(config) {
            const defaultLocationId = config.defaultLocationId || '';

            return {
                activeTab: 'issue',
                items: Array.isArray(config.items) ? config.items : [],
                locations: Array.isArray(config.locations) ? config.locations : [],
                lots: {
                    issue: [],
                    receipt: [],
                    transfer: [],
                },
                balance: {
                    issue: null,
                },
                issue: {
                    item_id: '',
                    lot_id: '',
                    location_id: defaultLocationId,
                    qty: '',
                    notes: '',
                },
                receipt: {
                    item_id: '',
                    location_id: defaultLocationId,
                    qty: '',
                    notes: '',
                },
                transfer: {
                    item_id: '',
                    lot_id: '',
                    from_location_id: defaultLocationId,
                    to_location_id: '',
                    qty: '',
                    notes: '',
                },
                async loadLots(mode) {
                    const itemId = this[mode]?.item_id;
                    if (!itemId) {
                        this.lots[mode] = [];
                        if (mode === 'issue') {
                            this.balance.issue = null;
                        }
                        return;
                    }

                    const url = `{{ route('inventory.ajax.lots') }}?item_id=${encodeURIComponent(itemId)}`;
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) {
                        this.lots[mode] = [];
                        return;
                    }

                    const data = await res.json();
                    this.lots[mode] = Array.isArray(data) ? data : [];

                    if (mode === 'issue') {
                        await this.loadBalance('issue');
                    }
                },
                async loadBalance(mode) {
                    if (mode !== 'issue') {
                        return;
                    }

                    const itemId = this.issue.item_id;
                    const lotId = this.issue.lot_id || '';
                    const locationId = this.issue.location_id;
                    if (!itemId || !locationId) {
                        this.balance.issue = null;
                        return;
                    }

                    const url = `{{ route('inventory.ajax.balance') }}?item_id=${encodeURIComponent(itemId)}&lot_id=${encodeURIComponent(lotId)}&location_id=${encodeURIComponent(locationId)}`;
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) {
                        this.balance.issue = null;
                        return;
                    }

                    const data = await res.json();
                    this.balance.issue = typeof data?.on_hand_qty !== 'undefined' ? data.on_hand_qty : null;
                },
            };
        }
    </script>
@endpush