<div
    x-data="inventoryGlobalSearch({
        endpoint: @js(route('inventory.ajax.search')),
        issueBaseUrl: @js(route('inventory.transaction.issue')),
    })"
    class="bg-white rounded-lg shadow-sm p-5"
>
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div class="flex-1">
            <label for="inventory-global-search" class="block text-sm font-medium text-gray-700">Cari / Scan Lot</label>
            <p class="mt-1 text-xs text-gray-500">Scan barcode/QR lot (biasanya mengirim Enter) untuk langsung buka form Pengeluaran.</p>
        </div>
        <div class="text-xs text-gray-500 md:text-right">
            Shortcut: <span class="font-mono">Enter</span> untuk exact match
        </div>
    </div>

    <div class="mt-3 relative" @keydown.escape.window="close()">
        <input
            id="inventory-global-search"
            type="text"
            x-model="query"
            @input.debounce.200ms="search()"
            @keydown.enter.prevent="submitExact()"
            @focus="open = true"
            autocomplete="off"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
            placeholder="Ketik nama item atau lot no..."
            role="combobox"
            :aria-expanded="open ? 'true' : 'false'"
        >

        <div
            x-show="open && (loading || results.length > 0 || query.length > 0)"
            x-transition.opacity
            class="absolute z-20 mt-2 w-full rounded-md border border-gray-200 bg-white shadow-lg overflow-hidden"
            style="display: none;"
        >
            <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                <span class="text-xs text-gray-500" x-text="loading ? 'Mencari...' : (results.length + ' hasil')"></span>
                <button type="button" class="text-xs text-gray-500 hover:text-gray-700" @click="close()">Tutup</button>
            </div>

            <template x-if="!loading && results.length === 0">
                <div class="px-3 py-4 text-sm text-gray-500">Tidak ada hasil.</div>
            </template>

            <ul class="max-h-72 overflow-y-auto">
                <template x-for="row in results" :key="row.type + ':' + row.id">
                    <li>
                        <button
                            type="button"
                            class="w-full text-left px-3 py-2 hover:bg-gray-50"
                            @click="go(row.issue_url)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium text-gray-900" x-text="row.label"></div>
                                    <div class="text-xs text-gray-500" x-text="row.meta || (row.type === 'lot' ? 'Lot' : 'Item')"></div>
                                </div>
                                <span
                                    class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="row.type === 'lot' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-700'"
                                    x-text="row.type.toUpperCase()"
                                ></span>
                            </div>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function inventoryGlobalSearch(config) {
            return {
                endpoint: config.endpoint,
                issueBaseUrl: config.issueBaseUrl,
                query: '',
                loading: false,
                open: false,
                results: [],
                exactMatch: null,
                lastRequestId: 0,
                close() {
                    this.open = false;
                },
                go(url) {
                    if (!url) return;
                    window.location.href = url;
                },
                async search() {
                    const q = (this.query || '').trim();
                    this.open = true;

                    if (!q) {
                        this.results = [];
                        this.loading = false;
                        return;
                    }

                    const requestId = ++this.lastRequestId;
                    this.loading = true;

                    try {
                        const url = this.endpoint + '?q=' + encodeURIComponent(q);
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Bad response');
                        const data = await res.json();
                        if (requestId !== this.lastRequestId) return;

                        this.results = Array.isArray(data.results) ? data.results : [];
                        this.exactMatch = data.exact_match || null;
                    } catch (e) {
                        if (requestId !== this.lastRequestId) return;
                        this.results = [];
                    } finally {
                        if (requestId === this.lastRequestId) {
                            this.loading = false;
                        }
                    }
                },
                async submitExact() {
                    const q = (this.query || '').trim();
                    if (!q) return;

                    await this.search();

                    if (this.exactMatch && this.exactMatch.issue_url) {
                        this.go(this.exactMatch.issue_url);
                        return;
                    }

                    if (this.results.length === 1 && this.results[0].issue_url) {
                        this.go(this.results[0].issue_url);
                    }
                },
            };
        }
    </script>
@endpush
