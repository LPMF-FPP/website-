<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Monitoring Instrumen"
            :breadcrumbs="[['label' => 'Monitoring'], ['label' => 'Instrumen']]"
            description="Daftar instrumen dan log penggunaan"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Halaman Instrumen</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Fitur monitoring instrumen terintegrasi dengan pemrosesan sampel.
                        Silakan akses menu Laporan untuk melihat log penggunaan.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
