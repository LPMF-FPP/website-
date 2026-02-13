<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Preview Template DOCX"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Template QMH', 'route' => 'quality.templates.index'],
                ['label' => 'Preview Template'],
            ]"
        />
    </x-slot>

    <div class="space-y-4 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-700">
                Preview template: <strong>{{ $template->name }}</strong> ({{ strtoupper($template->doc_type) }} v{{ $template->version }})
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Jika preview tidak muncul, gunakan tombol "Buka File Langsung".
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ $previewFileUrl }}"
                   target="_blank"
                   rel="noopener"
                   class="inline-flex items-center rounded-md border border-blue-300 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                    Buka File Langsung
                </a>
                <a href="{{ route('quality.templates.index') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Kembali ke Template QMH
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <iframe
                src="{{ $officeViewerUrl }}"
                title="Preview Template DOCX"
                class="h-[80vh] w-full"
            ></iframe>
        </div>
    </div>
</x-app-layout>
