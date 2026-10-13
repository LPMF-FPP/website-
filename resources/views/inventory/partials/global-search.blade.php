<div
    x-data="inventoryGlobalSearch({
        endpoint: @js(route('inventory.ajax.search')),
        issueBaseUrl: @js(route('inventory.transaction.issue')),
    })"
    class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 dark:bg-slate-800 dark:border-slate-700"
>
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div class="flex-1">
            <label for="inventory-global-search" class="block text-sm font-medium text-gray-800 dark:text-slate-100">Cari atau scan lot</label>
            <p class="mt-1 text-xs text-gray-700 dark:text-slate-300">Cari kode atau nama item maupun nomor lot untuk membuka konteks inventori yang diizinkan.</p>
        </div>
        <div class="text-xs text-gray-700 md:text-right dark:text-slate-300">
            Tekan <span class="font-mono">Enter</span> untuk membuka hasil yang cocok
        </div>
    </div>

    <div class="mt-3 relative" @keydown.escape.window="close()">
        <input
            id="inventory-global-search"
            type="search"
            x-model="query"
            @input="invalidateSearch()"
            @keydown.enter.prevent="submitExact()"
            @focus="open = true"
            autocomplete="off"
            :aria-busy="loading ? 'true' : 'false'"
            class="min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:border-primary-400 dark:focus:ring-primary-400"
            placeholder="Ketik nama item, kode, atau nomor lot"
        >

        <div
            x-show="open && (loading || results.length > 0 || query.length > 0)"
            x-transition.opacity
            id="inventory-global-search-results"
            role="region"
            aria-label="Hasil pencarian inventori"
            class="absolute z-20 mt-2 w-full rounded-md border border-gray-200 bg-white shadow-lg overflow-hidden dark:border-slate-700 dark:bg-slate-800"
            style="display: none;"
        >
            <div class="px-3 py-2 border-b border-gray-200 flex items-center justify-between dark:border-slate-700">
                <span role="status" aria-live="polite" class="text-sm text-gray-700 dark:text-slate-300" x-text="statusMessage"></span>
                <button type="button" class="inline-flex min-h-11 items-center px-3 text-sm font-semibold text-gray-700 hover:text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-slate-200 dark:hover:text-white dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900" @click="close()">Tutup</button>
            </div>

            <template x-if="!loading && !errorMessage && results.length === 0 && query.trim()">
                <div class="px-3 py-4 text-sm text-gray-700 dark:text-slate-300">
                    <p>Tidak ada item atau lot yang cocok.</p>
                    <button type="button" @click="clearSearch()" class="mt-2 min-h-11 font-semibold text-primary-800 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-primary-300 dark:focus-visible:ring-primary-400">Hapus pencarian</button>
                </div>
            </template>

            <div x-cloak x-show="!loading && errorMessage" role="alert" class="px-3 py-4 text-sm text-red-900 dark:bg-red-950 dark:text-red-100">
                <p x-text="errorMessage"></p>
                <button type="button" @click="search()" class="mt-2 min-h-11 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 focus-visible:ring-offset-2 dark:focus-visible:ring-red-300">Coba lagi</button>
            </div>

            <ul class="max-h-72 overflow-y-auto" x-show="!errorMessage">
                <template x-for="row in results" :key="row.type + ':' + row.id">
                    <li>
                        <button
                            type="button"
                            class="min-h-11 w-full text-left px-3 py-2 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-700 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400"
                            @click="go(row.issue_url || row.view_url)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-slate-100" x-text="row.label"></div>
                                    <div class="text-xs text-gray-700 dark:text-slate-300" x-text="row.meta || (row.type === 'lot' ? 'Lot' : 'Item')"></div>
                                </div>
                                <span
                                    class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="row.type === 'lot' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-700'"
                                    x-text="row.type.toUpperCase()"
                                ></span>
                            </div>
                            <span class="mt-1 block text-xs text-gray-700 dark:text-slate-300" x-text="row.issue_url ? 'Buka pengeluaran' : 'Lihat kartu stok'"></span>
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
                errorMessage: '',
                statusMessage: '',
                lastRequestId: 0,
                searchTimer: null,
                close() {
                    window.clearTimeout(this.searchTimer);
                    this.lastRequestId += 1;
                    this.loading = false;
                    this.statusMessage = '';
                    this.open = false;
                },
                invalidateSearch() {
                    this.lastRequestId += 1;
                    window.clearTimeout(this.searchTimer);
                    this.results = [];
                    this.exactMatch = null;
                    this.errorMessage = '';
                    this.loading = Boolean((this.query || '').trim());
                    this.statusMessage = this.loading ? 'Mencari inventori...' : '';
                    if (this.loading) this.searchTimer = window.setTimeout(() => this.search(), 200);
                },
                clearSearch() {
                    this.query = '';
                    this.invalidateSearch();
                    this.open = true;
                },
                go(url) {
                    if (!url) return;
                    window.location.href = url;
                },
                async search() {
                    window.clearTimeout(this.searchTimer);
                    this.searchTimer = null;
                    const q = (this.query || '').trim();
                    this.open = true;

                    if (!q) {
                        this.invalidateSearch();
                        return;
                    }

                    const requestId = ++this.lastRequestId;
                    this.loading = true;
                    this.errorMessage = '';
                    this.statusMessage = 'Mencari inventori...';
                    this.results = [];
                    this.exactMatch = null;

                    try {
                        const url = this.endpoint + '?q=' + encodeURIComponent(q);
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Bad response');
                        const data = await res.json();
                        if (requestId !== this.lastRequestId) return;

                        this.results = Array.isArray(data.results) ? data.results : [];
                        this.exactMatch = data.exact_match || null;
                        this.statusMessage = `${this.results.length} hasil ditemukan.`;
                    } catch (e) {
                        if (requestId !== this.lastRequestId) return;
                        this.results = [];
                        this.exactMatch = null;
                        this.errorMessage = 'Pencarian gagal dimuat. Periksa koneksi lalu coba lagi.';
                        this.statusMessage = '';
                    } finally {
                        if (requestId === this.lastRequestId) {
                            this.loading = false;
                        }
                    }
                },
                async submitExact() {
                    const q = (this.query || '').trim();
                    if (!q) return;

                    const pendingSearch = this.search();
                    const requestId = this.lastRequestId;
                    await pendingSearch;
                    if (requestId !== this.lastRequestId || q !== this.query.trim() || this.errorMessage) return;

                    if (this.exactMatch && (this.exactMatch.issue_url || this.exactMatch.view_url)) {
                        this.go(this.exactMatch.issue_url || this.exactMatch.view_url);
                        return;
                    }

                    if (this.results.length === 1 && (this.results[0].issue_url || this.results[0].view_url)) {
                        this.go(this.results[0].issue_url || this.results[0].view_url);
                    }
                },
            };
        }
    </script>
@endpush
