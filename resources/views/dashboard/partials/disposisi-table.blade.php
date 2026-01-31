@props(['data'])

@php
    $data->appends(request()->query());
@endphp

<div id="disposisi" class="card" x-data="{ 
    open: {{ request()->has('page') || request()->filled('disposisi_search') ? 'true' : 'false' }}, 
    search: '{{ request('disposisi_search') }}' 
}">
    {{-- Collapsible Header --}}
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
                Rekapitulasi Disposisi
                <span class="text-sm font-normal text-gray-500">({{ $data->total() }} data)</span>
            </h2>
        </div>
        <span class="text-sm text-gray-500" x-text="open ? 'Tutup' : 'Buka'"></span>
    </button>

    {{-- Collapsible Content --}}
    <div x-show="open" x-collapse x-cloak>
        <div class="p-4 space-y-4">
            {{-- Search --}}
            <div class="flex items-center gap-3">
                <div class="relative flex-1 max-w-xs">
                    <input 
                        type="text" 
                        x-model="search"
                        @keydown.enter="window.location.href = '?disposisi_search=' + search"
                        placeholder="Cari TSK / No Sampel (Enter)..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                    >
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 w-12">No</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 min-w-[180px]">NAMA TSK</th>
                            <th class="px-3 py-3 text-left font-semibold text-gray-700 min-w-[120px]">No SAMPEL</th>
                            <th colspan="5" class="px-3 py-2 text-center font-semibold text-gray-700 bg-blue-50 border-l border-gray-200">
                                D I S P O S I S I
                            </th>
                        </tr>
                        <tr class="bg-gray-100">
                            <th colspan="3"></th>
                            <th class="px-2 py-2 text-center font-medium text-gray-600 text-xs border-l border-gray-200 w-24">MASUK</th>
                            <th class="px-2 py-2 text-center font-medium text-gray-600 text-xs w-24">URMIN</th>
                            <th class="px-2 py-2 text-center font-medium text-gray-600 text-xs w-24">HASIL</th>
                            <th class="px-2 py-2 text-center font-medium text-gray-600 text-xs w-20">SP</th>
                            <th class="px-2 py-2 text-center font-medium text-gray-600 text-xs w-24">AMBIL</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($data as $index => $row)
                        <tr 
                            class="hover:bg-gray-50 transition-colors duration-150
                                @if($row['status'] === 'stuck_14_days') bg-red-50 hover:bg-red-100 @endif
                                @if($row['status'] === 'stuck_7_days') bg-yellow-50 hover:bg-yellow-100 @endif
                                @if($row['status'] === 'completed') bg-green-50 hover:bg-green-100 @endif"
                            x-show="search === '' || @json(strtolower($row['nama_tsk'] . ' ' . $row['no_sampel'])).includes(search.toLowerCase())"
                        >
                            <td class="px-3 py-2.5 text-gray-600 text-center">{{ $data->firstItem() + $index }}</td>
                            <td class="px-3 py-2.5 font-medium text-gray-900">
                                <a href="{{ route('requests.show', $row['id']) }}" class="hover:text-primary-600 hover:underline">
                                    @if($row['status'] === 'stuck_14_days')
                                        <span class="text-red-500 mr-1">🔴</span>
                                    @elseif($row['status'] === 'stuck_7_days')
                                        <span class="text-yellow-500 mr-1">🟡</span>
                                    @elseif($row['status'] === 'completed')
                                        <span class="text-green-500 mr-1">🟢</span>
                                    @endif
                                    {{ $row['nama_tsk'] }}
                                </a>
                            </td>
                            <td class="px-3 py-2.5 text-gray-600 font-mono text-xs">{{ $row['no_sampel'] }}</td>
                            
                            {{-- MASUK --}}
                            <td class="px-2 py-2.5 text-center text-gray-600 border-l border-gray-100 tabular-nums text-xs">
                                {{ $row['masuk'] ? $row['masuk']->format('d-M-y') : '-' }}
                            </td>
                            
                            {{-- URMIN --}}
                            <td class="px-2 py-2.5 text-center tabular-nums text-xs">
                                @if($row['urmin'])
                                    <span class="text-gray-600">{{ $row['urmin']->format('d-M-y') }}</span>
                                @else
                                    <a href="{{ route('requests.show', $row['id']) }}" 
                                       class="inline-flex items-center text-amber-600 hover:text-amber-800 font-medium">
                                        <span class="mr-1">⚠️</span>
                                        <span class="underline">Input</span>
                                    </a>
                                @endif
                            </td>
                            
                            {{-- HASIL --}}
                            <td class="px-2 py-2.5 text-center text-gray-600 tabular-nums text-xs">
                                {{ $row['hasil'] ? $row['hasil']->format('d-M-y') : '-' }}
                            </td>
                            
                            {{-- SP --}}
                            <td class="px-2 py-2.5 text-center tabular-nums text-xs">
                                @if($row['sp'])
                                    <span class="text-gray-600">{{ $row['sp']->format('d-M') }}</span>
                                @elseif($row['has_delivery'])
                                    <a href="{{ route('delivery.show', $row['id']) }}" 
                                       class="inline-flex items-center text-amber-600 hover:text-amber-800">
                                        <span>⚠️</span>
                                    </a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            
                            {{-- AMBIL --}}
                            <td class="px-2 py-2.5 text-center tabular-nums text-xs
                                @if($row['ambil']) text-green-600 font-medium @else text-gray-400 @endif">
                                {{ $row['ambil'] ? $row['ambil']->format('d-M-y') : '-' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span>Tidak ada data disposisi</span>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($data->hasPages())
            <div class="flex items-center justify-between pt-2">
                <div class="text-sm text-gray-500">
                    Menampilkan {{ $data->firstItem() }} - {{ $data->lastItem() }} dari {{ $data->total() }} data
                </div>
                <div class="flex items-center gap-2">
                    @if($data->onFirstPage())
                        <span class="px-3 py-1.5 text-sm text-gray-400 bg-gray-100 rounded cursor-not-allowed">◀ Prev</span>
                    @else
                        <a href="{{ $data->previousPageUrl() }}" class="px-3 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">◀ Prev</a>
                    @endif
                    
                    <span class="px-3 py-1.5 text-sm text-gray-600">
                        Hal {{ $data->currentPage() }} / {{ $data->lastPage() }}
                    </span>
                    
                    @if($data->hasMorePages())
                        <a href="{{ $data->nextPageUrl() }}" class="px-3 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Next ▶</a>
                    @else
                        <span class="px-3 py-1.5 text-sm text-gray-400 bg-gray-100 rounded cursor-not-allowed">Next ▶</span>
                    @endif
                </div>
            </div>
            @endif

            {{-- Legend --}}
            <div class="flex flex-wrap gap-4 pt-3 border-t border-gray-100 text-xs text-gray-600">
                <div class="flex items-center gap-1.5">
                    <span>🔴</span>
                    <span>Tidak ada perkembangan > 14 hari kerja</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span>🟡</span>
                    <span>Tidak ada perkembangan > 7 hari kerja</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span>🟢</span>
                    <span>Selesai & sudah diambil</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span>⚠️</span>
                    <span>Perlu input (klik untuk ke halaman terkait)</span>
                </div>
            </div>
        </div>
    </div>
</div>
