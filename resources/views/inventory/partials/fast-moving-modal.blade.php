<div
    x-data="fastMovingItems()"
    x-show="show"
    @open-modal-fast-moving.window="open($event.detail?.trigger)"
    @keydown.escape.window="if (show) close()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="fast-moving-title"
    :aria-hidden="show ? 'false' : 'true'"
    class="relative z-50"
    style="display: none;"
>
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-black/50" @click.self="close()">
        <div class="fixed inset-0 overflow-y-auto p-4 sm:p-6" @click.self="close()">
            <section x-show="show" x-transition class="mx-auto mt-8 w-full max-w-3xl overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-xl dark:border-slate-700 dark:bg-slate-800" @click.stop @keydown.tab="trapFocus($event)">
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-4 dark:border-slate-700 sm:px-6">
                    <h2 id="fast-moving-title" class="text-lg font-semibold text-gray-900 dark:text-slate-100">Item dengan pengeluaran tertinggi, 30 hari</h2>
                    <button x-ref="closeButton" type="button" @click="close()" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                        <span class="sr-only">Tutup daftar pergerakan item</span>
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-4 py-5 sm:px-6">
                    <p x-cloak x-show="isLoading" role="status" aria-live="polite" class="flex min-h-16 items-center justify-center gap-3 text-sm text-gray-800 dark:text-slate-200">
                        <svg aria-hidden="true" class="h-5 w-5 animate-spin text-primary-700 dark:text-primary-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memuat data pengeluaran item.
                    </p>

                    <div x-cloak x-show="!isLoading && errorMessage" role="alert" class="rounded border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
                        <p x-text="errorMessage"></p>
                        <button type="button" @click="fetchData(retryUrl)" class="mt-2 inline-flex min-h-11 items-center font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 focus-visible:ring-offset-2 dark:focus-visible:ring-red-300 dark:focus-visible:ring-offset-slate-900">Coba lagi</button>
                    </div>

                    <div x-show="!isLoading && !errorMessage && items.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                            <caption class="sr-only">Urutan pengeluaran item selama 30 hari terakhir</caption>
                            <thead class="bg-gray-50 dark:bg-slate-900">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-300">Urutan</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-300">Item</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-300">Kategori</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-300">Total keluar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-slate-700 dark:bg-slate-800">
                                <template x-for="(item, index) in items" :key="item.item_id">
                                    <tr>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-800 dark:text-slate-200" x-text="from + index"></td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100" x-text="item.item?.name || 'Item tidak tersedia'"></div>
                                            <div class="text-xs text-gray-700 dark:text-slate-300" x-text="item.item?.item_code || 'Kode belum ditetapkan'"></div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-800 dark:text-slate-200" x-text="item.item?.category || '-' "></td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900 dark:text-slate-100">
                                            <span class="font-semibold tabular-nums" x-text="parseFloat(item.total_out).toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 })"></span>
                                            <span class="ml-1 text-xs text-gray-700 dark:text-slate-300" x-text="item.item?.uom || ''"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <p x-show="!isLoading && !errorMessage && items.length === 0" role="status" class="py-8 text-center text-sm text-gray-700 dark:text-slate-300">Belum ada pengeluaran stok untuk ditampilkan dalam periode ini.</p>

                    <div x-show="!isLoading && !errorMessage && (prev_page_url || next_page_url)" class="mt-4 flex items-center justify-between border-t border-gray-200 pt-4 dark:border-slate-700">
                        <button type="button" @click="fetchData(prev_page_url)" :disabled="!prev_page_url" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">Sebelumnya</button>
                        <span class="text-sm text-gray-700 dark:text-slate-300">Halaman <span x-text="current_page"></span></span>
                        <button type="button" @click="fetchData(next_page_url)" :disabled="!next_page_url" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">Berikutnya</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
    function fastMovingItems() {
        return {
            show: false,
            items: [],
            isLoading: false,
            errorMessage: '',
            current_page: 1,
            prev_page_url: null,
            next_page_url: null,
            from: 1,
            retryUrl: null,
            requestSequence: 0,
            returnFocus: null,

            open(trigger = null) {
                this.returnFocus = trigger || document.activeElement;
                this.show = true;
                this.$nextTick(() => this.$refs.closeButton?.focus());
                this.fetchData("{{ route('inventory.ajax.fast-moving') }}");
            },

            close() {
                this.requestSequence += 1;
                this.isLoading = false;
                this.show = false;
                this.$nextTick(() => this.returnFocus?.focus());
            },

            trapFocus(event) {
                const focusable = Array.from(this.$el.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                    .filter((element) => element.offsetParent !== null);
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            },

            async fetchData(url) {
                if (!url) return;
                const requestSequence = ++this.requestSequence;
                this.retryUrl = url;
                this.items = [];
                this.errorMessage = '';
                this.isLoading = true;
                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!response.ok) throw new Error('Request failed');
                    const data = await response.json();
                    if (requestSequence !== this.requestSequence) return;
                    this.items = Array.isArray(data.data) ? data.data : [];
                    this.current_page = data.current_page || 1;
                    this.prev_page_url = data.prev_page_url;
                    this.next_page_url = data.next_page_url;
                    this.from = data.from || 1;
                } catch (error) {
                    if (requestSequence === this.requestSequence) {
                        this.items = [];
                        this.errorMessage = 'Data pengeluaran gagal dimuat. Periksa koneksi lalu coba lagi.';
                    }
                } finally {
                    if (requestSequence === this.requestSequence) this.isLoading = false;
                }
            }
        };
    }
</script>
