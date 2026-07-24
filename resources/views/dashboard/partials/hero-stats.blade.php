@props(['avgProcessing', 'customerSatisfaction'])

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Kecepatan Pengerjaan Card --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 p-6 shadow-xl min-h-[200px]">
        {{-- Background Pattern --}}
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <defs>
                    <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                        <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/>
                    </pattern>
                </defs>
                <rect width="100" height="100" fill="url(#grid)" />
            </svg>
        </div>
        
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-blue-100 font-medium">Rata-rata Kecepatan</span>
            </div>
            
            @if(isset($avgProcessing) && $avgProcessing['average'] !== null)
                <div class="mb-4">
                    <span class="text-5xl font-bold text-white tabular-nums">{{ $avgProcessing['average'] }}</span>
                    <span class="text-xl text-blue-100 ml-2">hari/permintaan</span>
                </div>
                <div class="pt-4 border-t border-white/20">
                    <div class="flex items-center gap-4 text-sm">
                        <div class="flex items-center gap-1.5 text-blue-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span>{{ $avgProcessing['count'] }} permintaan selesai</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-4">
                    <span class="text-3xl font-semibold text-blue-100">Belum ada data</span>
                </div>
                <p class="text-blue-200 text-sm">Data akan muncul setelah ada permintaan selesai bulan ini</p>
            @endif
        </div>
    </div>

    {{-- Kepuasan Pengguna Card --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-500 to-teal-600 p-6 shadow-xl min-h-[200px]">
        {{-- Background Pattern --}}
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                <defs>
                    <pattern id="circles" width="20" height="20" patternUnits="userSpaceOnUse">
                        <circle cx="10" cy="10" r="2" fill="white"/>
                    </pattern>
                </defs>
                <rect width="100" height="100" fill="url(#circles)" />
            </svg>
        </div>
        
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-green-100 font-medium">Kepuasan Pengguna</span>
            </div>
            
            @if(isset($customerSatisfaction) && $customerSatisfaction['total_responses'] > 0)
                <div class="mb-4">
                    <span class="text-5xl font-bold text-white tabular-nums">{{ $customerSatisfaction['score'] }}/4</span>
                    <span class="text-xl text-green-100 ml-2">({{ $customerSatisfaction['percentage'] }}%)</span>
                </div>
                <div class="pt-4 border-t border-white/20">
                    <div class="flex items-center gap-4 text-sm">
                        <div class="flex items-center gap-1.5 text-green-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>{{ $customerSatisfaction['total_responses'] }} responden</span>
                        </div>
                        @if($customerSatisfaction['trend_direction'] === 'new')
                            <div class="flex items-center gap-1 text-green-200">
                                <span>✨</span>
                                <span>Data bulan pertama</span>
                            </div>
                        @elseif($customerSatisfaction['trend_direction'] === 'up')
                            <div class="flex items-center gap-1 text-green-200">
                                <span>↑</span>
                                <span>{{ $customerSatisfaction['trend'] }} dari bulan lalu</span>
                            </div>
                        @elseif($customerSatisfaction['trend_direction'] === 'down')
                            <div class="flex items-center gap-1 text-red-200">
                                <span>↓</span>
                                <span>{{ $customerSatisfaction['trend'] }} dari bulan lalu</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1 text-green-200">
                                <span>→</span>
                                <span>Stabil</span>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="mb-4">
                    <span class="text-3xl font-semibold text-green-100">Belum ada data</span>
                </div>
                <p class="text-green-200 text-sm">Data akan muncul setelah ada survey selesai bulan ini</p>
            @endif
        </div>
    </div>
</div>
