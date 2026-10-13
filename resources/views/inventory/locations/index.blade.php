<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Lokasi Penyimpanan"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Lokasi']]"
        >
            <x-slot name="actions">
                @if(auth()->user()?->hasPermission('inventori.create'))
                    <a href="{{ route('inventory.locations.create') }}"
                        class="btn btn-primary inline-flex min-h-11 items-center justify-center rounded-md px-4 text-sm font-semibold">
                        Tambah lokasi
                    </a>
                @endif
                @if(auth()->user()?->hasPermission('inventori.view') && auth()->user()?->hasPermission('inventori.location-access.manage'))
                    <a href="{{ route('inventory.baseline.policies') }}"
                        class="inline-flex items-center rounded-md border border-primary-600 px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50">
                        Atur Nomor Lot Barang
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div role="status" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-100">
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
                        <th class="px-4 py-3 text-right">Saldo tercatat</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @forelse($locations as $location)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $location->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inventory-status-neutral inline-flex items-center rounded px-2 py-1 text-xs font-medium">
                                    {{ $location->location_type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($location->is_restricted)
                                    <span class="inventory-status-warning inline-flex items-center rounded px-2 py-1 text-xs font-medium">Terbatas</span>
                                @else
                                    <span class="inventory-status-neutral inline-flex items-center rounded px-2 py-1 text-xs font-medium">Umum</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-600">
                                {{ in_array($location->id, $visibleLocationIds, true) ? $location->balances_count : 'Tidak terlihat' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(in_array($location->id, $visibleLocationIds, true) && auth()->user()?->hasPermission('inventori.view') && auth()->user()?->hasPermission('inventori.edit'))
                                    <a href="{{ route('inventory.locations.edit', $location) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary-800 underline hover:text-primary-950 dark:text-primary-300 dark:hover:text-primary-200">Ubah</a>
                                @endif
                                @if($location->is_restricted && auth()->user()?->hasPermission('inventori.location-access.manage'))
                                    <a href="{{ route('inventory.locations.access', $location) }}" class="ml-3 inline-flex min-h-11 items-center text-sm font-medium text-primary-800 underline hover:text-primary-950 dark:text-primary-300 dark:hover:text-primary-200">Atur akses</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                Belum ada lokasi penyimpanan yang dapat ditampilkan dalam cakupan akses Anda.
                                @if(auth()->user()?->hasPermission('inventori.create'))
                                    <a href="{{ route('inventory.locations.create') }}" class="text-primary-600 hover:underline">Tambah lokasi pertama</a>
                                @endif
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
