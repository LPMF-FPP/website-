<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Editor DOCX QMH"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Dokumen', 'route' => 'quality.documents.show', 'params' => ['document' => $document->id]],
                ['label' => 'Editor DOCX'],
            ]"
        >
            <x-slot name="actions">
                <a href="{{ route('quality.documents.show', $document) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Kembali
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ $document->doc_code }} - {{ $revision?->version_label ?? '-' }}</h3>
                    <p class="text-xs text-gray-500">Edit DOCX langsung di browser. Tiptap tetap tersedia sebagai backup.</p>
                </div>
                <a href="{{ route('quality.documents.edit', $document) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Buka Editor Backup
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div id="qmh-docx-editor" class="min-h-[70vh]"></div>
        </div>
    </div>

    @php
        $docxEditorConfig = [
            'revisionId' => $revision?->id,
            'documentId' => $document->id,
            'docCode' => $document->doc_code,
            'versionLabel' => $revision?->version_label,
            'lockUrl' => $revision ? '/api/quality/revisions/'.$revision->id.'/lock' : null,
            'heartbeatUrl' => $revision ? '/api/quality/revisions/'.$revision->id.'/heartbeat' : null,
            'unlockUrl' => $revision ? '/api/quality/revisions/'.$revision->id.'/unlock' : null,
            'docxUrl' => $revision ? '/api/quality/revisions/'.$revision->id.'/docx' : null,
            'saveDocxUrl' => $revision ? '/api/quality/revisions/'.$revision->id.'/docx' : null,
            'showUrl' => route('quality.documents.show', $document),
            'csrfToken' => csrf_token(),
        ];
    @endphp

    @push('scripts')
        <script type="application/json" id="qmh-docx-editor-config">@json($docxEditorConfig)</script>

        @vite('resources/js/pages/qmh-docx-editor.jsx')
    @endpush
</x-app-layout>
