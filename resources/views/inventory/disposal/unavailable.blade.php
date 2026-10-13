<x-app-layout>
    <x-slot name="header"><x-page-header class="inventory-page-header" title="Pemusnahan Sampel" :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pemusnahan Sampel']]" /></x-slot>
    <section class="mx-auto max-w-5xl card"><div role="alert" class="inventory-status-danger rounded-md p-4"><h2 class="font-semibold">Data pemusnahan gagal dimuat</h2><p>Sumber data sementara tidak tersedia. Daftar dan total belum dapat ditampilkan.</p></div><a href="{{ $retryUrl }}" class="btn btn-primary mt-4">Coba lagi</a></section>
</x-app-layout>
