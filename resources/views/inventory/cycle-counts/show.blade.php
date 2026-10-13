<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pemeriksaan Stok #{{ $cycleCount->id }}"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pemeriksaan Sebagian Stok', 'href' => route('inventory.cycle-counts.index')], ['label' => 'Pemeriksaan #'.$cycleCount->id]]"
        />
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Perubahan pemeriksaan stok belum tersimpan:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-lg border border-sky-300 bg-sky-50 p-5 dark:border-sky-800 dark:bg-sky-950">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-sky-950 dark:text-sky-100">{{ ['draft' => 'Sedang dihitung', 'pending_approval' => 'Menunggu persetujuan selisih', 'completed' => 'Selesai tanpa selisih', 'approved' => 'Selisih disetujui'][$cycleCount->status] ?? 'Status tidak diketahui' }}</h2>
                    <p class="mt-1 text-sm text-sky-900 dark:text-sky-200">Jumlah yang sudah dihitung: {{ $visibleCountedCount }} dari {{ $visibleTupleCount }} barang, nomor lot, dan lokasi yang dapat Anda lihat. Barang yang tidak dapat Anda lihat tidak ditampilkan dan tidak dihitung.</p>
                    @if($cycleCount->purpose)<p class="mt-1 text-sm text-sky-900 dark:text-sky-200">Tujuan: {{ $cycleCount->purpose }}</p>@endif
                </div>
                <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-900">Pemeriksaan sebagian stok</span>
            </div>
        @if($mode !== \App\Models\InventoryMutationMode::ACTIVE)
            <p role="status" class="mt-3 rounded border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">Perubahan stok berstatus {{ ['disabled' => 'tidak aktif', 'opening_baseline' => 'pencatatan stok awal', 'active' => 'aktif'][$mode] ?? 'belum diketahui' }}. Pemeriksaan ini tidak dapat diubah.</p>
        @endif
        @if(! $hasCompleteViewScope && $cycleCount->status === \App\Models\InventoryCycleCount::STATUS_PENDING_APPROVAL)
            <p role="alert" class="mt-3 rounded border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">Anda belum memiliki akses ke semua lokasi barang yang diperiksa. Persetujuan baru tersedia setelah akses lokasi diberikan.</p>
        @endif
        </section>

        @if($canEdit && $candidateTuples)
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-accent-900">
                <h2 class="font-semibold text-gray-950 dark:text-white">Pilih barang yang akan diperiksa</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Daftar hanya menampilkan barang, nomor lot, dan lokasi yang boleh Anda lihat dan gunakan. Saat Anda memilihnya, sistem mencatat jumlah stok saat ini. Sistem akan memeriksa kembali jumlah itu sebelum menyimpan hasil hitung.</p>
                <form method="GET" action="{{ route('inventory.cycle-counts.show', $cycleCount) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    <label class="min-w-64 flex-1 text-sm font-medium text-gray-700 dark:text-gray-200">Cari kode barang, nama, nomor lot, atau lokasi
                        <input name="search" value="{{ $search }}" class="mt-1 min-h-11 w-full rounded-md border-gray-500 bg-white text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white">
                    </label>
                    <button class="min-h-11 rounded-md border border-gray-500 px-4 font-medium text-gray-800 hover:bg-gray-50 dark:border-white/30 dark:text-gray-100 dark:hover:bg-accent-800">Cari</button>
                </form>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-accent-800 dark:text-gray-200"><tr><th class="px-3 py-3">Barang / nomor lot</th><th class="px-3 py-3">Lokasi</th><th class="px-3 py-3 text-right">Jumlah saat ini</th><th class="px-3 py-3">Tindakan</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse($candidateTuples as $tuple)
                                <tr>
                                    <td class="px-3 py-3 text-gray-900 dark:text-gray-100">{{ $tuple['item_code_snapshot'] ?? 'Kode belum ditetapkan' }} · {{ $tuple['item_name_snapshot'] }}<span class="block text-xs text-gray-600 dark:text-gray-300">{{ $tuple['lot_no_snapshot'] ?? 'Tanpa lot' }} · {{ $tuple['uom_snapshot'] }}</span></td>
                                    <td class="px-3 py-3 text-gray-900 dark:text-gray-100">{{ $tuple['location_name_snapshot'] }} · {{ $tuple['location_type_snapshot'] }}</td>
                                    <td class="px-3 py-3 text-right font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((float) $tuple['system_on_hand_qty'], 3, ',', '.') }} · dipesan {{ number_format((float) $tuple['reserved_qty_snapshot'], 3, ',', '.') }}</td>
                                    <td class="px-3 py-3">
                                        <form method="POST" action="{{ route('inventory.cycle-counts.tuples.store', $cycleCount) }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
                                            @csrf
                                            <input type="hidden" name="item_id" value="{{ $tuple['item_id'] }}">
                                            <input type="hidden" name="lot_id" value="{{ $tuple['lot_id'] }}">
                                            <input type="hidden" name="location_id" value="{{ $tuple['location_id'] }}">
                                            <button type="submit" :disabled="submitting" class="min-h-11 rounded-md border border-primary-700 px-3 font-medium text-primary-800 hover:bg-primary-50 disabled:cursor-wait disabled:bg-gray-100 dark:border-primary-300 dark:text-primary-200 dark:hover:bg-slate-800 dark:disabled:bg-slate-700 dark:disabled:text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                                                <span x-show="!submitting">Tambahkan ke pemeriksaan</span><span x-cloak x-show="submitting" role="status">Menambahkan…</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-600 dark:text-gray-300">Tidak ada barang lain yang cocok dengan pencarian.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $candidateTuples->links() }}</div>
            </section>
        @endif

        <section class="overflow-x-auto rounded-lg bg-white shadow-sm dark:bg-accent-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-accent-800 dark:text-gray-200">
                    <tr><th class="px-3 py-3">Barang / nomor lot</th><th class="px-3 py-3">Lokasi</th><th class="px-3 py-3 text-right">Jumlah di catatan</th><th class="px-3 py-3">Catatan saldo</th><th class="px-3 py-3">Jumlah fisik</th><th class="px-3 py-3">Selisih / alasan</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Tindakan</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse($lines as $line)
                        <tr>
                            <td class="px-3 py-3 text-gray-900 dark:text-gray-100">{{ $line->item_code_snapshot ?? 'Kode belum ditetapkan' }} · {{ $line->item_name_snapshot }}<span class="block text-xs text-gray-600 dark:text-gray-300">{{ $line->lot_no_snapshot ?? 'Tanpa lot' }} · {{ $line->uom_snapshot }}</span></td>
                            <td class="px-3 py-3 text-gray-900 dark:text-gray-100">{{ $line->location_name_snapshot }}</td>
                            <td class="px-3 py-3 text-right font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((float) $line->system_on_hand_qty, 3, ',', '.') }}<span class="block text-xs text-gray-600 dark:text-gray-300">dipesan {{ number_format((float) $line->reserved_qty_snapshot, 3, ',', '.') }}</span></td>
                            <td class="px-3 py-3">{{ $line->balance_existed ? 'Ada' : 'Belum ada, jumlah 0' }}</td>
                            <td class="px-3 py-3">
                                @if($canEdit)
                    <form method="POST" action="{{ route('inventory.cycle-counts.lines.capture', [$cycleCount, $line]) }}" class="flex items-center gap-2" x-data="{ submitting: false }" x-on:submit="submitting = true">
                        @csrf @method('PUT')
                        <label class="sr-only" for="cycle-physical-{{ $line->id }}">Hitungan fisik {{ $line->item_name_snapshot }} di {{ $line->location_name_snapshot }}</label>
                        <input id="cycle-physical-{{ $line->id }}" type="number" name="physical_qty" min="0" max="99999999999.999" step="0.001" value="{{ $line->physical_qty }}" class="min-h-11 w-36 rounded-md border-gray-500 bg-white text-right font-mono text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white" required>
                        <label class="sr-only" for="cycle-reason-{{ $line->id }}">Alasan selisih untuk {{ $line->item_name_snapshot }}</label>
                        <input id="cycle-reason-{{ $line->id }}" type="text" name="variance_reason" maxlength="1000" value="{{ $line->variance_reason }}" placeholder="Alasan jika jumlah berbeda" class="min-h-11 w-48 rounded-md border-gray-500 bg-white text-gray-900 placeholder:text-gray-500 focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white dark:placeholder:text-gray-300">
                        <button type="submit" :disabled="submitting" class="min-h-11 rounded-md border border-primary-700 px-3 font-medium text-primary-800 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 disabled:cursor-wait disabled:bg-gray-100 dark:border-primary-300 dark:text-primary-200 dark:hover:bg-slate-800 dark:disabled:bg-slate-700 dark:disabled:text-slate-200 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                                            <span x-show="!submitting">Simpan</span><span x-cloak x-show="submitting" role="status">Menyimpan…</span>
                                        </button>
                                    </form>
                                @else
                                    {{ $line->physical_qty === null ? 'Belum dihitung' : number_format((float) $line->physical_qty, 3, ',', '.').' '.$line->uom_snapshot }}
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @if($line->physical_qty !== null && (float) $line->physical_qty !== (float) $line->system_on_hand_qty)
                                    <span class="font-mono font-semibold">{{ number_format((float) $line->physical_qty - (float) $line->system_on_hand_qty, 3, ',', '.') }}</span>
                                    <span class="block max-w-xs text-xs text-gray-700 dark:text-gray-200">{{ $line->variance_reason ?: 'Alasan belum dicatat' }}</span>
                                @else
                                    {{ $line->physical_qty === null ? 'Belum dihitung' : 'Jumlah cocok' }}
                                @endif
                            </td>
                            <td class="px-3 py-3">{{ $line->counted_at ? 'Dihitung '.$line->counted_at->format('d/m/Y H:i T').' oleh '.($line->counter?->name ?? 'Pengguna tidak tersedia') : 'Belum dihitung' }}</td>
                            <td class="px-3 py-3">
                                @if($canEdit)
                                    <form method="POST" action="{{ route('inventory.cycle-counts.tuples.destroy', [$cycleCount, $line]) }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
                                        @csrf @method('DELETE')
                                        <button type="submit" :disabled="submitting" class="min-h-11 rounded-md border border-red-700 px-3 font-medium text-red-800 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-red-300 dark:text-red-200 dark:hover:bg-red-950 disabled:cursor-wait disabled:bg-red-50 dark:disabled:bg-red-950 dark:focus-visible:ring-red-300 dark:focus-visible:ring-offset-slate-900">
                                            <span x-show="!submitting">Hapus dari pemeriksaan</span><span x-cloak x-show="submitting" role="status">Menghapus…</span>
                                        </button>
                                    </form>
                                @else
                                    Tidak ada
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-600 dark:text-gray-300">Belum ada barang yang dipilih. Tambahkan barang, nomor lot, dan lokasi yang akan dihitung.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $lines->links() }}</div>
        </section>

        @if($canSubmit)
            <section class="rounded-lg border border-primary-300 bg-white p-5 dark:border-primary-800 dark:bg-accent-900">
                <h2 class="font-semibold text-gray-950 dark:text-white">Kirim hasil pemeriksaan</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Jika jumlah cocok, pemeriksaan selesai tanpa mengubah stok. Jika ada selisih, pemeriksaan menunggu persetujuan orang lain. Stok belum berubah.</p>
                <form method="POST" action="{{ route('inventory.cycle-counts.submit', $cycleCount) }}" class="mt-3" x-data="{ submitting: false }" x-on:submit="submitting = true">
                    @csrf
                    <button type="submit" :disabled="submitting" class="btn btn-primary min-h-11 rounded-md px-4 font-semibold disabled:cursor-wait">
                        <span x-show="!submitting">Kirim hasil hitung</span><span x-cloak x-show="submitting" role="status">Memeriksa kembali jumlah stok…</span>
                    </button>
                </form>
            </section>
        @endif

        @if($canApprove)
            <section class="rounded-lg border border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950">
                <h2 class="font-semibold text-amber-950 dark:text-amber-100">Periksa selisih stok</h2>
                <p class="mt-1 text-sm text-amber-900 dark:text-amber-200">Sistem memeriksa kembali jumlah stok dan barang yang dipesan sebelum menyimpan perubahan. Jika menolak hasil hitung, tulis alasannya. Penolakan tidak mengubah stok.</p>
                <form method="POST" action="{{ route('inventory.cycle-counts.decision', $cycleCount) }}" class="mt-4 space-y-3" x-data="{ submitting: false, decision: '' }" x-on:submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }">
                    @csrf
                    <input type="hidden" name="decision" x-model="decision">
                    <label for="cycle-decision-reason" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Alasan <span class="font-normal text-gray-500 dark:text-gray-400">(wajib jika ditolak)</span></label>
                    <textarea id="cycle-decision-reason" name="reason" maxlength="2000" rows="3" class="w-full rounded-md border-gray-500 bg-white text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-white/40 dark:bg-accent-800 dark:text-white">{{ old('reason') }}</textarea>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" :disabled="submitting" @click="decision = 'approve'" class="btn btn-primary min-h-11 rounded-md px-4 font-semibold disabled:cursor-wait">Setujui dan catat selisih</button>
                        <button type="submit" :disabled="submitting" @click="decision = 'reject'" class="min-h-11 rounded-md border border-red-800 px-4 font-semibold text-red-900 hover:bg-red-50 disabled:cursor-wait dark:border-red-300 dark:text-red-200 dark:hover:bg-red-950 dark:focus-visible:ring-red-300">Tolak dan minta hitung ulang</button>
                    </div>
                    <p x-cloak x-show="submitting" role="status" class="text-sm text-gray-700">Memeriksa kembali barang yang dipilih…</p>
                </form>
            </section>
        @endif

        @if($revisionHistory->isNotEmpty())
            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-accent-900">
                <h2 class="font-semibold text-gray-950 dark:text-white">Riwayat pemeriksaan</h2>
                <ol class="mt-3 space-y-3">
                    @foreach($revisionHistory as $revision)
                        <li class="border-l-2 border-gray-300 pl-4 text-sm text-gray-700 dark:border-white/20 dark:text-gray-200">
                            <p class="font-medium">Versi {{ $revision->revision_number }} · dikirim {{ $revision->submitted_at->format('d/m/Y H:i T') }} oleh {{ $revision->submitter?->name ?? 'Pengguna tidak tersedia' }}</p>
                            @if($revision->decision)
                                <p>{{ ['completed' => 'Selesai, jumlah cocok', 'approved' => 'Selisih disetujui', 'rejected' => 'Ditolak', 'recount' => 'Diminta untuk dihitung ulang'][$revision->decision->decision] ?? 'Status tidak diketahui' }} · {{ $revision->decision->actor?->name ?? 'Pengguna tidak tersedia' }} · {{ $revision->decision->decided_at->format('d/m/Y H:i T') }}</p>
                                @if($revision->decision->reason)<p class="mt-1">Alasan: {{ $revision->decision->reason }}</p>@endif
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</x-app-layout>
