<div x-data="{
    historyData: [],
    loading: false,
    errorMessage: '',
    meta: {},

    init() {
        this.loadHistory();
        window.loadHistory = () => this.loadHistory();
    },

    loadHistory(page = 1) {
        this.loading = true;
        this.errorMessage = '';
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
                console.error('Failed to load history:', error);
                this.errorMessage = 'Riwayat laporan gagal dimuat. Coba perbarui kembali.';
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
                this.errorMessage = error.response?.data?.message || 'Gagal menghapus laporan.';
            });
    },

    reviseReport(report) {
        const reason = window.prompt('Jelaskan alasan revisi laporan (minimal 10 karakter):');
        if (!reason) return;
        if (reason.trim().length < 10) {
            this.errorMessage = 'Alasan revisi minimal 10 karakter.';
            return;
        }

        axios.post(`/statistics/reports/${report.id}/revisions`, { reason: reason.trim() })
            .then(() => this.loadHistory(this.meta.current_page))
            .catch(error => {
                this.errorMessage = error.response?.data?.message || 'Gagal menerbitkan revisi laporan.';
            });
    }
}">
    <div x-show="errorMessage" x-cloak role="alert" class="border-b border-red-200 bg-red-50 p-3 text-sm text-red-700" x-text="errorMessage"></div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Generate</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dibuat Oleh</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-if="loading">
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Memuat data...</td>
                    </tr>
                </template>
                
                <template x-if="!loading && historyData.length === 0">
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada riwayat laporan.</td>
                    </tr>
                </template>

                <template x-for="report in historyData" :key="report.id">
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="report.revision_number > 1 ? `${report.period_label} (Revisi ${report.revision_number} dari #${report.revision_of_id})` : report.period_label" :title="report.revision_reason || ''"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="new Date(report.generated_at).toLocaleString('id-ID')"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="report.generated_by?.name || 'System'"></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                :class="report.is_auto_generated ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'"
                                x-text="report.is_auto_generated ? 'Auto' : 'Manual'">
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a :href="report.download_url" target="_blank" rel="noopener" class="inline-flex min-h-11 min-w-11 items-center justify-center text-indigo-600 hover:text-indigo-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-700" title="Unduh PDF" :aria-label="`Unduh PDF ${report.period_label}`">
                                ⬇️
                            </a>
                            <button type="button" @click="reviseReport(report)" class="min-h-11 min-w-11 text-amber-700 hover:text-amber-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700" :aria-label="`Buat revisi untuk ${report.period_label}`" title="Buat revisi">
                                Revisi
                            </button>
                            <button @click="deleteReport(report.id)" :disabled="report.revision_number > 1 || report.revisions_count > 0" class="inline-flex min-h-11 min-w-11 items-center justify-center text-red-600 hover:text-red-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 disabled:cursor-not-allowed disabled:opacity-40" title="Hapus Laporan" :aria-label="`Hapus ${report.period_label}`">
                                🗑️
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6" x-show="meta.last_page > 1">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Showing <span class="font-medium" x-text="meta.from"></span> to <span class="font-medium" x-text="meta.to"></span> of <span class="font-medium" x-text="meta.total"></span> results
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button @click="loadHistory(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                        <span class="sr-only">Previous</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.707 5.291a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button @click="loadHistory(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                        <span class="sr-only">Next</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>
</div>
