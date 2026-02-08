<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
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
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-all"
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
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-all"
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
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-all"
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
    <div class="p-0">
        <!-- Low Stock Tab -->
        <div x-show="tab === 'low_stock'" x-transition.opacity>
            @if($lowStockItems->isEmpty())
                <div class="p-8 text-center text-gray-500">
                    <div class="text-2xl mb-2">✅</div>
                    Stok aman. Tidak ada item di bawah minimum stok.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Min</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($lowStockItems as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $item->name }}</div>
                                    @if($item->brand)
                                        <div class="text-xs text-gray-500">{{ $item->brand }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono text-red-600 font-bold">
                                    {{ number_format($item->total_on_hand, 2) }} {{ $item->uom }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono text-gray-600">
                                    {{ number_format($item->min_stock, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('inventory.transaction.receipt', ['item_id' => $item->id]) }}" class="text-primary-600 hover:text-primary-900">Restock</a>
                                        <span class="text-gray-300">|</span>
                                        <a href="{{ route('inventory.transaction.transfer', ['item_id' => $item->id]) }}" class="text-gray-600 hover:text-gray-900">Transfer</a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Near Expiry Tab -->
        <div x-show="tab === 'near_expiry'" x-transition.opacity style="display: none;">
            @if($nearExpiry30->isEmpty())
                <div class="p-8 text-center text-gray-500">
                    <div class="text-2xl mb-2">✅</div>
                    Tidak ada lot yang akan kadaluarsa dalam 30 hari.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / Lot</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expired</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa Hari</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($nearExpiry30 as $lot)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $lot->item->name }}</div>
                                    <div class="text-xs text-gray-500">Lot: {{ $lot->lot_no }}</div>
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

        <!-- Expired Tab -->
        <div x-show="tab === 'expired'" x-transition.opacity style="display: none;">
            @if($expiredLots->isEmpty())
                <div class="p-8 text-center text-gray-500">
                    <div class="text-2xl mb-2">✅</div>
                    Bersih! Tidak ada stok kadaluarsa yang belum dibuang.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / Lot</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Exp</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($expiredLots as $lot)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $lot->item->name }}</div>
                                    <div class="text-xs text-gray-500">Lot: {{ $lot->lot_no }}</div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-red-600 font-medium">
                                    {{ $lot->expiry_date->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono text-gray-600">
                                    {{ number_format($lot->balances->sum('on_hand_qty'), 2) }} {{ $lot->item->uom }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium">
                                    <a href="{{ route('inventory.transaction.dispose', ['item_id' => $lot->item_id, 'lot_id' => $lot->id]) }}" class="text-red-600 hover:text-red-900">
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