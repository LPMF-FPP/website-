<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Disposal Material Inventori"
            class="inventory-page-header"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Transaksi'],
                ['label' => 'Disposal material']
            ]"
        />
    </x-slot>

    <div class="inventory-workflow max-w-5xl mx-auto">
        @if(session('success'))
            <div role="status" class="mb-6 rounded border border-green-200 bg-green-50 p-4 text-sm text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100">
                <p class="font-semibold">{{ session('success') }}</p>
                @if(session('disposal_confirmation'))
                    @php($disposal = session('disposal_confirmation'))
                    <dl class="mt-3 grid gap-x-4 gap-y-2 sm:grid-cols-2">
                        <div><dt class="font-semibold">Material</dt><dd>{{ $disposal['item_code'] ?? 'Kode belum ditetapkan' }} · {{ $disposal['item_name'] }}</dd></div>
                        <div><dt class="font-semibold">Lot</dt><dd>{{ $disposal['lot_mode'] === 'untracked' ? 'Untracked, tanpa lot' : $disposal['lot_no'] }}</dd></div>
                        <div><dt class="font-semibold">Lokasi sumber</dt><dd>{{ $disposal['location_name'] }}</dd></div>
                        <div><dt class="font-semibold">Jumlah disposal</dt><dd>{{ number_format((float) $disposal['qty'], 3, ',', '.') }} {{ $disposal['uom'] }}</dd></div>
                        <div><dt class="font-semibold">Alasan</dt><dd>{{ $disposal['reason_label'] }}</dd></div>
                        <div><dt class="font-semibold">Movement</dt><dd>#{{ $disposal['movement_id'] }}</dd></div>
                        <div><dt class="font-semibold">Saldo setelah disposal</dt><dd>{{ number_format((float) $disposal['resulting_balance'], 3, ',', '.') }} / tersedia {{ number_format((float) $disposal['resulting_available'], 3, ',', '.') }} {{ $disposal['uom'] }}</dd></div>
                        @if($disposal['lot_status'] === 'DISPOSED')
                            <div><dt class="font-semibold">Status lot</dt><dd>Lot telah habis dan ditandai disposed.</dd></div>
                        @endif
                    </dl>
                    @if($disposal['can_view_stock_card'])
                        <a href="{{ $disposal['stock_card_url'] }}" class="mt-3 inline-flex min-h-11 items-center font-semibold underline">Lihat riwayat stok barang ini</a>
                    @endif
                @endif
            </div>
        @endif

        @if($errors->any())
            <div role="alert" tabindex="-1" x-data="{}" x-init="$nextTick(() => $el.focus())" class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Disposal belum dicatat:</p>
                @include('inventory.partials.transaction-errors', ['operation' => 'disposal'])
            </div>
        @endif

        @if($mode !== \App\Models\InventoryMutationMode::ACTIVE)
            <p role="status" class="mb-6 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">Perubahan stok saat ini {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'sedang mencatat stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}. Pengurangan stok hanya dapat dicatat saat perubahan stok aktif.</p>
        @endif

        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-950 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
            <p class="font-semibold">Disposal material inventori</p>
            <p class="mt-1">Tindakan ini mengurangi stok barang atau bahan laboratorium secara permanen dan dicatat dalam riwayat perubahan stok.</p>
            <p class="mt-2 font-medium">Fitur ini bukan pemusnahan sampel. Pemusnahan sampel tetap dilakukan melalui workflow sampel yang terpisah.</p>
        </div>

        <div class="card">
            <h2 class="mb-4 font-semibold semantic-text">1. Pilih material, lot, dan lokasi</h2>
            <form id="disposal-form" method="POST" action="{{ route('inventory.transaction.dispose') }}">
                @csrf
                <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,1fr)]">
                    <div class="space-y-6">
                    <div>
                        <label for="item-select" class="mb-1 block text-sm font-medium text-gray-700">Material inventori <span class="text-red-500">*</span></label>
                        <select name="item_id" id="item-select" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih item...</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-uom="{{ $item->uom }}" data-code="{{ $item->item_code ?? 'Kode belum ditetapkan' }}" data-name="{{ $item->name }}" data-lot-policy="{{ $lotTrackingModes[$item->item_type] ?? 'unclassified' }}" {{ (string) old('item_id') === (string) $item->id ? 'selected' : '' }}>
                                    {{ $item->item_code ?? 'Kode belum ditetapkan' }} · {{ $item->name }} ({{ $item->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-3">
                        <p id="dispose-lot-label" class="text-sm font-medium text-gray-700">Mode lot sesuai kebijakan item <span class="text-red-500">*</span></p>
                        <div role="group" aria-labelledby="dispose-lot-label" class="flex flex-wrap gap-4">
                            <label data-disposal-mode="tracked" class="hidden min-h-11 items-center gap-2">
                                <input type="radio" name="lot_mode" value="existing" {{ old('lot_mode') === 'existing' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Pilih satu lot</span>
                            </label>
                            <label data-disposal-mode="untracked" class="hidden min-h-11 items-center gap-2">
                                <input type="radio" name="lot_mode" value="untracked" {{ old('lot_mode') === 'untracked' ? 'checked' : '' }} class="text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Tanpa lot (untracked)</span>
                            </label>
                        </div>
                        @error('lot_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <p id="disposal-policy-warning" class="hidden text-sm text-amber-800" role="status">Kebijakan lot belum disetujui. Hubungi administrator inventori.</p>
                        <div id="disposal-lot-section" class="hidden">
                            <label for="lot-select" class="mb-1 block text-sm font-medium text-gray-700">Lot material yang akan dibuang</label>
                            <select name="lot_id" id="lot-select" disabled class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Pilih lot...</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-600">Lot expired atau karantina dapat dipilih untuk disposal. Lot yang sudah disposed tidak dapat diproses kembali.</p>
                            <p id="disposal-lot-status" class="mt-1 text-sm text-gray-600" role="status" aria-live="polite"></p>
                        </div>
                    </div>

                    <div>
                        <label for="location-select" class="mb-1 block text-sm font-medium text-gray-700">Lokasi sumber <span class="text-red-500">*</span></label>
                        <select name="location_id" id="location-select" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih lokasi...</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ (string) old('location_id') === (string) $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('location_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <section class="rounded-lg bg-red-50 p-4 dark:bg-red-950" aria-labelledby="disposal-stock-heading" aria-live="polite">
                        <h2 id="disposal-stock-heading" class="text-sm font-semibold text-red-900 dark:text-red-100">Jumlah stok barang, lot, dan lokasi yang dipilih</h2>
                        <p class="mt-2 text-sm text-red-900 dark:text-red-100">Saldo: <span id="on-hand-qty">-</span></p>
                        <p class="text-sm text-red-900 dark:text-red-100">Tersedia setelah reservasi: <span id="available-qty">-</span></p>
                        <p class="mt-2 text-xs text-red-800 dark:text-red-200">Stok tersedia dihitung sebagai saldo dikurangi jumlah yang telah direservasi.</p>
                    </section>
                    <h2 class="font-semibold semantic-text">2. Konteks stok, jumlah, dan alasan</h2>
                    <p id="disposal-context-error" class="hidden text-sm text-red-700" role="alert" tabindex="-1">Konteks saldo belum siap. Periksa pilihan item, lot, dan lokasi, lalu muat ulang.</p>
                    <input type="hidden" name="expected_on_hand_qty" id="expected-on-hand">
                    <input type="hidden" name="expected_available_qty" id="expected-available">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="disposal-qty" class="mb-1 block text-sm font-medium text-gray-700">Jumlah disposal <span class="text-red-500">*</span></label>
                            <input id="disposal-qty" type="number" step="0.001" name="qty" value="{{ old('qty') }}" required min="0.001" max="99999999999.999" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="0.000">
                            @error('qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="uom-display" class="mb-1 block text-sm font-medium text-gray-700">Satuan</label>
                            <input id="uom-display" type="text" readonly class="w-full rounded-md border-gray-200 bg-gray-100 shadow-sm text-gray-600" value="Pilih item dulu">
                        </div>
                    </div>

                    <div>
                        <label for="reason-code" class="mb-1 block text-sm font-medium text-gray-700">Alasan disposal <span class="text-red-500">*</span></label>
                        <select id="reason-code" name="reason_code" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih alasan...</option>
                            @foreach($reasonCodes as $key => $label)
                                <option value="{{ $key }}" {{ old('reason_code') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reason_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="disposal-notes" class="mb-1 block text-sm font-medium text-gray-700">Catatan / dokumen pendukung</label>
                        <textarea id="disposal-notes" name="notes" rows="3" maxlength="4000" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Nomor dokumen atau keterangan pendukung disposal...">{{ old('notes') }}</textarea>
                    </div>

                    </div>
                    <aside class="space-y-4 xl:sticky xl:top-4">
                    <section x-data="inventoryTransactionReview({ operation: 'dispose', allowed: @js($mode === \App\Models\InventoryMutationMode::ACTIVE) })" class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950" aria-labelledby="disposal-review-heading">
                        <h2 id="disposal-review-heading" class="text-sm font-semibold text-amber-950 dark:text-amber-100">3. Tinjau sebelum konfirmasi</h2>
                        <dl class="mt-3 grid gap-x-4 gap-y-2 sm:grid-cols-2">
                            <div><dt class="font-semibold">Material</dt><dd id="review-item">-</dd></div>
                            <div><dt class="font-semibold">Lot</dt><dd id="review-lot">-</dd></div>
                            <div><dt class="font-semibold">Lokasi sumber</dt><dd id="review-location">-</dd></div>
                            <div><dt class="font-semibold">Jumlah / alasan</dt><dd id="review-quantity">-</dd></div>
                        </dl>
                        <label class="mt-4 flex min-h-11 items-center gap-3 text-sm font-medium text-amber-950 dark:text-amber-100">
                            <input type="checkbox" name="review_confirmed" value="1" required class="rounded border-amber-500 text-red-700 focus:ring-red-600">
                            <span>Saya telah memeriksa barang, lot, lokasi, jumlah, dan alasan pengurangan stok di atas.</span>
                        </label>
                        @error('review_confirmed')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        <p role="status" aria-live="polite" class="mt-3 text-sm semantic-text-muted" x-text="effect"></p>
                    </section>
                <div class="flex flex-wrap justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.dashboard') }}" class="inline-flex min-h-11 items-center px-4 text-gray-700 hover:text-gray-900">Batal</a>
                    <button id="dispose-submit" type="submit" @disabled($mode !== \App\Models\InventoryMutationMode::ACTIVE) class="min-h-11 rounded-md bg-red-700 px-6 py-2 font-medium text-white hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:border-dashed disabled:bg-red-800 dark:focus-visible:ring-red-300 dark:focus-visible:ring-offset-slate-900">4. Konfirmasi disposal material</button>
                </div>
                    </aside>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const disposalForm = document.getElementById('disposal-form');
        const itemSelect = document.getElementById('item-select');
        const lotSelect = document.getElementById('lot-select');
        const locationSelect = document.getElementById('location-select');
        const qtyInput = document.getElementById('disposal-qty');
        const reasonSelect = document.getElementById('reason-code');
        const reviewCheckbox = document.querySelector('[name="review_confirmed"]');
        const currentItem = () => itemSelect.options[itemSelect.selectedIndex];
        const selectedLotMode = () => document.querySelector('[name="lot_mode"]:checked')?.value ?? '';

        const currentUom = document.getElementById('uom-display');
        const onHandQty = document.getElementById('on-hand-qty');
        const availableQty = document.getElementById('available-qty');
        const expectedOnHand = document.getElementById('expected-on-hand');
        const expectedAvailable = document.getElementById('expected-available');
        const policyWarning = document.getElementById('disposal-policy-warning');
        const lotStatus = document.getElementById('disposal-lot-status');
        const contextError = document.getElementById('disposal-context-error');

        let currentUnit = '';
        let lotRequestSequence = 0;
        let balanceRequestSequence = 0;
        let balanceContext = null;

        function resetBalance(label = '-') {
            balanceContext = null;
            onHandQty.textContent = label;
            availableQty.textContent = label;
            expectedOnHand.value = '';
            expectedAvailable.value = '';
        }

        function clearReviewConfirmation() {
            reviewCheckbox.checked = false;
            updateReviewSummary();
        }

        function updateModeVisibility() {
            const policy = itemSelect.value ? (currentItem()?.dataset.lotPolicy ?? 'unclassified') : 'none';
            const tracked = policy === 'lot_tracked';
            const untracked = policy === 'untracked';
            document.querySelectorAll('[data-disposal-mode="tracked"]').forEach((label) => label.classList.toggle('hidden', !tracked));
            document.querySelectorAll('[data-disposal-mode="untracked"]').forEach((label) => label.classList.toggle('hidden', !untracked));
            policyWarning.classList.toggle('hidden', !itemSelect.value || tracked || untracked);
            const mode = selectedLotMode();
            if (mode && ((tracked && mode !== 'existing') || (untracked && mode !== 'untracked') || (!tracked && !untracked))) {
                document.querySelectorAll('[name="lot_mode"]').forEach((radio) => { radio.checked = false; });
                lotSelect.value = '';
            }
            const trackedMode = tracked && selectedLotMode() === 'existing';
            document.getElementById('disposal-lot-section').classList.toggle('hidden', !trackedMode);
            lotSelect.disabled = !trackedMode;
            lotSelect.required = trackedMode;
            if (untracked) lotSelect.value = '';
        }

        function updateReviewSummary() {
            const option = currentItem();
            const policy = option?.dataset.lotPolicy;
            const mode = selectedLotMode();
            const lotOption = lotSelect.options[lotSelect.selectedIndex];
            const locationOption = locationSelect.options[locationSelect.selectedIndex];
            const selectedReason = reasonSelect.options[reasonSelect.selectedIndex];
            const tupleReady = balanceContext && expectedOnHand.value !== '' && expectedAvailable.value !== '';
            document.getElementById('review-item').textContent = option?.value ? `${option.dataset.code} · ${option.dataset.name}` : '-';
            document.getElementById('review-lot').textContent = mode === 'untracked' ? 'Untracked, tanpa lot'
                : (lotSelect.value ? lotOption.textContent : '-');
            document.getElementById('review-location').textContent = locationSelect.value ? locationOption.textContent : '-';
            const quantity = Number(qtyInput.value);
            document.getElementById('review-quantity').textContent = quantity > 0 && reasonSelect.value
                ? `${quantity.toFixed(3)} ${currentUnit} · ${selectedReason.textContent}`
                : '-';
            return Boolean(itemSelect.value && policy && policy !== 'unclassified' && tupleReady
                && ((policy === 'lot_tracked' && mode === 'existing' && lotSelect.value) || (policy === 'untracked' && mode === 'untracked'))
                && locationSelect.value && quantity > 0 && reasonSelect.value);
        }

        function updateBalance() {
            const requestSequence = ++balanceRequestSequence;
            const itemId = itemSelect.value;
            const lotId = lotSelect.value || '';
            const locationId = locationSelect.value;
            const mode = selectedLotMode();
            const policy = currentItem()?.dataset.lotPolicy;
            const validTuple = itemId && locationId && policy && policy !== 'unclassified'
                && ((policy === 'lot_tracked' && mode === 'existing' && lotId) || (policy === 'untracked' && mode === 'untracked'));

            if (!validTuple) {
                resetBalance();
                updateReviewSummary();
                return;
            }

            clearReviewConfirmation();
            resetBalance('Memuat...');
            const params = new URLSearchParams({ context: 'material-disposal', item_id: itemId, lot_id: lotId, lot_mode: mode, location_id: locationId });
            fetch(`{{ url('referensi/inventori/ajax/balance') }}?${params.toString()}`)
                .then((response) => {
                    if (!response.ok) throw new Error('Saldo tidak dapat dimuat.');
                    return response.json();
                })
                .then((data) => {
                    if (requestSequence !== balanceRequestSequence || itemId !== itemSelect.value
                        || lotId !== (lotSelect.value || '') || locationId !== locationSelect.value || mode !== selectedLotMode()) return;
                    balanceContext = { onHand: Number(data.on_hand_qty), available: Number(data.available_qty) };
                    onHandQty.textContent = `${balanceContext.onHand.toFixed(3)} ${currentUnit}`;
                    availableQty.textContent = `${balanceContext.available.toFixed(3)} ${currentUnit}`;
                    expectedOnHand.value = balanceContext.onHand.toFixed(3);
                    expectedAvailable.value = balanceContext.available.toFixed(3);
                    contextError.classList.add('hidden');
                    updateReviewSummary();
                })
                .catch(() => {
                    if (requestSequence !== balanceRequestSequence) return;
                    resetBalance('Gagal dimuat');
                    contextError.classList.remove('hidden');
                    updateReviewSummary();
                });
        }

        function loadLots() {
            const requestSequence = ++lotRequestSequence;
            const itemId = itemSelect.value;
            const locationId = locationSelect.value;
            const mode = selectedLotMode();
            lotSelect.innerHTML = '<option value="">Pilih lot...</option>';
            lotStatus.textContent = '';
            resetBalance();
            if (currentItem()?.dataset.lotPolicy === 'untracked' && mode === 'untracked') {
                updateBalance();
                return;
            }
            if (!itemId || !locationId || currentItem()?.dataset.lotPolicy !== 'lot_tracked' || mode !== 'existing') {
                updateReviewSummary();
                return;
            }

            lotStatus.textContent = 'Memuat lot pada lokasi sumber...';
            const params = new URLSearchParams({ context: 'material-disposal', item_id: itemId, location_id: locationId, lot_mode: mode });
            fetch(`{{ url('referensi/inventori/ajax/lots') }}?${params.toString()}`)
                .then((response) => {
                    if (!response.ok) throw new Error('Lot tidak dapat dimuat.');
                    return response.json();
                })
                .then((lots) => {
                    if (requestSequence !== lotRequestSequence || itemId !== itemSelect.value
                        || locationId !== locationSelect.value || mode !== selectedLotMode()) return;
                    lots.forEach((lot) => {
                        const option = document.createElement('option');
                        option.value = lot.id;
                        option.textContent = lot.lot_no + (lot.expiry_date ? ` (kedaluwarsa: ${lot.expiry_date})` : '');
                        if (lot.status === 'QUARANTINE') option.textContent += ' (karantina)';
                        if (lot.status === 'EXPIRED' || lot.is_expired) option.textContent += ' (kedaluwarsa)';
                        lotSelect.appendChild(option);
                    });
                    lotStatus.textContent = lots.length ? 'Pilih satu lot untuk disposal.' : 'Tidak ada lot yang dapat diproses pada lokasi ini.';
                    const oldLotId = @js((string) old('lot_id', ''));
                    if (oldLotId !== '') lotSelect.value = oldLotId;
                    updateBalance();
                })
                .catch(() => {
                    if (requestSequence !== lotRequestSequence) return;
                    lotSelect.innerHTML = '<option value="">Lot gagal dimuat</option>';
                    lotStatus.textContent = 'Lot gagal dimuat. Ubah pilihan item atau lokasi sumber.';
                    updateReviewSummary();
                });
        }

        function refreshTuple() {
            contextError.classList.add('hidden');
            updateModeVisibility();
            loadLots();
            clearReviewConfirmation();
        }

        itemSelect.addEventListener('change', () => {
            currentUnit = currentItem()?.dataset.uom ?? '';
            currentUom.value = currentUnit || 'Pilih item dulu';
            refreshTuple();
        });
        locationSelect.addEventListener('change', refreshTuple);
        lotSelect.addEventListener('change', () => {
            clearReviewConfirmation();
            updateBalance();
        });
        document.querySelectorAll('[name="lot_mode"]').forEach((radio) => radio.addEventListener('change', refreshTuple));
        [qtyInput, reasonSelect, document.getElementById('disposal-notes')].forEach((field) => {
            field.addEventListener(field === qtyInput ? 'input' : 'change', clearReviewConfirmation);
        });

        disposalForm.addEventListener('submit', (event) => {
            if (!updateReviewSummary() || !reviewCheckbox.checked || !expectedOnHand.value || !expectedAvailable.value) {
                event.preventDefault();
                contextError.textContent = 'Periksa barang, nomor lot, lokasi, dan jumlah stok. Tunggu sampai jumlah tampil, lalu konfirmasi pemeriksaan.';
                contextError.classList.remove('hidden');
                contextError.focus();
                return;
            }
            const submitButton = document.getElementById('dispose-submit');
            submitButton.disabled = true;
            submitButton.textContent = 'Mencatat disposal material...';
        });

        function initializeDisposalForm() {
            updateModeVisibility();
            if (itemSelect.value) {
                currentUnit = currentItem()?.dataset.uom ?? '';
                currentUom.value = currentUnit || 'Pilih item dulu';
                loadLots();
            } else {
                updateReviewSummary();
            }
        }
        initializeDisposalForm();
    </script>
    @endpush
</x-app-layout>
