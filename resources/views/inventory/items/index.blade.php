<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Master Item Inventori"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Master Item']]"
        >
            <x-slot name="actions">
                @if(auth()->user()?->hasPermission('inventori.create'))
                    <a href="{{ route('inventory.items.create') }}" class="btn btn-primary inline-flex min-h-11 items-center justify-center rounded-md px-4 py-2 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2">
                        Tambah item
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

        @if(session('error'))
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
                <p>Perubahan status item belum tersimpan:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <!-- Filters -->
        <div class="card">
            <form method="GET" action="{{ route('inventory.items.index') }}" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label for="item-search" class="block text-sm font-medium text-gray-800 mb-1 dark:text-slate-100">Cari item</label>
                        <input id="item-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                            placeholder="Kode, nama, merek, atau produsen"
                        class="min-h-11 w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:border-primary-400 dark:focus:ring-primary-400">
                </div>
                <div class="w-40">
                    <label for="item-type-filter" class="block text-sm font-medium text-gray-800 mb-1 dark:text-slate-100">Tipe</label>
                    <select id="item-type-filter" name="type" class="min-h-11 w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-primary-400 dark:focus:ring-primary-400">
                        <option value="">Semua Tipe</option>
                        @foreach($itemTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-32">
                    <label for="item-status-filter" class="block text-sm font-medium text-gray-800 mb-1 dark:text-slate-100">Status</label>
                    <select id="item-status-filter" name="status" class="min-h-11 w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-primary-400 dark:focus:ring-primary-400">
                        <option value="active" {{ ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                        <option value="">Semua</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-gray-400 bg-gray-100 px-4 py-2 font-semibold text-gray-900 hover:bg-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                    Terapkan filter
                </button>
                <a href="{{ route('inventory.items.index') }}" class="inline-flex min-h-11 items-center px-4 py-2 text-gray-700 underline hover:text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-slate-200 dark:hover:text-white dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                    Hapus filter
                </a>
            </form>
        </div>

        <!-- Items Table -->
        <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-slate-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-700 dark:bg-slate-900 dark:text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left">Nama Item</th>
                        <th class="px-4 py-3 text-left">Tipe</th>
                        <th class="px-4 py-3 text-left">Satuan</th>
                        <th class="px-4 py-3 text-right">Stok Total</th>
                        <th class="px-4 py-3 text-right">Min Stok</th>
                        <th class="px-4 py-3 text-center">Lot</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm dark:divide-slate-700">
                    @forelse($items as $item)
                        @php
                            $totalStock = $item->balances->sum('on_hand_qty');
                            $isBelowMin = $totalStock < $item->min_stock && $item->min_stock > 0;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900 dark:text-slate-100">{{ $item->name }}</div>
                                <div class="font-mono text-xs text-gray-700 dark:text-slate-300">{{ $item->item_code ?? 'Kode belum ditetapkan' }}</div>
                                @unless($item->is_active)
                                    <span role="status" class="mt-1 inline-flex items-center rounded px-2 py-1 text-xs font-semibold inventory-status-neutral">Diarsipkan</span>
                                @endunless
                                @if($item->brand || $item->manufacturer)
                                    <div class="text-xs text-gray-700 dark:text-slate-300">
                                        {{ collect([$item->brand, $item->manufacturer])->filter()->join(' · ') }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inventory-status-neutral inline-flex items-center rounded px-2 py-1 text-xs font-medium">
                                    {{ $item->item_type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-slate-200">{{ $item->uom }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $isBelowMin ? 'text-red-700 dark:text-red-300 font-bold' : 'text-gray-900 dark:text-slate-100' }}">
                                {{ number_format($totalStock, 3, ',', '.') }}
                                <span role="status" class="mt-1 inline-flex rounded px-2 py-1 font-sans text-xs font-semibold {{ $isBelowMin ? 'inventory-status-danger' : 'inventory-status-success' }}">
                                    {{ $isBelowMin ? 'Di bawah minimum' : 'Memenuhi minimum' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-slate-300">
                                {{ number_format($item->min_stock, 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('inventory.items.lots', $item) }}" class="inline-flex min-h-11 min-w-11 items-center justify-center font-semibold text-primary-700 underline hover:text-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:text-primary-300 dark:hover:text-primary-200 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                                    {{ $item->lots_count }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(auth()->user()?->hasPermission('inventori.edit'))
                                        <a href="{{ route('inventory.items.edit', $item) }}"
                                           class="btn btn-outline inline-flex min-h-11 items-center justify-center rounded-md px-3 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Ubah
                                        </a>
                                    @endif
                                    @if(auth()->user()?->hasPermission('inventori.edit'))
                                        @if($item->is_active)
                                            <details class="text-left">
                                                <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-md border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700">Arsipkan</summary>
                                                <form method="POST" action="{{ route('inventory.items.archive', $item) }}" class="mt-2 w-64 space-y-2 rounded-md border border-gray-200 bg-white p-3 text-left shadow-sm">
                                                    @csrf
                                                    <label for="archive-reason-{{ $item->id }}" class="block text-xs font-medium text-gray-700">Alasan (opsional)</label>
                                                    <textarea id="archive-reason-{{ $item->id }}" name="reason" rows="2" maxlength="2000" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                                    <button type="submit" class="min-h-11 rounded-md bg-amber-700 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-800">Konfirmasi arsip</button>
                                                </form>
                                            </details>
                                        @else
                                            <details class="text-left">
                                                <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-md border border-green-300 px-3 py-2 text-xs font-semibold text-green-800 hover:bg-green-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-700">Pulihkan</summary>
                                                <form method="POST" action="{{ route('inventory.items.restore', $item) }}" class="mt-2 w-64 space-y-2 rounded-md border border-gray-200 bg-white p-3 text-left shadow-sm">
                                                    @csrf
                                                    <label for="restore-reason-{{ $item->id }}" class="block text-xs font-medium text-gray-700">Alasan (opsional)</label>
                                                    <textarea id="restore-reason-{{ $item->id }}" name="reason" rows="2" maxlength="2000" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                                     <button type="submit" class="btn btn-primary min-h-11 rounded-md px-3 py-2 text-xs font-semibold">Konfirmasi pulihkan</button>
                                                </form>
                                            </details>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-700 dark:text-slate-200">
                                @if(filled($filters['search'] ?? '') || filled($filters['type'] ?? '') || ($filters['status'] ?? 'active') !== 'active')
                                    <p class="font-medium text-gray-900 dark:text-slate-100">Tidak ada item yang cocok dengan filter.</p>
                                    <a href="{{ route('inventory.items.index') }}" class="inline-flex min-h-11 items-center font-semibold text-primary-800 underline dark:text-primary-300">Hapus filter</a>
                                @else
                                    <p class="font-medium text-gray-900 dark:text-slate-100">Belum ada item inventori.</p>
                                    @if(auth()->user()?->hasPermission('inventori.create'))
                                        <a href="{{ route('inventory.items.create') }}" class="inline-flex min-h-11 items-center font-semibold text-primary-800 underline dark:text-primary-300">Tambah item pertama</a>
                                    @endif
                                @endif
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

</x-app-layout>
