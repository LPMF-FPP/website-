<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Master Item Inventori"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Master Item']]"
        >
            <x-slot name="actions">
                <a href="{{ route('inventory.items.create') }}"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    + Tambah Item
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

        <!-- Filters -->
        <div class="card">
            <form method="GET" action="{{ route('inventory.items.index') }}" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" 
                        placeholder="Nama, brand, manufacturer..."
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div class="w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                    <select name="type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Tipe</option>
                        @foreach($itemTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-32">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="active" {{ ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                        <option value="">Semua</option>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 font-medium">
                    Filter
                </button>
                <a href="{{ route('inventory.items.index') }}" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Reset
                </a>
            </form>
        </div>

        <!-- Items Table -->
        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Nama Item</th>
                        <th class="px-4 py-3 text-left">Tipe</th>
                        <th class="px-4 py-3 text-left">UOM</th>
                        <th class="px-4 py-3 text-right">Stok Total</th>
                        <th class="px-4 py-3 text-right">Min Stok</th>
                        <th class="px-4 py-3 text-center">Lots</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @forelse($items as $item)
                        @php
                            $totalStock = $item->balances->sum('on_hand_qty');
                            $isBelowMin = $totalStock < $item->min_stock && $item->min_stock > 0;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $item->name }}</div>
                                @if($item->brand || $item->manufacturer)
                                    <div class="text-xs text-gray-500">
                                        {{ collect([$item->brand, $item->manufacturer])->filter()->join(' · ') }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                    @switch($item->item_type)
                                        @case('REAGENT') bg-blue-100 text-blue-700 @break
                                        @case('CONSUMABLE') bg-green-100 text-green-700 @break
                                        @case('STANDARD') bg-purple-100 text-purple-700 @break
                                        @case('CONTROL') bg-orange-100 text-orange-700 @break
                                        @default bg-gray-100 text-gray-700
                                    @endswitch
                                ">
                                    {{ $item->item_type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $item->uom }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $isBelowMin ? 'text-red-600 font-bold' : 'text-gray-900' }}">
                                {{ number_format($totalStock, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500">
                                {{ number_format($item->min_stock, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('inventory.items.lots', $item) }}" class="text-primary-600 hover:text-primary-700 font-medium">
                                    {{ $item->lots_count }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- 3D Edit Button --}}
                                    <a href="{{ route('inventory.items.edit', $item) }}" 
                                       class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-primary-500 rounded-md shadow-[0_4px_0_0] shadow-primary-700 hover:shadow-[0_2px_0_0] hover:shadow-primary-700 hover:translate-y-[2px] active:shadow-none active:translate-y-[4px] transition-all duration-100">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit
                                    </a>
                                    @if(auth()->user()->role === 'admin')
                                        {{-- 3D Delete Button --}}
                                        <button type="button" 
                                                id="delete-item-{{ $item->id }}"
                                                onclick="confirmDelete('{{ $item->id }}', '{{ addslashes($item->name) }}')"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-red-500 rounded-md shadow-[0_4px_0_0] shadow-red-700 hover:shadow-[0_2px_0_0] hover:shadow-red-700 hover:translate-y-[2px] active:shadow-none active:translate-y-[4px] transition-all duration-100">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Hapus
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                Belum ada data item.
                                <a href="{{ route('inventory.items.create') }}" class="text-primary-600 hover:underline">Tambah item pertama</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    </div>

    {{-- Hidden Delete Form --}}
    <form id="delete-form" method="POST" style="display:none;">
        @csrf
        @method('DELETE')
    </form>

    {{-- Delete Confirmation Script --}}
    <script>
        function confirmDelete(itemId, itemName) {
            showConfirmDialog({
                type: 'danger',
                title: '⚠️ PERINGATAN!',
                message: 'Anda akan menghapus item: ' + itemName + '\n\nSemua data terkait (lot, saldo, mutasi) akan DIHAPUS PERMANEN.\n\nApakah Anda yakin?',
                confirmButtonText: 'Ya, Hapus Permanen',
                onConfirm: () => {
                    const form = document.getElementById('delete-form');
                    form.action = '{{ url("referensi/inventori/items") }}/' + itemId;
                    form.submit();
                }
            });
        }
    </script>
</x-app-layout>
