<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <x-breadcrumbs :items="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Changelogs']]" />
            <div>
                <h1 class="text-2xl font-semibold text-primary-900">Changelogs</h1>
                <p class="text-sm text-accent-600">Riwayat perubahan dan pembaruan sistem</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="card">
            <div class="text-center py-12">
                <div class="mx-auto h-20 w-20 bg-primary-50 rounded-full flex items-center justify-center mb-6">
                    <svg class="h-10 w-10 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Riwayat Perubahan Sistem</h2>
                <p class="text-gray-500 max-w-md mx-auto">
                    Halaman ini akan menampilkan riwayat pembaruan dan perubahan sistem.
                    Fitur ini sedang dalam pengembangan.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
