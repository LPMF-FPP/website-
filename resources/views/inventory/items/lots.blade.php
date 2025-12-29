<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Lots: ' . $item->name"
            :breadcrumbs="[
                ['label' => 'Inventori', 'href' => route('inventory.dashboard')],
                ['label' => 'Master Item', 'href' => route('inventory.items.index')],
                ['label' => 'Lots']
            ]"
        >
            <x-slot name="actions">
                <button type="button" onclick="document.getElementById('add-lot-modal').classList.remove('hidden')"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    + Tambah Lot
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('success'))
            <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Item Info -->
        <div class="card">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Tipe:</span>
                    <span class="font-medium">{{ $item->item_type_label }}</span>
                </div>
                <div>
                    <span class="text-gray-500">UOM:</span>
                    <span class="font-medium">{{ $item->uom }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Brand:</span>
                    <span class="font-medium">{{ $item->brand ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Penyimpanan:</span>
                    <span class="font-medium">{{ $item->storage_condition ?? '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Lots Table -->
        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Lot No</th>
                        <th class="px-4 py-3 text-left">Kadaluarsa</th>
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
                        @endphp
                        <tr class="hover:bg-gray-50 {{ $lot->is_expired ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $lot->lot_no }}</td>
                            <td class="px-4 py-3">
                                @if($lot->expiry_date)
                                    <span class="{{ $lot->is_expired ? 'text-red-600 font-medium' : ($lot->isNearExpiry(30) ? 'text-orange-600' : 'text-gray-600') }}">
                                        {{ $lot->expiry_date->format('d M Y') }}
                                    </span>
                                    @if($lot->days_until_expiry !== null)
                                        <span class="text-xs text-gray-400 ml-1">
                                            ({{ $lot->days_until_expiry > 0 ? $lot->days_until_expiry . ' hari' : 'Expired' }})
                                        </span>
                                    @endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                    @switch($lot->status)
                                        @case('ACTIVE') bg-green-100 text-green-700 @break
                                        @case('QUARANTINE') bg-yellow-100 text-yellow-700 @break
                                        @case('EXPIRED') bg-red-100 text-red-700 @break
                                        @case('DISPOSED') bg-gray-100 text-gray-700 @break
                                    @endswitch
                                ">
                                    {{ $lot->status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono {{ $totalStock > 0 ? 'text-gray-900' : 'text-gray-400' }}">
                                {{ number_format($totalStock, 2) }} {{ $item->uom }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $locations ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
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
    <div id="add-lot-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Tambah Lot Baru</h3>
                <button type="button" onclick="document.getElementById('add-lot-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('inventory.lots.store') }}">
                @csrf
                <input type="hidden" name="item_id" value="{{ $item->id }}">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Lot <span class="text-red-500">*</span></label>
                        <input type="text" name="lot_no" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="e.g., LOT-2024-001">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tanggal Kadaluarsa
                            @if($item->requiresExpiry())<span class="text-red-500">*</span>@endif
                        </label>
                        <input type="date" name="expiry_date" {{ $item->requiresExpiry() ? 'required' : '' }}
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @if($item->requiresExpiry())
                            <p class="mt-1 text-xs text-gray-500">Wajib untuk item tipe {{ $item->item_type_label }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Terima</label>
                        <input type="date" name="received_date" value="{{ date('Y-m-d') }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea name="notes" rows="2"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('add-lot-modal').classList.add('hidden')" 
                        class="px-4 py-2 text-gray-700 hover:text-gray-900">Batal</button>
                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 font-medium">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
