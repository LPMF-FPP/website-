<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="{ tab: 'low_stock' }">
    <!-- Header with Tabs -->
    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h3 class="text-base font-semibold text-gray-900">
                ⚠️ Perhatian Diperlukan
            </h3>
            <div class="flex space-x-1 rounded-lg bg-gray-200 p-1">
                <button
                    @click="tab = 'low_stock'"
                    :class="{ 'bg-white shadow text-gray-900': tab === 'low_stock', 'text-gray-600 hover:text-gray-900': tab !== 'low_stock' }"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-all flex items-center"
                >
                    Stok Rendah
                    @if($lowStockItems->isNotEmpty())
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
                            {{ $lowStockItems->count() }}
                        </span>
                    @endif
                </button>
                <button
                    @click="tab = 'near_expiry'"
                    :class="{ 'bg-white shadow text-gray-900': tab === 'near_expiry', 'text-gray-600 hover:text-gray-900': tab !== 'near_expiry' }"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-all flex items-center"
                >
                    Hampir Expired
                    @if($nearExpiry30->isNotEmpty())
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800">
                            {{ $nearExpiry30->count() }}
                        </span>
                    @endif
                </button>
                <button
                    @click="tab = 'expired'"
                    :class="{ 'bg-white shadow text-gray-900': tab === 'expired', 'text-gray-600 hover:text-gray-900': tab !== 'expired' }"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-all flex items-center"
                >
                    Expired
                    @if($expiredLots->isNotEmpty())
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
                            {{ $expiredLots->count() }}
                        </span>
                    @endif
                </button>
            </div>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="p-4">
        <!-- Low Stock Tab (List Card Layout) -->
        <div x-show="tab === 'low_stock'" x-transition.opacity>
            @if($lowStockItems->isEmpty())
                <div class="py-8 text-center text-gray-500">
                    <div class="text-4xl mb-2">✅</div>
                    <p class="font-medium text-gray-900">Stok Aman</p>
                    <p class="text-sm">Tidak ada item di bawah minimum stok.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($lowStockItems as $item)
                        <div class="flex flex-col p-4 bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                            <!-- Header: Item & Action -->
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="text-sm font-bold text-gray-900">
                                        <a href="{{ route('inventory.items.edit', $item) }}" class="hover:text-primary-600 hover:underline">
                                            {{ $item->name }}
                                        </a>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        Min: <span class="font-mono">{{ number_format($item->min_stock, 0) }}</span> {{ $item->uom }}
                                    </div>
                                </div>
                                <a href="{{ route('inventory.transaction.receipt', ['item_id' => $item->id]) }}" 
                                   class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-emerald-700 bg-emerald-100 hover:bg-emerald-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors">
                                    Restock
                                </a>
                            </div>

                            <!-- Visual Health Bar -->
                            @php
                                $percent = $item->min_stock > 0 ? min(($item->total_on_hand / $item->min_stock) * 100, 100) : 0;
                                $barColor = $percent < 25 ? 'bg-red-600' : ($percent < 50 ? 'bg-orange-500' : 'bg-yellow-400');
                            @endphp
                            <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden mb-2">
                                <div class="h-2.5 rounded-full {{ $barColor }} transition-all duration-500" 
                                     style="width: {{ $percent }}%"></div>
                            </div>

                            <!-- Footer: Stock & Trend -->
                            <div class="flex justify-between items-center text-xs">
                                <div class="font-mono font-bold {{ $item->total_on_hand <= 0 ? 'text-red-600' : 'text-gray-900' }}">
                                    Sisa {{ number_format($item->total_on_hand, 2) }} {{ $item->uom }}
                                    <span class="text-gray-400 font-normal">({{ number_format($percent, 0) }}%)</span>
                                </div>
                                
                                <!-- Usage Trend (Requires Backend Support, fallback if null) -->
                                <div class="flex items-center gap-1 text-gray-600" title="Pemakaian 30 hari terakhir">
                                    @if(isset($item->trend))
                                        @if($item->trend == 'high')
                                            <span class="text-red-600 font-bold">📉 High Usage</span>
                                        @elseif($item->trend == 'moderate')
                                            <span class="text-orange-600">➖ Moderate</span>
                                        @else
                                            <span class="text-gray-400">💤 Low Usage</span>
                                        @endif
                                        <span class="text-gray-400">({{ number_format($item->monthly_usage ?? 0, 0) }}/bln)</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Near Expiry Tab (Table Layout) -->
        <div x-show="tab === 'near_expiry'" x-transition.opacity style="display: none;">
            @if($nearExpiry30->isEmpty())
                <div class="py-8 text-center text-gray-500">
                    <div class="text-4xl mb-2">✅</div>
                    <p class="font-medium text-gray-900">Aman</p>
                    <p class="text-sm">Tidak ada lot yang akan kadaluarsa dalam 30 hari.</p>
                </div>
            @else
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / Lot</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expired</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($nearExpiry30 as $lot)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $lot->item->name }}</div>
                                    <div class="text-xs text-gray-500 font-mono">{{ $lot->lot_no }}</div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">
                                    {{ $lot->expiry_date->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $lot->days_until_expiry <= 7 ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' }}">
                                        {{ $lot->days_until_expiry }} hari
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Expired Tab (Table Layout) -->
        <div x-show="tab === 'expired'" x-transition.opacity style="display: none;">
            @if($expiredLots->isEmpty())
                <div class="py-8 text-center text-gray-500">
                    <div class="text-4xl mb-2">✅</div>
                    <p class="font-medium text-gray-900">Bersih</p>
                    <p class="text-sm">Tidak ada stok kadaluarsa yang belum dibuang.</p>
                </div>
            @else
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / Lot</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($expiredLots as $lot)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $lot->item->name }}</div>
                                    <div class="text-xs text-gray-500 font-mono">{{ $lot->lot_no }}</div>
                                    <div class="text-[10px] text-red-600 font-medium">Exp: {{ $lot->expiry_date->format('d M Y') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono text-gray-600">
                                    {{ number_format($lot->balances->sum('on_hand_qty'), 2) }} {{ $lot->item->uom }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium">
                                    <a href="{{ route('inventory.transaction.dispose', ['item_id' => $lot->item_id, 'lot_id' => $lot->id]) }}" class="text-red-600 hover:text-red-900 text-xs uppercase font-bold tracking-wide">
                                        Disposal
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
