<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Buku Tamu">
            <x-slot name="actions">
                @can('create', \App\Models\GuestVisit::class)
                    <a href="{{ route('guest-book.create') }}"
                       class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                        + Catat Kunjungan
                    </a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6">
        {{-- Filters --}}
        <form method="GET" action="{{ route('guest-book.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label for="q" class="block text-sm font-medium text-gray-700">Cari</label>
                <input type="text" name="q" id="q" value="{{ request('q') }}"
                       placeholder="Nama tamu / NRP / instansi..."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Semua</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="checked_out" @selected(request('status') === 'checked_out')>Keluar</option>
                </select>
            </div>
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700">Dari</label>
                <input type="date" name="from" id="from" value="{{ request('from') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700">Sampai</label>
                <input type="date" name="to" id="to" value="{{ request('to') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
            <div>
                <label for="host_id" class="block text-sm font-medium text-gray-700">Petugas</label>
                <select name="host_id" id="host_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Semua</option>
                    @foreach($hosts as $host)
                        <option value="{{ $host->id }}" @selected(request('host_id') == $host->id)>{{ $host->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center rounded bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Cari
                </button>
                <a href="{{ route('guest-book.index') }}" class="inline-flex items-center rounded px-3 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
                    Reset
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto bg-white rounded-lg shadow-sm ring-1 ring-gray-200">
            @if($visits->isEmpty())
                <x-empty-state icon="users" title="Belum ada kunjungan">
                    Data tamu akan muncul otomatis saat permohonan baru atau penyerahan selesai.
                    @can('create', \App\Models\GuestVisit::class)
                        <x-slot name="action">
                            <a href="{{ route('guest-book.create') }}" class="text-blue-600 hover:text-blue-500">Catat Kunjungan Manual</a>
                        </x-slot>
                    @endcan
                </x-empty-state>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pemilik Kasus</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pihak Datang</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keperluan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($visits as $i => $visit)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $visits->firstItem() + $i }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $visit->investigator->full_name ?? $visit->investigator->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $visit->investigator->jurisdiction ?? $visit->investigator->institution }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($visit->isVisitorVerified())
                                        <span class="text-sm text-gray-900">{{ $visit->visitor_name }}</span>
                                        <div class="text-xs text-gray-500">{{ $visit->visitor_relation }}</div>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-sm text-amber-600">
                                            ⚠️ Belum diverifikasi
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $visit->purpose }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $visit->visit_date->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    @if($visit->isActive())
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                            ● Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                            ○ Keluar
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('guest-book.show', $visit) }}" class="p-1.5 text-gray-400 hover:text-gray-600" title="Detail">
                                            📋
                                        </a>
                                        @can('update', $visit)
                                            <a href="{{ route('guest-book.edit', $visit) }}" class="p-1.5 text-gray-400 hover:text-blue-600" title="Edit">
                                                ✏️
                                            </a>
                                        @endcan
                                        @if($visit->isActive() && auth()->user()->hasPermission('guest-book.checkout'))
                                            <form method="POST" action="{{ route('guest-book.checkout', $visit) }}" x-data class="inline">
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
                                                        class="p-1.5 text-gray-400 hover:text-red-600"
                                                        title="Catat Keluar">
                                                    🚪
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $visits->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
