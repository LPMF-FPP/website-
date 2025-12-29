<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Disposal Stok"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Transaksi'],
                ['label' => 'Disposal']
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
            <div class="mb-6 p-4 bg-red-50 rounded-lg border border-red-200">
                <p class="text-sm text-red-700">
                    <strong>⚠️ Perhatian:</strong> Disposal akan mengurangi stok secara permanen. Pastikan item memang perlu dibuang.
                </p>
            </div>

            <form method="POST" action="{{ route('inventory.transaction.dispose') }}">
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
                    </div>

                    <!-- Lot -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lot</label>
                        <select name="lot_id" id="lot-select"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih item dulu</option>
                        </select>
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
                    </div>

                    <!-- Available Balance Display -->
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Stok Tersedia:</span>
                            <span id="available-qty" class="text-lg font-bold text-gray-900">-</span>
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Disposal <span class="text-red-500">*</span></label>
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

                    <!-- Reason Code -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Disposal <span class="text-red-500">*</span></label>
                        <select name="reason_code" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih alasan...</option>
                            @foreach($reasonCodes as $key => $label)
                                <option value="{{ $key }}" {{ old('reason_code') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reason_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea name="notes" rows="3"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Detail alasan disposal, nomor dokumen pendukung, dll.">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.dashboard') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">
                        🗑️ Simpan Disposal
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
            
            lotSelect.innerHTML = '<option value="">Tanpa lot</option>';
            
            if (!itemId) return;
            
            fetch(`{{ url('referensi/inventori/ajax/lots') }}?item_id=${itemId}`)
                .then(r => r.json())
                .then(lots => {
                    lots.forEach(lot => {
                        const opt = document.createElement('option');
                        opt.value = lot.id;
                        opt.textContent = lot.lot_no + (lot.expiry_date ? ` (exp: ${lot.expiry_date})` : '');
                        if (lot.is_expired) opt.textContent += ' ⚠️ EXPIRED';
                        lotSelect.appendChild(opt);
                    });
                    updateBalance();
                });
        });

        lotSelect.addEventListener('change', updateBalance);
        locationSelect.addEventListener('change', updateBalance);
    </script>
    @endpush
</x-app-layout>
