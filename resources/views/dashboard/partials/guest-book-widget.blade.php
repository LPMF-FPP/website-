<div class="card">
    {{-- Header Row --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
            </span>
            <h2 class="text-base font-semibold text-gray-900">Buku Tamu</h2>
        </div>
        @can('guest-book.create')
        <a href="{{ route('guest-book.create') }}"
           class="inline-flex items-center gap-1 rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500 transition-colors">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Catat
        </a>
        @endcan
    </div>

    {{-- Date Actions Row --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1 flex items-center gap-2" x-data>
            <label class="text-xs font-medium text-gray-500 shrink-0">Lihat</label>
            <input type="date" value="{{ now()->format('Y-m-d') }}"
                   class="h-8 flex-1 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 cursor-pointer"
                   @change="window.location.href='{{ route('guest-book.index') }}?from='+$el.value+'&to='+$el.value">
        </div>
        @if(auth()->user()->hasPermission('guest-book.export'))
        <div class="flex items-center gap-2" x-data>
            <label class="text-xs font-medium text-gray-500 shrink-0">Rekap</label>
            <input type="month" value="{{ now()->format('Y-m') }}"
                   class="h-8 w-[135px] rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-600 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 cursor-pointer"
                   @change="window.open('{{ route('guest-book.monthly-report') }}?month='+$el.value)">
        </div>
        @endif
    </div>

    {{-- Stat Ring --}}
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="rounded-lg bg-gray-50 p-3 text-center">
            <div class="text-2xl font-bold text-gray-900">{{ $guestBookToday['total'] }}</div>
            <div class="text-xs text-gray-500">Total Hari Ini</div>
        </div>
        <div class="rounded-lg bg-emerald-50 p-3 text-center">
            <div class="text-2xl font-bold text-emerald-700">{{ $guestBookToday['active'] }}</div>
            <div class="text-xs text-emerald-600">Masih Aktif</div>
        </div>
        <div class="rounded-lg bg-gray-50 p-3 text-center">
            <div class="text-2xl font-bold text-gray-500">{{ $guestBookToday['checked_out'] }}</div>
            <div class="text-xs text-gray-500">Selesai</div>
        </div>
    </div>

    {{-- Visitor List --}}
    @if($guestBookToday['latest']->isNotEmpty())
        <div class="space-y-1.5">
            @foreach($guestBookToday['latest'] as $visit)
                <a href="{{ route('guest-book.show', $visit) }}"
                   class="flex items-center gap-3 rounded-md px-2.5 py-2 hover:bg-gray-50 transition-colors group">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-medium
                        {{ $visit->isActive() ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ strtoupper(substr($visit->display_name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900 group-hover:text-blue-600 transition-colors">
                            {{ $visit->display_name }}
                        </p>
                        <p class="truncate text-xs text-gray-500">
                            {{ $visit->purpose }}
                            @if($visit->request_count > 0)
                                · {{ $visit->request_count }} permintaan
                            @endif
                            @if($visit->visitor_institution)
                                · {{ $visit->visitor_institution }}
                            @elseif($visit->investigator?->jurisdiction ?? $visit->investigator?->institution)
                                · {{ $visit->investigator->jurisdiction ?? $visit->investigator->institution }}
                            @endif
                        </p>
                    </div>
                    <span class="shrink-0 text-xs {{ $visit->isActive() ? 'text-emerald-600' : 'text-gray-400' }}">
                        {{ substr($visit->visit_time, 0, 5) }}
                    </span>
                </a>
            @endforeach
        </div>
    @else
        <div class="py-6 text-center">
            <p class="text-sm text-gray-400">Belum ada kunjungan hari ini</p>
            @can('guest-book.create')
            <p class="mt-1 text-xs text-gray-400">Klik <span class="text-blue-600 font-medium">Catat</span> untuk menambah</p>
            @endcan
        </div>
    @endif

    @if($guestBookToday['total'] > 6)
        <div class="mt-3 pt-2 border-t border-gray-100 text-center">
            <a href="{{ route('guest-book.index') }}" class="text-xs font-medium text-gray-500 hover:text-blue-600 transition-colors">
                + {{ $guestBookToday['total'] - 6 }} kunjungan lainnya &rarr;
            </a>
        </div>
    @endif
</div>
