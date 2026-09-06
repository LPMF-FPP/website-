<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Buku Tamu">
            <x-slot name="actions">
                @if(auth()->user()->hasPermission('guest-book.export'))
                    <div class="flex items-center gap-1.5" x-data>
                        <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wider">Rekap</span>
                        <input type="month" value="{{ now()->format('Y-m') }}"
                               class="h-8 w-[135px] rounded-md border border-gray-200 bg-white px-2 text-xs text-gray-500 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 cursor-pointer"
                               @change="window.open('{{ route('guest-book.monthly-report') }}?month='+$el.value)">
                    </div>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4 pb-20">
        {{-- Filter + Summary Bar --}}
        <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 px-5 py-4">
            <form method="GET" action="{{ route('guest-book.index') }}" class="flex flex-wrap items-end gap-3">
                {{-- Search --}}
                <div class="flex-1 min-w-[220px]">
                    <label for="q" class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                        <input type="text" name="q" id="q" value="{{ request('q') }}"
                               placeholder="Nama tamu, NRP, atau instansi..."
                               class="block w-full rounded-md border-gray-300 pl-9 pr-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                {{-- Status --}}
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <select name="status" id="status" class="block rounded-md border-gray-300 py-2 pr-8 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua</option>
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="checked_out" @selected(request('status') === 'checked_out')>Keluar</option>
                    </select>
                </div>
                {{-- Date range --}}
                <div class="flex items-end gap-1.5">
                    <div>
                        <label for="from" class="block text-xs font-medium text-gray-500 mb-1">Dari</label>
                        <input type="date" name="from" id="from" value="{{ request('from') }}"
                               class="block w-[135px] rounded-md border-gray-300 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <span class="pb-2 text-xs text-gray-400">&mdash;</span>
                    <div>
                        <label for="to" class="block text-xs font-medium text-gray-500 mb-1">Sampai</label>
                        <input type="date" name="to" id="to" value="{{ request('to') }}"
                               class="block w-[135px] rounded-md border-gray-300 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                {{-- Host --}}
                <div>
                    <label for="host_id" class="block text-xs font-medium text-gray-500 mb-1">Petugas</label>
                    <select name="host_id" id="host_id" class="block rounded-md border-gray-300 py-2 pr-8 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua</option>
                        @foreach($hosts as $host)
                            <option value="{{ $host->id }}" @selected(request('host_id') == $host->id)>{{ $host->name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Actions --}}
                <div class="flex gap-2 pb-px">
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition-colors shadow-sm">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                        Cari
                    </button>
                    @if(request()->anyFilled(['q', 'status', 'from', 'to', 'host_id']))
                        <a href="{{ route('guest-book.index') }}"
                           class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 overflow-hidden">
            @if($visits->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <svg class="h-12 w-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                    <h3 class="text-base font-semibold text-gray-900 mb-1">Belum ada kunjungan</h3>
                    <p class="text-sm text-gray-500 max-w-sm">
                        Data tamu akan muncul otomatis saat permohonan baru atau penyerahan selesai.
                    </p>
                    @can('create', \App\Models\GuestVisit::class)
                        <a href="{{ route('guest-book.create') }}"
                           class="mt-4 inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition-colors">
                            Catat Kunjungan Manual
                        </a>
                    @endcan
                </div>
            @else
                {{-- Summary --}}
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50/50">
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span><span class="font-semibold text-gray-700">{{ $visits->total() }}</span> kunjungan</span>
                        <span class="flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span class="font-semibold text-gray-700">{{ $visits->where('status', 'active')->count() }}</span> aktif
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                            <span class="font-semibold text-gray-700">{{ $visits->where('status', 'checked_out')->count() }}</span> keluar
                        </span>
                    </div>
                    <span class="text-xs text-gray-400">Halaman {{ $visits->currentPage() }} dari {{ $visits->lastPage() }}</span>
                </div>

                <table class="min-w-full divide-y divide-gray-100">
                    <thead>
                        <tr class="bg-gray-50/80">
                            <th class="pl-5 pr-2 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider w-10">#</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Identifikasi</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Pemilik Kasus / Instansi</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Keperluan</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider w-28">Tanggal</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wider w-24">Status</th>
                            <th class="pl-3 pr-5 py-3 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wider w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($visits as $i => $visit)
                            <tr class="group hover:bg-blue-50/40 transition-colors">
                                {{-- No --}}
                                <td class="pl-5 pr-2 py-3.5 text-xs text-gray-400 tabular-nums">
                                    {{ $visits->firstItem() + $i }}
                                </td>
                                {{-- Tamu --}}
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold
                                            {{ $visit->isActive() ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ strtoupper(substr($visit->display_name, 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                    @if($visit->isVisitorVerified())
                                        <p class="truncate text-sm font-medium text-gray-900">{{ $visit->visitor_name }}</p>
                                                @if($visit->visitor_relation)
                                                    <p class="text-xs text-gray-500">{{ $visit->visitor_relation }}</p>
                                                @endif
                                    @else
                                        <p class="text-sm font-medium text-amber-600">Berdasarkan pemilik kasus</p>
                                        <p class="text-xs text-gray-500">Identitas tamu belum diverifikasi</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                {{-- Pemilik Kasus / Instansi --}}
                                <td class="px-3 py-3.5">
                                    @if($visit->investigator)
                                        <p class="text-sm font-medium text-gray-700">{{ $visit->investigator->full_name ?? $visit->investigator->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $visit->investigator->jurisdiction ?? $visit->investigator->institution }}</p>
                                    @elseif($visit->visitor_institution)
                                        <p class="text-sm text-gray-700">{{ $visit->visitor_institution }}</p>
                                    @else
                                        <span class="text-sm text-gray-300">&mdash;</span>
                                    @endif
                                </td>
                                {{-- Keperluan --}}
                                <td class="px-3 py-3.5">
                                    <p class="text-sm text-gray-700">{{ $visit->purpose }}</p>
                                    @if($visit->request_count > 0)
                                        <p class="text-xs text-gray-400">{{ $visit->request_count }} permintaan</p>
                                    @endif
                                    @if($visit->purpose_detail)
                                        <p class="text-xs text-gray-400 truncate max-w-[160px]">{{ $visit->purpose_detail }}</p>
                                    @endif
                                </td>
                                {{-- Tanggal --}}
                                <td class="px-3 py-3.5">
                                    <p class="text-sm text-gray-700">{{ $visit->visit_date->format('d M Y') }}</p>
                                    <p class="text-xs text-gray-400">{{ substr($visit->visit_time, 0, 5) }}</p>
                                </td>
                                {{-- Status --}}
                                <td class="px-3 py-3.5">
                                    @if($visit->isActive())
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">
                                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                            Keluar
                                        </span>
                                    @endif
                                </td>
                                {{-- Aksi --}}
                                <td class="pl-3 pr-5 py-3.5">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <a href="{{ route('guest-book.show', $visit) }}"
                                           class="rounded-md p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                                           title="Lihat detail">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        @can('update', $visit)
                                            <a href="{{ route('guest-book.edit', $visit) }}"
                                               class="rounded-md p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                               title="Edit">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                        @endcan
                                        @if($visit->isActive() && auth()->user()->hasPermission('guest-book.checkout'))
                                            <form method="POST" action="{{ route('guest-book.checkout', $visit) }}" x-data class="inline-flex">
                                                @csrf
                                                @method('PATCH')
                                                <button type="button"
                                                        @click.prevent="showConfirmDialog({
                                                            type: 'info',
                                                            title: 'Konfirmasi Catat Keluar',
                                                            message: 'Tandai kunjungan {{ e($visit->display_name) }} sebagai selesai?<br><br>Status akan berubah menjadi Keluar.',
                                                            confirmButtonText: 'Ya, Catat Keluar',
                                                            onConfirm: () => $el.closest('form').submit()
                                                        })"
                                                        class="rounded-md p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                                        title="Catat Keluar">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/50">
                    {{ $visits->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- FAB — Catat Kunjungan --}}
    @can('create', \App\Models\GuestVisit::class)
        <a href="{{ route('guest-book.create') }}"
           class="fixed bottom-6 right-6 z-40 inline-flex items-center gap-2 rounded-full bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-500 hover:shadow-xl hover:shadow-blue-500/30 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200"
           title="Catat Kunjungan Baru">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Catat Kunjungan
        </a>
    @endcan
</x-app-layout>
