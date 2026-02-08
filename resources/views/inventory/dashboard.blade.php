<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Dashboard Inventori"
            :breadcrumbs="[['label' => 'Inventori']]"
        >
            <x-slot name="actions">
                <div class="flex flex-wrap gap-2">
                    <x-button variant="outline" size="sm" href="{{ route('inventory.items.index') }}">Master Item</x-button>
                    <x-button variant="outline" size="sm" href="{{ route('inventory.stock-card') }}">Kartu Stok</x-button>
                    <x-button variant="outline" size="sm" href="{{ route('inventory.disposal.index') }}">Disposal Sampel</x-button>

                    <x-button variant="primary" size="sm" href="{{ route('inventory.transaction.receipt') }}">+ Penerimaan</x-button>
                    <x-button variant="danger" size="sm" href="{{ route('inventory.transaction.issue') }}">- Pengeluaran</x-button>
                    <x-button variant="secondary" size="sm" href="{{ route('inventory.transaction.transfer') }}">Transfer</x-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- Global Search / Barcode -->
        @include('inventory.partials.global-search')
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="card">
                <div class="text-2xl font-bold text-primary-900">{{ $stats['total_items'] }}</div>
                <div class="text-xs text-gray-600">Total Item Aktif</div>
            </div>
            <div class="card">
                <div class="text-2xl font-bold text-primary-900">{{ $stats['total_lots'] }}</div>
                <div class="text-xs text-gray-600">Lot Aktif</div>
            </div>
            <div class="card {{ $stats['low_stock'] > 0 ? 'border-l-4 border-amber-400' : '' }}">
                <div class="text-2xl font-bold {{ $stats['low_stock'] > 0 ? 'text-amber-600' : 'text-primary-900' }}">{{ $stats['low_stock'] }}</div>
                <div class="text-xs text-gray-600">Stok Rendah</div>
            </div>
            <div class="card {{ $stats['expired'] > 0 ? 'border-l-4 border-red-400' : '' }}">
                <div class="text-2xl font-bold {{ $stats['expired'] > 0 ? 'text-red-600' : 'text-primary-900' }}">{{ $stats['expired'] }}</div>
                <div class="text-xs text-gray-600">Lot Kadaluarsa</div>
            </div>
        </div>

        <x-page-section title="🧪 Disposal Sampel">
            <div class="card flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-lg border border-gray-200 bg-white p-3">
                            <div class="text-xs font-medium text-gray-600">Selesai Uji (Belum Musnah)</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900">
                                <span data-testid="disposal-finished-count">{{ (int) ($finishedSamplesCount ?? 0) }}</span>
                            </div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <div class="text-xs font-medium text-amber-800">Siap Musnah</div>
                            <div class="mt-1 text-2xl font-bold text-amber-900">
                                <span data-testid="disposal-eligible-count">{{ (int) ($eligibleSamplesCount ?? 0) }}</span>
                            </div>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <div class="text-xs font-medium text-emerald-800">Dimusnahkan Bulan Ini</div>
                            <div class="mt-1 text-2xl font-bold text-emerald-900">
                                <span data-testid="disposal-disposed-month-count">{{ (int) ($disposedThisMonthCount ?? 0) }}</span>
                            </div>
                        </div>
                    </div>

                    @if(($recentSampleDisposals ?? collect())->isNotEmpty())
                        <div class="mt-4">
                            <div class="text-xs font-semibold text-gray-600">Batch Terakhir</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($recentSampleDisposals as $d)
                                    <a
                                        href="{{ route('inventory.disposal.show', $d) }}"
                                        class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-800 hover:bg-gray-200"
                                    >
                                        <span class="font-mono">{{ $d->batch_number }}</span>
                                        <span class="text-gray-500">({{ (int) ($d->samples_count ?? 0) }})</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <x-button variant="outline" size="sm" href="{{ route('inventory.disposal.index') }}">
                    Kelola Disposal
                </x-button>
            </div>
        </x-page-section>

        @if(($topMovers ?? collect())->isNotEmpty())
        <x-page-section title="🔥 Barang Paling Boros (7 hari terakhir)">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Item</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Total Keluar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($topMovers as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                {{ $row->item?->name ?? ('Item #'.$row->item_id) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-sm text-gray-700">
                                {{ number_format((float) ($row->total_qty ?? 0), 2) }} {{ $row->item?->uom ?? '' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-page-section>
        @endif

        @if($lowStockItems->isNotEmpty())
        <x-page-section title="Kesehatan Stok">
            <div class="card space-y-4">
                <p class="text-xs text-gray-500">Visual cepat: stok aktual (bar) dengan marker min stock.</p>
                @foreach($lowStockItems as $item)
                    <x-bullet-graph
                        :label="$item->name"
                        :actual="$item->total_on_hand"
                        :min="$item->min_stock"
                        :uom="$item->uom"
                    />
                @endforeach
            </div>
        </x-page-section>
        @endif

        <!-- Low Stock Items -->
        @if($lowStockItems->isNotEmpty())
        <x-page-section title="⚠️ Stok Rendah (di bawah minimum)">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm low-stock-warning">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-amber-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tipe</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Stok</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Min</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($lowStockItems as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('inventory.items.edit', $item) }}" class="font-medium text-primary-700 hover:underline">{{ $item->name }}</a>
                                @if($item->brand)<span class="text-gray-500 text-sm"> · {{ $item->brand }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $item->item_type_label }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm text-red-600">{{ number_format($item->total_on_hand, 2) }} {{ $item->uom }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">{{ number_format($item->min_stock, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <x-button variant="outline" size="xs" href="{{ route('inventory.transaction.transfer', ['item_id' => $item->id]) }}">Transfer</x-button>
                                    <x-button variant="outline" size="xs" href="{{ route('inventory.transaction.receipt', ['item_id' => $item->id]) }}">Penerimaan</x-button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-page-section>
        @endif

        <!-- Near Expiry 30 Days -->
        @if($nearExpiry30->isNotEmpty())
        <x-page-section title="⏰ Mendekati Kadaluarsa (30 hari)">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-orange-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Lot No</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kadaluarsa</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Sisa Hari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($nearExpiry30 as $lot)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $lot->item->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $lot->lot_no }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $lot->expiry_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $lot->days_until_expiry <= 7 ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' }}">
                                    {{ $lot->days_until_expiry }} hari
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-page-section>
        @endif

        <!-- Expired Lots -->
        @if($expiredLots->isNotEmpty())
        <x-page-section title="🚫 Kadaluarsa (Perlu Disposal)">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-red-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Lot No</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kadaluarsa</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Stok</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($expiredLots as $lot)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $lot->item->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $lot->lot_no }}</td>
                            <td class="px-4 py-3 text-sm text-red-600">{{ $lot->expiry_date?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">
                                {{ number_format($lot->balances->sum('on_hand_qty'), 2) }} {{ $lot->item->uom }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-button
                                    variant="outline"
                                    size="xs"
                                    href="{{ route('inventory.transaction.dispose', ['item_id' => $lot->item_id, 'lot_id' => $lot->id]) }}"
                                    class="text-red-700"
                                >
                                    Disposal
                                </x-button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-page-section>
        @endif

        <!-- Quick Actions -->
        <x-page-section title="⚡ Transaksi Cepat">
            <div class="text-xs text-gray-500 mb-3">Pengeluaran/Penerimaan/Transfer cepat langsung dari dashboard.</div>
            @include('inventory.partials.quick-actions')
        </x-page-section>

        @if($lowStockItems->isEmpty() && $nearExpiry30->isEmpty() && $expiredLots->isEmpty())
        <div class="card text-center py-12">
            <div class="text-4xl mb-4">✅</div>
            <h3 class="text-lg font-semibold text-gray-900">Semua Stok Normal</h3>
            <p class="text-gray-600">Tidak ada item dengan stok rendah atau mendekati kadaluarsa.</p>
        </div>
        @endif
    </div>
</x-app-layout>
