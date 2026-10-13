<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pencatatan Stok Awal"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pencatatan Stok Awal']]"
        />
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Pencatatan stok awal belum tersimpan:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-lg border border-amber-300 bg-amber-50 p-5">
            <h2 class="font-semibold text-amber-950">Status perubahan stok: {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'pencatatan stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}</h2>
            <p class="mt-1 text-sm text-amber-900">Catat jumlah fisik setiap barang, nomor lot, dan lokasi, termasuk yang belum memiliki stok. Mengisi jumlah tidak mengubah stok atau riwayat perubahannya. Perubahan stok rutin tetap terkunci sampai catatan stok awal disetujui dan semua persyaratan kesiapan dipenuhi.</p>
            @if($canManagePolicy)
                <a href="{{ route('inventory.baseline.policies') }}" class="mt-2 inline-flex min-h-11 items-center font-medium text-primary-700 underline">Atur penggunaan nomor lot untuk tiap jenis barang</a>
            @endif
            <a href="{{ route('inventory.opening-baseline.readiness') }}" class="mt-2 inline-flex min-h-11 items-center font-medium text-primary-700 underline">Periksa persyaratan sebelum perubahan stok diaktifkan</a>
            @if($mode !== \App\Models\InventoryMutationMode::OPENING_BASELINE)
                <p class="mt-2 text-sm font-medium text-amber-950">Minta administrator memulai pencatatan stok awal sebelum menyusun daftar hitung.</p>
                @if($mode === \App\Models\InventoryMutationMode::DISABLED && $canStartBaseline)
                    <form method="POST" action="{{ route('inventory.opening-baseline.start') }}" class="mt-4 max-w-xl space-y-3">
                        @csrf
                        <label for="baseline-start-reason" class="block text-sm font-medium text-gray-700">Alasan memulai pencatatan stok awal <span class="text-gray-500">(opsional)</span></label>
                        <input id="baseline-start-reason" name="reason" maxlength="2000" value="{{ old('reason') }}" class="min-h-11 w-full rounded-md border-gray-300">
                        @error('reason')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        <button type="submit" class="btn btn-primary min-h-11 rounded-md px-4 py-2 font-semibold">Mulai pencatatan stok awal</button>
                    </form>
                @endif
            @elseif($canGenerate)
                <form method="POST" action="{{ route('inventory.opening-baseline.generate') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-primary min-h-11 rounded-md px-4 py-2 font-semibold">Buat daftar barang yang harus dihitung</button>
                </form>
            @endif
        </section>

        @if($baselines->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-10 text-center">
                <h2 class="font-semibold text-gray-900">Belum ada daftar hitung stok awal</h2>
                <p class="mt-1 text-sm text-gray-600">Daftar barang baru dapat dibuat setelah pencatatan stok awal dimulai.</p>
            </div>
        @else
            <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr><th class="px-4 py-3">Daftar hitung</th><th class="px-4 py-3">Dibuat</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Tindakan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($baselines as $baseline)
                            <tr>
                                <td class="px-4 py-3">#{{ $baseline->id }}</td>
                                <td class="px-4 py-3">{{ $baseline->generated_at->format('d/m/Y H:i T') }} · {{ $baseline->generator?->name ?? 'Pengguna tidak tersedia' }}</td>
                                <td class="px-4 py-3">{{ ['draft' => 'Siap dihitung', 'blocked' => 'Perlu dilengkapi', 'stale' => 'Data berubah, buat daftar baru', 'submitted' => 'Menunggu pemeriksaan', 'approved' => 'Disetujui'][$baseline->status] ?? 'Status tidak diketahui' }}</td>
                                <td class="px-4 py-3"><a class="font-medium text-primary-700 underline" href="{{ route('inventory.opening-baseline.show', $baseline) }}">Buka daftar hitung</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="p-4">{{ $baselines->links() }}</div>
            </section>
        @endif

        <p class="text-sm text-gray-600">Jika menemukan barang atau nomor lot yang belum terdaftar, tambahkan melalui menu Barang atau Lot, lalu buat daftar hitung baru. Jangan menghapus barang dari daftar atau mengubah riwayat stok untuk melanjutkan pemeriksaan.</p>

        @if($mode === \App\Models\InventoryMutationMode::ACTIVE && $canDisable)
            <section class="rounded-lg border border-red-300 bg-red-50 p-5">
                <h2 class="font-semibold text-red-950">Matikan perubahan stok darurat</h2>
                <p class="mt-1 text-sm text-red-900">Sistem akan menunggu transaksi stok yang sedang berjalan selesai sebelum menolak perubahan berikutnya.</p>
                <form method="POST" action="{{ route('inventory.opening-baseline.disable') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="min-w-64 flex-1 text-sm font-medium text-gray-700">Alasan wajib
                        <input name="reason" minlength="5" maxlength="2000" required class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                    </label>
                    <button type="submit" class="min-h-11 rounded-md bg-red-700 px-4 font-semibold text-white hover:bg-red-800">Matikan perubahan stok</button>
                </form>
            </section>
        @endif
    </div>
</x-app-layout>
