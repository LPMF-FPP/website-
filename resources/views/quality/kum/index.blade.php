<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Kaji Ulang Manajemen (KUM)"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'KUM'],
                ]"
            >
                <x-slot name="actions">
                    <a href="{{ route('quality.governance.index') }}"
                       class="inline-flex items-center rounded-md border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-700 transition hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                        ← Kembali ke Tata Kelola
                    </a>
                    @if($canCreate)
                        <a href="{{ route('quality.kum.create') }}"
                           class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                            + Buat KUM
                        </a>
                    @endif
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="kum" />
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.kum.index') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari judul KUM" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600 lg:col-span-2">
                <input type="number" name="year" min="2000" max="2100" value="{{ request('year') }}" placeholder="Tahun" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <select name="period" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Periode</option>
                    <option value="q1" @selected(request('period') === 'q1')>Q1</option>
                    <option value="q2" @selected(request('period') === 'q2')>Q2</option>
                    <option value="q3" @selected(request('period') === 'q3')>Q3</option>
                    <option value="q4" @selected(request('period') === 'q4')>Q4</option>
                    <option value="annual" @selected(request('period') === 'annual')>Annual</option>
                </select>
                <select name="status" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Status</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="scheduled" @selected(request('status') === 'scheduled')>Terjadwal</option>
                    <option value="in_progress" @selected(request('status') === 'in_progress')>Berjalan</option>
                    <option value="completed" @selected(request('status') === 'completed')>Selesai</option>
                    <option value="closed" @selected(request('status') === 'closed')>Ditutup</option>
                </select>
                <div class="flex gap-2 lg:col-span-5">
                    <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Terapkan</button>
                    <a href="{{ route('quality.kum.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Judul</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Tahun</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Periode</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Jadwal</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kums as $kum)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3"><a href="{{ route('quality.kum.show', $kum) }}" class="font-medium text-primary-700 hover:underline">{{ $kum->title }}</a></td>
                            <td class="px-4 py-3 text-gray-700">{{ $kum->year }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ strtoupper($kum->period) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $kum->scheduled_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ strtoupper($kum->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">Belum ada data KUM QMH.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $kums->links() }}</div>
    </div>
</x-app-layout>
