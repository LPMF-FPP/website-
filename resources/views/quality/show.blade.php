<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Dokumen QMH"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Dokumen'],
                ['label' => $document->doc_code],
            ]"
        >
            <x-slot name="actions">
                <a href="{{ route('quality.documents.index') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Kembali
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900">{{ $document->doc_code }}</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $document->title }}</p>
            <p class="mt-4 text-sm text-gray-600">Halaman detail lengkap akan dilanjutkan pada task berikutnya.</p>
        </div>
    </div>
</x-app-layout>
