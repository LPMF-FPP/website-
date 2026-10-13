<div id="inventory-alerts" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden dark:bg-slate-800 dark:border-slate-700" x-data="{ tab: @js($initialAlertTab ?? 'low_stock'), selectTab(value) { this.tab = value; const url = new URL(window.location.href); url.searchParams.set('tab', value); window.history.replaceState({}, '', url); } }" @inventory-select-alert-tab.window="selectTab($event.detail)">
    <!-- Header with Tabs -->
    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6 dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">
                Perhatian diperlukan
            </h3>
            <div role="group" aria-label="Kategori peringatan inventori" class="flex flex-wrap gap-1 rounded-lg bg-gray-200 p-1 dark:bg-slate-700">
                <button
                    id="inventory-alert-low-stock"
                    @click="selectTab('low_stock')"
                    :aria-pressed="tab === 'low_stock' ? 'true' : 'false'"
                    aria-controls="inventory-panel-low-stock"
                    :class="{ 'bg-white shadow text-gray-900 dark:bg-slate-800 dark:text-slate-100': tab === 'low_stock', 'text-gray-700 hover:text-gray-900 dark:text-slate-200 dark:hover:text-white': tab !== 'low_stock' }"
                    class="min-h-11 rounded-md px-3 py-1.5 text-sm font-medium transition-colors flex items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900"
                >
                    Stok Rendah
                    @if($lowStockCount > 0)
                        <span class="ml-1.5 inline-flex min-h-6 min-w-6 items-center justify-center rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900 dark:text-red-100">
                            {{ $lowStockCount }}
                        </span>
                    @endif
                </button>
                <button
                    id="inventory-alert-near-expiry"
                    @click="selectTab('near_expiry')"
                    :aria-pressed="tab === 'near_expiry' ? 'true' : 'false'"
                    aria-controls="inventory-panel-near-expiry"
                    :class="{ 'bg-white shadow text-gray-900 dark:bg-slate-800 dark:text-slate-100': tab === 'near_expiry', 'text-gray-700 hover:text-gray-900 dark:text-slate-200 dark:hover:text-white': tab !== 'near_expiry' }"
                    class="min-h-11 rounded-md px-3 py-1.5 text-sm font-medium transition-colors flex items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900"
                >
                    Hampir kedaluwarsa
                    @if($nearExpiry30Count > 0)
                        <span class="ml-1.5 inline-flex min-h-6 min-w-6 items-center justify-center rounded bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-800 dark:bg-orange-900 dark:text-orange-100">
                            {{ $nearExpiry30Count }}
                        </span>
                    @endif
                </button>
                <button
                    id="inventory-alert-expired"
                    @click="selectTab('expired')"
                    :aria-pressed="tab === 'expired' ? 'true' : 'false'"
                    aria-controls="inventory-panel-expired"
                    :class="{ 'bg-white shadow text-gray-900 dark:bg-slate-800 dark:text-slate-100': tab === 'expired', 'text-gray-700 hover:text-gray-900 dark:text-slate-200 dark:hover:text-white': tab !== 'expired' }"
                    class="min-h-11 rounded-md px-3 py-1.5 text-sm font-medium transition-colors flex items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900"
                >
                    Kedaluwarsa
                    @if($expiredCount > 0)
                        <span class="ml-1.5 inline-flex min-h-6 min-w-6 items-center justify-center rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900 dark:text-red-100">
                            {{ $expiredCount }}
                        </span>
                    @endif
                </button>
            </div>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="bg-white p-4 dark:bg-slate-800">
        <!-- Low Stock Tab (List Card Layout) -->
        <div id="inventory-panel-low-stock" x-show="tab === 'low_stock'" x-transition.opacity role="region" aria-labelledby="inventory-alert-low-stock">
            <a href="{{ route('inventory.alerts.index', ['category' => 'low_stock']) }}" class="mb-3 inline-flex min-h-11 items-center font-semibold text-primary-800 underline dark:text-primary-300">Lihat semua stok rendah ({{ $lowStockCount }})</a>
            @if($lowStockItems->isEmpty())
                <div class="py-8 text-center text-gray-700 dark:text-slate-200">
                    <p class="font-medium text-gray-900 dark:text-slate-100">Tidak ada peringatan stok rendah</p>
                    <p class="text-sm text-gray-700 dark:text-slate-300">Tidak ada item dengan stok di bawah minimum pada lokasi yang dapat Anda lihat.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($lowStockItems as $item)
                        <div class="flex flex-col p-4 bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow dark:bg-slate-800 dark:border-slate-700">
                            <!-- Header: Item & Action -->
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <div class="text-sm font-bold text-gray-900 dark:text-slate-100">
                            @if(auth()->user()?->hasPermission('inventori.edit'))
                                <a href="{{ route('inventory.items.edit', $item) }}" class="hover:text-primary-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-slate-100 dark:hover:text-primary-300 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                                                {{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }}
                                            </a>
                                        @else
                                            {{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }}
                                        @endif
                                    </div>
                        <div class="text-xs text-gray-700 mt-0.5 dark:text-slate-300">
                                        Min: <span class="font-mono tabular-nums">{{ number_format($item->min_stock, 3, ',', '.') }}</span> {{ $item->uom }}
                                    </div>
                                </div>
                                @if(auth()->user()?->hasPermission('inventori.receipt.create'))
                                    <a href="{{ route('inventory.transaction.receipt', ['item_id' => $item->id]) }}"
                                       class="inline-flex min-h-11 items-center justify-center rounded border border-emerald-800 bg-emerald-100 px-3 py-2 text-sm font-semibold text-emerald-950 transition-colors hover:bg-emerald-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-800 focus-visible:ring-offset-2 dark:border-emerald-200 dark:bg-emerald-950 dark:text-emerald-100 dark:hover:bg-emerald-900 dark:focus-visible:ring-emerald-300 dark:focus-visible:ring-offset-slate-900">
                                        Terima stok
                                    </a>
                                @endif
                            </div>

                            <!-- Visual Health Bar -->
                            @php
                                $percent = $item->min_stock > 0 ? min(($item->total_on_hand / $item->min_stock) * 100, 100) : 0;
                                $barColor = $percent < 25 ? 'bg-red-600' : ($percent < 50 ? 'bg-orange-500' : 'bg-yellow-400');
                            @endphp
                            <div class="w-full bg-gray-200 h-2.5 rounded-full overflow-hidden mb-2 dark:bg-slate-700" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($percent, 0) }}" aria-label="Persentase stok dibanding stok minimum">
                                <div class="h-2.5 rounded-full {{ $barColor }} transition-all duration-500" 
                                     style="width: {{ $percent }}%"></div>
                            </div>

                            <!-- Footer: Stock & Trend -->
                            <div class="flex flex-wrap justify-between items-center gap-2 text-xs">
                                <div class="font-mono font-bold {{ $item->total_on_hand <= 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-900 dark:text-slate-100' }}">
                                    Sisa <span class="tabular-nums">{{ number_format($item->total_on_hand, 3, ',', '.') }}</span> {{ $item->uom }}
                                    <span class="text-gray-700 font-normal dark:text-slate-300">({{ number_format($percent, 0) }}% dari minimum)</span>
                                </div>
                                
                                <!-- Usage Trend (Requires Backend Support, fallback if null) -->
                                <div class="flex items-center gap-1 text-gray-700 dark:text-slate-300" title="Pemakaian 30 hari terakhir">
                                    @if(isset($item->trend))
                                        @if($item->trend == 'high')
                                            <span class="inventory-status-danger rounded px-2 py-1 font-bold">Pemakaian tinggi</span>
                                        @elseif($item->trend == 'moderate')
                                            <span class="inventory-status-warning rounded px-2 py-1">Pemakaian sedang</span>
                                        @else
                                            <span class="inventory-status-neutral rounded px-2 py-1">Pemakaian rendah</span>
                                        @endif
                                        <span class="text-gray-700 dark:text-slate-300">({{ number_format($item->monthly_usage ?? 0, 3, ',', '.') }}/bln)</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Near Expiry Tab (Table Layout) -->
        <div id="inventory-panel-near-expiry" x-show="tab === 'near_expiry'" x-transition.opacity style="display: none;" role="region" aria-labelledby="inventory-alert-near-expiry">
            <a href="{{ route('inventory.alerts.index', ['category' => 'near_expiry']) }}" class="mb-3 inline-flex min-h-11 items-center font-semibold text-primary-800 underline dark:text-primary-300">Lihat semua lot hampir kedaluwarsa ({{ $nearExpiry30Count }})</a>
            @if($nearExpiry30->isEmpty())
                <div class="py-8 text-center text-gray-700 dark:text-slate-200">
                    <p class="font-medium text-gray-900 dark:text-slate-100">Tidak ada lot mendekati kedaluwarsa</p>
                    <p class="text-sm text-gray-700 dark:text-slate-300">Tidak ada lot bersaldo yang akan kedaluwarsa dalam 30 hari ke depan di lokasi yang dapat Anda lihat.</p>
                </div>
            @else
                <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-slate-700">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider dark:text-slate-300">Item / Lot</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider dark:text-slate-300">Tanggal kedaluwarsa</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider dark:text-slate-300">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-700">
                            @foreach($nearExpiry30 as $lot)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $lot->item->item_code ?? 'Kode belum ditetapkan' }} · {{ $lot->item->name }}</div>
                                    <div class="text-xs text-gray-700 font-mono dark:text-slate-300">{{ $lot->lot_no }}</div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-slate-100">
                                    {{ $lot->expiry_date->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <span role="status" class="inline-flex items-center rounded px-2.5 py-1 text-xs font-semibold {{ $lot->days_until_expiry <= 7 ? 'inventory-status-danger' : 'inventory-status-warning' }}">
                                        {{ $lot->days_until_expiry <= 7 ? 'Segera · ' : 'Hampir kedaluwarsa · ' }}{{ $lot->days_until_expiry }} hari
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
        <div id="inventory-panel-expired" x-show="tab === 'expired'" x-transition.opacity style="display: none;" role="region" aria-labelledby="inventory-alert-expired">
            <a href="{{ route('inventory.alerts.index', ['category' => 'expired']) }}" class="mb-3 inline-flex min-h-11 items-center font-semibold text-primary-800 underline dark:text-primary-300">Lihat semua lot kedaluwarsa ({{ $expiredCount }})</a>
            @if($expiredLots->isEmpty())
                <div class="py-8 text-center text-gray-700 dark:text-slate-200">
                    <p class="font-medium text-gray-900 dark:text-slate-100">Tidak ada lot kedaluwarsa</p>
                    <p class="text-sm text-gray-700 dark:text-slate-300">Tidak ada stok pada lot yang sudah kedaluwarsa di lokasi yang dapat Anda lihat.</p>
                </div>
            @else
                <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-slate-700">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider dark:text-slate-300">Item / Lot</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider dark:text-slate-300">Stok</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider dark:text-slate-300">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-700">
                            @foreach($expiredLots as $lot)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $lot->item->item_code ?? 'Kode belum ditetapkan' }} · {{ $lot->item->name }}</div>
                                    <div class="text-xs text-gray-700 font-mono dark:text-slate-300">{{ $lot->lot_no }}</div>
                                    <div class="inventory-status-danger inline-flex rounded px-2 py-1 text-xs font-semibold">Kedaluwarsa: {{ $lot->expiry_date->format('d M Y') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono text-gray-700 dark:text-slate-200">
                                    {{ number_format($lot->balances->sum('on_hand_qty'), 3, ',', '.') }} {{ $lot->item->uom }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium">
                                    @if(auth()->user()?->hasPermission('inventori.material-disposal.create'))
                                        <a href="{{ route('inventory.transaction.dispose', ['item_id' => $lot->item_id, 'lot_id' => $lot->id]) }}" class="inline-flex min-h-11 items-center rounded px-3 text-sm font-semibold text-red-800 underline hover:text-red-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800 focus-visible:ring-offset-2 dark:text-red-200 dark:hover:text-white dark:focus-visible:ring-red-300 dark:focus-visible:ring-offset-slate-900">
                                            Disposal
                                        </a>
                                    @endif
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
