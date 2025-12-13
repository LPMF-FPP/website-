<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-primary-900 leading-tight">Design Examples</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto p-6 space-y-8">
        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-primary-900">Buttons</h3>
            <div class="flex flex-wrap gap-3">
                <x-button variant="primary" icon="plus">Primary</x-button>
                <x-button variant="secondary">Secondary</x-button>
                <x-button variant="outline">Outline</x-button>
                <x-button variant="success" icon="check">Success</x-button>
                <x-button variant="warning">Warning</x-button>
                <x-button variant="danger">Danger</x-button>
                <x-button variant="ghost">Ghost</x-button>
                <x-button size="sm">Sm</x-button>
                <x-button size="lg">Lg</x-button>
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-primary-900">Form Inputs</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="form-label" for="name">Nama</label>
                    <input id="name" class="form-input" placeholder="Masukkan nama" />
                    <p class="form-help">Contoh: John Doe</p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email (error)</label>
                    <input id="email" class="form-input form-input-error" placeholder="nama@domain.com" />
                    <p class="form-error">Email tidak valid</p>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-primary-900">Cards</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <x-card title="Card Default" subtitle="Dengan subtitle">
                    Contoh isi card dengan tipografi body.
                </x-card>
                <x-card title="Interactive" :interactive="true" :elevated="true">
                    Card dengan hover elevation.
                </x-card>
                <x-card title="Dengan Gambar" image="/images/logo-pusdokkes-polri.png" image-position="top">
                    Card dengan gambar di atas.
                </x-card>
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-primary-900">Alerts & Badges</h3>
            <div class="space-y-3">
                <x-alert type="success" title="Berhasil" dismissible>Data tersimpan.</x-alert>
                <x-alert type="warning" title="Perhatian">Periksa kembali inputan Anda.</x-alert>
                <x-alert type="error" title="Gagal">Terjadi kesalahan sistem.</x-alert>
                <x-alert type="info">Informasi umum untuk pengguna.</x-alert>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="badge badge-success">Selesai</span>
                <span class="badge badge-warning">Berjalan</span>
                <span class="badge badge-danger">Error</span>
                <span class="badge badge-info">Info</span>
                <span class="badge badge-secondary">Default</span>
            </div>
        </section>
    </div>
</x-app-layout>

