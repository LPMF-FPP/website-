@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Quality Management Hub</h1>
                <p class="text-sm text-slate-600">Buku induk dokumen klausul 4 sampai 8.</p>
            </div>
            <a href="{{ route('quality.documents.create') }}"
               class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Buat Dokumen
            </a>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.documents.index') }}" class="grid gap-3 md:grid-cols-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode atau judul"
                       class="rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900 md:col-span-2">
                <select name="clause" class="rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900">
                    <option value="">Semua Klausul</option>
                    @foreach([4, 5, 6, 7, 8] as $clause)
                        <option value="{{ $clause }}" @selected((string) request('clause') === (string) $clause)>Klausul {{ $clause }}</option>
                    @endforeach
                </select>
                <select name="doc_type" class="rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900">
                    <option value="">Semua Jenis</option>
                    <option value="sop" @selected(request('doc_type') === 'sop')>SOP</option>
                    <option value="ik" @selected(request('doc_type') === 'ik')>IK</option>
                    <option value="formulir" @selected(request('doc_type') === 'formulir')>Formulir</option>
                </select>
                <input type="date" name="from" value="{{ request('from') }}"
                       class="rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900">
                <input type="date" name="to" value="{{ request('to') }}"
                       class="rounded-md border-slate-300 text-sm focus:border-slate-900 focus:ring-slate-900">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Cari
                </button>
            </form>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Dokumen</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $summary['total_documents'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Dokumen Published</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $summary['published_documents'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Dokumen In Review</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $summary['in_review_documents'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Revisi Obsolete</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $summary['obsolete_revisions'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Unduhan Controlled</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $summary['controlled_downloads'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Unduhan Uncontrolled</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $summary['uncontrolled_downloads'] ?? 0 }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kode</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Judul</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Klausul</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Jenis</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Versi</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($documents as $document)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $document->doc_code }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $document->title }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $document->clause }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ strtoupper($document->doc_type) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $document->currentRevision?->version_label ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $document->currentRevision?->status ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada dokumen QMH.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $documents->links() }}
        </div>
    </div>
@endsection
