@if(($topMovers ?? collect())->isNotEmpty())
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden dark:bg-slate-800 dark:border-slate-700">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 dark:bg-slate-900 dark:border-slate-700">
        <h3 class="font-semibold text-gray-900 dark:text-slate-100">Barang dengan pengeluaran tertinggi (7 hari terakhir)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <caption class="sr-only">Item dengan pengeluaran terbesar selama tujuh hari terakhir</caption>
            <thead class="bg-gray-50 dark:bg-slate-900">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider dark:text-slate-300">Item</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider dark:text-slate-300">Total Keluar</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-700">
                @foreach($topMovers as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-gray-900 dark:text-slate-100">
                            {{ ($row->item?->item_code ?? 'Kode belum ditetapkan').' · '.($row->item?->name ?? ('Item #'.$row->item_id)) }}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-sm text-gray-800 font-semibold dark:text-slate-200">
                        {{ number_format((float) ($row->total_qty ?? 0), 3, ',', '.') }}
                        <span class="text-gray-700 font-normal dark:text-slate-300">{{ $row->item?->uom ?? '' }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
