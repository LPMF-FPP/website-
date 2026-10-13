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

    <div class="max-w-2xl mx-auto">
        @if(session('success'))
            <p role="status" class="mb-4 rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="mb-4 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Lokasi belum tersimpan. Periksa kembali isian berikut:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
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
                        @if(!$location->exists && !$canRestrictLocations)
                            <p class="text-sm text-gray-600">Penetapan akses terbatas memerlukan izin pengelolaan akses lokasi.</p>
                        @else
                            <label class="flex items-center gap-2">
                                @unless($location->exists)
                                    <input type="hidden" name="is_restricted" value="0">
                                @endunless
                                <input type="checkbox" name="is_restricted" value="1" @disabled($location->exists) {{ old('is_restricted', $location->is_restricted) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">Akses Terbatas</span>
                            </label>
                            <p class="mt-1 text-xs text-gray-500">
                                @if($location->exists)
                                    Status akses terbatas dikunci setelah lokasi dibuat agar hak akses tidak dapat dilewati melalui edit.
                                @else
                                    Lokasi dengan akses terbatas hanya bisa diakses oleh personil yang berwenang.
                                @endif
                            </p>
                        @endif
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t pt-4">
                    <a href="{{ route('inventory.locations.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</a>
                    <button type="submit" class="btn btn-primary min-h-11 px-6 font-medium">
                        {{ $location->exists ? 'Simpan Perubahan' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>

        @if($location->exists)
            <section class="mt-6 rounded-lg bg-white p-6 shadow-sm" aria-labelledby="location-configuration-history-heading">
                <h2 id="location-configuration-history-heading" class="text-lg font-semibold">Riwayat konfigurasi lokasi</h2>
                <p class="mt-1 text-sm text-gray-600">Riwayat ini mencakup perubahan sejak pencatatan konfigurasi lokasi diberlakukan; perubahan sebelumnya mungkin belum tercatat.</p>
                @forelse($configurationEvents as $event)
                    <article class="border-b py-3 text-sm text-gray-700">
                        <p class="font-medium">
                            {{ $event->occurred_at->format('d/m/Y H:i T') }} ·
                            {{ $event->event_type === 'created' ? 'Lokasi dibuat' : 'Konfigurasi diperbarui' }}
                            oleh {{ $event->actor?->name ?? 'Pengguna tidak tersedia' }}
                        </p>
                        @if($event->previous_configuration)
                            @php($previousConfiguration = $event->previous_configuration)
                            @php($previousType = $previousConfiguration['location_type'] ?? null)
                            <p class="mt-1">
                                Sebelumnya: {{ $previousConfiguration['name'] ?? 'Nama tidak tersedia' }} ·
                                {{ $previousType ? ($locationTypes[$previousType] ?? $previousType) : 'Tipe tidak tersedia' }} ·
                                {{ ($previousConfiguration['is_restricted'] ?? null) === null ? 'Status akses tidak tersedia' : ($previousConfiguration['is_restricted'] ? 'Akses terbatas' : 'Akses umum') }}
                            </p>
                        @endif
                        @php($newConfiguration = $event->new_configuration ?? [])
                        @php($newType = $newConfiguration['location_type'] ?? null)
                        <p class="mt-1">
                            Menjadi: {{ $newConfiguration['name'] ?? 'Nama tidak tersedia' }} ·
                            {{ $newType ? ($locationTypes[$newType] ?? $newType) : 'Tipe tidak tersedia' }} ·
                            {{ ($newConfiguration['is_restricted'] ?? null) === null ? 'Status akses tidak tersedia' : ($newConfiguration['is_restricted'] ? 'Akses terbatas' : 'Akses umum') }}
                        </p>
                    </article>
                @empty
                    <p class="mt-3 text-sm text-gray-600">Belum ada riwayat konfigurasi.</p>
                @endforelse
                @if($configurationEvents->hasPages())
                    <div class="mt-4">{{ $configurationEvents->links() }}</div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
