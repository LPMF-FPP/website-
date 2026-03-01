<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Preview Template (HTML) QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Preview Template (HTML)'],
                ]"
            />

            <x-qmh-subnav active="documents" />
        </div>
    </x-slot>

    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-700">
                Preview Template (HTML): <strong>{{ $template->name }}</strong> ({{ strtoupper($template->doc_type) }} v{{ $template->version }})
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Preview ini menggunakan konten HTML template yang dipilih, bukan hasil render PDF final.
            </p>

        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="qmh-editor-surface">
                {!! \App\Support\QmhHtmlSanitizer::sanitize($contentHtml) !!}
            </div>
        </div>
    </div>
</x-app-layout>
