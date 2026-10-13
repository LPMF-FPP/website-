<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Penerimaan Stok"
            class="inventory-page-header"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Transaksi'],
                ['label' => 'Penerimaan']
            ]"
        />
    </x-slot>

    <div class="inventory-workflow max-w-5xl mx-auto">
        @if($mode !== \App\Models\InventoryMutationMode::ACTIVE)
            <p role="status" class="mb-6 inventory-status-warning rounded-md p-4 text-sm">Perubahan stok saat ini {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'sedang mencatat stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}. Penerimaan stok hanya dapat dicatat saat perubahan stok aktif. Hubungi administrator inventori jika perlu bantuan.</p>
        @endif
        @if(session('success'))
            <div class="mb-6 rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
                @if(session('receipt_confirmation'))
                    @php($receipt = session('receipt_confirmation'))
                    <dl class="mt-3 grid gap-x-4 gap-y-2 sm:grid-cols-2">
                        <div><dt class="font-semibold">Item</dt><dd>{{ $receipt['item_code'] ?? 'Kode belum ditetapkan' }} · {{ $receipt['item_name'] }}</dd></div>
                        <div><dt class="font-semibold">Mode lot</dt><dd>{{ $receipt['lot_mode_label'] }}</dd></div>
                        <div><dt class="font-semibold">Lokasi</dt><dd>{{ $receipt['location_name'] }}</dd></div>
                        <div><dt class="font-semibold">Jumlah diterima</dt><dd>{{ number_format((float) $receipt['qty'], 3, ',', '.') }} {{ $receipt['uom'] }}</dd></div>
                        <div><dt class="font-semibold">Referensi / movement</dt><dd>{{ $receipt['reference_type'] }} · #{{ $receipt['movement_id'] }}</dd></div>
                        <div><dt class="font-semibold">Saldo setelah penerimaan</dt><dd>{{ number_format((float) $receipt['resulting_balance'], 3, ',', '.') }} {{ $receipt['uom'] }}</dd></div>
                    </dl>
                    @if($receipt['can_view_stock_card'])
                        <a href="{{ $receipt['stock_card_url'] }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-green-900 underline">Lihat riwayat stok barang ini</a>
                    @else
                        <p class="mt-3 text-sm text-green-900">Kartu stok memerlukan akses lihat lokasi ini.</p>
                    @endif
                @endif
            </div>
        @endif

        @if($errors->any())
            <div role="alert" tabindex="-1" x-data="{}" x-init="$nextTick(() => $el.focus())" class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Periksa kembali penerimaan:</p>
                @include('inventory.partials.transaction-errors', ['operation' => 'receipt'])
            </div>
        @endif

        <div class="card">
            <form method="POST" action="{{ route('inventory.transaction.receipt') }}" id="receipt-form">
                @csrf

                <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,1fr)]">
                    <div class="space-y-6">
                    <h2 class="font-semibold semantic-text">1. Pilih item, lot, dan lokasi</h2>
                    <!-- Item -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="item-select" required
                            class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white @error('item_id') border-red-500 @enderror">
                                <option value="">Pilih item...</option>
                                @foreach($items as $item)
                                <option value="{{ $item->id }}" data-uom="{{ $item->uom }}" data-requires-expiry="{{ $item->requiresExpiry() ? '1' : '0' }}" data-lot-policy="{{ $lotTrackingModes[$item->item_type] ?? 'unclassified' }}"
                                    {{ (string) old('item_id', $selectedItemId) === (string) $item->id ? 'selected' : '' }}>
                                    {{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }} ({{ $item->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Lot Selection -->
                    <div class="p-4 bg-gray-50 rounded-lg space-y-4 dark:bg-accent-800">
                        <p id="lot-mode-label" class="text-sm font-semibold text-gray-800 dark:text-gray-100">Mode lot berdasarkan kebijakan item <span class="text-red-500">*</span></p>
                        <div role="group" aria-labelledby="lot-mode-label" class="flex flex-wrap items-center gap-4">
                            <label data-lot-choice="tracked" class="hidden min-h-11 items-center gap-2">
                                <input type="radio" name="lot_mode" value="existing" {{ old('lot_mode') === 'existing' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Lot yang ada</span>
                            </label>
                            @if(auth()->user()?->hasPermission('inventori.create'))
                                <label data-lot-choice="tracked-new" class="hidden min-h-11 items-center gap-2">
                                    <input type="radio" name="lot_mode" value="new" {{ old('lot_mode') === 'new' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Lot baru</span>
                                </label>
                            @endif
                            <label data-lot-choice="untracked" class="hidden min-h-11 items-center gap-2">
                                <input type="radio" name="lot_mode" value="untracked" {{ old('lot_mode') === 'untracked' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Tanpa lot (untracked)</span>
                            </label>
                        </div>
                        @error('lot_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <p id="unclassified-lot-policy" tabindex="-1" class="hidden text-sm text-amber-800 dark:text-amber-200" role="status">Kebijakan lot belum disetujui. Minta administrator inventori menetapkan klasifikasi sebelum menerima stok.</p>
                        <p id="lot-mode-error" tabindex="-1" class="hidden text-sm text-red-700 dark:text-red-300" role="alert">Pilih item, lokasi, dan satu mode lot sesuai kebijakan item.</p>

                        <div id="existing-lot-section" class="hidden">
                            <label for="lot-select" class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Pilih Lot <span class="text-red-500">*</span></label>
                            <select name="lot_id" id="lot-select"
                                class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white">
                                <option value="">Pilih item, lokasi, lalu lot...</option>
                            </select>
                            <p id="lot-load-status" class="mt-1 text-sm text-gray-600 dark:text-gray-300" role="status" aria-live="polite"></p>
                            @error('lot_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @if(auth()->user()?->hasPermission('inventori.create'))
                            <div id="new-lot-section" class="hidden space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Nomor Lot Baru <span class="text-red-500">*</span></label>
                                    <input type="text" name="new_lot_no" value="{{ old('new_lot_no') }}"
                                        class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white"
                                        placeholder="Contoh: LOT-2026-001">
                                    @error('new_lot_no')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">
                                        Tanggal Kadaluarsa <span id="expiry-required" class="text-red-500 hidden">*</span>
                                    </label>
                                    <input type="date" name="new_lot_expiry" value="{{ old('new_lot_expiry') }}"
                                        class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white">
                                    @error('new_lot_expiry')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Location -->
                    <div class="p-4 bg-gray-50 rounded-lg space-y-4 dark:bg-accent-800">
                        @error('location_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Lokasi Penyimpanan <span class="text-red-500">*</span></label>
                        <select name="location_id" id="location-select"
                            class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white @error('location_id') border-red-500 @enderror" required>
                            <option value="">Pilih lokasi...</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                    {{ $location->name }} ({{ $location->location_type_label }})
                                </option>
                            @endforeach
                        </select>
                        @error('location_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        <p class="text-xs text-gray-500 dark:text-gray-300">Lokasi baru dan aksesnya diatur melalui Master Lokasi.</p>
                    </div>

                    <!-- Quantity -->
                    <h2 class="font-semibold semantic-text">2. Konteks penerimaan dan jumlah</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Jumlah <span class="text-red-500">*</span></label>
                            <input type="number" step="0.001" name="qty" value="{{ old('qty') }}" required min="0.001"
                                class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white @error('qty') border-red-500 @enderror"
                                placeholder="0.00">
                            @error('qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Satuan</label>
                            <input type="text" id="uom-display" readonly
                                class="w-full rounded-md border-gray-300 bg-gray-100 shadow-sm text-gray-700 dark:border-white/20 dark:bg-accent-900 dark:text-gray-200"
                                value="Pilih item dulu">
                        </div>
                    </div>

                    <!-- Reference & Cost -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Referensi</label>
                            <select name="reference_type"
                                class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white">
                                <option value="MANUAL" {{ old('reference_type', 'MANUAL') === 'MANUAL' ? 'selected' : '' }}>Manual</option>
                                @foreach($referenceTypes as $key => $label)
                                    @if($key !== 'MANUAL')<option value="{{ $key }}" {{ old('reference_type') === $key ? 'selected' : '' }}>{{ $label }}</option>@endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Harga Satuan (Rp)</label>
                            <input type="number" step="0.01" name="unit_cost" value="{{ old('unit_cost') }}"
                                class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white"
                                placeholder="0.00">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-200">Catatan</label>
                        <textarea name="notes" rows="2"
                            class="w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white"
                            placeholder="Keterangan tambahan...">{{ old('notes') }}</textarea>
                    </div>
                    </div>
                    <aside class="space-y-4 xl:sticky xl:top-4">
                @include('inventory.partials.transaction-review', ['operation' => 'receipt'])
                <h2 class="mt-6 font-semibold semantic-text">4. Konfirmasi penerimaan</h2>
                <div class="mt-3 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.dashboard') }}" class="inline-flex min-h-11 items-center px-4 py-2 text-gray-700 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:text-gray-200 dark:hover:text-white">Batal</a>
                    <button id="receipt-submit" type="submit" class="btn btn-primary min-h-11 rounded-md px-6 py-2 font-medium">
                        Simpan penerimaan
                    </button>
                </div>
                    </aside>
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
        const existingLotSection = document.getElementById('existing-lot-section');
        const newLotSection = document.getElementById('new-lot-section');
        const unclassifiedLotPolicy = document.getElementById('unclassified-lot-policy');
        const lotModeError = document.getElementById('lot-mode-error');
        const lotLoadStatus = document.getElementById('lot-load-status');
        const expiryRequired = document.getElementById('expiry-required');
        const lotModeInputs = Array.from(document.querySelectorAll('[name="lot_mode"]'));
        let preselectedLotId = @json((string) old('lot_id', ''));

        const selectedLotMode = () => document.querySelector('[name="lot_mode"]:checked')?.value ?? '';
        const newLotNumberInput = newLotSection?.querySelector('[name="new_lot_no"]');
        const newLotExpiryInput = newLotSection?.querySelector('[name="new_lot_expiry"]');
        let lotRequestSequence = 0;
        let loadedLotContext = '';

        function updateLotModeOptions() {
            const option = itemSelect.options[itemSelect.selectedIndex];
            const hasSelectedItem = Boolean(itemSelect.value);
            const policy = hasSelectedItem ? (option?.dataset.lotPolicy ?? 'unclassified') : 'none';
            const tracked = policy === 'lot_tracked';
            const untracked = policy === 'untracked';
            const currentMode = selectedLotMode();

            document.querySelectorAll('[data-lot-choice="tracked"]').forEach(label => label.classList.toggle('hidden', !tracked));
            document.querySelectorAll('[data-lot-choice="tracked-new"]').forEach(label => label.classList.toggle('hidden', !tracked));
            document.querySelectorAll('[data-lot-choice="untracked"]').forEach(label => label.classList.toggle('hidden', !untracked));
            unclassifiedLotPolicy?.classList.toggle('hidden', !hasSelectedItem || tracked || untracked);

            const allowedModes = tracked ? ['existing', 'new'] : (untracked ? ['untracked'] : []);
            if (currentMode && !allowedModes.includes(currentMode)) {
                lotModeInputs.forEach(radio => { radio.checked = false; });
                lotSelect.value = '';
                if (newLotNumberInput) newLotNumberInput.value = '';
                if (newLotExpiryInput) newLotExpiryInput.value = '';
            }
            updateLotModeFields();
        }

        function updateLotModeFields() {
            const mode = selectedLotMode();
            existingLotSection.classList.toggle('hidden', mode !== 'existing');
            newLotSection?.classList.toggle('hidden', mode !== 'new');
            lotSelect.disabled = mode !== 'existing';
            lotSelect.required = mode === 'existing';
            if (newLotNumberInput) {
                newLotNumberInput.disabled = mode !== 'new';
                newLotNumberInput.required = mode === 'new';
            }

            const requiresExpiry = itemSelect.options[itemSelect.selectedIndex]?.dataset.requiresExpiry === '1';
            expiryRequired?.classList.toggle('hidden', mode !== 'new' || !requiresExpiry);
            if (newLotExpiryInput) {
                newLotExpiryInput.disabled = mode !== 'new';
                newLotExpiryInput.required = mode === 'new' && requiresExpiry;
            }
            if (mode === 'untracked') lotSelect.value = '';
            if (mode !== 'existing' && newLotNumberInput) newLotNumberInput.value = mode === 'new' ? newLotNumberInput.value : '';
            if (mode !== 'new' && newLotExpiryInput) newLotExpiryInput.value = mode === 'new' ? newLotExpiryInput.value : '';
        }

        function loadExistingLots() {
            const requestSequence = ++lotRequestSequence;
            const itemId = itemSelect.value;
            const locationId = locationSelect.value;
            const context = `${itemId}:${locationId}`;
            if (loadedLotContext && loadedLotContext !== context) {
                loadedLotContext = '';
                preselectedLotId = '';
                lotSelect.innerHTML = '<option value="">Pilih lot aktif...</option>';
            }
            if (selectedLotMode() !== 'existing' || !itemId || !locationId) {
                if (lotLoadStatus) lotLoadStatus.textContent = '';
                return;
            }
            if (loadedLotContext === context) return;
            lotSelect.innerHTML = '<option value="">Pilih lot aktif...</option>';
            if (lotLoadStatus) lotLoadStatus.textContent = 'Memuat lot aktif...';

            fetch(`{{ url('referensi/inventori/ajax/lots') }}?context=receipt&item_id=${encodeURIComponent(itemId)}&location_id=${encodeURIComponent(locationId)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Gagal memuat lot.');
                    return response.json();
                })
                .then(lots => {
                    if (requestSequence !== lotRequestSequence || itemId !== itemSelect.value || locationId !== locationSelect.value) return;
                    loadedLotContext = context;
                    if (lotLoadStatus) lotLoadStatus.textContent = lots.length ? '' : 'Belum ada lot aktif pada lokasi ini. Gunakan mode lot baru jika Anda berwenang.';
                    lots.forEach(lot => {
                        const lotOption = document.createElement('option');
                        lotOption.value = lot.id;
                        lotOption.textContent = lot.lot_no + (lot.expiry_date ? ` (kadaluarsa: ${lot.expiry_date})` : '');
                        lotSelect.appendChild(lotOption);
                    });
                    if (preselectedLotId) {
                        lotSelect.value = preselectedLotId;
                        preselectedLotId = '';
                    }
                })
                .catch(() => {
                    if (requestSequence !== lotRequestSequence || itemId !== itemSelect.value || locationId !== locationSelect.value) return;
                    lotSelect.innerHTML = '<option value="">Lot gagal dimuat. Coba lagi.</option>';
                    if (lotLoadStatus) lotLoadStatus.textContent = 'Lot gagal dimuat. Periksa koneksi lalu pilih ulang item atau lokasi.';
                });
        }

        itemSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            uomDisplay.value = option?.dataset.uom || 'Pilih item dulu';
            updateLotModeOptions();
            loadExistingLots();
        });

        locationSelect.addEventListener('change', loadExistingLots);
        lotModeInputs.forEach(radio => radio.addEventListener('change', () => {
            updateLotModeFields();
            loadExistingLots();
        }));

        document.getElementById('receipt-form').addEventListener('submit', function(e) {
            const selectedItem = itemSelect.options[itemSelect.selectedIndex];
            const policy = selectedItem?.dataset.lotPolicy;
            const locationId = locationSelect.value;
            if (!policy || policy === 'unclassified') {
                e.preventDefault();
                unclassifiedLotPolicy?.classList.remove('hidden');
                unclassifiedLotPolicy?.focus();
                return;
            }
            if (!locationId || !selectedLotMode()) {
                e.preventDefault();
                lotModeError?.classList.remove('hidden');
                lotModeError?.focus();
                return false;
            }
            lotModeError?.classList.add('hidden');
            const submitButton = document.getElementById('receipt-submit');
            submitButton.disabled = true;
            submitButton.textContent = 'Menyimpan penerimaan…';
        });

        itemSelect.dispatchEvent(new Event('change'));
        updateLotModeFields();
    </script>
    @endpush
</x-app-layout>
