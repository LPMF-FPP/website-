<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Rapat QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Rapat'],
                ]"
            >
                <x-slot name="actions">
                    <a href="{{ route('quality.governance.index') }}"
                       class="inline-flex items-center rounded-md border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-700 transition hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                        ← Kembali ke Tata Kelola
                    </a>
                    @if($canCreate)
                        <a href="{{ route('quality.rapat.create') }}"
                           class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                            + Buat Rapat
                        </a>
                    @endif
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="rapat" />
        </div>
    </x-slot>

    <div class="space-y-6" x-data="qmhRapatPage()">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.rapat.index') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari judul/lokasi rapat"
                    class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600 lg:col-span-2"
                >

                <select name="meeting_type" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Jenis</option>
                    <option value="mingguan" @selected(request('meeting_type') === 'mingguan')>Mingguan</option>
                    <option value="bulanan" @selected(request('meeting_type') === 'bulanan')>Bulanan</option>
                    <option value="ad_hoc" @selected(request('meeting_type') === 'ad_hoc')>Ad-hoc</option>
                </select>

                <select name="status" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Status</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="scheduled" @selected(request('status') === 'scheduled')>Terjadwal</option>
                    <option value="in_progress" @selected(request('status') === 'in_progress')>Berjalan</option>
                    <option value="completed" @selected(request('status') === 'completed')>Selesai</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option>
                </select>

                <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">

                <div class="flex gap-2 lg:col-span-6">
                    <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Terapkan</button>
                    <a href="{{ route('quality.rapat.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Judul</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Jenis</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Jadwal</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Lokasi</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Peserta</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rapats as $rapat)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('quality.rapat.show', $rapat) }}" class="font-medium text-primary-700 hover:underline">
                                    {{ $rapat->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ ucfirst(str_replace('_', ' ', $rapat->meeting_type)) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $rapat->scheduled_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $rapat->location ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                      :class="statusBadgeClass('{{ $rapat->status }}')">
                                    {{ strtoupper($rapat->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $rapat->pesertas_count ?? $rapat->pesertas->count() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                Belum ada data rapat QMH.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $rapats->links() }}
        </div>
    </div>
</x-app-layout>
