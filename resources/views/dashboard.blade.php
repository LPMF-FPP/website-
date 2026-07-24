<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between py-3 flex items-center justify-between">
            <div>
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

            {{-- Environment Monitoring Alert --}}
            @if(isset($environment_monitoring) && $environment_monitoring['enabled'] && $environment_monitoring['is_work_day'] && $environment_monitoring['due_locations']->isNotEmpty())
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div class="flex-1">
                        <h2 class="text-sm font-medium text-yellow-800">Monitoring Lingkungan - Input Diperlukan</h2>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p class="mb-2">
                                @if($environment_monitoring['active_window'])
                                    Window aktif: <strong>{{ $environment_monitoring['active_window']['label'] }}</strong> 
                                    ({{ $environment_monitoring['active_window']['start'] }} - {{ $environment_monitoring['active_window']['end'] }})
                                @else
                                    Mohon segera input data monitoring lingkungan.
                                @endif
                            </p>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($environment_monitoring['due_locations']->take(5) as $item)
                                    <li>
                                        <span class="font-medium">{{ $item['location']->name }}</span>
                                        @if($item['status'] === 'overdue')
                                            <span class="text-red-600">(Terlambat)</span>
                                        @else
                                            <span class="text-yellow-600">(Perlu Input)</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if($environment_monitoring['due_locations']->count() > 5)
                                <p class="mt-1 text-yellow-600">...dan {{ $environment_monitoring['due_locations']->count() - 5 }} lokasi lainnya</p>
                            @endif
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('monitoring.environment.index') }}" 
                               class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-yellow-800 bg-yellow-100 border border-yellow-300 rounded-lg hover:bg-yellow-200">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                Input Monitoring Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- 🎯 HERO SECTION (PALING ATAS) --}}
            @include('dashboard.partials.hero-stats', [
                'avgProcessing' => $avg_processing,
                'customerSatisfaction' => $customer_satisfaction
            ])

            {{-- Buku Tamu Widget --}}
            @if(auth()->user()->can('guest-book.view'))
                @include('dashboard.partials.guest-book-widget', [
                    'guestBookToday' => $guest_book_today ?? ['total' => 0, 'active' => 0, 'checked_out' => 0, 'latest' => collect([])]
                ])
            @endif

            {{-- Stats Cards (SSR) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @php $cards = [
                    ['label' => 'Total Permintaan', 'key' => 'total_requests'],
                    ['label' => 'Sampel Pending', 'key' => 'pending_samples'],
                    ['label' => 'Pengujian Selesai', 'key' => 'completed_tests'],
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

                {{-- IKU Performance Card --}}
                <div class="card bg-gradient-to-br from-blue-50 to-indigo-50 border-blue-100">
                    <div class="space-y-1">
                        <div class="flex items-baseline gap-2">
                            <div class="text-3xl font-semibold text-blue-700">
                                {{ number_format($stats['iku_value'] ?? 0, 2) }}
                            </div>
                            <div class="text-lg font-medium text-blue-500">
                                ({{ $stats['iku_category'] ?? '-' }})
                            </div>
                        </div>
                        <div class="text-sm font-medium text-blue-600">IKU Performance</div>
                        <div class="text-xs text-blue-400">
                            {{ $iku_data['quarter_label'] ?? ($iku_data['period']['start'] ?? 'Periode ini') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status Breakdown Bar --}}
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
                        <h2 class="text-lg font-semibold text-primary-900">Status Permintaan</h2>
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

            {{-- 📊 Rekapitulasi Disposisi Table --}}
            @include('dashboard.partials.disposisi-table', ['data' => $disposisi_table])

            {{-- Recent Activities (Collapsible) --}}
            <div class="card" x-data="{ open: false }">
                <button 
                    @click="open = !open" 
                    class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 transition-colors rounded-t-lg"
                    :class="{ 'border-b border-gray-200': open }"
                >
                    <div class="flex items-center gap-3">
                        <svg 
                            class="w-5 h-5 text-gray-500 transition-transform duration-200" 
                            :class="{ 'rotate-90': open }"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <h2 class="text-lg font-semibold text-primary-900">
                            Aktivitas Terbaru
                            <span class="text-sm font-normal text-gray-500">({{ $recent_activities->count() }} data)</span>
                        </h2>
                    </div>
                    <span class="text-sm text-gray-500" x-text="open ? 'Tutup' : 'Buka'"></span>
                </button>

                <div x-show="open" x-collapse x-cloak>
                    <div class="p-4 space-y-4">
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

        </div>
    </div>

</x-app-layout>
