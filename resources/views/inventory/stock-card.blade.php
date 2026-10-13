<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Kartu Stok"
            class="inventory-page-header"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Kartu Stok']]"
        />
    </x-slot>

    <div class="inventory-report space-y-6">
        @if($errors->any())
            <div role="alert" class="inventory-status-danger rounded-md p-4"><p>Periksa filter kartu stok:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <!-- Filters -->
        <div class="card">
            <form method="GET" action="{{ route('inventory.stock-card') }}" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <div class="md:col-span-2">
                        <label for="item-select" class="block text-sm font-medium text-gray-700 mb-1 dark:text-slate-200">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="item-select" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih item...</option>
                            @foreach($items as $item)
                            <option value="{{ $item->id }}" title="{{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }} ({{ $item->uom }})" {{ ($filters['item_id'] ?? '') == $item->id ? 'selected' : '' }}>
                                    {{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }} ({{ $item->uom }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="lot-select" class="block text-sm font-medium text-gray-700 mb-1 dark:text-slate-200">Lot</label>
                        <select name="lot_id" id="lot-select"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Semua Lot</option>
                            @foreach($lots as $lot)
                                <option value="{{ $lot->id }}" {{ ($filters['lot_id'] ?? '') == $lot->id ? 'selected' : '' }}>
                                    {{ $lot->lot_no }}
                                </option>
                            @endforeach
                        </select>
                        <p id="stock-card-lot-status" role="status" aria-live="polite" class="mt-1 text-sm text-gray-700 dark:text-slate-300"></p>
                        <button id="stock-card-lot-retry" type="button" hidden class="min-h-11 px-2 font-semibold underline">Muat ulang lot</button>
                    </div>
                    <div>
                        <label for="report-location" class="block text-sm font-medium text-gray-700 mb-1 dark:text-slate-200">Lokasi</label>
                        <select id="report-location" name="location_id"
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
                        <button type="submit" class="btn btn-primary min-h-11 w-full px-4 font-medium">
                            Tampilkan
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="report-from" class="block text-sm font-medium text-gray-700 mb-1 dark:text-slate-200">Dari Tanggal</label>
                        <input id="report-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="report-to" class="block text-sm font-medium text-gray-700 mb-1 dark:text-slate-200">Sampai Tanggal</label>
                        <input id="report-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="report-search" class="block text-sm font-medium text-gray-700 mb-1 dark:text-slate-200">Cari baris mutasi</label>
                        <input id="report-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="200" class="w-full rounded-md border-gray-300 shadow-sm" placeholder="Lot, lokasi, keterangan, atau nomor mutasi">
                        <p class="mt-1 text-xs semantic-text-muted">Pencarian menyaring baris, saldo dan total tetap untuk seluruh periode.</p>
                    </div>
                </div>
            </form>
        </div>

        @if($selectedItem)
        <!-- Item Summary -->
        <div class="card">
            <div class="flex flex-wrap gap-4 justify-between items-start">
                <div>
                    <h3 class="text-lg font-semibold text-primary-900">{{ $selectedItem->item_code ?? 'Kode belum ditetapkan' }} · {{ $selectedItem->name }}</h3>
                    <p class="text-sm text-primary-700">{{ $selectedItem->brand ?? '' }} · {{ $selectedItem->item_type_label }} · {{ $selectedItem->uom }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <div class="text-sm semantic-text-muted">Jumlah stok pada akhir periode</div>
                        <div class="text-2xl font-bold semantic-text break-all">
                            {{ \App\Services\InventoryStockCardService::formatQuantity($report['closing_balance']) }} {{ $selectedItem->uom }}
                        </div>
                    </div>
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
        <section class="card" aria-labelledby="report-summary-title">
            <h2 id="report-summary-title" class="font-semibold semantic-text">Ringkasan stok {{ $filters['date_from'] ?? 'sejak awal' }} sampai {{ $filters['date_to'] ?? 'hari ini' }}</h2>
            <p class="mt-1 text-sm semantic-text-muted">{{ $report['historical'] ? 'Ini adalah jumlah stok pada akhir periode, bukan jumlah saat ini.' : 'Jumlah dihitung dari barang masuk, keluar, dan perubahan lain selama periode yang dipilih.' }} Zona waktu: {{ $report['timezone'] }}.</p>
            <dl class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-4 semantic-text">
                @foreach(['opening_balance' => 'Saldo awal', 'total_in' => 'Total masuk', 'total_out' => 'Total keluar', 'closing_balance' => 'Saldo akhir'] as $key => $label)
                    <div><dt class="text-sm semantic-text-muted">{{ $label }}</dt><dd data-report-total="{{ $key }}" class="mt-1 font-mono font-semibold break-all">{{ \App\Services\InventoryStockCardService::formatQuantity($report[$key]) }} {{ $selectedItem->uom }}</dd></div>
                @endforeach
            </dl>
        </section>
        @endif

        <!-- Stock Card Table -->
        @if(!empty($stockCard))
        <div class="overflow-x-auto rounded-lg semantic-surface shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 semantic-text">
                <caption class="sr-only">Riwayat perubahan stok barang {{ $selectedItem->item_code }} dalam periode terpilih</caption>
                <thead class="semantic-bg text-xs font-semibold uppercase tracking-wide semantic-text-muted">
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
                                {{ $movement->performed_at->timezone($report['timezone'])->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded px-2 py-1 text-xs font-semibold
                                    @switch($movement->movement_type)
                                        @case('RECEIPT') inventory-status-success @break
                                        @case('ISSUE') inventory-status-danger @break
                                        @case('TRANSFER') inventory-status-info @break
                                        @case('ADJUST') inventory-status-warning @break
                                        @case('DISPOSE') inventory-status-neutral @break
                                        @case('RETURN') inventory-status-info @break
                                    @endswitch
                                ">
                                    {{ $movement->movement_type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $movement->lot?->lot_no ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $movement->fromLocation?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $movement->toLocation?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $change > 0 ? 'text-green-600' : '' }}">
                                {{ $change > 0 ? \App\Services\InventoryStockCardService::formatQuantity($change) : '' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono {{ $change < 0 ? 'text-red-600' : '' }}">
                                {{ $change < 0 ? \App\Services\InventoryStockCardService::formatQuantity(ltrim($change, '-')) : '' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-gray-900">
                                {{ \App\Services\InventoryStockCardService::formatQuantity($balance) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs min-w-40 whitespace-normal break-words" title="{{ $movement->notes }}">
                                {{ $movement->notes ?? ($movement->reference_type ?? '') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $pagination->links() }}
        @elseif($selectedItem)
        <div class="card text-center py-12">
            <h3 class="text-lg font-semibold semantic-text">Tidak ada mutasi pada periode atau pencarian ini</h3>
            <p class="semantic-text-muted">Saldo awal dan akhir tetap ditampilkan di ringkasan. Ubah filter untuk melihat periode lain.</p>
        </div>
        @else
        <div class="card text-center py-12">
            <h3 class="text-lg font-semibold text-gray-900">Pilih Item</h3>
            <p class="text-gray-600">Pilih item di atas untuk melihat kartu stok.</p>
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
        const stockCardItem = document.getElementById('item-select');
        const stockCardLot = document.getElementById('lot-select');
        const stockCardLotStatus = document.getElementById('stock-card-lot-status');
        const stockCardLotRetry = document.getElementById('stock-card-lot-retry');
        let stockCardLotSequence = 0;

        async function loadStockCardLots() {
            const sequence = ++stockCardLotSequence;
            const itemId = stockCardItem.value;
            stockCardLot.replaceChildren(new Option('Semua Lot', ''));
            stockCardLotRetry.hidden = true;
            stockCardLotStatus.textContent = '';
            stockCardLot.disabled = Boolean(itemId);
            if (!itemId) return;
            stockCardLotStatus.textContent = 'Memuat lot untuk item terpilih.';
            try {
                const response = await fetch(`{{ route('inventory.ajax.lots') }}?context=history&item_id=${encodeURIComponent(itemId)}&location_id=${encodeURIComponent(document.querySelector('[name="location_id"]').value)}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Request failed');
                const lots = await response.json();
                if (sequence !== stockCardLotSequence) return;
                lots.forEach(lot => stockCardLot.add(new Option(lot.lot_no + (lot.expiry_date ? ` (exp: ${lot.expiry_date})` : ''), lot.id)));
                stockCardLotStatus.textContent = lots.length ? `${lots.length} lot tersedia.` : 'Tidak ada lot untuk item pada lokasi terpilih.';
            } catch (error) {
                if (sequence !== stockCardLotSequence) return;
                stockCardLotStatus.textContent = 'Daftar lot gagal dimuat. Periksa koneksi lalu muat ulang lot.';
                stockCardLotRetry.hidden = false;
            } finally {
                if (sequence === stockCardLotSequence) stockCardLot.disabled = false;
            }
        }

        stockCardItem.addEventListener('change', loadStockCardLots);
        document.querySelector('[name="location_id"]').addEventListener('change', loadStockCardLots);
        stockCardLotRetry.addEventListener('click', loadStockCardLots);
    </script>
    @endpush
</x-app-layout>
