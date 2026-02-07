<div
    x-data="inventoryQuickActions({
        items: @js(($quickActionItems ?? collect())->values()),
        locations: @js(($locations ?? collect())->values()),
        defaultLocationId: @js(($locations ?? collect())->first()?->id),
    })"
    class="bg-white rounded-lg shadow-sm p-6 mb-6"
>
    <div class="border-b border-gray-200 mb-4">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button 
                @click="activeTab = 'issue'"
                :class="{ 'border-primary-500 text-primary-600': activeTab === 'issue', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'issue' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                📤 Pengeluaran Cepat
            </button>
            <button 
                @click="activeTab = 'receipt'"
                :class="{ 'border-primary-500 text-primary-600': activeTab === 'receipt', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'receipt' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                📥 Penerimaan Cepat
            </button>
             <button 
                @click="activeTab = 'transfer'"
                :class="{ 'border-primary-500 text-primary-600': activeTab === 'transfer', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'transfer' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                🔄 Transfer Cepat
            </button>
        </nav>
    </div>

    <!-- Quick Issue Form -->
    <div x-show="activeTab === 'issue'" class="space-y-4">
        <p class="text-gray-500 text-sm">Catat pengeluaran barang dengan cepat.</p>

        <form method="POST" action="{{ route('inventory.transaction.issue') }}" class="grid grid-cols-12 gap-4 items-end">
            @csrf
            <input type="hidden" name="reference_type" value="MANUAL" />

            <div class="col-span-12 md:col-span-4">
                <label class="block text-sm font-medium text-gray-700">Item</label>
                <select name="item_id" x-model.number="issue.item_id" @change="loadLots('issue')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Pilih item...</option>
                    <template x-for="it in items" :key="it.id">
                        <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                    </template>
                </select>
            </div>

            <div class="col-span-12 md:col-span-3">
                <label class="block text-sm font-medium text-gray-700">Lot (opsional)</label>
                <select name="lot_id" x-model.number="issue.lot_id" @change="loadBalance('issue')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Semua lot</option>
                    <template x-for="lot in lots.issue" :key="lot.id">
                        <option :value="lot.id" :disabled="!lot.can_issue" x-text="lot.lot_no + (lot.expiry_date ? ' · exp ' + lot.expiry_date : '')"></option>
                    </template>
                </select>
            </div>

            <div class="col-span-12 md:col-span-3">
                <label class="block text-sm font-medium text-gray-700">Lokasi</label>
                <select name="location_id" x-model.number="issue.location_id" @change="loadBalance('issue')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Pilih lokasi...</option>
                    <template x-for="loc in locations" :key="loc.id">
                        <option :value="loc.id" x-text="loc.name"></option>
                    </template>
                </select>
            </div>

            <div class="col-span-6 md:col-span-1">
                <label class="block text-sm font-medium text-gray-700">Qty</label>
                <input name="qty" x-model.number="issue.qty" type="number" step="0.001" min="0.001" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
            </div>

            <div class="col-span-6 md:col-span-1">
                <x-primary-button type="submit" class="w-full justify-center">Proses</x-primary-button>
            </div>

            <div class="col-span-12">
                <label class="block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                <input name="notes" x-model="issue.notes" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Contoh: pemakaian harian / permintaan analis" />
                <p class="mt-1 text-xs text-gray-500" x-show="balance.issue !== null">Sisa stok (estimasi): <span class="font-mono" x-text="balance.issue"></span></p>
            </div>
        </form>
    </div>
    
    <!-- Quick Receipt Form -->
    <div x-show="activeTab === 'receipt'" class="space-y-4" style="display: none;">
        <p class="text-gray-500 text-sm">Input penerimaan barang baru.</p>

        <form method="POST" action="{{ route('inventory.transaction.receipt') }}" class="grid grid-cols-12 gap-4 items-end">
            @csrf
            <input type="hidden" name="reference_type" value="MANUAL" />

            <div class="col-span-12 md:col-span-5">
                <label class="block text-sm font-medium text-gray-700">Item</label>
                <select name="item_id" x-model.number="receipt.item_id" @change="loadLots('receipt')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Pilih item...</option>
                    <template x-for="it in items" :key="it.id">
                        <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                    </template>
                </select>
            </div>

            <div class="col-span-12 md:col-span-3">
                <label class="block text-sm font-medium text-gray-700">Lokasi</label>
                <select name="location_id" x-model.number="receipt.location_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    <option value="">Pilih lokasi...</option>
                    <template x-for="loc in locations" :key="loc.id">
                        <option :value="loc.id" x-text="loc.name"></option>
                    </template>
                </select>
            </div>

            <div class="col-span-6 md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Qty</label>
                <input name="qty" x-model.number="receipt.qty" type="number" step="0.001" min="0.001" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
            </div>

            <div class="col-span-6 md:col-span-2">
                <x-primary-button type="submit" class="w-full justify-center">Terima</x-primary-button>
            </div>

            <div class="col-span-12">
                <label class="block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                <input name="notes" x-model="receipt.notes" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Contoh: penerimaan vendor / hibah" />
            </div>
        </form>
    </div>

    <!-- Quick Transfer Form -->
    <div x-show="activeTab === 'transfer'" class="space-y-4" style="display: none;">
        <p class="text-gray-500 text-sm">Pindahkan barang antar lokasi.</p>

        <form method="POST" action="{{ route('inventory.transaction.transfer') }}" class="grid grid-cols-12 gap-4 items-end">
            @csrf

            <div class="col-span-12 md:col-span-4">
                <label class="block text-sm font-medium text-gray-700">Item</label>
                <select name="item_id" x-model.number="transfer.item_id" @change="loadLots('transfer')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Pilih item...</option>
                    <template x-for="it in items" :key="it.id">
                        <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                    </template>
                </select>
            </div>

            <div class="col-span-12 md:col-span-3">
                <label class="block text-sm font-medium text-gray-700">Lot (opsional)</label>
                <select name="lot_id" x-model.number="transfer.lot_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Semua lot</option>
                    <template x-for="lot in lots.transfer" :key="lot.id">
                        <option :value="lot.id" x-text="lot.lot_no + (lot.expiry_date ? ' · exp ' + lot.expiry_date : '')"></option>
                    </template>
                </select>
            </div>

            <div class="col-span-12 md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Dari</label>
                <select name="from_location_id" x-model.number="transfer.from_location_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    <option value="">Pilih...</option>
                    <template x-for="loc in locations" :key="loc.id">
                        <option :value="loc.id" x-text="loc.name"></option>
                    </template>
                </select>
            </div>

            <div class="col-span-12 md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Ke</label>
                <select name="to_location_id" x-model.number="transfer.to_location_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    <option value="">Pilih...</option>
                    <template x-for="loc in locations" :key="loc.id">
                        <option :value="loc.id" x-text="loc.name"></option>
                    </template>
                </select>
            </div>

            <div class="col-span-6 md:col-span-1">
                <label class="block text-sm font-medium text-gray-700">Qty</label>
                <input name="qty" x-model.number="transfer.qty" type="number" step="0.001" min="0.001" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
            </div>

            <div class="col-span-6 md:col-span-1">
                <x-primary-button type="submit" class="w-full justify-center">Transfer</x-primary-button>
            </div>

            <div class="col-span-12">
                <label class="block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                <input name="notes" x-model="transfer.notes" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Contoh: pindah gudang ke lab" />
            </div>
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
