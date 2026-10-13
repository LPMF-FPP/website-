<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Transfer Stok"
            class="inventory-page-header"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Transaksi'],
                ['label' => 'Transfer']
            ]"
        />
    </x-slot>

    <div class="inventory-workflow max-w-5xl mx-auto">
        @if(session('success'))
            <div role="status" class="mb-6 rounded border border-green-200 bg-green-50 p-4 text-sm text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100">
                <p class="font-semibold">{{ session('success') }}</p>
                @if(session('transfer_confirmation'))
                    @php($transfer = session('transfer_confirmation'))
                    <dl class="mt-3 grid gap-x-4 gap-y-2 sm:grid-cols-2">
                        <div><dt class="font-semibold">Item</dt><dd>{{ $transfer['item_code'] ?? 'Kode belum ditetapkan' }} · {{ $transfer['item_name'] }}</dd></div>
                        <div><dt class="font-semibold">Lot</dt><dd>{{ $transfer['lot_mode'] === 'untracked' ? 'Untracked, tanpa lot' : $transfer['lot_no'] }}</dd></div>
                        <div><dt class="font-semibold">Asal</dt><dd>{{ $transfer['source_location'] }}</dd></div>
                        <div><dt class="font-semibold">Tujuan</dt><dd>{{ $transfer['destination_location'] }}</dd></div>
                        <div><dt class="font-semibold">Jumlah / satuan</dt><dd>{{ number_format((float) $transfer['qty'], 3, ',', '.') }} {{ $transfer['uom'] }}</dd></div>
                        <div><dt class="font-semibold">Referensi / movement</dt><dd>{{ $transfer['reference_type'] }} · #{{ $transfer['movement_id'] }}</dd></div>
                        <div><dt class="font-semibold">Saldo asal / tersedia</dt><dd>{{ number_format((float) $transfer['source_balance'], 3, ',', '.') }} / {{ number_format((float) $transfer['source_available'], 3, ',', '.') }} {{ $transfer['uom'] }}</dd></div>
                        <div><dt class="font-semibold">Saldo tujuan / tersedia</dt><dd>{{ number_format((float) $transfer['destination_balance'], 3, ',', '.') }} / {{ number_format((float) $transfer['destination_available'], 3, ',', '.') }} {{ $transfer['uom'] }}</dd></div>
                    </dl>
                    @if($transfer['can_view_stock_card'])
                        <a href="{{ $transfer['stock_card_url'] }}" class="mt-3 inline-flex min-h-11 items-center font-semibold underline">Buka kartu stok transfer</a>
                    @endif
                @endif
            </div>
        @endif

        @if($errors->any())
            <div role="alert" tabindex="-1" x-data="{}" x-init="$nextTick(() => $el.focus())" class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Transfer belum dicatat:</p>
                @include('inventory.partials.transaction-errors', ['operation' => 'transfer'])
            </div>
        @endif

        @if($mode !== \App\Models\InventoryMutationMode::ACTIVE)
            <p role="status" class="mb-6 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">Perubahan stok saat ini {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'sedang mencatat stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}. Pemindahan barang hanya dapat dicatat saat perubahan stok aktif.</p>
        @endif

        <div class="card">
            <h2 class="mb-4 font-semibold semantic-text">1. Pilih item, lot, dan lokasi</h2>
            <form id="transfer-form" method="POST" action="{{ route('inventory.transaction.transfer') }}">
                @csrf
                <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,1fr)]">
                    <div class="space-y-6">
                    <div>
                        <label for="item-select" class="mb-1 block text-sm font-medium text-gray-700">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="item-select" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih item...</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-uom="{{ $item->uom }}" data-lot-policy="{{ $lotTrackingModes[$item->item_type] ?? 'unclassified' }}" {{ (string) old('item_id') === (string) $item->id ? 'selected' : '' }}>
                                    {{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }} ({{ $item->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-3">
                        <p id="transfer-lot-label" class="text-sm font-medium text-gray-700">Mode lot sesuai kebijakan item <span class="text-red-500">*</span></p>
                        <div role="group" aria-labelledby="transfer-lot-label" class="flex flex-wrap gap-4">
                            <label data-transfer-mode="tracked" class="hidden min-h-11 items-center gap-2">
                                <input type="radio" name="lot_mode" value="existing" {{ old('lot_mode') === 'existing' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Pilih satu lot</span>
                            </label>
                            <label data-transfer-mode="untracked" class="hidden min-h-11 items-center gap-2">
                                <input type="radio" name="lot_mode" value="untracked" {{ old('lot_mode') === 'untracked' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Tanpa lot (untracked)</span>
                            </label>
                        </div>
                        @error('lot_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <p id="transfer-policy-warning" class="hidden text-sm text-amber-800" role="status">Kebijakan lot belum disetujui. Hubungi administrator inventori.</p>
                        <div id="transfer-lot-section" class="hidden">
                            <label for="lot-select" class="mb-1 block text-sm font-medium text-gray-700">Lot (FEFO rekomendasi; pilih satu)</label>
                            <select name="lot_id" id="lot-select" disabled class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Pilih lot aktif...</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">FEFO hanya mengurutkan rekomendasi. Sistem tidak memilih lot atau membagi jumlah secara otomatis.</p>
                            <p id="transfer-lot-status" class="mt-1 text-sm text-gray-600" role="status" aria-live="polite"></p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="from-location" class="mb-1 block text-sm font-medium text-gray-700">Dari lokasi <span class="text-red-500">*</span></label>
                            <select name="from_location_id" id="from-location" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Pilih lokasi asal...</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ (string) old('from_location_id') === (string) $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                                @endforeach
                            </select>
                            @error('from_location_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="to-location" class="mb-1 block text-sm font-medium text-gray-700">Ke lokasi <span class="text-red-500">*</span></label>
                            <select name="to_location_id" id="to-location" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Pilih lokasi tujuan...</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ (string) old('to_location_id') === (string) $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                                @endforeach
                            </select>
                            @error('to_location_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2" aria-live="polite">
                        <section class="rounded-lg bg-blue-50 p-4 dark:bg-blue-950" aria-labelledby="transfer-source-heading">
                            <h2 id="transfer-source-heading" class="text-sm font-semibold text-blue-900 dark:text-blue-100">2. Konteks lokasi asal</h2>
                            <p class="mt-2 text-sm text-blue-900 dark:text-blue-100">Saldo: <span id="source-on-hand">-</span></p>
                            <p class="text-sm text-blue-900 dark:text-blue-100">Tersedia: <span id="source-available">-</span></p>
                            <p class="mt-2 text-xs text-blue-800 dark:text-blue-200">Setelah transfer: <span id="source-projected">-</span></p>
                        </section>
                        <section class="rounded-lg bg-slate-50 p-4 dark:bg-slate-900" aria-labelledby="transfer-destination-heading">
                            <h2 id="transfer-destination-heading" class="text-sm font-semibold text-slate-900 dark:text-slate-100">Konteks lokasi tujuan</h2>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">Saldo: <span id="destination-on-hand">-</span></p>
                            <p class="text-sm text-slate-900 dark:text-slate-100">Tersedia: <span id="destination-available">-</span></p>
                            <p class="mt-2 text-xs text-slate-700 dark:text-slate-300">Setelah transfer: <span id="destination-projected">-</span></p>
                        </section>
                    </div>
                    <p id="transfer-context-error" class="hidden text-sm text-red-700" role="alert" tabindex="-1">Konteks kedua lokasi belum siap. Periksa pilihan dan muat ulang sebelum menyimpan.</p>
                    <input type="hidden" name="expected_on_hand_qty" id="expected-source-on-hand">
                    <input type="hidden" name="expected_available_qty" id="expected-source-available">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="transfer-qty" class="mb-1 block text-sm font-medium text-gray-700">Jumlah transfer <span class="text-red-500">*</span></label>
                            <input id="transfer-qty" type="number" step="0.001" name="qty" value="{{ old('qty') }}" required min="0.001" max="99999999999.999" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="0.000">
                            @error('qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="uom-display" class="mb-1 block text-sm font-medium text-gray-700">Satuan</label>
                            <input id="uom-display" type="text" readonly class="w-full rounded-md border-gray-200 bg-gray-100 shadow-sm text-gray-600" value="Pilih item dulu">
                        </div>
                    </div>

                    <div>
                        <label for="reference-type" class="mb-1 block text-sm font-medium text-gray-700">Referensi</label>
                        <select id="reference-type" name="reference_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="MANUAL" {{ old('reference_type', 'MANUAL') === 'MANUAL' ? 'selected' : '' }}>Manual</option>
                            @foreach($referenceTypes as $key => $label)
                                @if($key !== 'MANUAL')<option value="{{ $key }}" {{ old('reference_type') === $key ? 'selected' : '' }}>{{ $label }}</option>@endif
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="transfer-notes" class="mb-1 block text-sm font-medium text-gray-700">Catatan</label>
                        <textarea id="transfer-notes" name="notes" rows="2" maxlength="4000" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Alasan transfer...">{{ old('notes') }}</textarea>
                    </div>
                    </div>
                    <aside class="space-y-4 xl:sticky xl:top-4">
                @include('inventory.partials.transaction-review', ['operation' => 'transfer'])
                <h2 class="mt-6 font-semibold semantic-text">4. Konfirmasi transfer</h2>
                <div class="mt-3 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.dashboard') }}" class="inline-flex min-h-11 items-center px-4 text-gray-700 hover:text-gray-900">Batal</a>
                    <button id="transfer-submit" type="submit" @disabled($mode !== \App\Models\InventoryMutationMode::ACTIVE) class="btn btn-primary min-h-11 rounded-md px-6 py-2 font-medium">Simpan transfer</button>
                </div>
                    </aside>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const transferForm = document.getElementById('transfer-form');
        const itemSelect = document.getElementById('item-select');
        const lotSelect = document.getElementById('lot-select');
        const fromLocation = document.getElementById('from-location');
        const toLocation = document.getElementById('to-location');
        const qtyInput = document.getElementById('transfer-qty');
        const uomDisplay = document.getElementById('uom-display');
        const sourceOnHand = document.getElementById('source-on-hand');
        const sourceAvailable = document.getElementById('source-available');
        const destinationOnHand = document.getElementById('destination-on-hand');
        const destinationAvailable = document.getElementById('destination-available');
        const sourceProjected = document.getElementById('source-projected');
        const destinationProjected = document.getElementById('destination-projected');
        const expectedSourceOnHand = document.getElementById('expected-source-on-hand');
        const expectedSourceAvailable = document.getElementById('expected-source-available');
        const policyWarning = document.getElementById('transfer-policy-warning');
        const lotStatus = document.getElementById('transfer-lot-status');
        const contextError = document.getElementById('transfer-context-error');
        const lotModeInputs = Array.from(document.querySelectorAll('[name="lot_mode"]'));

        let currentUom = '';
        let lotRequestSequence = 0;
        let sourceRequestSequence = 0;
        let destinationRequestSequence = 0;
        let sourceContext = null;
        let destinationContext = null;

        const selectedLotMode = () => document.querySelector('[name="lot_mode"]:checked')?.value ?? '';
        const selectedItem = () => itemSelect.options[itemSelect.selectedIndex];

        function resetContext(endpoint, label = '-') {
            if (endpoint === 'source') {
                sourceContext = null;
                sourceOnHand.textContent = label;
                sourceAvailable.textContent = label;
                expectedSourceOnHand.value = '';
                expectedSourceAvailable.value = '';
            } else {
                destinationContext = null;
                destinationOnHand.textContent = label;
                destinationAvailable.textContent = label;
            }
            updateProjectedEffects();
        }

        function updateProjectedEffects() {
            const quantity = Number(qtyInput.value);
            sourceProjected.textContent = sourceContext && quantity > 0
                ? `${(sourceContext.onHand - quantity).toFixed(3)} ${currentUom}`
                : '-';
            destinationProjected.textContent = destinationContext && quantity > 0
                ? `${(destinationContext.onHand + quantity).toFixed(3)} ${currentUom}`
                : '-';
        }

        function updateLotMode() {
            const policy = itemSelect.value ? (selectedItem()?.dataset.lotPolicy ?? 'unclassified') : 'none';
            const tracked = policy === 'lot_tracked';
            const untracked = policy === 'untracked';
            document.querySelectorAll('[data-transfer-mode="tracked"]').forEach((label) => label.classList.toggle('hidden', !tracked));
            document.querySelectorAll('[data-transfer-mode="untracked"]').forEach((label) => label.classList.toggle('hidden', !untracked));
            policyWarning.classList.toggle('hidden', !itemSelect.value || tracked || untracked);
            const mode = selectedLotMode();
            if (mode && ((tracked && mode !== 'existing') || (untracked && mode !== 'untracked') || (!tracked && !untracked))) {
                lotModeInputs.forEach((radio) => { radio.checked = false; });
                lotSelect.value = '';
            }
            const activeMode = selectedLotMode();
            const lotSection = document.getElementById('transfer-lot-section');
            lotSection.classList.toggle('hidden', !tracked || activeMode !== 'existing');
            lotSelect.disabled = !tracked || activeMode !== 'existing';
            lotSelect.required = tracked && activeMode === 'existing';
            if (untracked) lotSelect.value = '';
        }

        function requestMatches(endpoint, itemId, lotId, locationId, mode, sequence) {
            const latestSequence = endpoint === 'source' ? sourceRequestSequence : destinationRequestSequence;
            return sequence === latestSequence && itemId === itemSelect.value
                && lotId === (lotSelect.value || '') && mode === selectedLotMode()
                && (endpoint === 'source' ? locationId === fromLocation.value : locationId === toLocation.value)
                && fromLocation.value !== toLocation.value;
        }

        function loadBalance(endpoint) {
            const locationId = endpoint === 'source' ? fromLocation.value : toLocation.value;
            const sequence = endpoint === 'source' ? ++sourceRequestSequence : ++destinationRequestSequence;
            const itemId = itemSelect.value;
            const lotId = lotSelect.value || '';
            const mode = selectedLotMode();
            const policy = selectedItem()?.dataset.lotPolicy;
            const validTuple = itemId && locationId && fromLocation.value !== toLocation.value && policy
                && policy !== 'unclassified'
                && ((policy === 'lot_tracked' && mode === 'existing' && lotId) || (policy === 'untracked' && mode === 'untracked'));

            if (!validTuple) {
                resetContext(endpoint);
                return;
            }

            resetContext(endpoint, 'Memuat...');
            const params = new URLSearchParams({
                context: 'transfer',
                balance_endpoint: endpoint,
                item_id: itemId,
                lot_id: lotId,
                lot_mode: mode,
                location_id: locationId,
                from_location_id: fromLocation.value,
            });
            fetch(`{{ url('referensi/inventori/ajax/balance') }}?${params.toString()}`)
                .then((response) => {
                    if (!response.ok) throw new Error('Konteks saldo tidak dapat dimuat.');
                    return response.json();
                })
                .then((data) => {
                    if (!requestMatches(endpoint, itemId, lotId, locationId, mode, sequence)) return;
                    const context = { onHand: Number(data.on_hand_qty), available: Number(data.available_qty) };
                    if (endpoint === 'source') {
                        sourceContext = context;
                        sourceOnHand.textContent = `${context.onHand.toFixed(3)} ${currentUom}`;
                        sourceAvailable.textContent = `${context.available.toFixed(3)} ${currentUom}`;
                        expectedSourceOnHand.value = context.onHand.toFixed(3);
                        expectedSourceAvailable.value = context.available.toFixed(3);
                    } else {
                        destinationContext = context;
                        destinationOnHand.textContent = `${context.onHand.toFixed(3)} ${currentUom}`;
                        destinationAvailable.textContent = `${context.available.toFixed(3)} ${currentUom}`;
                    }
                    updateProjectedEffects();
                })
                .catch(() => {
                    if (!requestMatches(endpoint, itemId, lotId, locationId, mode, sequence)) return;
                    resetContext(endpoint, 'Gagal dimuat');
                    contextError.classList.remove('hidden');
                });
        }

        function updateTransferContext() {
            contextError.classList.add('hidden');
            updateLotMode();
            loadBalance('source');
            loadBalance('destination');
        }

        function loadLots() {
            const sequence = ++lotRequestSequence;
            const itemId = itemSelect.value;
            const locationId = fromLocation.value;
            const mode = selectedLotMode();
            lotSelect.innerHTML = '<option value="">Pilih lot aktif...</option>';
            lotStatus.textContent = '';
            resetContext('source');
            resetContext('destination');
            if (selectedItem()?.dataset.lotPolicy === 'untracked' && mode === 'untracked') {
                updateTransferContext();
                return;
            }
            if (!itemId || !locationId || selectedItem()?.dataset.lotPolicy !== 'lot_tracked' || mode !== 'existing') return;

            lotStatus.textContent = 'Memuat rekomendasi lot FEFO...';
            const params = new URLSearchParams({ context: 'transfer', lot_mode: mode, item_id: itemId, location_id: locationId });
            fetch(`{{ url('referensi/inventori/ajax/lots') }}?${params.toString()}`)
                .then((response) => {
                    if (!response.ok) throw new Error('Rekomendasi lot tidak dapat dimuat.');
                    return response.json();
                })
                .then((lots) => {
                    if (sequence !== lotRequestSequence || itemId !== itemSelect.value || locationId !== fromLocation.value || mode !== selectedLotMode()) return;
                    lots.forEach((lot) => {
                        const option = document.createElement('option');
                        option.value = lot.id;
                        option.textContent = lot.lot_no + (lot.expiry_date ? ` (kedaluwarsa: ${lot.expiry_date})` : '');
                        option.dataset.canIssue = lot.can_issue ? '1' : '0';
                        if (!lot.can_issue) {
                            option.disabled = true;
                            option.textContent += ` (tidak dapat ditransfer: ${lot.is_expired ? 'kedaluwarsa' : lot.status})`;
                        }
                        lotSelect.appendChild(option);
                    });
                    lotStatus.textContent = lots.length ? 'Pilih satu lot aktif untuk transfer.' : 'Tidak ada lot terkait pada lokasi asal ini.';
                    const oldLotId = @js((string) old('lot_id', ''));
                    if (oldLotId !== '') {
                        lotSelect.value = oldLotId;
                    }
                    updateTransferContext();
                })
                .catch(() => {
                    if (sequence !== lotRequestSequence) return;
                    lotSelect.innerHTML = '<option value="">Lot gagal dimuat</option>';
                    lotStatus.textContent = 'Rekomendasi lot gagal dimuat. Coba ubah pilihan item atau lokasi asal.';
                });
        }

        itemSelect.addEventListener('change', () => {
            const option = selectedItem();
            currentUom = option?.dataset.uom ?? '';
            uomDisplay.value = currentUom || 'Pilih item dulu';
            updateLotMode();
            loadLots();
        });
        lotModeInputs.forEach((radio) => radio.addEventListener('change', () => {
            updateLotMode();
            loadLots();
        }));
        lotSelect.addEventListener('change', updateTransferContext);
        fromLocation.addEventListener('change', () => {
            updateEndpoints();
            loadLots();
        });
        toLocation.addEventListener('change', updateTransferContext);
        qtyInput.addEventListener('input', updateProjectedEffects);

        function updateEndpoints() {
            Array.from(toLocation.options).forEach((option) => {
                option.disabled = option.value !== '' && option.value === fromLocation.value;
            });
            if (toLocation.value && toLocation.value === fromLocation.value) toLocation.value = '';
            updateTransferContext();
        }

        transferForm.addEventListener('submit', (event) => {
            const policy = selectedItem()?.dataset.lotPolicy;
            const mode = selectedLotMode();
            const validMode = policy === 'lot_tracked' ? mode === 'existing' && Boolean(lotSelect.value)
                : policy === 'untracked' && mode === 'untracked';
            if (!validMode || !fromLocation.value || !toLocation.value || fromLocation.value === toLocation.value) {
                event.preventDefault();
                contextError.textContent = 'Pilih mode lot, satu lot bila diwajibkan, serta dua lokasi yang berbeda.';
                contextError.classList.remove('hidden');
                contextError.focus();
                return;
            }
            if (!sourceContext || !destinationContext || !expectedSourceOnHand.value || !expectedSourceAvailable.value) {
                event.preventDefault();
                contextError.textContent = 'Konteks kedua lokasi belum siap. Periksa pilihan dan muat ulang sebelum menyimpan.';
                contextError.classList.remove('hidden');
                contextError.focus();
                return;
            }
            document.getElementById('transfer-submit').disabled = true;
            document.getElementById('transfer-submit').textContent = 'Memeriksa stok...';
        });

        function initializeTransferForm() {
            updateEndpoints();
            if (itemSelect.value) loadLots();
        }

        initializeTransferForm();
    </script>
    @endpush
</x-app-layout>
