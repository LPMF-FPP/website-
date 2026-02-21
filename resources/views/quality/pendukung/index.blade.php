<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Dokumen Pendukung QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Dokumen Pendukung'],
                ]"
            >
                <x-slot name="actions">
                    @if(auth()->user()?->hasPermission('qmh.create'))
                        <a href="{{ route('quality.pendukung.create') }}"
                           class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                            Upload Pendukung
                        </a>
                    @endif
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="documents" />
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.pendukung.index') }}" class="grid gap-3 md:grid-cols-4">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari kode atau judul"
                    class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600 md:col-span-2"
                >

                <select name="clause" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Klausul</option>
                    @foreach([4, 5, 6, 7, 8] as $clause)
                        <option value="{{ $clause }}" @selected((string) request('clause') === (string) $clause)>Klausul {{ $clause }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Cari</button>
                    <a href="{{ route('quality.pendukung.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Kode</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Judul</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Klausul</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Versi</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($documents as $document)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $document->doc_code }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $document->title }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $document->clause }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $document->currentRevision?->version_label ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('quality.pendukung.show', $document) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Lihat</a>
                                    @if(auth()->user()?->hasPermission('qmh.create'))
                                        <a href="{{ route('quality.pendukung.edit', $document) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">Belum ada dokumen pendukung.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $documents->links() }}
        </div>
    </div>
</x-app-layout>
