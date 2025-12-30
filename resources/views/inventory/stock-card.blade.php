<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Kartu Stok"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Kartu Stok']]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Filters -->
        <div class="card">
            <form method="GET" action="{{ route('inventory.stock-card') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="item-select" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih item...</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ ($filters['item_id'] ?? '') == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }} ({{ $item->uom }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lot</label>
                        <select name="lot_id" id="lot-select"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Semua Lot</option>
                            @foreach($lots as $lot)
                                <option value="{{ $lot->id }}" {{ ($filters['lot_id'] ?? '') == $lot->id ? 'selected' : '' }}>
                                    {{ $lot->lot_no }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                        <select name="location_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Semua Lokasi</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ ($filters['location_id'] ?? '') == $location->id ? 'selected' : '' }}>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 font-medium">
                            Tampilkan
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                </div>
            </form>
        </div>

        @if($selectedItem)
        <!-- Item Summary -->
        <div class="card bg-primary-50">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-lg font-semibold text-primary-900">{{ $selectedItem->name }}</h3>
                    <p class="text-sm text-primary-700">{{ $selectedItem->brand ?? '' }} · {{ $selectedItem->item_type_label }} · {{ $selectedItem->uom }}</p>
                </div>
                <div class="flex items-center gap-4">
                    @if(!empty($stockCard))
                    <div class="text-right">
                        <div class="text-sm text-primary-700">Saldo Akhir</div>
                        <div class="text-2xl font-bold text-primary-900">
                            {{ number_format(end($stockCard)['running_balance'] ?? 0, 2) }} {{ $selectedItem->uom }}
                        </div>
                    </div>
                    @endif
                    <a href="{{ route('inventory.stock-card.print', $filters) }}" 
                       target="_blank"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-primary-300 text-primary-700 rounded-md hover:bg-primary-100 font-medium shadow-sm transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Cetak PDF
                    </a>
                </div>
            </div>
        </div>
        @endif

        <!-- Stock Card Table -->
        @if(!empty($stockCard))
        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">Tipe</th>
                        <th class="px-4 py-3 text-left">Lot</th>
                        <th class="px-4 py-3 text-left">Dari</th>
                        <th class="px-4 py-3 text-left">Ke</th>
                        <th class="px-4 py-3 text-right">Masuk</th>
                        <th class="px-4 py-3 text-right">Keluar</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3 text-left">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @foreach($stockCard as $row)
                        @php
                            $movement = $row['movement'];
                            $change = $row['change'];
                            $balance = $row['running_balance'];
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $movement->performed_at->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                    @switch($movement->movement_type)
                                        @case('RECEIPT') bg-green-100 text-green-700 @break
                                        @case('ISSUE') bg-red-100 text-red-700 @break
                                        @case('TRANSFER') bg-blue-100 text-blue-700 @break
                                        @case('ADJUST') bg-yellow-100 text-yellow-700 @break
                                        @case('DISPOSE') bg-gray-100 text-gray-700 @break
                                        @case('RETURN') bg-purple-100 text-purple-700 @break
                                    @endswitch
                                ">
                                    {{ $movement->movement_type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $movement->lot?->lot_no ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $movement->fromLocation?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $movement->toLocation?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $change > 0 ? 'text-green-600' : '' }}">
                                {{ $change > 0 ? number_format($change, 2) : '' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono {{ $change < 0 ? 'text-red-600' : '' }}">
                                {{ $change < 0 ? number_format(abs($change), 2) : '' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-gray-900">
                                {{ number_format($balance, 2) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs max-w-xs truncate" title="{{ $movement->notes }}">
                                {{ $movement->notes ?? ($movement->reference_type ?? '') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @elseif($selectedItem)
        <div class="card text-center py-12">
            <div class="text-4xl mb-4">📋</div>
            <h3 class="text-lg font-semibold text-gray-900">Belum Ada Mutasi</h3>
            <p class="text-gray-600">Tidak ada data mutasi untuk filter yang dipilih.</p>
        </div>
        @else
        <div class="card text-center py-12">
            <div class="text-4xl mb-4">🔍</div>
            <h3 class="text-lg font-semibold text-gray-900">Pilih Item</h3>
            <p class="text-gray-600">Pilih item di atas untuk melihat kartu stok.</p>
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
        // Load lots when item changes
        document.getElementById('item-select').addEventListener('change', function() {
            const itemId = this.value;
            const lotSelect = document.getElementById('lot-select');
            
            lotSelect.innerHTML = '<option value="">Semua Lot</option>';
            
            if (!itemId) return;
            
            fetch(`{{ url('referensi/inventori/ajax/lots') }}?item_id=${itemId}`)
                .then(r => r.json())
                .then(lots => {
                    lots.forEach(lot => {
                        const opt = document.createElement('option');
                        opt.value = lot.id;
                        opt.textContent = lot.lot_no + (lot.expiry_date ? ` (exp: ${lot.expiry_date})` : '');
                        lotSelect.appendChild(opt);
                    });
                });
        });
    </script>
    @endpush
</x-app-layout>
