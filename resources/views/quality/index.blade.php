<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Dashboard QMH"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH'],
            ]"
        >
            <x-slot name="actions">
                <a href="{{ route('quality.documents.create') }}"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Buat Dokumen
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.documents.index') }}" class="grid gap-3 md:grid-cols-4 lg:grid-cols-5">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode atau judul"
                       class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600 md:col-span-2">
                <select name="clause" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Klausul</option>
                    @foreach([4, 5, 6, 7, 8] as $clause)
                        <option value="{{ $clause }}" @selected((string) request('clause') === (string) $clause)>Klausul {{ $clause }}</option>
                    @endforeach
                </select>
                <select name="doc_type" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Jenis</option>
                    <option value="sop" @selected(request('doc_type') === 'sop')>SOP</option>
                    <option value="ik" @selected(request('doc_type') === 'ik')>IK</option>
                    <option value="formulir" @selected(request('doc_type') === 'formulir')>Formulir</option>
                </select>
                <select name="status" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Status</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="in_review" @selected(request('status') === 'in_review')>In Review</option>
                    <option value="in_approval" @selected(request('status') === 'in_approval')>In Approval</option>
                    <option value="published" @selected(request('status') === 'published')>Published</option>
                    <option value="obsolete" @selected(request('status') === 'obsolete')>Obsolete</option>
                </select>
                <input type="number" min="0" name="edition_number" value="{{ request('edition_number') }}" placeholder="Edisi"
                       class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <input type="number" min="0" name="revision_number" value="{{ request('revision_number') }}" placeholder="Revisi"
                       class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <input type="date" name="from" value="{{ request('from') }}"
                       class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <input type="date" name="to" value="{{ request('to') }}"
                       class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                <div class="flex gap-2 md:col-span-2">
                    <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        Cari
                    </button>
                    <a href="{{ route('quality.documents.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Total Dokumen</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['total_documents'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Dokumen Published</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['published_documents'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Dokumen In Review</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['in_review_documents'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Revisi Obsolete</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['obsolete_revisions'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Unduhan Controlled</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['controlled_downloads'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Unduhan Uncontrolled</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['uncontrolled_downloads'] ?? 0 }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Kode</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Judul</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Klausul</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Jenis</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Versi</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($documents as $document)
                    @php
                        $status = $document->currentRevision?->status;
                        $statusVariant = match ($status) {
                            'draft' => 'neutral',
                            'in_review' => 'warning',
                            'in_approval' => 'info',
                            'published' => 'success',
                            'obsolete' => 'danger',
                            default => 'neutral',
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $document->doc_code }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $document->title }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $document->clause }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ strtoupper($document->doc_type) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $document->currentRevision?->version_label ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">
                            <x-status-badge :status="$status" :variant="$statusVariant" subtle="true" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('quality.documents.show', $document) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                Lihat
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                            <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                                <span class="text-3xl" aria-hidden="true">📄</span>
                                <p class="font-medium text-gray-700">Belum ada dokumen QMH.</p>
                                <p class="text-sm text-gray-500">Klik tombol <strong>Buat Dokumen</strong> untuk menambahkan draft pertama.</p>
                            </div>
                        </td>
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
