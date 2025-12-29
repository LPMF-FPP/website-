<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Penerimaan Stok"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Transaksi'],
                ['label' => 'Penerimaan']
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
            <form method="POST" action="{{ route('inventory.transaction.receipt') }}" id="receipt-form">
                @csrf

                <div class="space-y-6">
                    <!-- Item -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="item-select" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('item_id') border-red-500 @enderror">
                            <option value="">Pilih item...</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-uom="{{ $item->uom }}" data-requires-expiry="{{ $item->requiresExpiry() ? '1' : '0' }}"
                                    {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }} ({{ $item->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Lot Selection -->
                    <div class="p-4 bg-gray-50 rounded-lg space-y-4">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="lot_mode" value="existing" checked class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Lot yang ada</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="lot_mode" value="new" class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Lot baru</span>
                            </label>
                        </div>

                        <div id="existing-lot-section">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Lot</label>
                            <select name="lot_id" id="lot-select"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Tanpa lot / pilih item dulu</option>
                            </select>
                        </div>

                        <div id="new-lot-section" class="hidden space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Lot Baru <span class="text-red-500">*</span></label>
                                <input type="text" name="new_lot_no" value="{{ old('new_lot_no') }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    placeholder="e.g., LOT-2024-001">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Tanggal Kadaluarsa <span id="expiry-required" class="text-red-500 hidden">*</span>
                                </label>
                                <input type="date" name="new_lot_expiry" value="{{ old('new_lot_expiry') }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                @error('new_lot_expiry')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi Penyimpanan <span class="text-red-500">*</span></label>
                        <select name="location_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('location_id') border-red-500 @enderror">
                            <option value="">Pilih lokasi...</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                    {{ $location->name }} ({{ $location->location_type_label }})
                                </option>
                            @endforeach
                        </select>
                        @error('location_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Quantity -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah <span class="text-red-500">*</span></label>
                            <input type="number" step="0.001" name="qty" value="{{ old('qty') }}" required min="0.001"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('qty') border-red-500 @enderror"
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

                    <!-- Reference & Cost -->
                    <div class="grid grid-cols-2 gap-4">
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Satuan (Rp)</label>
                            <input type="number" step="0.01" name="unit_cost" value="{{ old('unit_cost') }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="0.00">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea name="notes" rows="2"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Keterangan tambahan...">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.dashboard') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                        📥 Simpan Penerimaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const itemSelect = document.getElementById('item-select');
        const lotSelect = document.getElementById('lot-select');
        const uomDisplay = document.getElementById('uom-display');
        const existingLotSection = document.getElementById('existing-lot-section');
        const newLotSection = document.getElementById('new-lot-section');
        const expiryRequired = document.getElementById('expiry-required');

        // Load lots when item changes
        itemSelect.addEventListener('change', function() {
            const itemId = this.value;
            const option = this.options[this.selectedIndex];
            
            uomDisplay.value = option.dataset.uom || 'Pilih item dulu';
            
            // Update expiry requirement
            expiryRequired.classList.toggle('hidden', option.dataset.requiresExpiry !== '1');
            
            lotSelect.innerHTML = '<option value="">Tanpa lot</option>';
            
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

        // Toggle lot mode
        document.querySelectorAll('[name="lot_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                existingLotSection.classList.toggle('hidden', this.value === 'new');
                newLotSection.classList.toggle('hidden', this.value !== 'new');
            });
        });
    </script>
    @endpush
</x-app-layout>
