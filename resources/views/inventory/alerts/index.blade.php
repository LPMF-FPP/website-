<x-app-layout>
    <x-slot name="header">
        <x-page-header class="inventory-page-header" :title="$categories[$category]" :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard', ['tab' => $category])], ['label' => 'Daftar peringatan']]" />
    </x-slot>
    <div class="space-y-6">
        <nav aria-label="Kategori peringatan" class="flex flex-wrap gap-3">
            @foreach($categories as $key => $label)
                <a href="{{ route('inventory.alerts.index', ['category' => $key]) }}" @if($category === $key) aria-current="page" @endif class="btn {{ $category === $key ? 'btn-primary' : 'border-sem semantic-text' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <p role="status" class="semantic-text">{{ $records->total() }} {{ $category === 'low_stock' ? 'item' : 'lot' }} dalam cakupan lokasi yang dapat Anda lihat.</p>
        <div class="card overflow-x-auto">
            <table class="min-w-full text-sm semantic-text">
                <caption class="sr-only">Daftar lengkap {{ $categories[$category] }}</caption>
                <thead class="semantic-bg"><tr>
                    <th scope="col" class="px-4 py-3 text-left">Item</th>
                    <th scope="col" class="px-4 py-3 text-left">{{ $category === 'low_stock' ? 'Stok minimum' : 'Lot / kedaluwarsa' }}</th>
                    <th scope="col" class="px-4 py-3 text-right">Stok terlihat</th>
                    <th scope="col" class="px-4 py-3 text-left">Rincian</th>
                </tr></thead>
                <tbody>
                    @forelse($records as $record)
                        @php($item = $category === 'low_stock' ? $record : $record->item)
                        <tr class="border-t semantic-border">
                            <td class="px-4 py-3">{{ $item->item_code }} · {{ $item->name }}</td>
                            <td class="px-4 py-3">
                                @if($category === 'low_stock'){{ number_format((float) $item->min_stock, 3, ',', '.') }} {{ $item->uom }}
                                @else{{ $record->lot_no }} · {{ $record->expiry_date?->format('d/m/Y') ?? 'Tanggal tidak dicatat' }}@endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums">{{ number_format((float) $record->balances->sum('on_hand_qty'), 3, ',', '.') }} {{ $item->uom }}</td>
                            <td class="px-4 py-3"><a href="{{ route('inventory.items.lots', $item) }}" class="inline-flex min-h-11 items-center text-primary-800 underline dark:text-primary-300">Lihat lot item</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center">Tidak ada stok yang cocok dengan kategori ini. <a href="{{ route('inventory.dashboard', ['tab' => $category]) }}" class="underline">Kembali ke dashboard</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $records->links() }}
    </div>
</x-app-layout>
