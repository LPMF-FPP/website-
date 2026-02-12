<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Laporan QMH"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Laporan QMH'],
            ]"
        />
    </x-slot>

    <div class="space-y-6 sm:px-6 lg:px-8" x-data="qmhReports({ csrfToken: @js(csrf_token()) })" x-init="init()">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form class="grid gap-3 md:grid-cols-2 lg:grid-cols-4" @submit.prevent="applyFilters()">
                <input type="text" x-model="filters.search" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" placeholder="Cari kode/judul dokumen">

                <select x-model="filters.clause" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Klausul</option>
                    <option value="4">Klausul 4</option>
                    <option value="5">Klausul 5</option>
                    <option value="6">Klausul 6</option>
                    <option value="7">Klausul 7</option>
                    <option value="8">Klausul 8</option>
                </select>

                <select x-model="filters.doc_type" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Jenis</option>
                    <option value="sop">SOP</option>
                    <option value="ik">IK</option>
                    <option value="formulir">Formulir</option>
                </select>

                <input type="number" min="1" x-model="filters.actor_id" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" placeholder="ID Aktor (opsional)">

                <input type="date" x-model="filters.from" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <input type="date" x-model="filters.to" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">

                <select x-model="filters.per_page" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="10">10 / halaman</option>
                    <option value="15">15 / halaman</option>
                    <option value="25">25 / halaman</option>
                    <option value="50">50 / halaman</option>
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Terapkan</button>
                    <button type="button" @click="resetFilters()" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded-md px-3 py-2 text-sm font-medium" :class="tabClass('revision-history')" @click="switchTab('revision-history')">Riwayat Revisi</button>
                    <button type="button" class="rounded-md px-3 py-2 text-sm font-medium" :class="tabClass('download-history')" @click="switchTab('download-history')">Riwayat Unduhan</button>
                    <button type="button" class="rounded-md px-3 py-2 text-sm font-medium" :class="tabClass('controlled-distribution')" @click="switchTab('controlled-distribution')">Distribusi Terkendali</button>

                    <button type="button" @click="exportCsv()" class="ml-auto inline-flex items-center rounded-md border border-primary-600 px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-50">
                        Export CSV
                    </button>
                </div>
            </div>

            <div class="p-4">
                <template x-if="loading">
                    <div class="space-y-2">
                        <div class="h-8 animate-pulse rounded bg-gray-100"></div>
                        <div class="h-8 animate-pulse rounded bg-gray-100"></div>
                        <div class="h-8 animate-pulse rounded bg-gray-100"></div>
                    </div>
                </template>

                <template x-if="errorMessage">
                    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="errorMessage"></div>
                </template>

                <div x-show="!loading && rows.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm" x-show="activeTab === 'revision-history'">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Tanggal</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Aktor</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Kode Dokumen</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Judul</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Versi</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Event</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Catatan</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        <template x-for="row in rows" :key="`${row.occurred_at}-${row.document_code}-${row.actor_id}`">
                            <tr>
                                <td class="px-3 py-2 text-gray-700" x-text="formatDate(row.occurred_at)"></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.actor_name || '-'" ></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.document_code"></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.document_title"></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.version_label || '-'" ></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.status_transition || '-'" ></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.reason || '-'" ></td>
                            </tr>
                        </template>
                        </tbody>
                    </table>

                    <table class="min-w-full divide-y divide-gray-200 text-sm" x-show="activeTab !== 'revision-history'">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Tanggal</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Pengunduh</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Kode Dokumen</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Judul</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Versi</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Jenis Copy</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Alasan</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">Target Distribusi</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        <template x-for="row in rows" :key="`${row.occurred_at}-${row.document_code}-${row.actor_id}`">
                            <tr>
                                <td class="px-3 py-2 text-gray-700" x-text="formatDate(row.occurred_at)"></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.actor_name || '-'" ></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.document_code"></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.document_title"></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.version_label || '-'" ></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.copy_type || '-'" ></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.reason || '-'" ></td>
                                <td class="px-3 py-2 text-gray-700" x-text="row.distribution_target || '-'" ></td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>

                <p x-show="!loading && rows.length === 0 && !errorMessage" class="text-sm text-gray-500">Tidak ada data laporan untuk filter yang dipilih.</p>

                <div x-show="pagination.total > 0" class="mt-4 flex items-center justify-between border-t border-gray-200 pt-3">
                    <p class="text-xs text-gray-500" x-text="`Menampilkan ${rows.length} dari ${pagination.total} data`"></p>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-700 disabled:opacity-50" @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1">Sebelumnya</button>
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-700 disabled:opacity-50" @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page">Berikutnya</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
    <script>
        function qmhReports(config) {
            return {
                csrfToken: config.csrfToken,
                activeTab: 'revision-history',
                loading: false,
                errorMessage: '',
                rows: [],
                filters: {
                    search: '',
                    clause: '',
                    doc_type: '',
                    actor_id: '',
                    from: '',
                    to: '',
                    per_page: '15',
                },
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    total: 0,
                },

                init() {
                    this.fetchRows();
                },

                tabClass(tab) {
                    if (this.activeTab === tab) {
                        return 'bg-primary-100 text-primary-700';
                    }

                    return 'text-gray-600 hover:bg-gray-100';
                },

                switchTab(tab) {
                    if (this.activeTab === tab) {
                        return;
                    }

                    this.activeTab = tab;
                    this.pagination.current_page = 1;
                    this.fetchRows();
                },

                applyFilters() {
                    this.pagination.current_page = 1;
                    this.fetchRows();
                },

                resetFilters() {
                    this.filters = {
                        search: '',
                        clause: '',
                        doc_type: '',
                        actor_id: '',
                        from: '',
                        to: '',
                        per_page: '15',
                    };

                    this.pagination.current_page = 1;
                    this.fetchRows();
                },

                goToPage(page) {
                    if (page < 1 || page > this.pagination.last_page) {
                        return;
                    }

                    this.pagination.current_page = page;
                    this.fetchRows();
                },

                buildEndpoint() {
                    if (this.activeTab === 'revision-history') {
                        return '/api/quality/reports/revision-history';
                    }

                    if (this.activeTab === 'download-history') {
                        return '/api/quality/reports/download-history';
                    }

                    return '/api/quality/reports/controlled-distribution';
                },

                buildExportEndpoint() {
                    if (this.activeTab === 'revision-history') {
                        return '/api/quality/reports/revision-history/export';
                    }

                    if (this.activeTab === 'download-history') {
                        return '/api/quality/reports/download-history/export';
                    }

                    return '/api/quality/reports/controlled-distribution/export';
                },

                buildParams(page = null) {
                    const params = new URLSearchParams();
                    params.set('per_page', this.filters.per_page || '15');
                    params.set('page', String(page || this.pagination.current_page));

                    const keys = ['search', 'clause', 'doc_type', 'actor_id', 'from', 'to'];
                    for (const key of keys) {
                        const value = this.filters[key];
                        if (value !== null && value !== undefined && String(value).trim() !== '') {
                            params.set(key, String(value));
                        }
                    }

                    return params;
                },

                async fetchRows() {
                    this.loading = true;
                    this.errorMessage = '';

                    const endpoint = this.buildEndpoint();
                    const params = this.buildParams();

                    try {
                        const response = await fetch(`${endpoint}?${params.toString()}`, {
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                            },
                        });

                        if (!response.ok) {
                            this.errorMessage = await this.extractErrorMessage(response, 'Gagal memuat data laporan.');
                            return;
                        }

                        const payload = await response.json();
                        this.rows = payload.data || [];
                        this.pagination = {
                            current_page: payload.current_page || 1,
                            last_page: payload.last_page || 1,
                            total: payload.total || 0,
                        };
                    } catch (error) {
                        this.errorMessage = 'Terjadi gangguan jaringan saat memuat laporan.';
                    } finally {
                        this.loading = false;
                    }
                },

                exportCsv() {
                    const endpoint = this.buildExportEndpoint();
                    const params = this.buildParams(1);
                    window.location.href = `${endpoint}?${params.toString()}`;
                },

                formatDate(value) {
                    if (!value) return '-';
                    const date = new Date(value);
                    if (Number.isNaN(date.getTime())) return value;
                    return date.toLocaleString('id-ID', { hour12: false });
                },

                async extractErrorMessage(response, fallback) {
                    try {
                        const payload = await response.json();
                        if (payload?.message) {
                            return payload.message;
                        }

                        if (payload?.errors) {
                            const firstKey = Object.keys(payload.errors)[0];
                            if (firstKey && payload.errors[firstKey]?.length) {
                                return payload.errors[firstKey][0];
                            }
                        }
                    } catch (error) {
                    }

                    return fallback;
                },
            };
        }
    </script>
@endpush
