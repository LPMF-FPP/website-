<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Lokasi Penyimpanan"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Lokasi']]"
        >
            <x-slot name="actions">
                <a href="{{ route('inventory.locations.create') }}"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    + Tambah Lokasi
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('success'))
            <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Nama Lokasi</th>
                        <th class="px-4 py-3 text-left">Tipe</th>
                        <th class="px-4 py-3 text-center">Terbatas</th>
                        <th class="px-4 py-3 text-right">Item</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @forelse($locations as $location)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $location->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                    @switch($location->location_type)
                                        @case('warehouse') bg-blue-100 text-blue-700 @break
                                        @case('lab') bg-green-100 text-green-700 @break
                                        @case('cold_storage') bg-cyan-100 text-cyan-700 @break
                                        @default bg-gray-100 text-gray-700
                                    @endswitch
                                ">
                                    {{ $location->location_type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($location->is_restricted)
                                    <span class="inline-flex items-center rounded-full bg-red-100 text-red-700 px-2 py-1 text-xs font-medium">Ya</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-600">{{ $location->balances_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('inventory.locations.edit', $location) }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                Belum ada lokasi penyimpanan.
                                <a href="{{ route('inventory.locations.create') }}" class="text-primary-600 hover:underline">Tambah lokasi pertama</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $locations->links() }}
        </div>
    </div>
</x-app-layout>
