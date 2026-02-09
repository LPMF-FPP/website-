<div
    x-data="fastMovingItems()"
    x-show="show"
    @open-modal-fast-moving.window="open()"
    class="relative z-50"
    style="display: none;"
>
    <!-- Backdrop -->
    <div x-show="show" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 transition-opacity" 
         @click="show = false">
    </div>

    <!-- Panel -->
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
             <div x-show="show"
                  x-transition:enter="ease-out duration-300"
                  x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                  x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                  x-transition:leave="ease-in duration-200"
                  x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                  x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                  class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                
                <!-- Content -->
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">🔥 Top Fast Moving Items (30 Hari)</h3>
                        <button @click="show = false" class="text-gray-400 hover:text-gray-500">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Loading State -->
                    <div x-show="isLoading" class="flex justify-center py-8">
                        <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <!-- Table -->
                    <div x-show="!isLoading && items.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Keluar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="item.item_id">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="from + index"></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900" x-text="item.item?.name || 'Unknown'"></div>
                                            <div class="text-xs text-gray-500" x-text="item.item?.code || ''"></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="item.item?.category || '-'"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            <span class="font-bold" x-text="parseFloat(item.total_out).toLocaleString()"></span>
                                            <span class="text-xs text-gray-500 ml-1" x-text="item.item?.uom || ''"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Empty State -->
                    <div x-show="!isLoading && items.length === 0" class="text-center py-8 text-gray-500">
                        Belum ada data items fast moving.
                    </div>

                    <!-- Pagination -->
                    <div x-show="!isLoading && (prev_page_url || next_page_url)" class="flex justify-between items-center mt-4 pt-4 border-t border-gray-200">
                        <button 
                            @click="fetchData(prev_page_url)" 
                            :disabled="!prev_page_url"
                            class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Previous
                        </button>
                        <span class="text-sm text-gray-500">
                            Page <span x-text="current_page"></span>
                        </span>
                        <button 
                            @click="fetchData(next_page_url)" 
                            :disabled="!next_page_url"
                            class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function fastMovingItems() {
        return {
            show: false,
            items: [],
            isLoading: false,
            current_page: 1,
            prev_page_url: null,
            next_page_url: null,
            from: 1,
            
            init() {
                // Listener is handled by @open-modal-fast-moving.window on root element
            },
            
            open() {
                this.show = true;
                this.fetchData("{{ route('inventory.ajax.fast-moving') }}");
            },
            
            async fetchData(url) {
                if (!url) return;
                
                this.isLoading = true;
                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        // Handle pagination response structure (Laravel paginate())
                        this.items = data.data || [];
                        this.current_page = data.current_page;
                        this.prev_page_url = data.prev_page_url;
                        this.next_page_url = data.next_page_url;
                        this.from = data.from || 1;
                    }
                } catch (error) {
                    console.error('Error fetching fast moving items:', error);
                } finally {
                    this.isLoading = false;
                }
            }
        }
    }
</script>
