<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Preview Template QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Template QMH', 'route' => 'quality.templates.index'],
                    ['label' => 'Preview Template'],
                ]"
            />

            <x-qmh-subnav active="templates" />
        </div>
    </x-slot>

    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-700">
                Preview template: <strong>{{ $template->name }}</strong> ({{ strtoupper($template->doc_type) }} v{{ $template->version }})
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Preview ini menggunakan konten HTML template yang dipilih.
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('quality.templates.index') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Kembali ke Template QMH
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="qmh-editor-surface">
                {!! \App\Support\QmhHtmlSanitizer::sanitize($contentHtml) !!}
            </div>
        </div>
    </div>
</x-app-layout>
