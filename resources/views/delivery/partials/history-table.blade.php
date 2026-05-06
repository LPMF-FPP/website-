@if($completedRequests->isNotEmpty())
    <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
        <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <a href="{{ route('delivery.index', array_merge(request()->query(), ['sort' => 'receipt_number', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}" class="group inline-flex">
                            No. Resi
                            @if(request('sort') == 'receipt_number')
                                <span class="ml-2 flex-none rounded bg-gray-200 text-gray-900 group-hover:bg-gray-300">
                                    {{ request('direction') == 'asc' ? '↑' : '↓' }}
                                </span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Penyidik</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tersangka</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Jumlah Sampel</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <a href="{{ route('delivery.index', array_merge(request()->query(), ['sort' => 'completed_at', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}" class="group inline-flex">
                            Tanggal Penyerahan
                            @if(request('sort', 'completed_at') == 'completed_at')
                                <span class="ml-2 flex-none rounded bg-gray-200 text-gray-900 group-hover:bg-gray-300">
                                    {{ request('direction', 'desc') == 'asc' ? '↑' : '↓' }}
                                </span>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @foreach($completedRequests as $request)
                    <tr class="group cursor-pointer transition-all duration-200 hover:bg-teal-50/50 hover:shadow-sm"
                        role="link"
                        tabindex="0"
                        x-on:click="window.location = '{{ route('delivery.show', $request) }}'"
                        x-on:keydown.enter="window.location = '{{ route('delivery.show', $request) }}'">
                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                            {{ $request->receipt_number ?? $request->request_number }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ optional($request->investigator)->name ?? '-' }}
                            @if($request->investigator)
                                <div class="text-xs text-gray-500">{{ $request->investigator->rank }} &middot; {{ $request->investigator->jurisdiction }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $request->suspect_name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <div class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $request->samples->count() }} Sampel</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ optional($request->completed_at)->format('d/m/Y') ?? '-' }}
                            <div class="text-xs text-gray-500">{{ optional($request->completed_at)->format('H:i') }} WIB</div>
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-medium">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('delivery.show', $request) }}"
                                   class="group inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-all duration-200 hover:bg-gray-50 hover:text-gray-900 hover:shadow-md hover:-translate-y-0.5">
                                    <svg class="size-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Detail
                                    <svg class="size-3 -translate-x-2 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $completedRequests->links() }}
    </div>
@else
    <div class="py-16 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-gray-100 to-slate-100 shadow-inner">
            <span class="text-3xl" aria-hidden="true">🔍</span>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Tidak Ditemukan</h3>
        <p class="mt-1 mx-auto max-w-sm text-sm text-gray-500">Coba ubah kata kunci pencarian atau hapus filter.</p>
    </div>
@endif
