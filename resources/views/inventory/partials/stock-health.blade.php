@if($lowStockItems->isNotEmpty())
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-900">🩺 Kesehatan Stok</h3>
        <span class="text-xs text-red-600 font-medium">{{ $lowStockItems->count() }} Item Kritis</span>
    </div>
    
    <div class="space-y-4">
        @foreach($lowStockItems->take(5) as $item)
            <x-bullet-graph
                :label="$item->name"
                :actual="$item->total_on_hand"
                :min="$item->min_stock"
                :uom="$item->uom"
                class="text-xs"
            />
        @endforeach
    </div>
    
    @if($lowStockItems->count() > 5)
        <div class="mt-3 text-center">
            <button @click="tab = 'low_stock'; $el.scrollIntoView({behavior: 'smooth'})" class="text-xs text-primary-600 hover:text-primary-800 font-medium">
                Lihat Semua &rarr;
            </button>
        </div>
    @endif
</div>
@endif