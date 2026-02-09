<div
    x-data="inventoryQuickActions({
        items: @js(($quickActionItems ?? collect())->values()),
        locations: @js(($locations ?? collect())->values()),
        defaultLocationId: @js(($locations ?? collect())->first()?->id),
    })"
>
    <!-- Grid Buttons -->
    <div class="grid grid-cols-2 gap-3 mb-4">
        <!-- Receipt -->
        <button @click="activeTab = (activeTab === 'receipt' ? null : 'receipt')" 
                class="flex flex-col items-center justify-center p-4 rounded-lg border transition-all group relative overflow-hidden shadow-sm hover:shadow-md"
                :class="activeTab === 'receipt' ? 'bg-emerald-50 border-emerald-300 ring-2 ring-emerald-500 ring-offset-1' : 'bg-white border-gray-200 hover:bg-emerald-50 hover:border-emerald-200'"
        >
            <div class="p-2 rounded-full mb-2 transition-transform group-hover:scale-110"
                 :class="activeTab === 'receipt' ? 'bg-emerald-100 text-emerald-700' : 'bg-emerald-50 text-emerald-600'"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M12 3v13.5M12 16.5l-4.5-4.5M12 16.5l4.5-4.5" />
                </svg>
            </div>
            <span class="text-sm font-bold" :class="activeTab === 'receipt' ? 'text-emerald-800' : 'text-gray-600 group-hover:text-emerald-700'">Terima</span>
        </button>

        <!-- Issue -->
        <button @click="activeTab = (activeTab === 'issue' ? null : 'issue')"
                class="flex flex-col items-center justify-center p-4 rounded-lg border transition-all group relative overflow-hidden shadow-sm hover:shadow-md"
                :class="activeTab === 'issue' ? 'bg-amber-50 border-amber-300 ring-2 ring-amber-500 ring-offset-1' : 'bg-white border-gray-200 hover:bg-amber-50 hover:border-amber-200'"
        >
            <div class="p-2 rounded-full mb-2 transition-transform group-hover:scale-110"
                 :class="activeTab === 'issue' ? 'bg-amber-100 text-amber-700' : 'bg-amber-50 text-amber-600'"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
            </div>
            <span class="text-sm font-bold" :class="activeTab === 'issue' ? 'text-amber-800' : 'text-gray-600 group-hover:text-amber-700'">Keluar</span>
        </button>

        <!-- Transfer -->
        <button @click="activeTab = (activeTab === 'transfer' ? null : 'transfer')"
                class="flex flex-col items-center justify-center p-4 rounded-lg border transition-all group relative overflow-hidden shadow-sm hover:shadow-md"
                :class="activeTab === 'transfer' ? 'bg-blue-50 border-blue-300 ring-2 ring-blue-500 ring-offset-1' : 'bg-white border-gray-200 hover:bg-blue-50 hover:border-blue-200'"
        >
            <div class="p-2 rounded-full mb-2 transition-transform group-hover:scale-110"
                 :class="activeTab === 'transfer' ? 'bg-blue-100 text-blue-700' : 'bg-blue-50 text-blue-600'"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
            </div>
            <span class="text-sm font-bold" :class="activeTab === 'transfer' ? 'text-blue-800' : 'text-gray-600 group-hover:text-blue-700'">Transfer</span>
        </button>

        <!-- Stocktake Shortcut -->
        <a href="{{ route('inventory.transaction.stocktake') }}"
           class="flex flex-col items-center justify-center p-4 rounded-lg border-2 border-dashed border-gray-300 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all group relative overflow-hidden"
        >
            <div class="p-2 rounded-full bg-gray-100 text-gray-600 mb-2 group-hover:scale-110 transition-transform">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                </svg>
            </div>
            <span class="text-sm font-bold text-gray-600 group-hover:text-gray-800">Opname</span>
        </a>
    </div>

    <!-- Forms Container -->
    <div x-show="activeTab" 
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0 transform scale-95" 
         x-transition:enter-end="opacity-100 transform scale-100" 
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100 transform scale-100" 
         x-transition:leave-end="opacity-0 transform scale-95" 
         class="bg-gray-50 rounded-lg p-4 border border-gray-200 shadow-lg relative mb-4"
         style="display: none;"
    >
        <button @click="activeTab = null" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <!-- Quick Issue Form -->
        <div x-show="activeTab === 'issue'" class="space-y-4">
            <h4 class="text-base font-bold text-amber-800 mb-3 flex items-center gap-2 pb-2 border-b border-amber-200">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                Pengeluaran Stok
            </h4>
            <form method="POST" action="{{ route('inventory.transaction.issue') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="reference_type" value="MANUAL" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
                    <select name="item_id" x-model.number="issue.item_id" @change="loadLots('issue')" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-amber-500 focus:ring-amber-500" required>
                        <option value="">Pilih item...</option>
                        <template x-for="it in items" :key="it.id">
                            <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                        </template>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lot (opsional)</label>
                        <select name="lot_id" x-model.number="issue.lot_id" @change="loadBalance('issue')" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Semua lot</option>
                            <template x-for="lot in lots.issue" :key="lot.id">
                                <option :value="lot.id" :disabled="!lot.can_issue" x-text="lot.lot_no"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                        <select name="location_id" x-model.number="issue.location_id" @change="loadBalance('issue')" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-amber-500 focus:ring-amber-500" required>
                            <option value="">Pilih...</option>
                            <template x-for="loc in locations" :key="loc.id">
                                <option :value="loc.id" x-text="loc.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                    <div class="relative rounded-md shadow-sm">
                        <input name="qty" x-model.number="issue.qty" type="number" step="0.001" min="0.001" required class="block w-full rounded-md border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="0.00" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                             <span class="text-gray-500 sm:text-xs" x-text="items.find(i => i.id == issue.item_id)?.uom || ''"></span>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500" x-show="balance.issue !== null">
                        Stok Tersedia: <span class="font-mono font-bold text-gray-800" x-text="balance.issue"></span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <input name="notes" x-model="issue.notes" type="text" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Opsional..." />
                </div>

                <div class="pt-2">
                    <x-primary-button type="submit" class="w-full justify-center bg-amber-600 hover:bg-amber-700 focus:ring-amber-500 py-2.5">Simpan Pengeluaran</x-primary-button>
                </div>
            </form>
        </div>
        
        <!-- Quick Receipt Form -->
        <div x-show="activeTab === 'receipt'" class="space-y-4">
             <h4 class="text-base font-bold text-emerald-800 mb-3 flex items-center gap-2 pb-2 border-b border-emerald-200">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M12 3v13.5M12 16.5l-4.5-4.5M12 16.5l4.5-4.5" /></svg>
                Penerimaan Stok
            </h4>
            <form method="POST" action="{{ route('inventory.transaction.receipt') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="reference_type" value="MANUAL" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
                    <select name="item_id" x-model.number="receipt.item_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                        <option value="">Pilih item...</option>
                        <template x-for="it in items" :key="it.id">
                            <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                        </template>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                        <select name="location_id" x-model.number="receipt.location_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                            <option value="">Pilih...</option>
                            <template x-for="loc in locations" :key="loc.id">
                                <option :value="loc.id" x-text="loc.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                        <input name="qty" x-model.number="receipt.qty" type="number" step="0.001" min="0.001" required class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="0.00" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <input name="notes" x-model="receipt.notes" type="text" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Opsional..." />
                </div>

                <div class="pt-2">
                    <x-primary-button type="submit" class="w-full justify-center bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 py-2.5">Simpan Penerimaan</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Quick Transfer Form -->
        <div x-show="activeTab === 'transfer'" class="space-y-4">
             <h4 class="text-base font-bold text-blue-800 mb-3 flex items-center gap-2 pb-2 border-b border-blue-200">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                Transfer Antar Lokasi
            </h4>
            <form method="POST" action="{{ route('inventory.transaction.transfer') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
                    <select name="item_id" x-model.number="transfer.item_id" @change="loadLots('transfer')" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Pilih item...</option>
                        <template x-for="it in items" :key="it.id">
                            <option :value="it.id" x-text="it.name + ' (' + it.uom + ')' "></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lot (opsional)</label>
                    <select name="lot_id" x-model.number="transfer.lot_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua lot</option>
                        <template x-for="lot in lots.transfer" :key="lot.id">
                            <option :value="lot.id" x-text="lot.lot_no"></option>
                        </template>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dari</label>
                        <select name="from_location_id" x-model.number="transfer.from_location_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Pilih...</option>
                            <template x-for="loc in locations" :key="loc.id">
                                <option :value="loc.id" x-text="loc.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ke</label>
                        <select name="to_location_id" x-model.number="transfer.to_location_id" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Pilih...</option>
                            <template x-for="loc in locations" :key="loc.id">
                                <option :value="loc.id" x-text="loc.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                    <input name="qty" x-model.number="transfer.qty" type="number" step="0.001" min="0.001" required class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="0.00" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <input name="notes" x-model="transfer.notes" type="text" class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Opsional..." />
                </div>

                <div class="pt-2">
                    <x-primary-button type="submit" class="w-full justify-center bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 py-2.5">Transfer</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function inventoryQuickActions(config) {
            const defaultLocationId = config.defaultLocationId || '';

            return {
                activeTab: null,
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
