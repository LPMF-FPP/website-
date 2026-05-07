<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Penyerahan Hasil Pengujian"
            :breadcrumbs="[]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="listFetcher('readyDeliveryList')" x-init="init()">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                @if(session('success'))
                    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Penyerahan Hasil Pengujian</h3>
                    <p class="text-gray-600">Ringkasan antrian penyerahan dan riwayat penyerahan.</p>
                </div>

                {{-- Hero Stats Cards --}}
                <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                    {{-- Siap Diserahkan --}}
                    <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-teal-500 to-teal-700 p-5 shadow-lg">
                        <div class="absolute inset-0 opacity-10">
                            <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                <defs>
                                    <pattern id="delivery_grid" width="10" height="10" patternUnits="userSpaceOnUse">
                                        <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5" />
                                    </pattern>
                                </defs>
                                <rect width="100" height="100" fill="url(#delivery_grid)" />
                            </svg>
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center gap-2 text-sm font-medium text-teal-100">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Siap Diserahkan
                            </div>
                            <div class="mt-2 text-3xl font-bold text-white tabular-nums">{{ $requests->count() }}</div>
                            <p class="mt-1 text-sm text-teal-200">permintaan menunggu</p>
                        </div>
                    </div>

                    {{-- Riwayat Penyerahan --}}
                    <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-green-500 to-emerald-700 p-5 shadow-lg">
                        <div class="absolute inset-0 opacity-10">
                            <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                <defs>
                                    <pattern id="delivery_circles" width="20" height="20" patternUnits="userSpaceOnUse">
                                        <circle cx="10" cy="10" r="2" fill="white" />
                                    </pattern>
                                </defs>
                                <rect width="100" height="100" fill="url(#delivery_circles)" />
                            </svg>
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center gap-2 text-sm font-medium text-green-100">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Riwayat Penyerahan
                            </div>
                            <div class="mt-2 text-3xl font-bold text-white tabular-nums">
                                {{ method_exists($completedRequests, 'total') ? $completedRequests->total() : $completedRequests->count() }}
                            </div>
                            <p class="mt-1 text-sm text-green-200">permintaan selesai</p>
                        </div>
                    </div>

                    {{-- Total Sampel Pending --}}
                    <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-indigo-700 p-5 shadow-lg">
                        <div class="absolute inset-0 opacity-10">
                            <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                <defs>
                                    <pattern id="delivery_dots" width="8" height="8" patternUnits="userSpaceOnUse">
                                        <circle cx="4" cy="4" r="1" fill="white" />
                                    </pattern>
                                </defs>
                                <rect width="100" height="100" fill="url(#delivery_dots)" />
                            </svg>
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center gap-2 text-sm font-medium text-blue-100">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Total Sampel Pending
                            </div>
                            @php
                                $totalSamples = $requests->sum(fn ($r) => $r->samples->count());
                            @endphp
                            <div class="mt-2 text-3xl font-bold text-white tabular-nums">{{ $totalSamples }}</div>
                            <p class="mt-1 text-sm text-blue-200">sampel siap diserahkan</p>
                        </div>
                    </div>
                </div>

                <div x-show="loading" class="mb-4">
                    <x-skeleton-table :columns="7" :rows="8" />
                </div>

                <div x-ref="readyDeliveryList">
                @if($requests->isNotEmpty())
                    <div x-show="!loading" class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <a href="{{ route('delivery.index', array_merge(request()->query(), [
                                            'request_sort' => 'receipt_number',
                                            'request_direction' => request('request_sort', 'completed_at') === 'receipt_number' && request('request_direction') === 'asc' ? 'desc' : 'asc',
                                        ])) }}" class="group inline-flex items-center">
                                            No. Resi
                                            @if(request('request_sort') === 'receipt_number')
                                                <span class="ml-2 flex-none rounded bg-gray-200 px-1 text-gray-900 group-hover:bg-gray-300">
                                                    {{ request('request_direction') === 'asc' ? '↑' : '↓' }}
                                                </span>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Penyidik</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tersangka</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Jumlah Sampel</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tanggal Selesai</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Waktu Pengerjaan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($requests as $request)
                                    <tr class="group cursor-pointer transition-all duration-200 hover:bg-teal-50/50 hover:shadow-sm"
                                        role="link"
                                        tabindex="0"
                                        x-on:click="window.location = '{{ route('delivery.show', $request) }}'"
                                        x-on:keydown.enter="window.location = '{{ route('delivery.show', $request) }}'">
                                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                            {{ $request->receipt_number ?? $request->request_number }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ optional($request->investigator)->name ?? '-' }}
                                            @if($request->investigator)
                                                <div class="text-xs text-gray-500">{{ $request->investigator->rank }} &middot; {{ $request->investigator->jurisdiction }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $request->suspect_name ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            @php
                                                $totalSamplesForRequest = $request->samples->count();
                                                $completedSamplesForRequest = $request->samples->filter(function ($sample) {
                                                    $requiredStages = ['preparation', 'instrumentation', 'interpretation'];

                                                    $completedStages = $sample->testProcesses
                                                        ->filter(fn ($p) => $p->completed_at)
                                                        ->map(fn ($p) => is_object($p->stage) ? $p->stage->value : $p->stage)
                                                        ->filter(fn ($stage) => in_array($stage, $requiredStages, true))
                                                        ->unique()
                                                        ->count();

                                                    return $completedStages === 3;
                                                })->count();

                                                $isComplete = $totalSamplesForRequest > 0 && $completedSamplesForRequest === $totalSamplesForRequest;
                                            @endphp

                                            @if($isComplete)
                                                <div class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span>{{ $totalSamplesForRequest }} Sampel</span>
                                                </div>
                                            @else
                                                <div class="text-gray-500 font-medium">
                                                    {{ $completedSamplesForRequest }}/{{ $totalSamplesForRequest }} Sampel
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ optional($request->completed_at)->format('d/m/Y') ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            @if($request->processing_working_days !== null)
                                                <div class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                    {{ number_format($request->processing_working_days, 0, ',', '.') }} hari kerja
                                                </div>
                                                <div class="mt-1 text-xs text-gray-500">Sejak permintaan masuk</div>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <a href="{{ route('delivery.show', $request) }}"
                                                   class="group inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-teal-200 bg-teal-50 px-3 py-1.5 text-sm font-medium text-teal-700 transition-all duration-200 hover:border-teal-300 hover:bg-teal-100 hover:shadow-md hover:-translate-y-0.5">
                                                    <svg class="size-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    Lihat Detail
                                                    <svg class="size-3 -translate-x-2 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-16 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-100 to-cyan-100 shadow-inner">
                            <span class="text-3xl" aria-hidden="true">📦</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Semua Beres!</h3>
                        <p class="mt-1 mx-auto max-w-sm text-sm text-gray-500">Tidak ada permintaan yang menunggu penyerahan. Permintaan akan muncul di sini setelah proses pengujian selesai.</p>
                    </div>
                @endif
                </div>
            </div>
        </div>

        <!-- Permintaan Sudah Diserahkan -->
        <div class="mt-8 bg-white shadow-sm sm:rounded-lg" x-data="{ ...deliveryList(), responseMode: 'fragment', requestHeaders: { 'X-Fragment': 'delivery-history' } }" x-init="init()">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Riwayat Penyerahan</h3>
                        <p class="text-gray-600">Daftar permintaan yang telah diserahkan kepada penyidik.</p>
                    </div>
                    
                    <form action="{{ route('delivery.index') }}" method="GET" class="flex gap-2" @submit.prevent="handleFilterSubmit($event)">
                        @if(request('request_sort'))
                            <input type="hidden" name="request_sort" value="{{ request('request_sort') }}">
                        @endif
                        @if(request('request_direction'))
                            <input type="hidden" name="request_direction" value="{{ request('request_direction') }}">
                        @endif
                        <div class="relative rounded-md shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="text" name="search" value="{{ request('search') }}" 
                                class="block w-full rounded-md border-gray-300 pl-10 focus:border-teal-500 focus:ring-teal-500 sm:text-sm" 
                                placeholder="Cari Resi / Penyidik...">
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                            Cari
                        </button>
                    </form>
                </div>

                <div x-show="loading" x-cloak class="mb-4">
                    <x-skeleton-table :columns="7" :rows="6" />
                </div>

                <div x-ref="listContainer">
                    @include('delivery.partials.history-table', ['completedRequests' => $completedRequests])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<!-- Uses centralized listFetcher from app.js -->
