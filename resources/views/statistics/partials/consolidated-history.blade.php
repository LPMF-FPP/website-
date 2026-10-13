<div x-data="{
    historyData: [],
    loading: false,
    historyError: '',
    meta: {},

    init() {
        this.loadHistory();
        window.loadHistory = () => this.loadHistory();
    },

    loadHistory(page = 1) {
        this.loading = true;
        this.historyError = '';
        axios.get('{{ route('consolidated-reports.history') }}?page=' + page)
            .then(response => {
                this.historyData = response.data.data;
                this.meta = {
                    current_page: response.data.current_page,
                    last_page: response.data.last_page,
                    from: response.data.from,
                    to: response.data.to,
                    total: response.data.total
                };
            })
            .catch(error => {
                this.historyError = 'Riwayat laporan gagal dimuat. Periksa koneksi lalu coba lagi.';
            })
            .finally(() => {
                this.loading = false;
            });
    },

    deleteReport(id) {
        if (!confirm('Apakah Anda yakin ingin menghapus laporan ini?')) return;

        axios.delete(`/statistics/reports/${id}`)
            .then(() => {
                this.loadHistory(this.meta.current_page);
            })
            .catch(error => {
                this.historyError = 'Laporan gagal dihapus. Periksa koneksi lalu coba lagi.';
            });
    }
}">
    <p x-cloak x-show="historyError" role="alert" aria-live="assertive" class="inventory-status-danger m-4 rounded-md p-3 text-sm" x-text="historyError"></p>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 semantic-text dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-900">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide semantic-text-muted">Periode</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide semantic-text-muted">Tanggal dibuat</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide semantic-text-muted">Dibuat oleh</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide semantic-text-muted">Tipe</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide semantic-text-muted">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-slate-700 dark:bg-slate-800">
                <template x-if="loading">
                    <tr>
                        <td colspan="5" role="status" aria-live="polite" class="px-6 py-4 text-center text-sm semantic-text-muted">Memuat riwayat laporan.</td>
                    </tr>
                </template>
                
                <template x-if="!loading && historyData.length === 0">
                    <tr>
                        <td colspan="5" role="status" class="px-6 py-8 text-center text-sm semantic-text-muted">Belum ada laporan yang dibuat.</td>
                    </tr>
                </template>

                <template x-for="report in historyData" :key="report.id">
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium semantic-text" x-text="report.period_label"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm semantic-text-muted" x-text="new Date(report.generated_at).toLocaleString('id-ID')"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm semantic-text-muted" x-text="report.generated_by?.name || 'Sistem'"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inventory-status-neutral inline-flex rounded px-2 py-1 text-xs font-semibold"
                                x-text="report.is_auto_generated ? 'Otomatis' : 'Manual'">
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a :href="report.download_url" target="_blank" class="inline-flex min-h-11 items-center px-2 font-medium text-primary-800 underline hover:text-primary-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 dark:text-primary-300 dark:hover:text-primary-200 dark:focus-visible:ring-primary-300" aria-label="Unduh PDF laporan">
                                Unduh PDF
                            </a>
                            <button type="button" @click="deleteReport(report.id)" class="inline-flex min-h-11 items-center px-2 font-medium text-red-800 underline hover:text-red-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:text-red-300 dark:hover:text-red-200 dark:focus-visible:ring-red-300" :aria-label="`Hapus laporan ${report.period_label}`">
                                Hapus
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between border-t border-sem semantic-surface px-4 py-3 sm:px-6" x-show="meta.last_page > 1">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm semantic-text">
                    Menampilkan <span class="font-medium tabular-nums" x-text="meta.from"></span> sampai <span class="font-medium tabular-nums" x-text="meta.to"></span> dari <span class="font-medium tabular-nums" x-text="meta.total"></span> laporan
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button type="button" @click="loadHistory(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        <span class="sr-only">Sebelumnya</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.707 5.291a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button type="button" @click="loadHistory(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        <span class="sr-only">Berikutnya</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>
</div>
