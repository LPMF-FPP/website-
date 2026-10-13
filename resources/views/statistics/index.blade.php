<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Statistik dan rekap data"
            class="statistics-page-header"
            :breadcrumbs="[[ 'label' => 'Statistik' ]]"
        />
    </x-slot>

    <div class="statistics-dashboard space-y-6" x-data="{
        activeTab: @js($activeTab),
        canExport: @js($canExportStatistics),
        popStateListener: null,
        init() {
            this.popStateListener = () => {
                const requestedTab = new URLSearchParams(window.location.search).get('tab');
                this.activeTab = requestedTab === 'reports' && this.canExport ? 'reports' : 'dashboard';
                if (this.activeTab === 'dashboard') window.dispatchEvent(new Event('statistics:dashboard-selected'));
            };
            window.addEventListener('popstate', this.popStateListener);
        },
        destroy() {
            window.removeEventListener('popstate', this.popStateListener);
        },
        switchTab(tab) {
            if (tab === 'reports' && !this.canExport) return;
            this.activeTab = tab;
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
            if (tab === 'dashboard') window.dispatchEvent(new Event('statistics:dashboard-selected'));
        }
    }">

        <!-- Tab Navigation -->
        <div class="statistics-tabs inline-flex flex-wrap gap-1 rounded-lg border border-sem semantic-surface p-1" role="group" aria-label="Tampilan statistik">
                <button type="button" @click="switchTab('dashboard')" :aria-pressed="activeTab === 'dashboard'"
                    :class="activeTab === 'dashboard' ? 'bg-primary-800 text-white dark:bg-primary-300 dark:text-slate-900' : 'text-gray-700 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex min-h-11 items-center rounded-md px-4 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-300 dark:focus-visible:ring-offset-slate-900">
                    <svg aria-hidden="true" class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Ringkasan</span>
                </button>

@if($canExportStatistics)
                <button type="button" @click="switchTab('reports')" :aria-pressed="activeTab === 'reports'"
                    :class="activeTab === 'reports' ? 'bg-primary-800 text-white dark:bg-primary-300 dark:text-slate-900' : 'text-gray-700 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex min-h-11 items-center rounded-md px-4 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-300 dark:focus-visible:ring-offset-slate-900">
                    <svg aria-hidden="true" class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Laporan Periodik</span>
                </button>
                @endif
        </div>

        <!-- Dashboard Content -->
        <div id="statistics-dashboard-panel" x-show="activeTab === 'dashboard'" role="region" aria-label="Ringkasan statistik">
            
            <div class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-sem semantic-surface p-5">
                        <div>
                            <div class="flex items-center">
                                <div class="text-primary-800 dark:text-primary-300">
                                    <svg aria-hidden="true" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-slate-300">Permintaan bulan ini</p>
                                    <p class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-slate-100">{{ $requests_this_month }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-sem semantic-surface p-5">
                        <div>
                            <div class="flex items-center">
                                <div class="text-primary-800 dark:text-primary-300">
                                    <svg aria-hidden="true" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-slate-300">Sampel tahun ini</p>
                                    <p class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-slate-100">{{ $samples_this_year }}</p>
                                    <p class="text-xs text-gray-600 dark:text-slate-300">Acuan IKU: {{ config('statistics.targets.annual_samples') }} per tahun</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-sem semantic-surface p-5">
                        <div>
                            <div class="flex items-center">
                                <div class="text-primary-800 dark:text-primary-300">
                                    <svg aria-hidden="true" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-slate-300">Zat aktif terdeteksi</p>
                                    <p class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-slate-100">{{ $active_substances_detected }}</p>
                                    @if(($activeSubstanceBreakdown['fallback'] ?? false) && ! empty($activeSubstanceBreakdown['labels']))
                                        <p class="mt-1 text-xs text-gray-600 dark:text-slate-300">Menampilkan data contoh, bukan hasil permintaan aktual.</p>
                                    @elseif(empty($activeSubstanceBreakdown['labels']))
                                        <p class="mt-1 text-xs text-gray-600 dark:text-slate-300">Belum ada data zat aktif tercatat.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <section class="rounded-lg border border-sem semantic-surface p-5" aria-labelledby="user-origin-title">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 id="user-origin-title" class="text-lg font-semibold semantic-text">Permintaan menurut wilayah penyidik</h3>
                            @if($canExportStatistics)
                                <button type="button" data-chart-export="userOrigin" disabled onclick="exportChart('user_origin')" aria-label="Unduh grafik wilayah penyidik dalam format PNG" class="btn btn-outline min-h-11 px-3 text-sm dark:border-primary-300 dark:text-primary-300 dark:hover:bg-slate-700 dark:focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-60">Unduh PNG</button>
                            @endif
                        </div>
                        <div class="relative flex min-h-[320px] items-center justify-center">
                            <canvas id="userOriginChart" class="hidden" role="img" aria-label="Diagram permintaan menurut wilayah penyidik" aria-describedby="userOriginChartSummary"></canvas>
                            <p id="userOriginChartSummary" class="sr-only"></p>
                            <p data-chart-loading="userOrigin" role="status" aria-live="polite" class="text-sm semantic-text-muted">Memuat data wilayah penyidik.</p>
                            <p data-chart-empty="userOrigin" role="status" aria-live="polite" class="hidden text-center text-sm semantic-text-muted">Belum ada permintaan dengan data wilayah penyidik.</p>
                            <div data-chart-error="userOrigin" role="alert" aria-live="assertive" class="inventory-status-danger hidden rounded-md p-3 text-sm"><span data-chart-error-text>Grafik wilayah penyidik gagal dimuat.</span> <button type="button" onclick="refreshCharts()" class="ml-2 min-h-11 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 dark:focus-visible:ring-red-300">Coba lagi</button></div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sem semantic-surface p-5" aria-labelledby="active-substances-title">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 id="active-substances-title" class="text-lg font-semibold semantic-text">Jenis zat aktif</h3>
                            @if($canExportStatistics)
                                <button type="button" data-chart-export="activeSubstances" disabled onclick="exportChart('active_substances')" aria-label="Unduh grafik zat aktif dalam format PNG" class="btn btn-outline min-h-11 px-3 text-sm dark:border-primary-300 dark:text-primary-300 dark:hover:bg-slate-700 dark:focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-60">Unduh PNG</button>
                            @endif
                        </div>
                        <div class="relative flex min-h-[320px] items-center justify-center">
                            <canvas id="activeSubstancesChart" class="hidden" role="img" aria-label="Diagram jenis zat aktif yang tercatat" aria-describedby="activeSubstancesChartSummary"></canvas>
                            <p id="activeSubstancesChartSummary" class="sr-only"></p>
                            <p data-chart-loading="activeSubstances" role="status" aria-live="polite" class="text-sm semantic-text-muted">Memuat data zat aktif.</p>
                            <p data-chart-empty="activeSubstances" role="status" aria-live="polite" class="hidden text-center text-sm semantic-text-muted">Belum ada catatan zat aktif dari permintaan atau hasil pengujian.</p>
                            <div data-chart-error="activeSubstances" role="alert" aria-live="assertive" class="inventory-status-danger hidden rounded-md p-3 text-sm"><span data-chart-error-text>Grafik zat aktif gagal dimuat.</span> <button type="button" onclick="refreshCharts()" class="ml-2 min-h-11 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 dark:focus-visible:ring-red-300">Coba lagi</button></div>
                        </div>
                        <p id="topActiveSubstancesText" role="status" aria-live="polite" class="mt-3 text-center text-sm semantic-text-muted"></p>
                        <p id="activeSubstancesSource" class="mt-1 hidden text-center text-xs semantic-text-muted">Sumber: catatan zat aktif pada sampel atau hasil pengujian.</p>
                    </section>

                    <section class="rounded-lg border border-sem semantic-surface p-5" aria-labelledby="suspect-gender-title">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 id="suspect-gender-title" class="text-lg font-semibold semantic-text">Jenis kelamin tersangka</h3>
                            @if($canExportStatistics)
                                <button type="button" data-chart-export="suspectGender" disabled onclick="exportChart('suspect_gender')" aria-label="Unduh grafik jenis kelamin tersangka dalam format PNG" class="btn btn-outline min-h-11 px-3 text-sm dark:border-primary-300 dark:text-primary-300 dark:hover:bg-slate-700 dark:focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-60">Unduh PNG</button>
                            @endif
                        </div>
                        <div class="relative flex min-h-[320px] items-center justify-center">
                            <canvas id="suspectGenderChart" class="hidden" role="img" aria-label="Diagram jenis kelamin tersangka" aria-describedby="suspectGenderChartSummary"></canvas>
                            <p id="suspectGenderChartSummary" class="sr-only"></p>
                            <p data-chart-loading="suspectGender" role="status" aria-live="polite" class="text-sm semantic-text-muted">Memuat data jenis kelamin.</p>
                            <p data-chart-empty="suspectGender" role="status" aria-live="polite" class="hidden text-center text-sm semantic-text-muted">Belum ada data jenis kelamin pada permintaan.</p>
                            <div data-chart-error="suspectGender" role="alert" aria-live="assertive" class="inventory-status-danger hidden rounded-md p-3 text-sm"><span data-chart-error-text>Grafik jenis kelamin gagal dimuat.</span> <button type="button" onclick="refreshCharts()" class="ml-2 min-h-11 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 dark:focus-visible:ring-red-300">Coba lagi</button></div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sem semantic-surface p-5" aria-labelledby="suspect-age-title">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 id="suspect-age-title" class="text-lg font-semibold semantic-text">Rentang umur tersangka</h3>
                            @if($canExportStatistics)
                                <button type="button" data-chart-export="suspectAge" disabled onclick="exportChart('suspect_age')" aria-label="Unduh grafik rentang umur tersangka dalam format PNG" class="btn btn-outline min-h-11 px-3 text-sm dark:border-primary-300 dark:text-primary-300 dark:hover:bg-slate-700 dark:focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-60">Unduh PNG</button>
                            @endif
                        </div>
                        <div class="relative flex min-h-[320px] items-center justify-center">
                            <canvas id="suspectAgeChart" class="hidden" role="img" aria-label="Diagram rentang umur tersangka" aria-describedby="suspectAgeChartSummary"></canvas>
                            <p id="suspectAgeChartSummary" class="sr-only"></p>
                            <p data-chart-loading="suspectAge" role="status" aria-live="polite" class="text-sm semantic-text-muted">Memuat data umur.</p>
                            <p data-chart-empty="suspectAge" role="status" aria-live="polite" class="hidden text-center text-sm semantic-text-muted">Belum ada data umur pada permintaan.</p>
                            <div data-chart-error="suspectAge" role="alert" aria-live="assertive" class="inventory-status-danger hidden rounded-md p-3 text-sm"><span data-chart-error-text>Grafik rentang umur gagal dimuat.</span> <button type="button" onclick="refreshCharts()" class="ml-2 min-h-11 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 dark:focus-visible:ring-red-300">Coba lagi</button></div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sem semantic-surface p-5 xl:col-span-2" aria-labelledby="monthly-requests-title">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 id="monthly-requests-title" class="text-lg font-semibold semantic-text">Permintaan per bulan</h3>
                            @if($canExportStatistics)
                                <button type="button" data-chart-export="monthlyRequests" disabled onclick="exportChart('monthly_requests')" aria-label="Unduh grafik permintaan bulanan dalam format PNG" class="btn btn-outline min-h-11 px-3 text-sm dark:border-primary-300 dark:text-primary-300 dark:hover:bg-slate-700 dark:focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-60">Unduh PNG</button>
                            @endif
                        </div>
                        <div class="relative flex min-h-[320px] items-center justify-center">
                            <canvas id="monthlyRequestsChart" class="hidden" role="img" aria-label="Grafik permintaan masuk dan selesai per bulan" aria-describedby="monthlyRequestsChartSummary"></canvas>
                            <p id="monthlyRequestsChartSummary" class="sr-only"></p>
                            <p data-chart-loading="monthlyRequests" role="status" aria-live="polite" class="text-sm semantic-text-muted">Memuat tren permintaan bulanan.</p>
                            <p data-chart-empty="monthlyRequests" role="status" aria-live="polite" class="hidden text-center text-sm semantic-text-muted">Belum ada permintaan pada periode grafik.</p>
                            <div data-chart-error="monthlyRequests" role="alert" aria-live="assertive" class="inventory-status-danger hidden rounded-md p-3 text-sm"><span data-chart-error-text>Grafik permintaan bulanan gagal dimuat.</span> <button type="button" onclick="refreshCharts()" class="ml-2 min-h-11 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 dark:focus-visible:ring-red-300">Coba lagi</button></div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sem semantic-surface p-5 xl:col-span-2" aria-labelledby="monthly-samples-title">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 id="monthly-samples-title" class="text-lg font-semibold semantic-text">Sampel dibanding target IKU</h3>
                                <p id="currentProgress" role="status" aria-live="polite" class="mt-1 text-sm semantic-text-muted">Memuat progres sampel.</p>
                            </div>
                            @if($canExportStatistics)
                                <button type="button" data-chart-export="monthlySamples" disabled onclick="exportChart('monthly_samples')" aria-label="Unduh grafik sampel bulanan dalam format PNG" class="btn btn-outline min-h-11 px-3 text-sm dark:border-primary-300 dark:text-primary-300 dark:hover:bg-slate-700 dark:focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-60">Unduh PNG</button>
                            @endif
                        </div>
                        <div class="relative flex min-h-[320px] items-center justify-center">
                            <canvas id="monthlySamplesChart" class="hidden" role="img" aria-label="Grafik jumlah sampel per bulan dibanding target" aria-describedby="monthlySamplesChartSummary"></canvas>
                            <p id="monthlySamplesChartSummary" class="sr-only"></p>
                            <p data-chart-loading="monthlySamples" role="status" aria-live="polite" class="text-sm semantic-text-muted">Memuat tren sampel bulanan.</p>
                            <p data-chart-empty="monthlySamples" role="status" aria-live="polite" class="hidden text-center text-sm semantic-text-muted">Belum ada sampel tercatat pada periode grafik.</p>
                            <div data-chart-error="monthlySamples" role="alert" aria-live="assertive" class="inventory-status-danger hidden rounded-md p-3 text-sm"><span data-chart-error-text>Grafik sampel bulanan gagal dimuat.</span> <button type="button" onclick="refreshCharts()" class="ml-2 min-h-11 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 dark:focus-visible:ring-red-300">Coba lagi</button></div>
                        </div>
                    </section>
                </div>

                <!-- Data Summary Table -->
                @php
                    $monthlyRequestsTarget = (int) config('statistics.targets.monthly_requests', 32);
                    $annualSamplesTarget = (int) config('statistics.targets.annual_samples', 200);
                    $requestStatus = $requests_this_month === 0
                        ? 'Belum ada permintaan'
                        : ($requests_this_month >= $monthlyRequestsTarget ? 'Target tercapai' : 'Di bawah acuan');
                    $sampleStatus = $samples_this_year === 0
                        ? 'Belum ada sampel'
                        : ($samples_this_year >= $annualSamplesTarget ? 'Target tercapai' : 'Di bawah acuan');
                @endphp
                <section class="rounded-lg border border-sem semantic-surface p-5" aria-labelledby="statistics-summary-title">
                        <h3 id="statistics-summary-title" class="mb-4 text-lg font-semibold semantic-text">Ringkasan data</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y semantic-border semantic-text">
                                <caption class="sr-only">Ringkasan permintaan pengujian dan sampel, bulan dan tahun berjalan</caption>
                                <thead class="semantic-bg text-left text-xs font-semibold uppercase tracking-wide semantic-text-muted">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Kategori</th>
                                        <th scope="col" class="px-4 py-3 text-right">Bulan ini</th>
                                        <th scope="col" class="px-4 py-3 text-right">Tahun ini</th>
                                        <th scope="col" class="px-4 py-3">Acuan</th>
                                        <th scope="col" class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y semantic-border">
                                    <tr>
                                        <th scope="row" class="px-4 py-3 text-left text-sm font-medium">Permintaan pengujian</th>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $requests_this_month }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $requests_this_year }}</td>
                                        <td class="px-4 py-3">{{ $monthlyRequestsTarget }} per bulan</td>
                                        <td class="px-4 py-3">
                                            <span class="{{ $requests_this_month >= $monthlyRequestsTarget && $requests_this_month > 0 ? 'inventory-status-success' : ($requests_this_month === 0 ? 'inventory-status-neutral' : 'inventory-status-warning') }} inline-flex rounded px-2 py-1 text-xs font-semibold">
                                                {{ $requestStatus }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="px-4 py-3 text-left text-sm font-medium">Sampel</th>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $samples_this_month }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $samples_this_year }}</td>
                                        <td class="px-4 py-3">{{ $annualSamplesTarget }} per tahun, IKU</td>
                                        <td class="px-4 py-3">
                                            <span class="{{ $samples_this_year >= $annualSamplesTarget && $samples_this_year > 0 ? 'inventory-status-success' : ($samples_this_year === 0 ? 'inventory-status-neutral' : 'inventory-status-warning') }} inline-flex rounded px-2 py-1 text-xs font-semibold">
                                                {{ $sampleStatus }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                </section>
            </div>
        </div>

        <!-- Consolidated Reports Tab Content -->
        @if($canExportStatistics)
        <div id="statistics-reports-panel" x-show="activeTab === 'reports'" role="region" aria-label="Laporan periodik" style="display: none;">
            @include('statistics.partials.consolidated-form')
        </div>
        @endif

    </div>

    @push('scripts')
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Chart.js Datalabels Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <script>
        const charts = {};
        let chartsLoaded = false;
        const chartCanvasIds = {
            userOrigin: 'userOriginChart',
            activeSubstances: 'activeSubstancesChart',
            suspectGender: 'suspectGenderChart',
            suspectAge: 'suspectAgeChart',
            monthlyRequests: 'monthlyRequestsChart',
            monthlySamples: 'monthlySamplesChart',
        };

        function statisticsChartColors() {
            const dark = document.documentElement.classList.contains('dark')
                || document.documentElement.dataset.theme === 'dark';

            return {
                text: dark ? '#e2e8f0' : '#334155',
                grid: dark ? 'rgba(148, 163, 184, 0.2)' : 'rgba(100, 116, 139, 0.2)',
                surface: dark ? '#1e293b' : '#ffffff',
            };
        }

        function setChartState(chartKey, state, message = '') {
            const canvas = document.getElementById(chartCanvasIds[chartKey]);
            const exportButton = document.querySelector(`[data-chart-export="${chartKey}"]`);
            if (state !== 'ready' && charts[chartKey]) {
                charts[chartKey].destroy();
                delete charts[chartKey];
            }
            if (state !== 'ready') {
                const summary = document.getElementById(`${chartCanvasIds[chartKey]}Summary`);
                if (summary) summary.textContent = '';
            }
            canvas?.classList.toggle('hidden', state !== 'ready');

            ['loading', 'empty', 'error'].forEach((stateName) => {
                const element = document.querySelector(`[data-chart-${stateName}="${chartKey}"]`);
                if (!element) return;
                element.classList.toggle('hidden', state !== stateName);
                const messageElement = element.querySelector('[data-chart-error-text]');
                if (state === stateName && message && messageElement) messageElement.textContent = message;
            });

            if (exportButton) exportButton.disabled = state !== 'ready';
            if (state === 'ready') applyStatisticsChartTheme();
        }

        function setChartSummary(chartKey, summaryText) {
            const summary = document.getElementById(`${chartCanvasIds[chartKey]}Summary`);
            if (summary) summary.textContent = summaryText;
        }

        function applyStatisticsChartTheme() {
            if (typeof Chart === 'undefined') return;
            const theme = statisticsChartColors();
            Chart.defaults.color = theme.text;
            Chart.defaults.borderColor = theme.grid;
            Chart.defaults.responsive = true;
            Chart.defaults.maintainAspectRatio = false;
            Chart.defaults.plugins.legend.position = 'bottom';

            Object.values(charts).forEach((chart) => {
                const options = chart.config.options;
                options.color = theme.text;
                Object.values(options.scales || {}).forEach((scale) => {
                    scale.ticks = scale.ticks || {};
                    scale.grid = scale.grid || {};
                    scale.ticks.color = theme.text;
                    scale.grid.color = theme.grid;
                });
                options.plugins = options.plugins || {};
                options.plugins.legend = options.plugins.legend || {};
                options.plugins.legend.labels = options.plugins.legend.labels || {};
                options.plugins.legend.labels.color = theme.text;
                chart.data.datasets.forEach((dataset) => {
                    if (dataset.borderColor === '#ffffff' || dataset.borderColor === '#1e293b') {
                        dataset.borderColor = theme.surface;
                    }
                });
                chart.update('none');
            });
        }

        applyStatisticsChartTheme();
        window.addEventListener('theme:change', applyStatisticsChartTheme);

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'dashboard';

            if (tab === 'dashboard') {
                loadAllCharts();
            }
        });

        window.addEventListener('statistics:dashboard-selected', loadAllCharts);
        
        function loadAllCharts() {
            if (chartsLoaded) return;
            chartsLoaded = true;

            if (typeof Chart === 'undefined') {
                Object.keys(chartCanvasIds).forEach((chartKey) => setChartState(chartKey, 'error', 'Pustaka grafik gagal dimuat. Muat ulang halaman untuk mencoba kembali.'));

                return;
            }

            loadUserOriginChart();
            loadActiveSubstancesChart();
            loadSuspectGenderChart();
            loadSuspectAgeChart();
            loadMonthlyRequestsChart();
            loadMonthlySamplesChart();
        }

        // 1. User Origin Pie Chart
        function loadUserOriginChart() {
            setChartState('userOrigin', 'loading');

            fetch('{{ route("statistics.data") }}?type=user_origin')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0) {
                        setChartState('userOrigin', 'empty', 'Belum ada permintaan dengan data wilayah penyidik.');

                        return;
                    }
                    setChartSummary('userOrigin', `Permintaan menurut wilayah penyidik: ${data.labels.map((label, index) => `${label}, ${data.data[index]} permintaan`).join('; ')}.`);

                    const ctx = document.getElementById('userOriginChart').getContext('2d');

                    if (charts.userOrigin) {
                        charts.userOrigin.destroy();
                    }

                    charts.userOrigin = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: data.colors,
                                borderWidth: 2,
                                borderColor: statisticsChartColors().surface,
                                hoverBorderWidth: 4,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        boxWidth: 12,
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + ' (' + data.percentages[context.dataIndex] + '%)';
                                        }
                                    }
                                },
                                datalabels: {
                                    color: '#ffffff',
                                    font: {
                                        weight: 'bold',
                                        size: 12
                                    },
                                    formatter: function(value, context) {
                                        const percentage = data.percentages[context.dataIndex];
                                        return percentage > 3 ? percentage + '%' : '';
                                    }
                                }
                            }
                        },
                        plugins: typeof ChartDataLabels === 'undefined' ? [] : [ChartDataLabels]
                    });
                    setChartState('userOrigin', 'ready');
                })
                .catch(error => {
                    console.error('Error loading user origin chart:', error);

                    if (charts.userOrigin) {
                        charts.userOrigin.destroy();
                        delete charts.userOrigin;
                    }

                    setChartState('userOrigin', 'error', 'Grafik wilayah penyidik gagal dimuat. Coba muat ulang halaman.');
                });
        }

        // 2. Active Substances Doughnut Chart
        function loadActiveSubstancesChart() {
            setChartState('activeSubstances', 'loading');

            fetch('{{ route("statistics.data") }}?type=active_substances')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    updateActiveSubstancesText(data);
                    if (!data.labels || data.labels.length === 0) {
                        setChartState('activeSubstances', 'empty', 'Belum ada catatan zat aktif dari permintaan atau hasil pengujian.');

                        return;
                    }
                    setChartSummary('activeSubstances', `Zat aktif tercatat: ${data.labels.map((label, index) => `${label}, ${data.data[index]} catatan`).join('; ')}.`);

                    const ctx = document.getElementById('activeSubstancesChart').getContext('2d');

                    if (charts.activeSubstances) {
                        charts.activeSubstances.destroy();
                    }

                    charts.activeSubstances = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: data.colors,
                                borderWidth: 3,
                                borderColor: statisticsChartColors().surface,
                                hoverBorderWidth: 5,
                                hoverOffset: 15
                            }]
                        },
                        options: {
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + ' sampel (' + data.percentages[context.dataIndex] + '%)';
                                        }
                                    }
                                },
                                datalabels: {
                                    color: '#ffffff',
                                    font: {
                                        weight: 'bold',
                                        size: 14
                                    },
                                    formatter: function(value, context) {
                                        const percentage = data.percentages[context.dataIndex];
                                        return percentage + '%';
                                    }
                                }
                            }
                        },
                        plugins: typeof ChartDataLabels === 'undefined' ? [] : [ChartDataLabels]
                    });
                    setChartState('activeSubstances', 'ready');
                })
                .catch(error => {
                    console.error('Error loading active substances chart:', error);

                    if (charts.activeSubstances) {
                        charts.activeSubstances.destroy();
                        delete charts.activeSubstances;
                    }

                    // Update text to show no data
                    updateActiveSubstancesText(null);

                    setChartState('activeSubstances', 'error', 'Grafik zat aktif gagal dimuat. Coba muat ulang halaman.');
                });
        }

        // Helper function to update active substances text
        function updateActiveSubstancesText(data) {
            const textEl = document.getElementById('topActiveSubstancesText');
            const sourceEl = document.getElementById('activeSubstancesSource');

            if (!textEl) return;

            if (!data || !data.labels || data.labels.length === 0) {
                textEl.textContent = 'Belum ada catatan zat aktif dari permintaan atau hasil pengujian.';
                if (sourceEl) sourceEl.classList.add('hidden');
                return;
            }

            // Get top 3 substances
            const top3 = data.labels.slice(0, 3).join(', ');
            textEl.textContent = 'Zat aktif terbanyak: ' + top3;

            if (sourceEl) sourceEl.classList.remove('hidden');
        }

        // 3. Suspect Gender Pie Chart
        function loadSuspectGenderChart() {
            setChartState('suspectGender', 'loading');

            fetch('{{ route("statistics.data") }}?type=suspect_gender')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0) {
                        setChartState('suspectGender', 'empty', 'Belum ada data jenis kelamin pada permintaan.');

                        return;
                    }
                    setChartSummary('suspectGender', `Jenis kelamin tersangka: ${data.labels.map((label, index) => `${label}, ${data.data[index]} orang`).join('; ')}.`);

                    const ctx = document.getElementById('suspectGenderChart').getContext('2d');

                    if (charts.suspectGender) {
                        charts.suspectGender.destroy();
                    }

                    charts.suspectGender = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: data.colors,
                                borderWidth: 2,
                                borderColor: statisticsChartColors().surface,
                                hoverBorderWidth: 4,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 15,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + ' orang (' + data.percentages[context.dataIndex] + '%)';
                                        }
                                    }
                                },
                                datalabels: {
                                    color: '#ffffff',
                                    font: {
                                        weight: 'bold',
                                        size: 14
                                    },
                                    formatter: function(value, context) {
                                        const percentage = data.percentages[context.dataIndex];
                                        return percentage + '%';
                                    }
                                }
                            }
                        },
                        plugins: typeof ChartDataLabels === 'undefined' ? [] : [ChartDataLabels]
                    });
                    setChartState('suspectGender', 'ready');
                })
                .catch(error => {
                    console.error('Error loading suspect gender chart:', error);

                    if (charts.suspectGender) {
                        charts.suspectGender.destroy();
                        delete charts.suspectGender;
                    }

                    setChartState('suspectGender', 'error', 'Grafik jenis kelamin gagal dimuat. Coba muat ulang halaman.');
                });
        }

        // 4. Suspect Age Bar Chart
        function loadSuspectAgeChart() {
            setChartState('suspectAge', 'loading');

            fetch('{{ route("statistics.data") }}?type=suspect_age')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const hasData = data.data && data.data.some(v => v > 0);
                    if (!data.labels || data.labels.length === 0 || !hasData) {
                        setChartState('suspectAge', 'empty', 'Belum ada data umur tersangka pada permintaan.');

                        return;
                    }
                    setChartSummary('suspectAge', `Rentang umur tersangka: ${data.labels.map((label, index) => `${label} tahun, ${data.data[index]} orang`).join('; ')}.`);

                    const ctx = document.getElementById('suspectAgeChart').getContext('2d');

                    if (charts.suspectAge) {
                        charts.suspectAge.destroy();
                    }

                    charts.suspectAge = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Jumlah Tersangka',
                                data: data.data,
                                backgroundColor: data.backgroundColor,
                                borderColor: data.borderColor,
                                borderWidth: 2
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Jumlah Tersangka',
                                        font: {
                                            weight: 'bold'
                                        }
                                    },
                                    ticks: {
                                        precision: 0,
                                        callback: function(value) {
                                            return value + ' orang';
                                        }
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Rentang Umur (Tahun)',
                                        font: {
                                            weight: 'bold'
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.parsed.y + ' orang';
                                        }
                                    }
                                },
                                datalabels: {
                                    anchor: 'end',
                                    align: 'top',
                                    color: statisticsChartColors().text,
                                    font: {
                                        weight: 'bold',
                                        size: 11
                                    },
                                    formatter: function(value) {
                                        return value > 0 ? value : '';
                                    }
                                }
                            }
                        },
                        plugins: typeof ChartDataLabels === 'undefined' ? [] : [ChartDataLabels]
                    });
                    setChartState('suspectAge', 'ready');
                })
                .catch(error => {
                    console.error('Error loading suspect age chart:', error);

                    if (charts.suspectAge) {
                        charts.suspectAge.destroy();
                        delete charts.suspectAge;
                    }

                    setChartState('suspectAge', 'error', 'Grafik rentang umur gagal dimuat. Coba muat ulang halaman.');
                });
        }

        // 5. Monthly Requests Line Chart
        function loadMonthlyRequestsChart() {
            setChartState('monthlyRequests', 'loading');

            fetch('{{ route("statistics.data") }}?type=monthly_requests')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const hasRequests = (data.datasets || []).some(dataset => (dataset.data || []).some(value => Number(value) > 0));
                    if (!data.labels || data.labels.length === 0 || !hasRequests) {
                        setChartState('monthlyRequests', 'empty', 'Belum ada permintaan tercatat pada periode grafik.');

                        return;
                    }
                    setChartSummary('monthlyRequests', `Permintaan bulanan: ${data.labels.map((label, index) => `${label}, masuk ${data.datasets[0]?.data[index] ?? 0}, selesai ${data.datasets[1]?.data[index] ?? 0}`).join('; ')}.`);

                    const ctx = document.getElementById('monthlyRequestsChart').getContext('2d');

                    if (charts.monthlyRequests) {
                        charts.monthlyRequests.destroy();
                    }

                    charts.monthlyRequests = new Chart(ctx, {
                        type: 'line',
                        data: data,
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Jumlah Permintaan',
                                        font: {
                                            weight: 'bold'
                                        }
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value + ' permintaan';
                                        }
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Bulan',
                                        font: {
                                            weight: 'bold'
                                        }
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y + ' permintaan';
                                        }
                                    }
                                },
                                datalabels: {
                                    display: false
                                }
                            },
                            interaction: {
                                mode: 'nearest',
                                axis: 'x',
                                intersect: false
                            }
                        }
                    });
                    setChartState('monthlyRequests', 'ready');
                })
                .catch(error => {
                    console.error('Error loading monthly requests chart:', error);

                    if (charts.monthlyRequests) {
                        charts.monthlyRequests.destroy();
                        delete charts.monthlyRequests;
                    }

                    setChartState('monthlyRequests', 'error', 'Grafik permintaan bulanan gagal dimuat. Coba muat ulang halaman.');
                });
        }

        // 6. Monthly Samples Mixed Chart
        function loadMonthlySamplesChart() {
            setChartState('monthlySamples', 'loading');

            fetch('{{ route("statistics.data") }}?type=monthly_samples')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.datasets || data.datasets.length === 0) {
                        setChartState('monthlySamples', 'empty', 'Belum ada data sampel bulanan.');

                        return;
                    }

                    const totalActual = data.datasets[0].data.reduce((a, b) => a + b, 0);
                    const yearlyTarget = Number(data.targetInfo?.yearly_target || @js(config('statistics.targets.annual_samples')));
                    const monthlyTarget = Number(data.targetInfo?.monthly_average || (yearlyTarget / 12));
                    const progressPercentage = Math.round((totalActual / yearlyTarget) * 100);

                    const progressElement = document.getElementById('currentProgress');
                    if (progressElement) {
                        progressElement.textContent = `${totalActual}/${yearlyTarget} sampel (${progressPercentage}%) dari acuan tahunan.`;
                    }

                    if (totalActual === 0) {
                        setChartState('monthlySamples', 'empty', 'Belum ada sampel tercatat pada periode grafik.');

                        return;
                    }
                    setChartSummary('monthlySamples', `Jumlah sampel per bulan: ${data.labels.map((label, index) => `${label}, ${data.datasets[0].data[index] ?? 0} sampel, acuan rata-rata ${monthlyTarget}`).join('; ')}. Total ${totalActual} sampel dari acuan tahunan ${yearlyTarget}.`);

                    const ctx = document.getElementById('monthlySamplesChart').getContext('2d');
                    if (charts.monthlySamples) charts.monthlySamples.destroy();

                    charts.monthlySamples = new Chart(ctx, {
                        type: 'bar',
                        data: data,
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: Math.max(30, Math.ceil(Math.max(...data.datasets[0].data, monthlyTarget) * 1.1)),
                                    title: {
                                        display: true,
                                        text: 'Jumlah Sampel per Bulan',
                                        font: {
                                            weight: 'bold'
                                        }
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value + ' sampel';
                                        }
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Bulan',
                                        font: {
                                            weight: 'bold'
                                        }
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            label += context.parsed.y + ' sampel';

                                            if (context.datasetIndex === 0) {
                                                const difference = context.parsed.y - monthlyTarget;
                                                const status = difference >= 0 ?
                                                    `(+${difference.toFixed(1)} dari rata-rata)` :
                                                    `(${difference.toFixed(1)} dari rata-rata)`;
                                                label += ' ' + status;
                                            }

                                            return label;
                                        },
                                        afterBody: function(context) {
                                            if (context[0].datasetIndex === 0) {
                                                return `Total tahun ini: ${totalActual} sampel\nTarget IKU: ${yearlyTarget} sampel/tahun\nProgress: ${progressPercentage}%`;
                                            }
                                            return '';
                                        }
                                    }
                                },
                                datalabels: {
                                    display: false
                                }
                            }
                        }
                    });
                    setChartState('monthlySamples', 'ready');
                })
                .catch(error => {
                    console.error('Error loading monthly samples chart:', error);

                    if (charts.monthlySamples) {
                        charts.monthlySamples.destroy();
                        delete charts.monthlySamples;
                    }

                    setChartState('monthlySamples', 'error', 'Grafik sampel bulanan gagal dimuat. Coba muat ulang halaman.');

                    const progressElement = document.getElementById('currentProgress');
                    if (progressElement) {
                        progressElement.textContent = 'Progres sampel tidak dapat dimuat.';
                    }
                });
        }

        function exportChart(type) {
            const chartKeys = {
                user_origin: 'userOrigin',
                active_substances: 'activeSubstances',
                suspect_gender: 'suspectGender',
                suspect_age: 'suspectAge',
                monthly_requests: 'monthlyRequests',
                monthly_samples: 'monthlySamples',
            };
            const chart = charts[chartKeys[type]];
            if (!chart) return;

            const link = document.createElement('a');
            link.href = chart.toBase64Image('image/png', 1);
            link.download = `statistik-${type}-${new Date().toISOString().slice(0, 10)}.png`;
            link.click();
        }

        function refreshCharts() {
            // Only refresh charts if in dashboard view to save resources
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'dashboard';
            
            if (tab === 'dashboard') {
                console.warn('Refreshing all charts...');
                loadUserOriginChart();
                loadActiveSubstancesChart();
                loadSuspectGenderChart();
                loadSuspectAgeChart();
                loadMonthlyRequestsChart();
                loadMonthlySamplesChart();
            }
        }

        setInterval(refreshCharts, 300000);
        window.refreshCharts = refreshCharts;
    </script>
    @endpush
</x-app-layout>
