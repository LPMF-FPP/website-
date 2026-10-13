<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pengeluaran Stok"
            class="inventory-page-header"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Transaksi'],
                ['label' => 'Pengeluaran']
            ]"
        />
    </x-slot>

    <div class="inventory-workflow max-w-5xl mx-auto">
        @if(session('success'))
            <div class="mb-6 rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
                @if(session('issue_confirmation'))
                    @php($issue = session('issue_confirmation'))
                    <dl class="mt-3 grid gap-x-4 gap-y-2 sm:grid-cols-2">
                        <div><dt class="font-semibold">Item</dt><dd>{{ $issue['item_code'] ?? 'Kode belum ditetapkan' }} · {{ $issue['item_name'] }}</dd></div>
                        <div><dt class="font-semibold">Lot</dt><dd>{{ $issue['lot_mode'] === 'untracked' ? 'Untracked, tanpa lot' : $issue['lot_no'] }}</dd></div>
                        <div><dt class="font-semibold">Sumber</dt><dd>{{ $issue['location_name'] }}</dd></div>
                        <div><dt class="font-semibold">Jumlah keluar</dt><dd>{{ number_format((float) $issue['qty'], 3, ',', '.') }} {{ $issue['uom'] }}</dd></div>
                        <div><dt class="font-semibold">Referensi / movement</dt><dd>{{ $issue['reference_type'] }} · #{{ $issue['movement_id'] }}</dd></div>
                        <div><dt class="font-semibold">Saldo / tersedia setelah keluar</dt><dd>{{ number_format((float) $issue['resulting_balance'], 3, ',', '.') }} / {{ number_format((float) $issue['resulting_available'], 3, ',', '.') }} {{ $issue['uom'] }}</dd></div>
                    </dl>
                    @if($issue['can_view_stock_card'])
                        <a href="{{ $issue['stock_card_url'] }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-green-900 underline">Lihat riwayat stok barang ini</a>
                    @else
                        <p class="mt-3 text-sm text-green-900">Kartu stok memerlukan akses lihat lokasi ini.</p>
                    @endif
                @endif
            </div>
        @endif

        @if($errors->any())
            <div role="alert" tabindex="-1" x-data="{}" x-init="$nextTick(() => $el.focus())" class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Pengeluaran belum dicatat:</p>
                @include('inventory.partials.transaction-errors', ['operation' => 'issue'])
            </div>
        @endif

        @if($mode !== \App\Models\InventoryMutationMode::ACTIVE)
            <p role="status" class="mb-6 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">Perubahan stok saat ini {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'sedang mencatat stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}. Pengeluaran stok hanya dapat dicatat saat perubahan stok aktif.</p>
        @endif

        <div class="card">
            <h2 class="mb-4 font-semibold semantic-text">1. Pilih item, lot, dan lokasi</h2>
            <form
                id="issue-form"
                method="POST"
                action="{{ route('inventory.transaction.issue') }}"
                data-prefill-item-id="{{ old('item_id', request('item_id')) }}"
                data-prefill-lot-id="{{ old('lot_id', request('lot_id')) }}"
            >
                @csrf

                <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,1fr)]">
                    <div class="space-y-6">
                    <!-- Item -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="item-select" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih item...</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-uom="{{ $item->uom }}" data-lot-policy="{{ $lotTrackingModes[$item->item_type] ?? 'unclassified' }}" {{ (string) old('item_id', request('item_id')) === (string) $item->id ? 'selected' : '' }}>
                                    {{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }} ({{ $item->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Explicit lot mode and lot -->
                    <div class="space-y-3">
                        <p id="issue-lot-mode-label" class="text-sm font-medium text-gray-700">Mode lot sesuai kebijakan item <span class="text-red-500">*</span></p>
                        <div role="group" aria-labelledby="issue-lot-mode-label" class="flex flex-wrap gap-4">
                            <label data-issue-lot-choice="tracked" class="hidden min-h-11 items-center gap-2">
                                <input type="radio" name="lot_mode" value="existing" {{ old('lot_mode', request('lot_id') ? 'existing' : '') === 'existing' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Pilih satu lot</span>
                            </label>
                            <label data-issue-lot-choice="untracked" class="hidden min-h-11 items-center gap-2">
                                <input type="radio" name="lot_mode" value="untracked" {{ old('lot_mode') === 'untracked' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Tanpa lot (untracked)</span>
                            </label>
                        </div>
                        @error('lot_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <p id="issue-policy-warning" class="hidden text-sm text-amber-800" role="status">Kebijakan lot belum disetujui. Hubungi administrator inventori.</p>
                        <p id="issue-mode-error" class="hidden text-sm text-red-700" role="alert">Pilih satu mode lot yang sesuai dengan kebijakan item.</p>
                        <div id="tracked-lot-section" class="hidden">
                        <label for="lot-select" class="block text-sm font-medium text-gray-700 mb-1">Lot (FEFO rekomendasi, wajib pilih satu)</label>
                        <select name="lot_id" id="lot-select"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih lot aktif...</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Lot diurutkan FEFO. Sistem tidak memilih lot atau membagi jumlah secara otomatis.</p>
                        <div id="lot-warning" class="mt-2 hidden rounded border border-red-200 bg-red-50 p-2 text-sm text-red-700"></div>
                        </div>
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
                    <h2 class="font-semibold semantic-text">2. Konteks stok dan jumlah</h2>
                    <div class="p-4 bg-blue-50 rounded-lg dark:bg-blue-950">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-blue-800 dark:text-blue-200">Saldo / tersedia:</span>
                            <span class="text-right text-sm font-bold text-blue-900 dark:text-blue-100"><span id="on-hand-qty">-</span><span class="block">Tersedia: <span id="available-qty">-</span></span></span>
                        </div>
                    </div>
                    <input type="hidden" name="expected_on_hand_qty" id="expected-on-hand-qty">
                    <input type="hidden" name="expected_available_qty" id="expected-available-qty">
                    <p id="issue-balance-error" class="hidden text-sm text-red-700" role="alert" tabindex="-1">Saldo terkini belum dimuat. Pilih ulang item, lot, atau lokasi sebelum menyimpan.</p>

                    <!-- Quantity -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Keluar <span class="text-red-500">*</span></label>
                            <input type="number" step="0.001" name="qty" value="{{ old('qty') }}" required min="0.001" max="99999999999.999"
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
                            <option value="MANUAL" {{ old('reference_type', 'MANUAL') === 'MANUAL' ? 'selected' : '' }}>Manual</option>
                            @foreach($referenceTypes as $key => $label)
                                @if($key !== 'MANUAL')<option value="{{ $key }}" {{ old('reference_type') === $key ? 'selected' : '' }}>{{ $label }}</option>@endif
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
                    <aside class="space-y-4 xl:sticky xl:top-4">
                @include('inventory.partials.transaction-review', ['operation' => 'issue'])
                <h2 class="mt-6 font-semibold semantic-text">4. Konfirmasi pengeluaran</h2>
                <div class="mt-3 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.dashboard') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button id="issue-submit" type="submit" @disabled($mode !== \App\Models\InventoryMutationMode::ACTIVE) class="btn btn-primary min-h-11 rounded-md px-6 py-2 font-medium">
                        Simpan pengeluaran
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
        const onHandQty = document.getElementById('on-hand-qty');
        const availableQty = document.getElementById('available-qty');
        const expectedOnHandQty = document.getElementById('expected-on-hand-qty');
        const expectedAvailableQty = document.getElementById('expected-available-qty');
        const lotWarning = document.getElementById('lot-warning');
        const lotLoadStatus = document.getElementById('lot-load-status');
        const policyWarning = document.getElementById('issue-policy-warning');
        const modeError = document.getElementById('issue-mode-error');
        const balanceError = document.getElementById('issue-balance-error');
        const trackedLotSection = document.getElementById('tracked-lot-section');
        const lotModeInputs = Array.from(document.querySelectorAll('[name="lot_mode"]'));

        const issueForm = document.getElementById('issue-form');
        const prefillItemId = issueForm?.dataset?.prefillItemId || '';
        const prefillLotId = issueForm?.dataset?.prefillLotId || '';

        let currentUom = '';
        let lotRequestSequence = 0;
        let balanceRequestSequence = 0;

        const selectedLotMode = () => document.querySelector('[name="lot_mode"]:checked')?.value ?? '';

        function resetBalances(value = '-') {
            onHandQty.textContent = value;
            availableQty.textContent = value;
            expectedOnHandQty.value = '';
            expectedAvailableQty.value = '';
        }

        function updateIssueLotMode() {
            const selectedItem = itemSelect.options[itemSelect.selectedIndex];
            const policy = itemSelect.value ? (selectedItem?.dataset.lotPolicy ?? 'unclassified') : 'none';
            const tracked = policy === 'lot_tracked';
            const untracked = policy === 'untracked';
            const mode = selectedLotMode();
            document.querySelectorAll('[data-issue-lot-choice="tracked"]').forEach(label => label.classList.toggle('hidden', !tracked));
            document.querySelectorAll('[data-issue-lot-choice="untracked"]').forEach(label => label.classList.toggle('hidden', !untracked));
            policyWarning?.classList.toggle('hidden', !itemSelect.value || tracked || untracked);

            if (mode && ((tracked && mode !== 'existing') || (untracked && mode !== 'untracked') || (!tracked && !untracked))) {
                lotModeInputs.forEach(radio => { radio.checked = false; });
                lotSelect.value = '';
            }

            const effectiveMode = selectedLotMode();
            trackedLotSection.classList.toggle('hidden', !tracked || effectiveMode !== 'existing');
            lotSelect.disabled = !tracked || effectiveMode !== 'existing';
            lotSelect.required = tracked && effectiveMode === 'existing';
            if (untracked) lotSelect.value = '';
            updateBalance();
        }

        function updateBalance() {
            const requestSequence = ++balanceRequestSequence;
            const itemId = itemSelect.value;
            const lotId = lotSelect.value || '';
            const locationId = locationSelect.value;
            const selectedItem = itemSelect.options[itemSelect.selectedIndex];
            const policy = selectedItem?.dataset.lotPolicy;
            const mode = selectedLotMode();

            if (!itemId || !locationId || !policy || policy === 'unclassified'
                || (policy === 'lot_tracked' && (mode !== 'existing' || !lotId))
                || (policy === 'untracked' && mode !== 'untracked')) {
                resetBalances();
                return;
            }

            resetBalances('Memuat...');
            fetch(`{{ url('referensi/inventori/ajax/balance') }}?context=issue&lot_mode=${encodeURIComponent(mode)}&item_id=${encodeURIComponent(itemId)}&lot_id=${encodeURIComponent(lotId)}&location_id=${encodeURIComponent(locationId)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Gagal memuat saldo.');
                    return response.json();
                })
                .then(data => {
                    if (requestSequence !== balanceRequestSequence || itemId !== itemSelect.value
                        || locationId !== locationSelect.value || lotId !== (lotSelect.value || '')
                        || mode !== selectedLotMode()) return;
                    onHandQty.textContent = Number(data.on_hand_qty).toFixed(3) + ' ' + currentUom;
                    availableQty.textContent = Number(data.available_qty).toFixed(3) + ' ' + currentUom;
                    expectedOnHandQty.value = Number(data.on_hand_qty).toFixed(3);
                    expectedAvailableQty.value = Number(data.available_qty).toFixed(3);
                })
                .catch(() => {
                    if (requestSequence !== balanceRequestSequence) return;
                    resetBalances('Saldo gagal dimuat');
                });
        }

        itemSelect.addEventListener('change', function() {
            const itemId = this.value;
            const option = this.options[this.selectedIndex];

            currentUom = option.dataset.uom || '';
            uomDisplay.value = currentUom || 'Pilih item dulu';
            updateIssueLotMode();
            const requestSequence = ++lotRequestSequence;
            lotSelect.innerHTML = '<option value="">Pilih satu lot aktif...</option>';
            lotWarning.classList.add('hidden');

            if (!itemId || !locationSelect.value || option.dataset.lotPolicy !== 'lot_tracked' || selectedLotMode() !== 'existing') return;
            const locationId = locationSelect.value;
            if (lotLoadStatus) lotLoadStatus.textContent = 'Memuat rekomendasi lot FEFO...';

            fetch(`{{ url('referensi/inventori/ajax/lots') }}?context=issue&lot_mode=existing&item_id=${encodeURIComponent(itemId)}&location_id=${encodeURIComponent(locationId)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Gagal memuat rekomendasi lot.');
                    return response.json();
                })
                .then(lots => {
                    if (requestSequence !== lotRequestSequence || itemId !== itemSelect.value || locationId !== locationSelect.value) return;
                    if (lotLoadStatus) lotLoadStatus.textContent = lots.length ? '' : 'Tidak ada lot terkait pada lokasi ini.';
                    lots.forEach(lot => {
                        const opt = document.createElement('option');
                        opt.value = lot.id;
                        opt.textContent = lot.lot_no + (lot.expiry_date ? ` (kadaluarsa: ${lot.expiry_date})` : '');
                        opt.dataset.canIssue = lot.can_issue ? '1' : '0';
                        opt.dataset.expired = lot.is_expired ? '1' : '0';
                        if (!lot.can_issue) {
                            opt.textContent += ' (tidak dapat dikeluarkan: ' + (lot.is_expired ? 'kadaluarsa' : lot.status) + ')';
                            opt.disabled = true;
                        }
                        lotSelect.appendChild(opt);
                    });

                    if (prefillLotId) {
                        lotSelect.value = String(prefillLotId);
                        lotSelect.dispatchEvent(new Event('change'));
                    }

                    updateBalance();
                })
                .catch(() => {
                    if (requestSequence !== lotRequestSequence) return;
                    lotSelect.innerHTML = '<option value="">Lot gagal dimuat</option>';
                    if (lotLoadStatus) lotLoadStatus.textContent = 'Rekomendasi lot gagal dimuat. Coba ubah pilihan item atau lokasi.';
                });
        });

        lotSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.dataset.expired === '1') {
                lotWarning.textContent = 'Lot ini sudah kadaluarsa dan tidak dapat dikeluarkan.';
                lotWarning.classList.remove('hidden');
            } else if (option.dataset.canIssue === '0') {
                lotWarning.textContent = 'Lot ini tidak dapat dikeluarkan (status: quarantine/disposed).';
                lotWarning.classList.remove('hidden');
            } else {
                lotWarning.classList.add('hidden');
            }
            updateBalance();
        });

        lotModeInputs.forEach(radio => radio.addEventListener('change', () => {
            updateIssueLotMode();
            itemSelect.dispatchEvent(new Event('change'));
        }));

        locationSelect.addEventListener('change', function() {
            if (itemSelect.value) {
                itemSelect.dispatchEvent(new Event('change'));
            } else {
                updateBalance();
            }
        });

        if (prefillItemId) {
            itemSelect.value = String(prefillItemId);
            itemSelect.dispatchEvent(new Event('change'));
        }

        document.getElementById('issue-form').addEventListener('submit', function(event) {
            const selectedItem = itemSelect.options[itemSelect.selectedIndex];
            const policy = selectedItem?.dataset.lotPolicy;
            const mode = selectedLotMode();
            balanceError?.classList.add('hidden');
            if (!policy || policy === 'unclassified' || (policy === 'lot_tracked' && (mode !== 'existing' || !lotSelect.value))
                || (policy === 'untracked' && mode !== 'untracked')) {
                event.preventDefault();
                if (policy === 'unclassified') policyWarning?.classList.remove('hidden');
                else modeError?.classList.remove('hidden');
                (policy === 'unclassified' ? policyWarning : modeError)?.focus();
                return;
            }
            if (!expectedOnHandQty.value || !expectedAvailableQty.value) {
                event.preventDefault();
                balanceError?.classList.remove('hidden');
                balanceError?.focus();
                return;
            }
            const submitButton = document.getElementById('issue-submit');
            submitButton.disabled = true;
            submitButton.textContent = 'Memeriksa stok tersedia...';
        });
    </script>
@endpush
</x-app-layout>
