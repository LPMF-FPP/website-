<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="$location->exists ? 'Edit Lokasi' : 'Tambah Lokasi Baru'"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Lokasi', 'href' => route('inventory.locations.index')],
                ['label' => $location->exists ? 'Edit' : 'Tambah']
            ]"
        />
    </x-slot>

    <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
        <div class="card">
            <form method="POST" action="{{ $location->exists ? route('inventory.locations.update', $location) : route('inventory.locations.store') }}">
                @csrf
                @if($location->exists)
                    @method('PUT')
                @endif

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lokasi <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $location->name) }}" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 @error('name') border-red-500 @enderror"
                            placeholder="e.g., Gudang Utama, Lab Kimia, Cold Room A">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Lokasi <span class="text-red-500">*</span></label>
                        <select name="location_type" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih tipe...</option>
                            @foreach($locationTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('location_type', $location->location_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('location_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="border-t pt-4">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_restricted" value="0">
                            <input type="checkbox" name="is_restricted" value="1" {{ old('is_restricted', $location->is_restricted) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm font-medium text-gray-700">Akses Terbatas</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500">Lokasi dengan akses terbatas hanya bisa diakses oleh personil yang berwenang.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.locations.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 font-medium">
                        {{ $location->exists ? 'Simpan Perubahan' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
