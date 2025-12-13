<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <x-breadcrumbs :items="[]" />
                <h2 class="font-semibold text-xl text-primary-900 leading-tight">
                    {{ __('Dashboard') }}
                </h2>
            </div>
            <button type="button" class="inline-flex items-center rounded border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-700"
                x-data="{ refresh: () => document.getElementById('dashboard-root')?.dispatchEvent(new CustomEvent('refresh-stats')) }"
                @click="refresh()">
                Refresh
            </button>
        </div>
    </x-slot>

    <div id="dashboard-root" class="py-12"
         x-data="{
             stats: @js($stats),
             loading: false,
             error: '',
             timer: null,
             refresh() {
                 this.loading = true;
                 fetch('{{ route('dashboard.stats') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                   .then(r => r.json())
                   .then(d => { this.stats = d; this.error = ''; })
                   .catch(() => { this.error = 'Gagal memuat data dashboard'; })
                   .finally(() => { this.loading = false; });
             }
         }"
         x-init="refresh(); timer = setInterval(() => refresh(), 60000)"
         @refresh-stats.window="refresh()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Error Alert -->
            <div x-show="error"
                 x-transition
                 class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <span class="text-red-600">⚠️</span>
                <span x-text="error"></span>
            </div>

            <!-- Stats Cards with skeletons -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @php $cards = [
                    ['label' => 'Total Permintaan', 'key' => 'total_requests'],
                    ['label' => 'Sampel Pending', 'key' => 'pending_samples'],
                    ['label' => 'Pengujian Selesai', 'key' => 'completed_tests'],
                    ['label' => 'SLA Performance', 'key' => 'sla_performance', 'suffix' => '%'],
                ]; @endphp
                @foreach($cards as $c)
                <div class="card">
                    <div class="space-y-1">
                        <div class="text-3xl font-semibold text-primary-900 relative min-h-[1.75rem]">
                            <!-- skeleton bar -->
                            <div x-show="loading" class="absolute inset-0 animate-pulse bg-gray-100 rounded"></div>
                            <template x-if="!loading">
                                <span>
                                    <span x-text="stats['{{ $c['key'] }}']">{{ $stats[$c['key']] ?? 0 }}</span>{{ $c['suffix'] ?? '' }}
                                </span>
                            </template>
                        </div>
                        <div class="text-sm font-medium text-accent-600">{{ $c['label'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Tiny Status Breakdown Bar -->
            @php
                $breakdown = $status_breakdown ?? [];
                $total = array_sum($breakdown);
                $colors = [
                    'submitted' => '#93c5fd', // blue-300
                    'in_testing' => '#fcd34d', // yellow-300
                    'analysis' => '#fdba74', // orange-300
                    'ready_for_delivery' => '#2dd4bf', // teal-400
                    'completed' => '#86efac', // green-300
                ];
            @endphp
            <div class="card">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-primary-900">Status Permintaan</h3>
                        <div class="text-xs text-accent-600">Total: {{ $total }}</div>
                    </div>
                    <div class="h-3 w-full rounded bg-gray-100 overflow-hidden flex">
                        @foreach($breakdown as $key => $val)
                            @php $pct = $total > 0 ? round(($val / $total) * 100, 2) : 0; @endphp
                            <div title="{{ $key }}: {{ $val }} ({{ $pct }}%)"
                                 style="width: {{ $pct }}%; background: {{ $colors[$key] ?? '#e5e7eb' }}"></div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs text-accent-700">
                        @foreach($breakdown as $key => $val)
                            <div class="inline-flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded" style="background: {{ $colors[$key] ?? '#e5e7eb' }}"></span>
                                <span class="capitalize">{{ str_replace('_',' ', $key) }}</span>
                                <span class="text-accent-500">— {{ $val }}</span>
                            </div>
                        @endforeach
                        @if(empty($breakdown))
                            <div class="text-accent-500">Belum ada data status.</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Recent Activities with skeletons -->
            <div class="card">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-primary-900">Aktivitas Terbaru</h3>
                    </div>

                    <div x-show="loading" class="space-y-4">
                        <template x-for="i in 3" :key="i">
                            <div class="h-10 bg-gray-100 rounded animate-pulse"></div>
                        </template>
                    </div>

                    <div x-show="!loading">
                        @if($recent_activities->count() > 0)
                            <div class="flow-root">
                                <ul class="-mb-8">
                                    @foreach($recent_activities as $index => $activity)
                                    <li>
                                        <div class="relative pb-8">
                                            @if($index < $recent_activities->count() - 1)
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-primary-100"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full bg-{{ $activity->color }}-500 flex items-center justify-center ring-2 ring-white">
                                                        <span class="text-white text-sm">{{ $activity->icon }}</span>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                    <div>
                                                        <p class="text-sm font-medium text-primary-900">{{ $activity->title }}</p>
                                                        <p class="text-sm text-accent-600">{{ $activity->description }}</p>
                                                    </div>
                                                    <div class="text-right text-sm whitespace-nowrap text-accent-600">
                                                        {{ $activity->time->diffForHumans() }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <p class="text-accent-600">Belum ada aktivitas</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Actions (unchanged other than labels earlier) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="{{ route('requests.create') }}" class="card block text-center hover:shadow-md transition-shadow duration-200">
                    <div class="space-y-2">
                        <div class="text-4xl mb-4">➕</div>
                        <h3 class="text-lg font-semibold text-primary-900">Buat Permintaan</h3>
                        <p class="text-sm text-accent-600">Submit permintaan pengujian baru</p>
                    </div>
                </a>

                <a href="{{ route('requests.index') }}" class="card block text-center hover:shadow-md transition-shadow duration-200">
                    <div class="space-y-2">
                        <div class="text-4xl mb-4">📄</div>
                        <h3 class="text-lg font-semibold text-primary-900">Lihat Permintaan</h3>
                        <p class="text-sm text-accent-600">Monitor status pengujian</p>
                    </div>
                </a>

                <a href="{{ route('tracking.index') }}" class="card block text-center hover:shadow-md transition-shadow duration-200">
                    <div class="space-y-2">
                        <div class="text-4xl mb-4">🔍</div>
                        <h3 class="text-lg font-semibold text-primary-900">Tracking Permintaan</h3>
                        <p class="text-sm text-accent-600">Lacak status permintaan</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

</x-app-layout>
