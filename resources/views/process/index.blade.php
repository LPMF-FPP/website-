<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pilih Nomor Resi"
            :breadcrumbs="[]"
        />
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4">
                <div>
                    <h3 class="text-base font-semibold text-primary-900">Cari nomor resi</h3>
                    <p class="mt-1 text-sm text-gray-500">Masukkan nomor resi atau filter lain untuk menemukan permintaan yang siap diuji.</p>
                </div>

                <form method="GET" action="{{ route('testing.index') }}" class="flex flex-wrap items-center gap-3">
                    <div class="min-w-[160px]">
                        <label for="scope" class="sr-only">Filter pencarian</label>
                        <select
                            id="scope"
                            name="scope"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="all" @selected(($filters['scope'] ?? '') === 'all')>Semua</option>
                            <option value="receipt_number" @selected(($filters['scope'] ?? '') === 'receipt_number')>Nomor Resi</option>
                            <option value="request_number" @selected(($filters['scope'] ?? '') === 'request_number')>Nomor Permintaan</option>
                            <option value="investigator" @selected(($filters['scope'] ?? '') === 'investigator')>Penyidik/Unit</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[220px]">
                        <label for="q" class="sr-only">Cari nomor resi</label>
                        <div class="relative">
                            <input
                                type="text"
                                id="q"
                                name="q"
                                value="{{ $filters['q'] ?? '' }}"
                                placeholder="Cari nomor resi..."
                                class="w-full rounded-md border-gray-300 pr-10 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            />
                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                <x-icon name="search" size="sm" color="muted" :decorative="true" />
                            </div>
                        </div>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                        <x-icon name="search" size="sm" :decorative="true" />
                        Cari
                    </button>
                </form>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        {{ ($filters['q'] ?? '') !== '' ? 'Hasil Pencarian' : 'Daftar Resi Siap Diproses' }}
                    </h4>
                    <span class="text-xs text-gray-400">{{ $requests->total() }} data</span>
                </div>

                @if($requests->count() > 0)
                    <ul class="mt-3 divide-y divide-gray-200">
                        @foreach($requests as $request)
                            @php
                                $investigator = $request->investigator;
                                $unit = $investigator?->jurisdiction ?? $investigator?->institution;
                                $receivedAt = $request->received_at ?? $request->created_at;
                            @endphp
                            <li>
                                <a
                                    href="{{ route('testing.show', $request) }}"
                                    class="flex flex-wrap items-center justify-between gap-4 py-4 transition hover:bg-gray-50/70">
                                    <div>
                                        <div class="text-base font-semibold text-primary-900">
                                            {{ $request->receipt_number ?? $request->request_number }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $investigator?->full_name ?? $investigator?->name ?? '-' }}
                                            @if($unit)
                                                / {{ $unit }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ optional($receivedAt)->format('d M Y') ?? '-' }}
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-4">
                        {{ $requests->links() }}
                    </div>
                @else
                    <div class="mt-4 rounded-md border border-dashed border-gray-200 p-4 text-sm text-gray-500">
                        Tidak ada resi yang ditemukan.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
