<x-app-layout>
    <x-slot name="header"><x-page-header class="inventory-page-header" :title="$title" /></x-slot>
    <section class="card" aria-labelledby="inventory-unavailable-heading">
        <div role="alert" class="inventory-status-danger rounded-md p-4">
            <h2 id="inventory-unavailable-heading" class="font-semibold">Data inventori gagal dimuat</h2>
            <p class="mt-2 text-sm">Sumber data sementara tidak dapat diakses. Total dan daftar stok belum tersedia; coba muat ulang.</p>
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ $retryUrl }}" class="btn btn-primary">Coba lagi</a>
            <a href="{{ route('inventory.items.index') }}" class="btn border-sem semantic-text">Buka master item</a>
        </div>
    </section>
</x-app-layout>
