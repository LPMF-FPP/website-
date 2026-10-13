<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="$item->exists ? 'Ubah Item' : 'Tambah Item Baru'"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Master Item', 'href' => route('inventory.items.index')],
                ['label' => $item->exists ? 'Ubah' : 'Tambah']
            ]"
        />
    </x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="card">
            <form method="POST" action="{{ $item->exists ? route('inventory.items.update', $item) : route('inventory.items.store') }}">
                @csrf
                @if($item->exists)
                    @method('PUT')
                @endif

                <div class="space-y-6">
                    <!-- Item Type -->
                    <div>
                        <label for="item-type" class="block text-sm font-medium text-gray-700 mb-1">Tipe Item <span class="text-red-500">*</span></label>
                        <select id="item-type" name="item_type" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('item_type') border-red-500 @enderror">
                            <option value="">Pilih tipe...</option>
                            @foreach($itemTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('item_type', $item->item_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('item_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="item-code" class="block text-sm font-medium text-gray-700 mb-1">Kode Item <span class="text-red-500">*</span></label>
                        <input id="item-code" type="text" name="item_code" value="{{ old('item_code', $item->item_code) }}" required maxlength="64" autocomplete="off"
                            class="w-full rounded-md border-gray-300 font-mono uppercase shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('item_code') border-red-500 @enderror"
                            placeholder="Contoh: REA-001.A">
                        <p class="mt-1 text-xs text-gray-500">Huruf ASCII, angka, titik, garis bawah, dan tanda hubung. Spasi ASCII di tepi dihapus; huruf disimpan kapital.</p>
                        @error('item_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    @if($item->exists && $identityHasHistory)
                        <div>
                            <label for="identity-change-reason" class="block text-sm font-medium text-gray-700 mb-1">Alasan perubahan kode</label>
                            <textarea id="identity-change-reason" name="identity_change_reason" rows="2" maxlength="2000"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('identity_change_reason') border-red-500 @enderror">{{ old('identity_change_reason') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Isi alasan jika kode item diubah. Perubahan kode ber-riwayat disimpan bersama kode sebelumnya dan pengguna yang melakukan perubahan.</p>
                            @error('identity_change_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <!-- Name -->
                    <div>
                        <label for="item-name" class="block text-sm font-medium text-gray-700 mb-1">Nama Item <span class="text-red-500">*</span></label>
                        <input id="item-name" type="text" name="name" value="{{ old('name', $item->name) }}" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('name') border-red-500 @enderror"
                            placeholder="Contoh: Metanol HPLC Grade">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Brand & Manufacturer -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="item-brand" class="block text-sm font-medium text-gray-700 mb-1">Merek</label>
                            <input id="item-brand" type="text" name="brand" value="{{ old('brand', $item->brand) }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="Contoh: Merck">
                        </div>
                        <div>
                            <label for="item-manufacturer" class="block text-sm font-medium text-gray-700 mb-1">Produsen</label>
                            <input id="item-manufacturer" type="text" name="manufacturer" value="{{ old('manufacturer', $item->manufacturer) }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="Contoh: Merck KGaA">
                        </div>
                    </div>

                    <!-- UOM & Pack Size -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="item-uom" class="block text-sm font-medium text-gray-700 mb-1">Satuan <span class="text-red-500">*</span></label>
                            <input id="item-uom" type="text" name="uom" value="{{ old('uom', $item->uom) }}" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('uom') border-red-500 @enderror"
                                placeholder="Contoh: mL, g, pcs, botol">
                            @error('uom')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="item-pack-size" class="block text-sm font-medium text-gray-700 mb-1">Ukuran Kemasan</label>
                            <input id="item-pack-size" type="number" step="0.001" name="pack_size" value="{{ old('pack_size', $item->pack_size) }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="Contoh: 1000">
                        </div>
                    </div>

                    <!-- Specification -->
                    <div>
                        <label for="item-specification" class="block text-sm font-medium text-gray-700 mb-1">Spesifikasi</label>
                        <textarea id="item-specification" name="specification" rows="2"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Grade, konsentrasi, kemasan, dll.">{{ old('specification', $item->specification) }}</textarea>
                    </div>

                    <!-- Min Stock -->
                    <div>
                        <label for="item-min-stock" class="block text-sm font-medium text-gray-700 mb-1">Minimum Stok</label>
                        <input id="item-min-stock" type="number" step="0.001" name="min_stock" value="{{ old('min_stock', $item->min_stock ?? 0) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="0">
                        <p class="mt-1 text-xs text-gray-500">Notifikasi akan muncul jika stok di bawah nilai ini.</p>
                    </div>

                    <!-- Storage Condition -->
                    <div>
                        <label for="storage-condition" class="block text-sm font-medium text-gray-700 mb-1">Kondisi Penyimpanan</label>
                        <select id="storage-condition" name="storage_condition"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih kondisi...</option>
                            @foreach($storageConditions as $key => $label)
                                <option value="{{ $key }}" {{ old('storage_condition', $item->storage_condition) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Hazardous -->
                    <div class="border-t pt-4">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_hazardous" value="0">
                            <input id="is-hazardous-checkbox" type="checkbox" name="is_hazardous" value="1" {{ old('is_hazardous', $item->is_hazardous) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm font-medium text-gray-700">Bahan Berbahaya (B3)</span>
                        </label>
                    </div>

                    <div id="hazard-class-field" class="{{ old('is_hazardous', $item->is_hazardous) ? '' : 'hidden' }}">
                        <label for="hazard-class" class="block text-sm font-medium text-gray-700 mb-1">Kelas Bahaya</label>
                        <input id="hazard-class" type="text" name="hazard_class" value="{{ old('hazard_class', $item->hazard_class) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Contoh: mudah terbakar, korosif">
                    </div>

                    @if($item->exists)
                        <div class="border-t pt-4">
                            <h2 class="text-sm font-semibold text-gray-800">Status item</h2>
                            <p class="mt-1 text-sm text-gray-700">{{ $item->is_active ? 'Aktif' : 'Diarsipkan' }}. Status arsip dikelola dengan tindakan khusus agar perubahan tercatat.</p>
                            @if($lifecycleEvents->isNotEmpty())
                                <ul class="mt-3 space-y-2 text-xs text-gray-600" aria-label="Riwayat arsip item">
                                    @foreach($lifecycleEvents as $event)
                                        <li class="border-l-2 border-gray-300 pl-3">
                                            {{ $event->occurred_at->format('d/m/Y H:i') }} ·
                                            {{ $event->event_type === 'archived' ? 'Diarsipkan' : 'Dipulihkan' }} oleh {{ $event->actor->name }}.
                                            @if($event->reason) Alasan: {{ $event->reason }} @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.items.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button type="submit" class="btn btn-primary min-h-11 px-6 font-medium">
                        {{ $item->exists ? 'Simpan Perubahan' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('is-hazardous-checkbox').addEventListener('change', function() {
            document.getElementById('hazard-class-field').classList.toggle('hidden', !this.checked);
        });
    </script>
    @endpush
</x-app-layout>
