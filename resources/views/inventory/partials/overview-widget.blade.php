<div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-slate-800 dark:border-slate-700" x-data="inventoryOverview()" :aria-busy="loading ? 'true' : 'false'">
    <div class="px-4 py-3 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 dark:border-slate-700">
        <h3 class="font-semibold text-gray-900 flex items-center gap-2 dark:text-slate-100">
            Ringkasan Inventori
        </h3>
        <div class="relative max-w-xs w-full">
            <input type="text" 
                   x-model="search"
                   aria-label="Cari item dalam ringkasan inventori"
                   placeholder="Cari item..." 
                   class="block min-h-11 w-full rounded-md border-gray-300 bg-white pl-8 text-sm text-gray-900 shadow-sm placeholder:text-gray-500 focus:border-primary-700 focus:ring-primary-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:border-primary-400 dark:focus:ring-primary-400" />
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    <p x-cloak x-show="loading" role="status" aria-live="polite" class="sr-only">Memuat ringkasan stok.</p>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 dark:bg-slate-900">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider w-8 dark:text-slate-300"><span class="sr-only">Rincian</span></th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider dark:text-slate-300">Item / Kategori</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider dark:text-slate-300">Total Stok</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider dark:text-slate-300">Status</th>
                </tr>
            </thead>

            <!-- Loading State -->
            <tbody class="bg-white divide-y divide-gray-100 dark:bg-slate-800 dark:divide-slate-700" x-show="loading">
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-600 text-sm dark:text-slate-300">
                        <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memuat data stok...
                    </td>
                </tr>
            </tbody>

            <!-- Empty State -->
            <tbody class="bg-white divide-y divide-gray-100 dark:bg-slate-800 dark:divide-slate-700" x-show="!loading && !errorMessage && items.length === 0">
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-600 text-sm dark:text-slate-300">
                        <span x-show="search.trim()" x-text="'Tidak ada item yang sesuai dengan pencarian.'"></span>
                        <span x-show="!search.trim()">Belum ada item inventori untuk ditampilkan.</span>
                        <button x-show="search.trim()" type="button" @click="search = ''" class="ml-2 min-h-11 rounded-md px-3 font-semibold text-primary-800 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-primary-300 dark:focus-visible:ring-primary-400">Hapus pencarian</button>
                        @if(auth()->user()?->hasPermission('inventori.create'))
                            <a x-show="!search.trim()" href="{{ route('inventory.items.create') }}" class="ml-2 inline-flex min-h-11 items-center px-3 font-semibold text-primary-800 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-primary-300 dark:focus-visible:ring-primary-400">Tambah item</a>
                        @endif
                    </td>
                </tr>
            </tbody>

            <tbody class="bg-white dark:bg-slate-800" x-show="!loading && errorMessage">
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-red-800 dark:bg-red-950 dark:text-red-100" role="alert">
                        <span x-text="errorMessage"></span>
                        <button type="button" @click="fetchData()" class="ml-2 min-h-11 rounded-md px-3 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400">Coba lagi</button>
                    </td>
                </tr>
            </tbody>

            <!-- Data Loop -->
            <template x-for="item in items" :key="item.id">
                <tbody x-show="!loading && !errorMessage" class="group border-b border-gray-200 bg-white hover:bg-gray-50 transition-colors dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700">
                    <!-- Main Row -->
                    <tr>
                        <td class="px-2 py-2 text-gray-500 dark:text-slate-300">
                            <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900" @click="toggleRow(item.id)" :aria-expanded="expandedRows.includes(item.id) ? 'true' : 'false'" :aria-controls="`inventory-item-detail-${item.id}`" :aria-label="`${expandedRows.includes(item.id) ? 'Tutup' : 'Lihat'} rincian ${item.name}`">
                            <svg aria-hidden="true" class="w-4 h-4 transform transition-transform duration-200"
                                    :class="{'rotate-90': expandedRows.includes(item.id)}" 
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-bold text-gray-900 dark:text-slate-100" x-text="(item.item_code || 'Kode belum ditetapkan') + ' · ' + item.name"></div>
                            <div class="text-xs text-gray-600 mt-0.5 dark:text-slate-300" x-text="item.category || '-'"></div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-sm font-mono font-bold tabular-nums text-gray-900 dark:text-slate-100" x-text="formatNumber(item.total_stock || 0)"></span>
                                <span class="text-[10px] text-gray-600 uppercase dark:text-slate-300" x-text="item.uom"></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span role="status" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                                    :class="{
                                        'inventory-status-success': item.status === 'ok',
                                        'inventory-status-danger': item.status === 'critical',
                                        'inventory-status-neutral': item.status === 'empty'
                                    }"
                                    x-text="getStatusLabel(item.status)">
                            </span>
                        </td>
                    </tr>
                    
                    <!-- Detail Row (Expanded) -->
                    <tr :id="`inventory-item-detail-${item.id}`" x-show="expandedRows.includes(item.id)"
                        x-transition:enter="transition ease-out duration-100" 
                        x-transition:enter-start="opacity-0" 
                        x-transition:enter-end="opacity-100" 
                        class="bg-gray-50 dark:bg-slate-900">
                        <td colspan="4" class="px-4 py-3 border-t border-gray-100 shadow-inner">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <!-- Locations -->
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-1 dark:text-slate-200">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        Lokasi Penyimpanan
                                    </h4>
                                    <ul class="space-y-1">
                                        <template x-for="balance in item.balances" :key="balance.id">
                                            <li class="flex justify-between border-b border-gray-200 border-dashed pb-1 last:border-0" x-show="parseFloat(balance.on_hand_qty) > 0">
                                                <span class="text-gray-700 dark:text-slate-300" x-text="balance.location?.name || 'Lokasi tidak tersedia'"></span>
                                                <span class="font-mono font-medium text-gray-900 dark:text-slate-100" x-text="formatNumber(balance.on_hand_qty) + ' ' + item.uom"></span>
                                            </li>
                                        </template>
                                        <li x-show="!item.balances?.some(balance => parseFloat(balance.on_hand_qty) > 0)" class="text-gray-600 italic dark:text-slate-300">Belum ada stok di lokasi yang dapat Anda lihat.</li>
                                    </ul>
                                </div>
                                <!-- Lots -->
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-1 dark:text-slate-200">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                        Lot Aktif
                                    </h4>
                                    <ul class="space-y-1">
                                        <template x-for="lot in item.lots" :key="lot.id">
                                            <li class="flex justify-between items-center border-b border-gray-200 border-dashed pb-1 last:border-0">
                                                <div>
                                                    <span class="font-mono text-gray-800 dark:text-slate-200" x-text="lot.lot_no"></span>
                                                    <span role="status" class="text-[10px] ml-1 px-1.5 py-0.5 rounded font-semibold"
                                                            :class="lot.is_expired ? 'inventory-status-danger' : (isNearExpiry(lot.expiry_date) ? 'inventory-status-warning' : 'inventory-status-neutral')"
                                                            x-show="lot.expiry_date"
                                                            x-text="getLotExpiryLabel(lot)"></span>
                                                </div>
                                            </li>
                                        </template>
                                        <li x-show="!item.lots || item.lots.length === 0" class="text-gray-600 italic dark:text-slate-300">Tidak ada informasi lot.</li>
                                    </ul>
                                    <div class="mt-3 text-right">
                                        <a :href="`{{ route('inventory.items.index') }}/${item.id}/lots`" class="inline-flex min-h-11 items-center text-primary-700 hover:text-primary-900 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-primary-300 dark:hover:text-primary-200 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">Lihat lot item</a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>
        </table>
    </div>

    <!-- Footer / Pagination -->
    <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6 dark:bg-slate-900 dark:border-slate-700" x-show="!loading && !errorMessage && total > 0">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-800 dark:text-slate-200">
                    Menampilkan <span class="font-medium" x-text="from"></span> sampai <span class="font-medium" x-text="to"></span> dari <span class="font-medium" x-text="total"></span> item
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button type="button" @click="prevPage" :disabled="!prevUrl"
                        class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                        <span class="sr-only">Sebelumnya</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button type="button" @click="nextPage" :disabled="!nextUrl"
                        class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                        <span class="sr-only">Berikutnya</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
        <!-- Mobile Pagination -->
        <div class="flex sm:hidden justify-between w-full" x-show="!loading && !errorMessage && total > 0">
            <button type="button" @click="prevPage" :disabled="!prevUrl" class="relative inline-flex min-h-11 items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                Sebelumnya
            </button>
            <button type="button" @click="nextPage" :disabled="!nextUrl" class="ml-3 relative inline-flex min-h-11 items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:bg-gray-100 disabled:text-gray-600 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                Berikutnya
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function inventoryOverview() {
        return {
            items: [],
            search: '',
            loading: false,
            errorMessage: '',
            requestSequence: 0,
            searchTimer: null,
            currentPage: 1,
            lastPage: 1,
            from: 0,
            to: 0,
            total: 0,
            prevUrl: null,
            nextUrl: null,
            expandedRows: [],

            init() {
                this.fetchData();
                this.$watch('search', () => {
                    window.clearTimeout(this.searchTimer);
                    this.requestSequence += 1;
                    this.currentPage = 1;
                    this.items = [];
                    this.total = 0;
                    this.from = 0;
                    this.to = 0;
                    this.prevUrl = null;
                    this.nextUrl = null;
                    this.expandedRows = [];
                    this.errorMessage = '';
                    this.loading = true;
                    this.searchTimer = window.setTimeout(() => this.fetchData(), 500);
                });
            },

            destroy() {
                window.clearTimeout(this.searchTimer);
                this.requestSequence += 1;
            },

            async fetchData(url = null) {
                window.clearTimeout(this.searchTimer);
                const requestSequence = ++this.requestSequence;
                this.loading = true;
                this.errorMessage = '';
                this.items = [];
                this.from = 0;
                this.to = 0;
                this.total = 0;
                this.prevUrl = null;
                this.nextUrl = null;
                this.expandedRows = [];
                const endpoint = url || `{{ route('inventory.ajax.overview') }}`;
                const separator = endpoint.includes('?') ? '&' : '?';
                const finalUrl = url ? url : `${endpoint}${separator}page=${this.currentPage}&q=${encodeURIComponent(this.search)}`;
                
                try {
                    const res = await fetch(finalUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (!res.ok) throw new Error('Request failed');
                    const data = await res.json();
                    if (requestSequence !== this.requestSequence) return;
                    this.items = data.data || [];
                    this.currentPage = data.current_page || 1;
                    this.lastPage = data.last_page || 1;
                    this.from = data.from || 0;
                    this.to = data.to || 0;
                    this.total = data.total || 0;
                    this.prevUrl = data.prev_page_url;
                    this.nextUrl = data.next_page_url;
                } catch (error) {
                    if (requestSequence === this.requestSequence) {
                        this.items = [];
                        this.errorMessage = 'Ringkasan inventori gagal dimuat. Periksa koneksi lalu coba lagi.';
                    }
                } finally {
                    if (requestSequence === this.requestSequence) this.loading = false;
                }
            },

            prevPage() { if(this.prevUrl) this.fetchData(this.prevUrl); },
            nextPage() { if(this.nextUrl) this.fetchData(this.nextUrl); },
            
            toggleRow(id) {
                if (this.expandedRows.includes(id)) {
                    this.expandedRows = this.expandedRows.filter(rowId => rowId !== id);
                } else {
                    this.expandedRows.push(id);
                }
            },

            formatNumber(n) { 
                return parseFloat(n).toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
            },
            
            formatDate(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
            },

            isNearExpiry(dateStr) {
                if (!dateStr) return false;
                const date = new Date(dateStr);
                const today = new Date();
                const diffTime = date - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                return diffDays <= 60;
            },
            
            getStatusLabel(status) {
                switch(status) {
                    case 'ok': return 'Aman';
                    case 'critical': return 'Stok kritis';
                    case 'empty': return 'Kosong';
                    default: return 'Status tidak diketahui';
                }
            },

            getLotExpiryLabel(lot) {
                if (lot.is_expired) return 'Kedaluwarsa';
                if (this.isNearExpiry(lot.expiry_date)) return `Hampir kedaluwarsa · ${this.formatDate(lot.expiry_date)}`;

                return this.formatDate(lot.expiry_date);
            }
        };
    }
</script>
@endpush
