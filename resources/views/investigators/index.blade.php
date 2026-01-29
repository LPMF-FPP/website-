<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Manajemen Penyidik"
            :breadcrumbs="[[ 'label' => 'Penyidik' ]]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-gray-600">Kelola biodata penyidik (pangkat, no HP, email, satker) tanpa mengubah data permintaan.</p>

        @if(session('success'))
            <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <x-page-section title="Filter Penyidik">
            <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipe</label>
                    <select name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua</option>
                        <option value="polri" @selected(($filters['type'] ?? '') === 'polri')>Polri</option>
                        <option value="non_polri" @selected(($filters['type'] ?? '') === 'non_polri')>Non-Polri</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Satker/Instansi</label>
                    <select name="jurisdiction" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua satker/instansi</option>
                        @foreach($jurisdictions as $jurisdiction)
                            <option value="{{ $jurisdiction }}" @selected(($filters['jurisdiction'] ?? '') === $jurisdiction)>
                                {{ $jurisdiction }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kata kunci</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama / NRP / HP / Email"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Terapkan</button>
                    <a href="{{ route('investigators.index') }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Reset</a>
                </div>
            </form>
        </x-page-section>

        <x-page-section title="Daftar Penyidik">
            <div class="overflow-visible rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Nama</th>
                            <th class="px-4 py-3 text-left">Satker/Instansi</th>
                            <th class="px-4 py-3 text-left">Kontak</th>
                            <th class="px-4 py-3 text-left">Tipe</th>
                            <th class="px-4 py-3 text-left">Permintaan</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse($investigators as $investigator)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ trim($investigator->full_name) }}</div>
                                    <div class="text-xs text-gray-500">NRP: {{ $investigator->nrp ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $investigator->jurisdiction ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $investigator->phone ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $investigator->email ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge
                                        variant="secondary"
                                        :label="$investigator->is_polri ? 'Polri' : 'Non-Polri'"
                                        subtle
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $investigator->test_requests_count }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-dropdown align="right" width="48">
                                        <x-slot name="trigger">
                                            <span class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">
                                                Aksi
                                            </span>
                                        </x-slot>
                                        <x-slot name="content">
                                            <x-dropdown-link :href="route('investigators.show', $investigator)">
                                                Lihat
                                            </x-dropdown-link>
                                            @can('investigators.edit')
                                                <x-dropdown-link :href="route('investigators.edit', $investigator)">
                                                    Edit
                                                </x-dropdown-link>
                                            @endcan

                                            <div class="border-t border-gray-100"></div>

                                            @can('investigators.delete')
                                                @if($investigator->test_requests_count > 0)
                                                    <div class="px-4 py-2 text-xs text-gray-400">Tidak bisa dihapus (punya permintaan)</div>
                                                @else
                                                    <form method="POST" action="{{ route('investigators.destroy', $investigator) }}" x-data>
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-dropdown-link href="#"
                                                            @click.prevent="showConfirmDialog({
                                                                type: 'danger',
                                                                title: 'Hapus Penyidik',
                                                                message: 'Hapus penyidik ini? Data permintaan tetap tersimpan.',
                                                                confirmButtonText: 'Ya, Hapus',
                                                                onConfirm: () => $el.closest('form').submit()
                                                            })">
                                                            Hapus
                                                        </x-dropdown-link>
                                                    </form>
                                                @endif
                                            @endcan
                                        </x-slot>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada data penyidik.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $investigators->links() }}
            </div>
        </x-page-section>
    </div>
</x-app-layout>
