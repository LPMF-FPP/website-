<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Detail Buku Tamu">
            <x-slot name="actions">
                @can('update', $visit)
                    <a href="{{ route('guest-book.edit', $visit) }}"
                       class="inline-flex items-center rounded bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Edit Penuh
                    </a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $isCasePurpose = in_array($visit->purpose, ['Permohonan Pengujian', 'Pengambilan Hasil Pengujian'], true);
    @endphp

    <div class="space-y-6">
        {{-- Kunjungan --}}
        <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Kunjungan</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Tanggal</span>
                    <p class="font-medium text-gray-900">{{ $visit->visit_date->format('d F Y') }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Jam</span>
                    <p class="font-medium text-gray-900">{{ $visit->visit_time }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Keperluan</span>
                    <p class="font-medium text-gray-900">{{ $visit->purpose }}</p>
                    @if($visit->purpose_detail)
                        <p class="text-sm text-gray-600 mt-1">{{ $visit->purpose_detail }}</p>
                    @endif
                </div>
                <div>
                    <span class="text-gray-500">Status</span>
                    <p>
                        @if($visit->isActive())
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">● Aktif</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">○ Keluar</span>
                            @if($visit->check_out_at)
                                <span class="ml-2 text-xs text-gray-400">{{ $visit->check_out_at->format('d M Y, H:i') }}</span>
                            @endif
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-gray-500">Petugas</span>
                    <p class="font-medium text-gray-900">{{ $visit->host?->name ?? '-' }}</p>
                </div>
                @if($visit->test_request_id && $visit->items->isEmpty())
                    <div>
                        <span class="text-gray-500">Permohonan</span>
                        <p>
                            <a href="{{ route('requests.show', $visit->test_request_id) }}" class="font-medium text-blue-600 hover:text-blue-500">
                                {{ $visit->testRequest?->request_number ?? '#' . $visit->test_request_id }}
                            </a>
                        </p>
                    </div>
                @endif
                @if($visit->request_count > 0)
                    <div>
                        <span class="text-gray-500">Jumlah permintaan</span>
                        <p class="font-medium text-gray-900">{{ $visit->request_count }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if($visit->items->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Permintaan Terkait</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-gray-400">
                                <th class="py-2 pr-4">Permintaan</th>
                                <th class="py-2 pr-4">Pemilik Kasus</th>
                                <th class="py-2">Aktivitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($visit->items as $item)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <a href="{{ route('requests.show', $item->test_request_id) }}" class="font-medium text-blue-600 hover:text-blue-500">
                                            {{ $item->testRequest?->request_number ?? '#'.$item->test_request_id }}
                                        </a>
                                    </td>
                                    <td class="py-2 pr-4 text-gray-700">{{ $item->investigator?->name ?? $visit->investigator?->name ?? '-' }}</td>
                                    <td class="py-2 text-gray-600">{{ $item->activity_type === 'collection' ? 'Pengambilan hasil' : 'Penyerahan permintaan' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Pemilik Kasus (hanya keperluan kasus) --}}
        @if($isCasePurpose && $visit->investigator)
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Pemilik Kasus</h3>
                @php $inv = $visit->investigator; @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-500">Nama</span>
                        <p class="font-medium text-gray-900">{{ $inv->full_name ?? $inv->name ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">NRP</span>
                        <p class="font-medium text-gray-900">{{ $inv->nrp ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Instansi</span>
                        <p class="font-medium text-gray-900">{{ $inv->jurisdiction ?? $inv->institution ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Telepon</span>
                        <p class="font-medium text-gray-900">{{ $inv->phone ?? '-' }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Pihak Yang Datang --}}
        <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Pihak Yang Datang</h3>

            @if($visit->isVisitorVerified())
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-500">Nama</span>
                        <p class="font-medium text-gray-900">{{ $visit->visitor_name }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Identitas</span>
                        <p class="font-medium text-gray-900">{{ $visit->visitor_identity ?? '-' }}</p>
                    </div>
                    @if($isCasePurpose)
                        <div>
                            <span class="text-gray-500">Relasi</span>
                            <p class="font-medium text-gray-900">{{ $visit->visitor_relation ?? '-' }}</p>
                        </div>
                    @else
                        <div>
                            <span class="text-gray-500">Instansi</span>
                            <p class="font-medium text-gray-900">{{ $visit->visitor_institution ?? '-' }}</p>
                        </div>
                    @endif
                    <div>
                        <span class="text-gray-500">Telepon</span>
                        <p class="font-medium text-gray-900">{{ $visit->visitor_phone ?? '-' }}</p>
                    </div>
                </div>
                @can('update', $visit)
                    <a href="{{ route('guest-book.edit', $visit) }}" class="mt-3 inline-flex items-center text-sm text-blue-600 hover:text-blue-500">
                        ✏️ Ubah Data
                    </a>
                @endcan
            @elseif($isCasePurpose)
                <div class="text-center py-4">
                    <p class="text-amber-600 font-medium">⚠️ Data pihak yang datang belum diverifikasi</p>
                    <div class="mt-3 flex items-center justify-center gap-3">
                        <form method="POST" action="{{ route('guest-book.visitor', $visit) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="same_as_owner" value="1">
                            <button type="submit"
                                    class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                                ☑ Sama dengan pemilik kasus
                            </button>
                        </form>
                        <a href="{{ route('guest-book.edit', $visit) }}"
                           class="inline-flex items-center rounded bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Isi Manual →
                        </a>
                    </div>
                </div>
            @else
                <div class="text-center py-4">
                    <p class="text-amber-600 font-medium">⚠️ Data pihak yang datang belum diverifikasi</p>
                    <div class="mt-3">
                        <a href="{{ route('guest-book.edit', $visit) }}"
                           class="inline-flex items-center rounded bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Isi Data Tamu →
                        </a>
                    </div>
                </div>
            @endif
        </div>

        {{-- NDA --}}
        <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-2">🔒 Perjanjian Kerahasiaan</h3>
            @if($visit->nda_accepted)
                <p class="text-sm text-green-700">
                    ✅ Disetujui — {{ $visit->nda_accepted_at?->format('d M Y, H:i') }} WIB
                </p>
            @else
                <p class="text-sm text-red-600">
                    ⚠️ Belum disetujui
                </p>
            @endif
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', {detail:'nda-modal'}))"
                    class="mt-2 text-sm text-blue-600 hover:text-blue-500 underline">
                📄 Lihat isi perjanjian →
            </button>
        </div>

        {{-- Notes --}}
        @if($visit->notes)
            <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Catatan</h3>
                <p class="text-sm text-gray-700">{{ $visit->notes }}</p>
            </div>
        @endif

        {{-- Checkout --}}
        @if($visit->isActive())
            <div class="flex justify-end">
                <form method="POST" action="{{ route('guest-book.checkout', $visit) }}" x-data>
                    @csrf
                    @method('PATCH')
                    <button type="button"
                            @click.prevent="showConfirmDialog({
                                type: 'info',
                                title: 'Konfirmasi Catat Keluar',
                                message: 'Tandai kunjungan {{ $visit->display_name }} sebagai selesai?<br><br>Status akan berubah menjadi Keluar.',
                                confirmButtonText: 'Ya, Catat Keluar',
                                onConfirm: () => $el.closest('form').submit()
                            })"
                            class="inline-flex items-center rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">
                        ⚠️ Catat Keluar
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- NDA Modal --}}
    <x-modal name="nda-modal" maxWidth="lg">
        <div class="p-6">
            @include('guest-book._nda-modal')
            <div class="mt-4 flex justify-end">
                <button type="button" @click="$dispatch('close-modal', 'nda-modal')"
                        class="rounded bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">
                    Tutup
                </button>
            </div>
        </div>
    </x-modal>
</x-app-layout>
