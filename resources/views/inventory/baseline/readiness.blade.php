<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Bukti Kesiapan Operasi Inventori"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pencatatan Stok Awal', 'href' => route('inventory.opening-baseline.index')], ['label' => 'Persyaratan Kesiapan']]"
        />
    </x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if(session('success'))
            <p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Catatan kesiapan belum tersimpan:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-lg border border-amber-300 bg-amber-50 p-5">
            <h2 class="font-semibold text-amber-950">Status perubahan stok: {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'pencatatan stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}</h2>
            <p class="mt-1 text-sm text-amber-900">Setiap bagian memerlukan nomor atau referensi dokumen dan persetujuan dari dua akun berbeda: Administrator Inventori dan Pemilik Sistem. Dokumen tetap disimpan di tempat asalnya, bukan di aplikasi ini.</p>
            @if($readiness['missing'] !== [])
                <p class="mt-3 text-sm font-medium text-amber-950">Perubahan stok belum bisa diaktifkan. Ada {{ count($readiness['missing']) }} bagian yang belum disetujui kedua pihak.</p>
            @endif
        </section>

        <div class="space-y-4">
            @foreach($readiness['items'] as $category => $item)
                <section class="rounded-lg border {{ $item['complete'] ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white' }} p-5">
                    @php($signedRoles = $item['evidence']?->signoffs->pluck('signoff_role')->all() ?? [])
                    @php($missingSignoffs = array_values(array_diff(['inventory_administrator', 'system_owner'], $signedRoles)))
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-gray-950">{{ $item['label'] }}</h2>
                            @if($item['evidence'])
                                <p class="mt-1 text-sm text-gray-700">Nomor atau referensi dokumen: <span class="font-mono">{{ $item['evidence']->evidence_reference }}</span> · versi {{ $item['evidence']->version }}</p>
                                <ul class="mt-2 space-y-1 text-sm text-gray-700">
                                    @foreach($item['evidence']->signoffs as $signoff)
                                        <li>{{ $signoff->signoff_role === 'inventory_administrator' ? 'Administrator Inventori' : 'Pemilik Sistem' }}: {{ $signoff->actor?->name ?? 'Pengguna tidak tersedia' }} · {{ $signoff->signed_at->format('d/m/Y H:i T') }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mt-1 text-sm text-gray-600">Belum ada referensi bukti.</p>
                            @endif
                        </div>
                        <span role="status" class="inventory-status-{{ $item['complete'] ? 'success' : ($item['evidence'] ? 'warning' : 'neutral') }} rounded px-3 py-1 text-xs font-semibold">
                            @if($item['complete']) Disetujui
                            @elseif(! $item['evidence']) Belum ada bukti
                            @elseif($missingSignoffs === ['inventory_administrator']) Menunggu Administrator Inventori
                            @elseif($missingSignoffs === ['system_owner']) Menunggu Pemilik Sistem
                            @else Menunggu persetujuan
                            @endif
                        </span>
                    </div>

                    @if($mode === \App\Models\InventoryMutationMode::OPENING_BASELINE && ($canAcceptAsInventoryAdmin || $canAcceptAsSystemOwner))
                        <form method="POST" action="{{ route('inventory.opening-baseline.readiness.evidence') }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="category" value="{{ $category }}">
                            <label class="min-w-64 flex-1 text-sm font-medium text-gray-700">Nomor atau referensi dokumen keputusan
                                <input name="evidence_reference" maxlength="255" value="{{ old('evidence_reference', $item['evidence']?->evidence_reference) }}" class="mt-1 min-h-11 w-full rounded-md border-gray-300" required>
                            </label>
                            <label class="min-w-52 text-sm font-medium text-gray-700">Saya bertindak sebagai
                                <select name="signoff_role" class="mt-1 min-h-11 w-full rounded-md border-gray-300" required>
                                    @if($canAcceptAsInventoryAdmin)<option value="inventory_administrator">Administrator Inventori</option>@endif
                                    @if($canAcceptAsSystemOwner)<option value="system_owner">Pemilik Sistem</option>@endif
                                </select>
                            </label>
                            <button type="submit" class="btn btn-primary min-h-11 rounded-md px-4 font-semibold">Simpan dokumen dan persetujuan</button>
                        </form>
                    @endif
                </section>
            @endforeach
        </div>

        @if($mode === \App\Models\InventoryMutationMode::OPENING_BASELINE && $canActivate)
            <section class="rounded-lg border border-primary-300 bg-white p-5">
                <h2 class="font-semibold text-gray-950">Aktivasi mutasi rutin</h2>
                <p class="mt-1 text-sm text-gray-600">Menyetujui stok awal saja belum cukup. Semua tujuh bagian kesiapan juga harus lengkap dan disetujui.</p>
                <form method="POST" action="{{ route('inventory.opening-baseline.activate') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="min-w-64 flex-1 text-sm font-medium text-gray-700">Catatan aktivasi <span class="font-normal text-gray-500">(opsional)</span>
                        <input name="reason" maxlength="2000" class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                    </label>
                    <button type="submit" @disabled($readiness['missing'] !== [] || ! $hasApprovedBaseline) class="btn btn-primary min-h-11 rounded-md px-4 font-semibold disabled:cursor-not-allowed">Aktifkan perubahan stok rutin</button>
                </form>
            </section>
        @endif

        @if($mode === \App\Models\InventoryMutationMode::ACTIVE && $canDisable)
            <section class="rounded-lg border border-red-300 bg-red-50 p-5">
                <h2 class="font-semibold text-red-950">Matikan perubahan stok darurat</h2>
                <p class="mt-1 text-sm text-red-900">Sistem menunggu transaksi yang sedang berjalan selesai, lalu menolak perubahan stok berikutnya.</p>
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
