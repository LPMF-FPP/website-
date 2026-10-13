<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pencatatan Stok Awal #{{ $baseline->id }}"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pencatatan Stok Awal', 'href' => route('inventory.opening-baseline.index')], ['label' => 'Daftar hitung #'.$baseline->id]]"
        />
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Jumlah stok belum tersimpan:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if($mode !== \App\Models\InventoryMutationMode::OPENING_BASELINE)
            <p role="status" class="rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">Pengisian jumlah sementara dikunci. Status perubahan stok: {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'pencatatan stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}.</p>
        @endif

        <section class="rounded-lg bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Status: {{ ['draft' => 'Siap dihitung', 'blocked' => 'Perlu dilengkapi', 'stale' => 'Data berubah, buat daftar baru', 'submitted' => 'Menunggu pemeriksaan', 'approved' => 'Disetujui, hasil dikunci'][$baseline->status] ?? 'Status tidak diketahui' }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $visible_counted_count }} dari {{ $visible_line_count }} kombinasi barang, lot, dan lokasi yang dapat Anda lihat sudah dihitung. Jumlah saat ini mengikuti catatan stok. Mengisi jumlah fisik tidak mengubah stok atau riwayat perubahannya.</p>
                </div>
                <p class="text-sm text-gray-600">Dibuat {{ $baseline->generated_at->format('d/m/Y H:i T') }} oleh {{ $baseline->generator?->name ?? 'Pengguna tidak tersedia' }}</p>
            </div>
            @if($blockers !== [])
                <div role="alert" class="mt-4 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                    <h3 class="font-semibold">Daftar hitung belum lengkap</h3>
                    <ul class="mt-2 list-inside list-disc">@foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
                </div>
            @endif
        </section>

        <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-3 py-3">Barang</th><th class="px-3 py-3">Nomor lot</th><th class="px-3 py-3">Lokasi</th>
                        <th class="px-3 py-3 text-right">Jumlah stok saat ini</th><th class="px-3 py-3 text-right">Jumlah menurut riwayat stok</th>
                        <th class="px-3 py-3">Catatan saldo</th><th class="px-3 py-3">Jumlah fisik</th><th class="px-3 py-3">Status hitung</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($lines as $line)
                        <tr>
                            <td class="px-3 py-3"><span class="block font-medium">{{ $line->item_code_snapshot ?? 'Kode belum ditetapkan' }} · {{ $line->item_name_snapshot }}</span><span class="text-xs text-gray-600">{{ $line->item_type_snapshot }} · {{ $line->uom_snapshot }}</span></td>
                            <td class="px-3 py-3">{{ $line->lot_no_snapshot ?? 'Tanpa lot' }}</td>
                            <td class="px-3 py-3">{{ $line->location_name_snapshot }} · {{ $line->location_type_snapshot }}</td>
                            <td class="px-3 py-3 text-right font-mono tabular-nums">{{ number_format((float) $line->system_on_hand_qty, 3, ',', '.') }} {{ $line->uom_snapshot }}<span class="block text-xs text-gray-600">dipesan {{ number_format((float) $line->reserved_qty_snapshot, 3, ',', '.') }}</span></td>
                            <td class="px-3 py-3 text-right font-mono tabular-nums">{{ number_format((float) $line->ledger_on_hand_qty, 3, ',', '.') }} {{ $line->uom_snapshot }}</td>
                            <td class="px-3 py-3">{{ $line->balance_existed ? 'Ada' : 'Belum ada, jumlah 0' }}</td>
                            <td class="px-3 py-3">
                                @if($canCapture)
                                    <form method="POST" action="{{ route('inventory.opening-baseline.lines.capture', [$baseline, $line]) }}" class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        <label class="sr-only" for="physical-{{ $line->id }}">Hitungan fisik untuk {{ $line->item_name_snapshot }} di {{ $line->location_name_snapshot }}</label>
                                        <input id="physical-{{ $line->id }}" type="number" name="physical_qty" min="0" max="99999999999.999" step="0.001" value="{{ $line->physical_qty }}" class="min-h-11 w-36 rounded-md border-gray-300 text-right font-mono" required>
                                        <button class="min-h-11 rounded-md border border-primary-700 px-3 font-medium text-primary-700 hover:bg-primary-50">Simpan</button>
                                    </form>
                                @else
                                    {{ $line->physical_qty === null ? 'Belum dihitung' : number_format((float) $line->physical_qty, 3, ',', '.').' '.$line->uom_snapshot }}
                                @endif
                            </td>
                            <td class="px-3 py-3">{{ $line->counted_at ? 'Dihitung '.$line->counted_at->format('d/m/Y H:i T').' oleh '.($line->counter?->name ?? 'Pengguna tidak tersedia') : 'Belum dihitung' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-600">Belum ada barang yang dapat Anda lihat. Periksa izin lokasi atau aturan nomor lot, lalu buat daftar hitung baru.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $lines->links() }}</div>
        </section>

        @if($canSubmit)
            <section class="rounded-lg border border-primary-300 bg-white p-5">
                <h2 class="font-semibold text-gray-950">Kirim hasil hitung untuk diperiksa</h2>
                <p class="mt-1 text-sm text-gray-700">Setelah dikirim, jumlah tidak dapat diubah. Pemeriksa harus orang yang berbeda dari pengaju. Stok baru berubah setelah pemeriksa menyetujui selisih.</p>
                <form method="POST" action="{{ route('inventory.opening-baseline.submit', $baseline) }}" class="mt-3">
                    @csrf
                    <button class="btn btn-primary min-h-11 rounded-md px-4 font-semibold">Kirim hasil hitung</button>
                </form>
            </section>
        @endif

        @if($canApprove)
            <section class="rounded-lg border border-amber-300 bg-amber-50 p-5">
                <h2 class="font-semibold text-amber-950">Pemeriksaan oleh petugas lain</h2>
                <p class="mt-1 text-sm text-amber-900">Persetujuan akan mencatat semua selisih sekaligus. Sebelum menyimpan, sistem memeriksa kembali jumlah stok, barang yang sudah dipesan, dan perubahan terbaru pada setiap barang.</p>
                <form method="POST" action="{{ route('inventory.opening-baseline.decision', $baseline) }}" class="mt-4 space-y-3">
                    @csrf
                    <label for="baseline-decision-note" class="block text-sm font-medium text-gray-700">Catatan pemeriksaan <span class="font-normal text-gray-500">(wajib jika dikembalikan untuk dihitung ulang)</span></label>
                    <textarea id="baseline-decision-note" name="decision_note" maxlength="2000" rows="3" class="w-full rounded-md border-gray-300">{{ old('decision_note') }}</textarea>
                    <div class="flex flex-wrap gap-3">
                        <button name="decision" value="approve" class="btn btn-primary min-h-11 rounded-md px-4 font-semibold">Setujui dan catat selisih</button>
                        <button name="decision" value="return" class="min-h-11 rounded-md border border-amber-800 px-4 font-semibold text-amber-950 hover:bg-amber-100">Minta hitung ulang</button>
                    </div>
                </form>
            </section>
        @endif

        @if($approvals->isNotEmpty())
            <section class="rounded-lg border border-gray-200 bg-white p-5">
                <h2 class="font-semibold text-gray-950">Riwayat pemeriksaan</h2>
                <ol class="mt-3 space-y-3">
                    @foreach($approvals as $approval)
                        <li class="border-l-2 border-gray-300 pl-4 text-sm text-gray-700">
                            <p class="font-medium">Versi {{ $approval->submission_revision }} · {{ $approval->decision === 'approved' ? 'Disetujui' : 'Diminta untuk dihitung ulang' }}</p>
                            <p>{{ $approval->decided_at->format('d/m/Y H:i T') }} · diperiksa oleh {{ $approval->approver?->name ?? 'Pengguna tidak tersedia' }} · dikirim oleh {{ $approval->submitter?->name ?? 'Pengguna tidak tersedia' }}</p>
                            @if($approval->decision_note)<p class="mt-1">Catatan: {{ $approval->decision_note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</x-app-layout>
