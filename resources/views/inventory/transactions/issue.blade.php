<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pengeluaran Stok"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Transaksi'],
                ['label' => 'Pengeluaran']
            ]"
        />
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
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

        <div class="card">
            <form method="POST" action="{{ route('inventory.transaction.issue') }}">
                @csrf

                <div class="space-y-6">
                    <!-- Item -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="item-select" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih item...</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-uom="{{ $item->uom }}" {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }} ({{ $item->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Lot -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lot (FEFO)</label>
                        <select name="lot_id" id="lot-select"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih item dulu</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Lot diurutkan berdasarkan tanggal kadaluarsa terdekat (FEFO).</p>
                        <div id="lot-warning" class="mt-2 hidden rounded border border-red-200 bg-red-50 p-2 text-sm text-red-700"></div>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dari Lokasi <span class="text-red-500">*</span></label>
                        <select name="location_id" id="location-select" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih lokasi...</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('location_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Available Balance Display -->
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-blue-700">Stok Tersedia:</span>
                            <span id="available-qty" class="text-lg font-bold text-blue-900">-</span>
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Keluar <span class="text-red-500">*</span></label>
                            <input type="number" step="0.001" name="qty" value="{{ old('qty') }}" required min="0.001"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="0.00">
                            @error('qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
                            <input type="text" id="uom-display" readonly
                                class="w-full rounded-md border-gray-200 bg-gray-100 shadow-sm text-gray-600"
                                value="Pilih item dulu">
                        </div>
                    </div>

                    <!-- Reference -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Referensi</label>
                        <select name="reference_type"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="MANUAL">Manual</option>
                            @foreach($referenceTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('reference_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea name="notes" rows="2"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Keperluan penggunaan...">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.dashboard') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">
                        📤 Simpan Pengeluaran
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const itemSelect = document.getElementById('item-select');
        const lotSelect = document.getElementById('lot-select');
        const locationSelect = document.getElementById('location-select');
        const uomDisplay = document.getElementById('uom-display');
        const availableQty = document.getElementById('available-qty');
        const lotWarning = document.getElementById('lot-warning');

        let currentUom = '';

        function updateBalance() {
            const itemId = itemSelect.value;
            const lotId = lotSelect.value || '';
            const locationId = locationSelect.value;

            if (!itemId || !locationId) {
                availableQty.textContent = '-';
                return;
            }

            fetch(`{{ url('referensi/inventori/ajax/balance') }}?item_id=${itemId}&lot_id=${lotId}&location_id=${locationId}`)
                .then(r => r.json())
                .then(data => {
                    availableQty.textContent = data.on_hand_qty.toFixed(2) + ' ' + currentUom;
                });
        }

        itemSelect.addEventListener('change', function() {
            const itemId = this.value;
            const option = this.options[this.selectedIndex];
            
            currentUom = option.dataset.uom || '';
            uomDisplay.value = currentUom || 'Pilih item dulu';
            
            lotSelect.innerHTML = '<option value="">Tanpa lot (semua lot)</option>';
            lotWarning.classList.add('hidden');
            
            if (!itemId) return;
            
            fetch(`{{ url('referensi/inventori/ajax/lots') }}?item_id=${itemId}`)
                .then(r => r.json())
                .then(lots => {
                    lots.forEach(lot => {
                        const opt = document.createElement('option');
                        opt.value = lot.id;
                        opt.textContent = lot.lot_no + (lot.expiry_date ? ` (exp: ${lot.expiry_date})` : '');
                        opt.dataset.canIssue = lot.can_issue ? '1' : '0';
                        opt.dataset.expired = lot.is_expired ? '1' : '0';
                        if (!lot.can_issue) {
                            opt.textContent += ' ⚠️ ' + (lot.is_expired ? 'EXPIRED' : lot.status);
                        }
                        lotSelect.appendChild(opt);
                    });
                    updateBalance();
                });
        });

        lotSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.dataset.expired === '1') {
                lotWarning.textContent = '⚠️ Lot ini sudah kadaluarsa dan tidak dapat dikeluarkan.';
                lotWarning.classList.remove('hidden');
            } else if (option.dataset.canIssue === '0') {
                lotWarning.textContent = '⚠️ Lot ini tidak dapat dikeluarkan (status: quarantine/disposed).';
                lotWarning.classList.remove('hidden');
            } else {
                lotWarning.classList.add('hidden');
            }
            updateBalance();
        });

        locationSelect.addEventListener('change', updateBalance);
    </script>
    @endpush
</x-app-layout>
