<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Stock Opname"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Transaksi'],
                ['label' => 'Stock Opname']
            ]"
        />
    </x-slot>

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-6 rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->has('error'))
            <div class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                {{ $errors->first('error') }}
            </div>
        @endif

        <div role="status" class="mb-6 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
            <p class="font-semibold">Form pemeriksaan stok lama sudah dinonaktifkan.</p>
            <p class="mt-1">Gunakan pencatatan stok awal atau pemeriksaan sebagian stok. Mengisi tabel di halaman ini tidak akan mengubah jumlah stok.</p>
            <a href="{{ route('inventory.opening-baseline.index') }}" class="mt-2 inline-flex min-h-11 items-center font-medium text-primary-700 underline">Buka Pencatatan Stok Awal</a>
        </div>

        <div class="card">
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Pemeriksaan Jumlah Stok</h3>
                <p class="text-sm text-gray-600">Halaman lama ini hanya disimpan untuk kebutuhan sistem. Gunakan pencatatan stok awal di atas.</p>
            </div>

            <form method="POST" action="{{ route('inventory.transaction.stocktake') }}" id="stocktake-form">
                @csrf

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="stocktake-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Item</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Lot</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Lokasi</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600">Jumlah di Catatan</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600">Stok Fisik</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600">Selisih</th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="rows-container">
                            <tr class="stocktake-row" data-row="0">
                                <td class="px-3 py-2">
                                    <select name="rows[0][item_id]" class="item-select w-full text-sm rounded-md border-gray-300" required>
                                        <option value="">Pilih item...</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" data-uom="{{ $item->uom }}">{{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }} ({{ $item->uom }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <select name="rows[0][lot_id]" class="lot-select w-full text-sm rounded-md border-gray-300">
                                        <option value="">Pilih item dulu</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <select name="rows[0][location_id]" class="location-select w-full text-sm rounded-md border-gray-300" required>
                                        <option value="">Pilih...</option>
                                        @foreach($locations as $location)
                                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <span class="system-qty font-mono text-sm text-gray-600">-</span>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" step="0.001" name="rows[0][counted_qty]" class="counted-qty w-24 text-sm text-right rounded-md border-gray-300" required min="0">
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <span class="variance font-mono text-sm">-</span>
                                </td>
                                <td class="px-3 py-2">
                                    <button type="button" class="remove-row text-red-500 hover:text-red-700 hidden">&times;</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="button" id="add-row" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                        + Tambah Baris
                    </button>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.dashboard') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button type="submit" disabled class="px-6 py-2 rounded-md bg-gray-300 font-medium text-gray-600 cursor-not-allowed">
                        Simpan (nonaktif)
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        let rowCount = 1;
        const locationsHtml = `@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach`;
        const itemsHtml = `@foreach($items as $item)<option value="{{ $item->id }}" data-uom="{{ $item->uom }}">{{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }} ({{ $item->uom }})</option>@endforeach`;

        function attachRowEvents(row) {
            const itemSelect = row.querySelector('.item-select');
            const lotSelect = row.querySelector('.lot-select');
            const locationSelect = row.querySelector('.location-select');
            const countedQty = row.querySelector('.counted-qty');
            const systemQty = row.querySelector('.system-qty');
            const variance = row.querySelector('.variance');
            const removeBtn = row.querySelector('.remove-row');

            function updateSystemQty() {
                const itemId = itemSelect.value;
                const lotId = lotSelect.value || '';
                const locationId = locationSelect.value;

                if (!itemId || !locationId) {
                    systemQty.textContent = '-';
                    return;
                }

                fetch(`{{ url('referensi/inventori/ajax/balance') }}?context=stocktake&item_id=${itemId}&lot_id=${lotId}&location_id=${locationId}`)
                    .then(r => r.json())
                    .then(data => {
                        systemQty.textContent = data.on_hand_qty.toFixed(2);
                        systemQty.dataset.value = data.on_hand_qty;
                        updateVariance();
                    });
            }

            function updateVariance() {
                const sys = parseFloat(systemQty.dataset.value) || 0;
                const counted = parseFloat(countedQty.value) || 0;
                const diff = counted - sys;

                if (isNaN(diff)) {
                    variance.textContent = '-';
                    variance.className = 'variance font-mono text-sm';
                } else {
                    const sign = diff > 0 ? '+' : '';
                    variance.textContent = sign + diff.toFixed(2);
                    variance.className = 'variance font-mono text-sm ' + (diff > 0 ? 'text-green-600' : diff < 0 ? 'text-red-600' : 'text-gray-600');
                }
            }

            itemSelect.addEventListener('change', function() {
                const itemId = this.value;
                lotSelect.innerHTML = '<option value="">Tanpa lot</option>';
                
                if (!itemId || !locationSelect.value) return;
                
                fetch(`{{ url('referensi/inventori/ajax/lots') }}?context=stocktake&item_id=${encodeURIComponent(itemId)}&location_id=${encodeURIComponent(locationSelect.value)}`)
                    .then(r => r.json())
                    .then(lots => {
                        lots.forEach(lot => {
                            const opt = document.createElement('option');
                            opt.value = lot.id;
                            opt.textContent = lot.lot_no;
                            lotSelect.appendChild(opt);
                        });
                        updateSystemQty();
                    });
            });

            lotSelect.addEventListener('change', updateSystemQty);
            locationSelect.addEventListener('change', function() {
                if (itemSelect.value) {
                    itemSelect.dispatchEvent(new Event('change'));
                } else {
                    updateSystemQty();
                }
            });
            countedQty.addEventListener('input', updateVariance);
            
            removeBtn.addEventListener('click', function() {
                row.remove();
                updateRemoveButtons();
            });
        }

        function updateRemoveButtons() {
            const rows = document.querySelectorAll('.stocktake-row');
            rows.forEach((row, index) => {
                const btn = row.querySelector('.remove-row');
                btn.classList.toggle('hidden', rows.length <= 1);
            });
        }

        document.getElementById('add-row').addEventListener('click', function() {
            const container = document.getElementById('rows-container');
            const template = document.querySelector('.stocktake-row').cloneNode(true);
            
            template.dataset.row = rowCount;
            template.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/\[0\]/, `[${rowCount}]`);
                el.value = '';
            });
            template.querySelector('.lot-select').innerHTML = '<option value="">Pilih item dulu</option>';
            template.querySelector('.system-qty').textContent = '-';
            template.querySelector('.variance').textContent = '-';
            
            container.appendChild(template);
            attachRowEvents(template);
            updateRemoveButtons();
            rowCount++;
        });

        // Initialize first row
        attachRowEvents(document.querySelector('.stocktake-row'));
    </script>
    @endpush
</x-app-layout>
