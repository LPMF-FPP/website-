<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                :title="$document->doc_code.' - '.$document->title"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Dokumen Pendukung', 'route' => 'quality.pendukung.index'],
                    ['label' => $document->doc_code],
                ]"
            />

            <x-qmh-subnav active="documents" />
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            @php($previewFileRoute = route('quality.pendukung.file', ['document' => $document, 'v' => (int) $document->current_revision_id]))
            @php($downloadFileRoute = route('quality.pendukung.file', ['document' => $document, 'v' => (int) $document->current_revision_id, 'download' => 1]))
            <dl class="grid gap-4 md:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Kode</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $document->doc_code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Klausul</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $document->clause }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Versi Aktif</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $document->currentRevision?->version_label ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tipe File</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $document->currentRevision?->source_pdf_mime ?? '-' }}</dd>
                </div>
            </dl>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                @if($fileExists)
                    <a href="{{ $previewFileRoute }}" target="_blank" rel="noopener"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Preview File
                    </a>
                    <a href="{{ $downloadFileRoute }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Unduh File
                    </a>
                @else
                    <span class="text-sm text-red-600">File fisik tidak ditemukan.</span>
                @endif

                @if(auth()->user()?->hasPermission('qmh.create'))
                    <a href="{{ route('quality.pendukung.edit', $document) }}"
                       class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        Update Versi
                    </a>

                    <form method="POST" action="{{ route('quality.pendukung.destroy', $document) }}" onsubmit="return confirm('Hapus permanen dokumen ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Hapus Permanen</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Penggunaan di SOP/IK</h3>
            <div class="mt-3 space-y-2 text-sm text-gray-700">
                @forelse($usage as $item)
                    <div class="rounded-md border border-gray-200 px-3 py-2">
                        {{ $item->sourceDocument?->doc_code }} - {{ $item->sourceDocument?->title }}
                    </div>
                @empty
                    <p class="text-gray-500">Belum dipakai di dokumen lain.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
