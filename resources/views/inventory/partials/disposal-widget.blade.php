<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-900">🧪 Disposal Sampel</h3>
        <a href="{{ route('inventory.disposal.index') }}" class="text-xs text-primary-600 hover:text-primary-800 font-medium">Kelola &rarr;</a>
    </div>

    <div class="space-y-3">
        <!-- Selesai Uji -->
        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 border border-gray-200">
            <span class="text-xs font-medium text-gray-600">Selesai Uji</span>
            <span class="text-lg font-bold text-gray-900" data-testid="disposal-finished-count">
                {{ (int) ($finishedSamplesCount ?? 0) }}
            </span>
        </div>

        <!-- Siap Musnah -->
        <div class="flex items-center justify-between p-3 rounded-lg bg-amber-50 border border-amber-200">
            <span class="text-xs font-medium text-amber-800">Siap Musnah</span>
            <span class="text-lg font-bold text-amber-900" data-testid="disposal-eligible-count">
                {{ (int) ($eligibleSamplesCount ?? 0) }}
            </span>
        </div>

        <!-- Dimusnahkan -->
        <div class="flex items-center justify-between p-3 rounded-lg bg-emerald-50 border border-emerald-200">
            <span class="text-xs font-medium text-emerald-800">Bulan Ini</span>
            <span class="text-lg font-bold text-emerald-900" data-testid="disposal-disposed-month-count">
                {{ (int) ($disposedThisMonthCount ?? 0) }}
            </span>
        </div>
    </div>

    @if(($recentSampleDisposals ?? collect())->isNotEmpty())
        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Batch Terakhir</div>
            <div class="flex flex-wrap gap-2">
                @foreach($recentSampleDisposals as $d)
                    <a
                        href="{{ route('inventory.disposal.show', $d) }}"
                        class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 hover:text-gray-900 transition-colors"
                        title="{{ $d->executed_at ? $d->executed_at->format('d M Y') : 'Draft' }}"
                    >
                        <span class="font-mono font-semibold">{{ $d->batch_number }}</span>
                        <span class="text-gray-400 text-[10px]">•</span>
                        <span class="text-gray-500">{{ (int) ($d->samples_count ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>