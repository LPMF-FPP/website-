<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Dashboard QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH'],
                ]"
            >
                <x-slot name="actions">
                    <a href="{{ route('quality.documents.create') }}" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                        + Buat Dokumen
                    </a>
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="overview" />
        </div>
    </x-slot>

    <div x-data="qmhDashboard()" x-init="init()">
        <div>
            <!-- Action Center Banner -->
            <div class="mb-8 rounded-xl bg-gradient-to-r from-primary-600 to-primary-800 p-6 text-white shadow-lg" x-show="userTasks > 0" x-transition x-cloak>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold">Halo, {{ auth()->user()->name }}!</h3>
                        <p class="mt-1 text-primary-100" aria-live="polite" aria-atomic="true">Ada <span class="font-bold" x-text="userTasks"></span> dokumen yang menunggu aksi Anda hari ini.</p>
                    </div>
                    <a href="{{ route('quality.documents.index', ['status' => 'in_review']) }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary-700">
                        Mulai Review
                    </a>
                </div>
            </div>

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('quality.index') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="sr-only" for="dashboard-filter-clause">Klausul</label>
                        <select id="dashboard-filter-clause" name="clause" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Semua Klausul</option>
                            @foreach([4, 5, 6, 7, 8] as $clause)
                                <option value="{{ $clause }}" @selected((string) request('clause') === (string) $clause)>Klausul {{ $clause }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="sr-only" for="dashboard-filter-doc-type">Jenis Dokumen</label>
                        <select id="dashboard-filter-doc-type" name="doc_type" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Semua Jenis</option>
                            <option value="sop" @selected(request('doc_type') === 'sop')>SOP</option>
                            <option value="ik" @selected(request('doc_type') === 'ik')>IK</option>
                            <option value="formulir" @selected(request('doc_type') === 'formulir')>Formulir</option>
                        </select>
                    </div>
                    <div>
                        <label class="sr-only" for="dashboard-filter-from">Dari Tanggal</label>
                        <input id="dashboard-filter-from" type="date" name="from" value="{{ request('from') }}" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    </div>
                    <div>
                        <label class="sr-only" for="dashboard-filter-to">Sampai Tanggal</label>
                        <input id="dashboard-filter-to" type="date" name="to" value="{{ request('to') }}" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Terapkan</button>
                        <a href="{{ route('quality.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Reset</a>
                    </div>
                </form>
            </div>

            <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Total Dokumen</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['total_documents'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Dokumen Terbit</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['published_documents'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Dokumen Dalam Tinjauan</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['in_review_documents'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Revisi Kedaluwarsa</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['obsolete_revisions'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Unduhan Terkendali</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['controlled_downloads'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Unduhan Tidak Terkendali</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['uncontrolled_downloads'] ?? 0 }}</p>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main PDCA Cycle -->
                <div class="lg:col-span-2 space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Siklus Mutu (PDCA)</h3>
                    
                    <!-- PLAN -->
                    <div class="relative rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                        <div class="absolute -left-3 top-6 rounded-r-lg bg-primary-100 px-2 py-1 text-xs font-bold text-primary-900">PLAN</div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <template x-for="c in [4, 5, 6]">
                                <a :href="`{{ route('quality.documents.index') }}?clause=${c}`" class="group flex flex-col justify-between rounded-xl border border-gray-100 bg-slate-50 p-4 hover:border-primary-300 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xl font-bold text-gray-400 group-hover:text-primary-700" x-text="`K${c}`"></span>
                                        <span class="h-2 w-2 rounded-full" :class="healthColor(stats[c]?.health)" aria-hidden="true"></span>
                                    </div>
                                    <div class="mt-2">
                                        <p class="text-sm font-medium text-gray-900" x-text="clauseLabel(c)"></p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            <span x-text="stats[c]?.total || 0"></span> Dokumen
                                        </p>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>

                    <!-- DO (Prominent) -->
                    <div class="relative rounded-2xl border-2 border-primary-100 bg-white p-6 shadow-md ring-4 ring-primary-50 transition hover:shadow-lg">
                        <div class="absolute -left-3 top-6 rounded-r-lg bg-primary-100 px-2 py-1 text-xs font-bold text-primary-900">DO</div>
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h4 class="text-2xl font-bold text-gray-900">Klausul 7</h4>
                                    <span class="rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-900">Operasional</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Persyaratan Proses (Review Kontrak s.d Pelaporan)</p>
                                
                                <div class="mt-4 flex gap-4 text-sm">
                                    <div class="flex items-center gap-1.5 text-amber-600" x-show="stats[7]?.review > 0" x-cloak>
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span class="font-medium" x-text="stats[7]?.review"></span> Review
                                    </div>
                                    <div class="flex items-center gap-1.5 text-red-600" x-show="stats[7]?.overdue > 0" x-cloak>
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        <span class="font-medium" x-text="stats[7]?.overdue"></span> Overdue
                                    </div>
                                    <div class="flex items-center gap-1.5 text-green-600">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span class="font-medium" x-text="stats[7]?.published"></span> Aktif
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('quality.documents.index', ['clause' => 7]) }}" class="flex items-center justify-center rounded-xl bg-primary-50 px-6 py-4 text-primary-800 transition hover:bg-primary-100 hover:text-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                <span class="text-sm font-semibold">Buka Workspace &rarr;</span>
                            </a>
                        </div>
                    </div>

                    <!-- CHECK & ACT -->
                    <div class="relative rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                        <div class="absolute -left-3 top-6 rounded-r-lg bg-green-100 px-2 py-1 text-xs font-bold text-green-800">CHECK</div>
                        <a href="{{ route('quality.documents.index', ['clause' => 8]) }}" class="group flex items-center justify-between pl-12 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 group-hover:text-green-700">Klausul 8: Sistem Manajemen</h4>
                                <p class="text-sm text-gray-500">Audit, Kaji Ulang, Risiko, & Improvement</p>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-gray-900" x-text="stats[8]?.total || 0"></div>
                                <div class="text-xs text-gray-500">Dokumen</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Right Panel: Audit Readiness -->
                <div class="space-y-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Audit Readiness</h3>
                        
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-1 flex h-8 w-8 items-center justify-center rounded-lg" :class="globalPulseIconClass()">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Pulse Sistem QMH</p>
                                    <p class="text-xs text-gray-500" x-text="globalPulseLabel()" aria-live="polite" aria-atomic="true"></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="mt-1 flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Tindakan Perbaikan</p>
                                    <p class="text-xs text-gray-500" x-text="`${totalOverdue()} dokumen overdue perlu ditindaklanjuti`" aria-live="polite" aria-atomic="true"></p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-gray-100 pt-4">
                            <a href="{{ route('quality.documents.index', ['status' => 'in_review']) }}" class="flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                Cek Status Lengkap
                            </a>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500">Pintas</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="{{ route('quality.documents.create') }}" class="group flex flex-col items-center rounded-lg bg-primary-600 p-3 text-center text-white transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                <svg class="h-6 w-6 text-primary-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <span class="mt-2 text-xs font-medium">Buat Dokumen</span>
                            </a>
                            <a href="{{ route('quality.documents.index') }}" class="group flex flex-col items-center rounded-lg bg-gray-50 p-3 text-center transition hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                <svg class="h-6 w-6 text-gray-400 group-hover:text-primary-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                <span class="mt-2 text-xs font-medium">Cari Dokumen</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('qmhDashboard', () => ({
                stats: {},
                globalPulse: 'healthy',
                userTasks: 0,
                loading: true,
                statsEndpoint: `${window.location.origin}/api/quality/dashboard/stats`,

                init() {
                    this.fetchStats();
                    setInterval(() => this.fetchStats(), 60000);
                },

                async fetchStats() {
                    try {
                        const res = await fetch(this.statsEndpoint, {
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                            },
                        });
                        if (res.ok) {
                            const data = await res.json();
                            this.stats = data.clauses || {};
                            this.globalPulse = data.global_pulse || 'healthy';
                            this.userTasks = data.user_tasks || 0;
                        }
                    } catch (e) {
                        console.error('Failed to fetch dashboard stats', e);
                    } finally {
                        this.loading = false;
                    }
                },

                healthColor(health) {
                    switch (health) {
                        case 'critical': return 'bg-red-500';
                        case 'warning': return 'bg-amber-500';
                        case 'active': return 'bg-primary-500';
                        default: return 'bg-green-500';
                    }
                },

                clauseLabel(c) {
                    const labels = {
                        4: 'Persyaratan Umum',
                        5: 'Persyaratan Struktural',
                        6: 'Sumber Daya',
                        7: 'Persyaratan Proses',
                        8: 'Sistem Manajemen'
                    };
                    return labels[c] || `Klausul ${c}`;
                },

                totalOverdue() {
                    return Object.values(this.stats).reduce((total, clause) => total + (clause?.overdue || 0), 0);
                },

                globalPulseIconClass() {
                    switch (this.globalPulse) {
                        case 'critical': return 'bg-red-100 text-red-600';
                        case 'warning': return 'bg-amber-100 text-amber-600';
                        case 'active': return 'bg-primary-100 text-primary-700';
                        default: return 'bg-green-100 text-green-600';
                    }
                },

                globalPulseLabel() {
                    switch (this.globalPulse) {
                        case 'critical': return 'Kondisi kritis: butuh tindakan prioritas.';
                        case 'warning': return 'Ada sinyal risiko: pantau dokumen overdue.';
                        case 'active': return 'Aktivitas tinggi: review beban kerja tim.';
                        default: return 'Kondisi sehat: lanjutkan pemantauan berkala.';
                    }
                }
            }));
        });
    </script>
    @endpush
</x-app-layout>
