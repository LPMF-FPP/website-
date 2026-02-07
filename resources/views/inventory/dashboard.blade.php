<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Dashboard Inventori"
            :breadcrumbs="[['label' => 'Inventori']]"
        >
            <x-slot name="actions">
                <div class="flex gap-2">
                    <a href="{{ route('inventory.items.index') }}"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                        Master Item
                    </a>
                    <a href="{{ route('inventory.transaction.receipt') }}"
                        class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        + Penerimaan
                    </a>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Quick Actions -->
        @include('inventory.partials.quick-actions')

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="card">
                <div class="text-3xl font-bold text-primary-900">{{ $stats['total_items'] }}</div>
                <div class="text-sm text-gray-600">Total Item Aktif</div>
            </div>
            <div class="card">
                <div class="text-3xl font-bold text-primary-900">{{ $stats['total_lots'] }}</div>
                <div class="text-sm text-gray-600">Lot Aktif</div>
            </div>
            <div class="card {{ $stats['low_stock'] > 0 ? 'border-l-4 border-amber-400' : '' }}">
                <div class="text-3xl font-bold {{ $stats['low_stock'] > 0 ? 'text-amber-600' : 'text-primary-900' }}">{{ $stats['low_stock'] }}</div>
                <div class="text-sm text-gray-600">Stok Rendah</div>
            </div>
            <div class="card {{ $stats['expired'] > 0 ? 'border-l-4 border-red-400' : '' }}">
                <div class="text-3xl font-bold {{ $stats['expired'] > 0 ? 'text-red-600' : 'text-primary-900' }}">{{ $stats['expired'] }}</div>
                <div class="text-sm text-gray-600">Lot Kadaluarsa</div>
            </div>
        </div>

        @if(($eligibleSamplesCount ?? 0) > 0)
        <x-page-section title="🧪 Sampel Siap Dimusnahkan">
            <div class="card flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-700">
                        Ada <span class="font-semibold text-primary-700">{{ $eligibleSamplesCount }}</span> sampel yang sudah memenuhi syarat pemusnahan.
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Kelola batch pemusnahan dari modul disposal sampel.</p>
                </div>
                <a href="{{ route('inventory.disposal.index') }}" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    Kelola Disposal Sampel
                </a>
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
                                <a href="{{ route('inventory.transaction.dispose') }}" class="text-sm text-red-600 hover:text-red-700 font-medium">Disposal</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-page-section>
        @endif

        @if($lowStockItems->isEmpty() && $nearExpiry30->isEmpty() && $expiredLots->isEmpty())
        <div class="card text-center py-12">
            <div class="text-4xl mb-4">✅</div>
            <h3 class="text-lg font-semibold text-gray-900">Semua Stok Normal</h3>
            <p class="text-gray-600">Tidak ada item dengan stok rendah atau mendekati kadaluarsa.</p>
        </div>
        @endif
    </div>
</x-app-layout>
