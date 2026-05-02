@if ($request->samples->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Kode</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Deskripsi Singkat</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Jumlah Diterima</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($request->samples as $sample)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $sample->sample_code }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $sample->short_description ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $sample->package_quantity ?? '—' }} {{ $sample->unit ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="text-center py-6">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
        </svg>
        <p class="mt-2 text-sm text-gray-500">Belum ada sampel terdaftar.</p>
    </div>
@endif
