<div class="bg-white rounded-lg shadow-sm border border-gray-200" x-data="inventoryOverview()">
    <div class="px-4 py-3 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
            <span>📦</span> Ringkasan Inventori
        </h3>
        <div class="relative max-w-xs w-full">
            <input type="text" 
                   x-model.debounce.500ms="search" 
                   placeholder="Cari item..." 
                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500 pl-8" />
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8"></th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / Kategori</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Stok</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>

            <!-- Loading State -->
            <tbody class="bg-white divide-y divide-gray-100" x-show="loading">
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-sm">
                        <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memuat data stok...
                    </td>
                </tr>
            </tbody>

            <!-- Empty State -->
            <tbody class="bg-white divide-y divide-gray-100" x-show="!loading && items.length === 0">
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-sm">
                        Tidak ada item ditemukan.
                    </td>
                </tr>
            </tbody>

            <!-- Data Loop -->
            <template x-for="item in items" :key="item.id">
                <tbody class="group border-b border-gray-100 bg-white hover:bg-gray-50 transition-colors">
                    <!-- Main Row -->
                    <tr class="cursor-pointer" @click="toggleRow(item.id)">
                        <td class="px-4 py-3 text-gray-400">
                            <svg class="w-4 h-4 transform transition-transform duration-200" 
                                    :class="{'rotate-90': expandedRows.includes(item.id)}" 
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-bold text-gray-900" x-text="item.name"></div>
                            <div class="text-xs text-gray-500 mt-0.5" x-text="item.category || '-'"></div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-sm font-mono font-bold text-gray-800" x-text="formatNumber(item.total_stock || 0)"></span>
                                <span class="text-[10px] text-gray-500 uppercase" x-text="item.uom"></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800': item.status === 'ok',
                                        'bg-red-100 text-red-800': item.status === 'critical',
                                        'bg-gray-100 text-gray-800': item.status === 'empty'
                                    }"
                                    x-text="getStatusLabel(item.status)">
                            </span>
                        </td>
                    </tr>
                    
                    <!-- Detail Row (Expanded) -->
                    <tr x-show="expandedRows.includes(item.id)" 
                        x-transition:enter="transition ease-out duration-100" 
                        x-transition:enter-start="opacity-0" 
                        x-transition:enter-end="opacity-100" 
                        class="bg-gray-50">
                        <td colspan="4" class="px-4 py-3 border-t border-gray-100 shadow-inner">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <!-- Locations -->
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-2 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        Lokasi Penyimpanan
                                    </h4>
                                    <ul class="space-y-1">
                                        <template x-for="balance in item.balances" :key="balance.id">
                                            <li class="flex justify-between border-b border-gray-200 border-dashed pb-1 last:border-0" x-show="parseFloat(balance.on_hand_qty) > 0">
                                                <span class="text-gray-600" x-text="balance.location?.name || 'Unknown'"></span>
                                                <span class="font-mono font-medium" x-text="formatNumber(balance.on_hand_qty) + ' ' + item.uom"></span>
                                            </li>
                                        </template>
                                        <li x-show="!item.balances || item.balances.length === 0" class="text-gray-400 italic">Belum ada stok di lokasi manapun.</li>
                                    </ul>
                                </div>
                                <!-- Lots -->
                                <div>
                                    <h4 class="font-semibold text-gray-700 mb-2 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                        Lot Aktif
                                    </h4>
                                    <ul class="space-y-1">
                                        <template x-for="lot in item.lots" :key="lot.id">
                                            <li class="flex justify-between items-center border-b border-gray-200 border-dashed pb-1 last:border-0">
                                                <div>
                                                    <span class="font-mono text-gray-700" x-text="lot.lot_no"></span>
                                                    <span class="text-[10px] ml-1 px-1.5 py-0.5 rounded text-white" 
                                                            :class="isNearExpiry(lot.expiry_date) ? 'bg-orange-500' : 'bg-gray-400'"
                                                            x-show="lot.expiry_date"
                                                            x-text="formatDate(lot.expiry_date)"></span>
                                                </div>
                                            </li>
                                        </template>
                                        <li x-show="!item.lots || item.lots.length === 0" class="text-gray-400 italic">Tidak ada informasi lot.</li>
                                    </ul>
                                    <div class="mt-3 text-right">
                                        <a :href="`{{ route('inventory.items.index') }}/${item.id}`" class="text-primary-600 hover:text-primary-800 font-medium">Lihat Detail Item &rarr;</a>
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
    <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6" x-show="!loading && total > 0">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Menampilkan <span class="font-medium" x-text="from"></span> sampai <span class="font-medium" x-text="to"></span> dari <span class="font-medium" x-text="total"></span> item
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button @click="prevPage" :disabled="!prevUrl" 
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="sr-only">Previous</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button @click="nextPage" :disabled="!nextUrl" 
                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="sr-only">Next</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
        <!-- Mobile Pagination -->
        <div class="flex sm:hidden justify-between w-full">
            <button @click="prevPage" :disabled="!prevUrl" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                Prev
            </button>
            <button @click="nextPage" :disabled="!nextUrl" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                Next
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
                    this.currentPage = 1; 
                    this.fetchData(); 
                });
            },

            async fetchData(url = null) {
                this.loading = true;
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
                    
                    if(res.ok) {
                        const data = await res.json();
                        this.items = data.data;
                        this.currentPage = data.current_page;
                        this.lastPage = data.last_page;
                        this.from = data.from;
                        this.to = data.to;
                        this.total = data.total;
                        this.prevUrl = data.prev_page_url;
                        this.nextUrl = data.next_page_url;
                        this.expandedRows = []; // Reset expanded on page change
                    }
                } catch(e) {
                    console.error('Failed to fetch inventory overview', e);
                } finally {
                    this.loading = false;
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
                return parseFloat(n).toLocaleString('id-ID', { maximumFractionDigits: 3 }); 
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
                    case 'critical': return 'Kritis';
                    case 'empty': return 'Kosong';
                    default: return status;
                }
            }
        }
    }
</script>
@endpush
