@if(($topMovers ?? collect())->isNotEmpty())
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="font-semibold text-gray-900">🔥 Barang Paling Boros (7 hari terakhir)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Keluar</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($topMovers as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-gray-900">
                            {{ $row->item?->name ?? ('Item #'.$row->item_id) }}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-sm text-gray-700 font-semibold">
                        {{ number_format((float) ($row->total_qty ?? 0), 2) }} 
                        <span class="text-gray-500 font-normal">{{ $row->item?->uom ?? '' }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif