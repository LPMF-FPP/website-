<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Audit QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Audit'],
                ]"
            >
                @if($canCreate)
                    <x-slot name="actions">
                        <a href="{{ route('quality.audit.create') }}"
                           class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                            + Buat Audit
                        </a>
                    </x-slot>
                @endif
            </x-page-header>

            <x-qmh-subnav active="audit" />
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.audit.index') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari judul/scope audit" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600 lg:col-span-2">
                <select name="audit_type" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Tipe</option>
                    <option value="internal" @selected(request('audit_type') === 'internal')>Internal</option>
                    <option value="eksternal" @selected(request('audit_type') === 'eksternal')>Eksternal</option>
                    <option value="surveillance" @selected(request('audit_type') === 'surveillance')>Surveillance</option>
                </select>
                <select name="status" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Status</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="scheduled" @selected(request('status') === 'scheduled')>Terjadwal</option>
                    <option value="in_progress" @selected(request('status') === 'in_progress')>Berjalan</option>
                    <option value="closed" @selected(request('status') === 'closed')>Ditutup</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option>
                </select>
                <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <div class="flex gap-2 lg:col-span-6">
                    <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Terapkan</button>
                    <a href="{{ route('quality.audit.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Judul</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Tipe</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Jadwal</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Temuan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($audits as $audit)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('quality.audit.show', $audit) }}" class="font-medium text-primary-700 hover:underline">{{ $audit->title }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ ucfirst($audit->audit_type) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $audit->scheduled_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ strtoupper($audit->status) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $audit->temuans_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">Belum ada audit QMH.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $audits->links() }}
        </div>
    </div>
</x-app-layout>
