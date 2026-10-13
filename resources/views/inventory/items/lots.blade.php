<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Lot: ' . ($item->item_code ?? 'Kode belum ditetapkan') . ' · ' . $item->name"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Master Item', 'href' => route('inventory.items.index')],
                ['label' => 'Lots']
            ]"
        >
            <x-slot name="actions">
                @if(auth()->user()?->hasPermission('inventori.create'))
                <div x-data>
                    <button id="add-lot-trigger" type="button" @click="$dispatch('open-add-lot-modal', { trigger: $el })"
                        class="btn btn-primary inline-flex min-h-11 items-center justify-center rounded-md px-4 text-sm font-semibold">
                        + Tambah Lot
                    </button>
                </div>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Item Info -->
        <div class="card">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-700 dark:text-slate-300">Tipe:</span>
                    <span class="font-medium text-gray-900 dark:text-slate-100">{{ $item->item_type_label }}</span>
                </div>
                <div>
                    <span class="text-gray-700 dark:text-slate-300">UOM:</span>
                    <span class="font-medium text-gray-900 dark:text-slate-100">{{ $item->uom }}</span>
                </div>
                <div>
                    <span class="text-gray-700 dark:text-slate-300">Merek:</span>
                    <span class="font-medium text-gray-900 dark:text-slate-100">{{ $item->brand ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-700 dark:text-slate-300">Penyimpanan:</span>
                    <span class="font-medium text-gray-900 dark:text-slate-100">{{ $item->storage_condition ?? '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Lots Table -->
        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Nomor lot</th>
                        <th class="px-4 py-3 text-left">Kedaluwarsa</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Stok Total</th>
                        <th class="px-4 py-3 text-left">Lokasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @forelse($lots as $lot)
                        @php
                            $totalStock = $lot->balances->sum('on_hand_qty');
                            $locations = $lot->balances->where('on_hand_qty', '>', 0)->pluck('location.name')->filter()->join(', ');
                            $displayStatus = $lot->is_expired ? 'EXPIRED' : $lot->status;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-slate-100">
                                <span class="block break-all">{{ $lot->lot_no }}</span>
                                <a href="{{ route('inventory.stock-card', ['item_id' => $item->id, 'lot_id' => $lot->id]) }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary-800 underline dark:text-primary-300">Lihat kartu stok</a>
                            </td>
                            <td class="px-4 py-3">
                                @if($lot->expiry_date)
                                    <span class="{{ $lot->is_expired ? 'text-red-700 font-medium dark:text-red-300' : ($lot->isNearExpiry(30) ? 'text-orange-800 dark:text-orange-200' : 'text-gray-700 dark:text-slate-300') }}">
                                        {{ $lot->expiry_date->format('d M Y') }}
                                    </span>
                                    @if($lot->is_expired)
                                        <span role="status" class="inventory-status-danger ml-1 inline-flex rounded px-2 py-1 text-xs font-semibold">Kedaluwarsa</span>
                                    @elseif($lot->isNearExpiry(30))
                                        <span role="status" class="inventory-status-warning ml-1 inline-flex rounded px-2 py-1 text-xs font-semibold">Hampir kedaluwarsa, {{ $lot->days_until_expiry }} hari</span>
                                    @endif
                                @else
                                    <span class="text-gray-600 dark:text-slate-300">Tanggal tidak dicatat</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span role="status" class="inline-flex items-center rounded px-2 py-1 text-xs font-semibold
                                    @switch($displayStatus)
                                        @case('ACTIVE') inventory-status-success @break
                                        @case('QUARANTINE') inventory-status-warning @break
                                        @case('EXPIRED') inventory-status-danger @break
                                        @case('DISPOSED') inventory-status-neutral @break
                                    @endswitch
                                ">
                                    {{ $displayStatus === 'EXPIRED' ? 'Kedaluwarsa' : (\App\Models\InventoryLot::STATUSES[$displayStatus] ?? 'Status tidak diketahui') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-slate-100">
                        <span class="tabular-nums">{{ number_format($totalStock, 3, ',', '.') }} {{ $item->uom }}</span>
                                <span role="status" class="mt-1 block text-xs font-sans {{ $totalStock > 0 ? 'text-gray-700 dark:text-slate-300' : 'inventory-status-neutral rounded px-2 py-1' }}">{{ $totalStock > 0 ? 'Ada saldo tercatat' : 'Stok kosong' }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-300">
                                {{ $locations ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-700 dark:text-slate-300">
                                Belum ada lot untuk item ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $lots->links() }}
        </div>
    </div>

    <!-- Add Lot Modal -->
    <div id="add-lot-modal" x-data="addInventoryLotDialog({ open: @js($errors->any()) })" x-show="open" @open-add-lot-modal.window="show($event.detail?.trigger)" @keydown.escape.window="if (open) close()" role="dialog" aria-modal="true" aria-labelledby="add-lot-title" :aria-hidden="open ? 'false' : 'true'" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 p-4" style="display: none;" @click.self="close()">
        <div class="relative mx-auto mt-12 w-full max-w-md rounded-md border border-gray-300 bg-white p-5 shadow-lg dark:border-slate-600 dark:bg-slate-800" @keydown.tab="trapFocus($event)" @click.stop>
            <div class="flex justify-between items-center mb-4">
                <h3 id="add-lot-title" class="text-lg font-semibold text-gray-900 dark:text-slate-100">Tambah Lot Baru</h3>
                <button x-ref="closeButton" type="button" @click="close()" aria-label="Tutup formulir lot" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                    <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('inventory.lots.store') }}">
                @csrf
                <input type="hidden" name="item_id" value="{{ $item->id }}">
                @if($errors->has('lot_no') || $errors->has('expiry_date'))
                    <div role="alert" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
                        @error('lot_no')<p>{{ $message }}</p>@enderror
                        @error('expiry_date')<p>{{ $message }}</p>@enderror
                    </div>
                @endif
                
                <div class="space-y-4">
                    <div>
                        <label for="new-lot-number" class="block text-sm font-medium text-gray-800 dark:text-slate-200 mb-1">Nomor Lot <span class="text-red-700 dark:text-red-300">*</span></label>
                        <input id="new-lot-number" type="text" name="lot_no" value="{{ old('lot_no') }}" required
                                class="min-h-11 w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-primary-400 dark:focus:ring-primary-400"
                                placeholder="Contoh: LOT-2024-001">
                    </div>
                    <div>
                        <label for="new-lot-expiry" class="block text-sm font-medium text-gray-800 dark:text-slate-200 mb-1">
                            Tanggal Kadaluarsa
                            @if($item->requiresExpiry())<span class="text-red-700 dark:text-red-300">*</span>@endif
                        </label>
                        <input id="new-lot-expiry" type="date" name="expiry_date" value="{{ old('expiry_date') }}" {{ $item->requiresExpiry() ? 'required' : '' }}
                            class="min-h-11 w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-primary-400 dark:focus:ring-primary-400">
                        @if($item->requiresExpiry())
                            <p class="mt-1 text-xs text-gray-700 dark:text-slate-300">Wajib untuk item tipe {{ $item->item_type_label }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="new-lot-received" class="block text-sm font-medium text-gray-800 dark:text-slate-200 mb-1">Tanggal Terima</label>
                        <input id="new-lot-received" type="date" name="received_date" value="{{ old('received_date', date('Y-m-d')) }}"
                            class="min-h-11 w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-primary-400 dark:focus:ring-primary-400">
                    </div>
                    <div>
                        <label for="new-lot-notes" class="block text-sm font-medium text-gray-800 dark:text-slate-200 mb-1">Catatan</label>
                        <textarea id="new-lot-notes" name="notes" rows="2"
                            class="min-h-11 w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-primary-400 dark:focus:ring-primary-400">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="close()" class="inline-flex min-h-11 items-center px-4 py-2 text-gray-800 hover:text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-slate-200 dark:hover:text-white dark:focus-visible:ring-primary-400">Batal</button>
                    <button type="submit" class="btn btn-primary min-h-11 px-6 font-medium">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function addInventoryLotDialog(config = {}) {
            return {
                open: Boolean(config.open),
                trigger: null,
                init() {
                    if (this.open) this.$nextTick(() => this.$refs.closeButton?.focus());
                },
                show(trigger) {
                    this.trigger = trigger || document.activeElement;
                    this.open = true;
                    this.$nextTick(() => this.$refs.closeButton?.focus());
                },
                close() {
                    this.open = false;
                    this.$nextTick(() => this.trigger?.focus());
                },
                trapFocus(event) {
                    const focusable = Array.from(this.$el.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'))
                        .filter((element) => element.offsetParent !== null);
                    if (focusable.length === 0) return;
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                },
            };
        }
    </script>
</x-app-layout>
