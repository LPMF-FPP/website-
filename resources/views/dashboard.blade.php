<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between py-3 flex items-center justify-between">
            <div>
                <x-breadcrumbs :items="[]" class="small text-xs" navClass="mb-1" />
                <h1 class="h4 mb-0 fw-semibold text-primary-900">
                    {{ __('Dashboard') }}
                </h1>
            </div>
            <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm px-3 inline-flex items-center rounded border border-gray-300 py-1.5 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-700">
                Refresh
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Stats Cards (SSR) -->
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
                        <div class="text-3xl font-semibold text-primary-900">
                            {{ $stats[$c['key']] ?? 0 }}{{ $c['suffix'] ?? '' }}
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

            <!-- Recent Activities (SSR) -->
            <div class="card">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-primary-900">Aktivitas Terbaru</h3>
                    </div>
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
