<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-900">🧪 Disposal Sampel</h3>
        @if(auth()->user()?->hasPermission('inventori.view'))
            <a href="{{ route('inventory.disposal.index') }}" class="text-xs text-primary-600 hover:text-primary-800 font-medium">Kelola &rarr;</a>
        @endif
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <!-- Selesai Uji -->
        <div class="flex items-center p-4 rounded-lg bg-gray-50 border border-gray-200">
            <div class="p-3 rounded-full bg-white mr-4 text-gray-500">
                <!-- Icon: Beaker -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-600 uppercase">Selesai Uji</p>
                <p class="text-2xl font-bold text-gray-900" data-testid="disposal-finished-count">
                    {{ (int) ($finishedSamplesCount ?? 0) }}
                </p>
            </div>
        </div>

        <!-- Siap Musnah -->
        <div class="flex items-center p-4 rounded-lg bg-amber-50 border border-amber-200">
            <div class="p-3 rounded-full bg-white mr-4 text-amber-500">
                <!-- Icon: Trash/Warning -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-medium text-amber-800 uppercase">Siap Musnah</p>
                <p class="text-2xl font-bold text-amber-900" data-testid="disposal-eligible-count">
                    {{ (int) ($eligibleSamplesCount ?? 0) }}
                </p>
            </div>
        </div>

        <!-- Bulan Ini -->
        <div class="flex items-center p-4 rounded-lg bg-emerald-50 border border-emerald-200">
            <div class="p-3 rounded-full bg-white mr-4 text-emerald-500">
                <!-- Icon: Calendar/Check -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-medium text-emerald-800 uppercase">Bulan Ini</p>
                <p class="text-2xl font-bold text-emerald-900" data-testid="disposal-disposed-month-count">
                    {{ (int) ($disposedThisMonthCount ?? 0) }}
                </p>
            </div>
        </div>
    </div>

    <!-- Recent Batches -->
    @if(($recentSampleDisposals ?? collect())->isNotEmpty())
        <div class="pt-4 border-t border-gray-100">
            <div class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Batch Terakhir</div>
            <div class="flex flex-row overflow-x-auto gap-3 pb-2 scrollbar-hide">
                @foreach($recentSampleDisposals as $d)
                    @if(auth()->user()?->hasPermission('inventori.view'))
                        <a
                            href="{{ route('inventory.disposal.show', $d) }}"
                            class="flex-shrink-0 inline-flex items-center gap-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-sm hover:bg-gray-100 transition-colors whitespace-nowrap"
                            title="{{ $d->executed_at ? $d->executed_at->format('d M Y') : 'Draft' }}"
                        >
                            <span class="font-mono font-semibold text-gray-900">{{ $d->batch_number }}</span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-600 text-xs">{{ (int) ($d->samples_count ?? 0) }} spl</span>
                        </a>
                    @else
                        <span
                            class="flex-shrink-0 inline-flex items-center gap-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-sm whitespace-nowrap"
                            title="{{ $d->executed_at ? $d->executed_at->format('d M Y') : 'Draft' }}"
                        >
                            <span class="font-mono font-semibold text-gray-900">{{ $d->batch_number }}</span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-600 text-xs">{{ (int) ($d->samples_count ?? 0) }} spl</span>
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
