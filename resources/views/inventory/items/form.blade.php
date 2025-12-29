<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="$item->exists ? 'Edit Item' : 'Tambah Item Baru'"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Master Item', 'href' => route('inventory.items.index')],
                ['label' => $item->exists ? 'Edit' : 'Tambah']
            ]"
        />
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="card">
            <form method="POST" action="{{ $item->exists ? route('inventory.items.update', $item) : route('inventory.items.store') }}">
                @csrf
                @if($item->exists)
                    @method('PUT')
                @endif

                <div class="space-y-6">
                    <!-- Item Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Item <span class="text-red-500">*</span></label>
                        <select name="item_type" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('item_type') border-red-500 @enderror">
                            <option value="">Pilih tipe...</option>
                            @foreach($itemTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('item_type', $item->item_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('item_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Item <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $item->name) }}" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('name') border-red-500 @enderror"
                            placeholder="e.g., Methanol HPLC Grade">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <!-- Brand & Manufacturer -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                            <input type="text" name="brand" value="{{ old('brand', $item->brand) }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="e.g., Merck">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Manufacturer</label>
                            <input type="text" name="manufacturer" value="{{ old('manufacturer', $item->manufacturer) }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="e.g., Merck KGaA">
                        </div>
                    </div>

                    <!-- UOM & Pack Size -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Satuan (UOM) <span class="text-red-500">*</span></label>
                            <input type="text" name="uom" value="{{ old('uom', $item->uom) }}" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('uom') border-red-500 @enderror"
                                placeholder="e.g., mL, g, pcs, btl">
                            @error('uom')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ukuran Kemasan</label>
                            <input type="number" step="0.001" name="pack_size" value="{{ old('pack_size', $item->pack_size) }}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="e.g., 1000">
                        </div>
                    </div>

                    <!-- Specification -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Spesifikasi</label>
                        <textarea name="specification" rows="2"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Grade, konsentrasi, kemasan, dll.">{{ old('specification', $item->specification) }}</textarea>
                    </div>

                    <!-- Min Stock -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Stok</label>
                        <input type="number" step="0.001" name="min_stock" value="{{ old('min_stock', $item->min_stock ?? 0) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="0">
                        <p class="mt-1 text-xs text-gray-500">Notifikasi akan muncul jika stok di bawah nilai ini.</p>
                    </div>

                    <!-- Storage Condition -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kondisi Penyimpanan</label>
                        <select name="storage_condition"
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
                            <input type="checkbox" name="is_hazardous" value="1" {{ old('is_hazardous', $item->is_hazardous) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm font-medium text-gray-700">Bahan Berbahaya (B3)</span>
                        </label>
                    </div>

                    <div id="hazard-class-field" class="{{ old('is_hazardous', $item->is_hazardous) ? '' : 'hidden' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kelas Bahaya</label>
                        <input type="text" name="hazard_class" value="{{ old('hazard_class', $item->hazard_class) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="e.g., Flammable, Corrosive">
                    </div>

                    <!-- Is Active -->
                    <div class="border-t pt-4">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm font-medium text-gray-700">Item Aktif</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.items.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 font-medium">
                        {{ $item->exists ? 'Simpan Perubahan' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.querySelector('[name="is_hazardous"]').addEventListener('change', function() {
            document.getElementById('hazard-class-field').classList.toggle('hidden', !this.checked);
        });
    </script>
    @endpush
</x-app-layout>
