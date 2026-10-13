<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pemeriksaan Sebagian Stok"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pemeriksaan Sebagian Stok']]"
        />
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Pemeriksaan stok belum tersimpan:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-lg border border-sky-300 bg-sky-50 p-5 dark:border-sky-800 dark:bg-sky-950">
            <h2 class="font-semibold text-sky-950 dark:text-sky-100">Status perubahan stok: {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'pencatatan stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}</h2>
            <p class="mt-1 text-sm text-sky-900 dark:text-sky-200">Pilih barang, nomor lot, dan lokasi yang akan diperiksa. Barang lain tidak ikut dihitung. Jika jumlahnya cocok, pemeriksaan selesai tanpa mengubah stok. Jika ada selisih, pemeriksa lain harus menyetujuinya sebelum catatan stok berubah.</p>
            @if($mode === \App\Models\InventoryMutationMode::ACTIVE && $canCreate)
                <form method="POST" action="{{ route('inventory.cycle-counts.create') }}" class="mt-4 flex flex-wrap items-end gap-3" x-data="{ submitting: false }" x-on:submit="submitting = true">
                    @csrf
                    <label class="min-w-64 flex-1 text-sm font-medium text-gray-700 dark:text-gray-200">Tujuan pemeriksaan <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span>
                        <input name="purpose" maxlength="255" value="{{ old('purpose') }}" class="mt-1 min-h-11 w-full rounded-md border-gray-500 bg-white text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white">
                    </label>
                    <button type="submit" :disabled="submitting" class="btn btn-primary min-h-11 rounded-md px-4 font-semibold disabled:cursor-wait">
                        <span x-show="!submitting">Mulai pemeriksaan stok</span>
                        <span x-cloak x-show="submitting" role="status">Membuat draft…</span>
                    </button>
                </form>
            @elseif($mode !== \App\Models\InventoryMutationMode::ACTIVE)
                <p class="mt-3 text-sm font-medium text-sky-950">Pemeriksaan stok hanya tersedia saat perubahan stok aktif.</p>
            @endif
        </section>

        @if($counts->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-10 text-center dark:border-white/20 dark:bg-accent-900">
                <h2 class="font-semibold text-gray-900 dark:text-white">Belum ada pemeriksaan stok</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    @if($mode !== \App\Models\InventoryMutationMode::ACTIVE)
                        Pemeriksaan sebagian stok belum tersedia selama perubahan stok rutin tidak aktif.
                    @elseif($canCreate)
                        Mulai pemeriksaan, lalu pilih barang, nomor lot, dan lokasi yang akan dihitung.
                    @else
                        Anda dapat meninjau pemeriksaan yang dikirim, tetapi tidak memiliki izin untuk memulai pemeriksaan baru.
                    @endif
                </p>
            </div>
        @else
            <section class="overflow-x-auto rounded-lg bg-white shadow-sm dark:bg-accent-900">
                <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-800 dark:divide-white/10 dark:text-gray-100">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-accent-800 dark:text-gray-200">
                        <tr><th class="px-4 py-3">Pemeriksaan</th><th class="px-4 py-3">Dibuat</th><th class="px-4 py-3">Tujuan</th><th class="px-4 py-3">Barang yang diperiksa</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Tindakan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach($counts as $count)
                            <tr>
                                <td class="px-4 py-3">#{{ $count->id }} · versi {{ $count->revision_number }}</td>
                                <td class="px-4 py-3">{{ $count->created_at->format('d/m/Y H:i T') }} · {{ $count->creator?->name ?? 'Pengguna tidak tersedia' }}</td>
                                <td class="px-4 py-3">{{ $count->purpose ?: 'Tanpa keterangan' }}</td>
                                <td class="px-4 py-3">Sudah dihitung: {{ $count->visible_counted_count }} dari {{ $count->visible_tuple_count }} barang yang dapat Anda lihat</td>
                                <td class="px-4 py-3">{{ ['draft' => 'Sedang dihitung', 'pending_approval' => 'Menunggu persetujuan selisih', 'completed' => 'Selesai tanpa selisih', 'approved' => 'Selisih disetujui'][$count->status] ?? 'Status tidak diketahui' }}</td>
                                <td class="px-4 py-3"><a class="font-medium text-primary-700 underline" href="{{ route('inventory.cycle-counts.show', $count) }}">Buka pemeriksaan</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="p-4">{{ $counts->links() }}</div>
            </section>
        @endif
    </div>
</x-app-layout>
