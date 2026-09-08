<div class="mx-auto max-w-5xl space-y-6">
    <header class="relative overflow-hidden rounded-2xl border border-primary-200 bg-primary-950 px-5 py-6 text-white shadow-pd-md sm:px-8 sm:py-8 dark:border-accent-700 dark:bg-accent-950">
        <div class="relative grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
            <div class="min-w-0">
                <a href="{{ route('sahli.show', $sahli) }}" class="inline-flex min-h-10 items-center text-sm font-medium text-primary-100 hover:text-white hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Kembali ke detail Sahli</a>
                <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-primary-200">Koreksi data pengajuan</p>
                <h1 class="mt-2 truncate text-2xl font-bold tracking-tight sm:text-3xl">{{ $letterNumber ?: 'Pengajuan Sahli' }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-primary-100">Perbarui identitas surat, tersangka, dan penyidik. Checklist BAP serta dokumen yang sudah diunggah tidak berubah.</p>
            </div>
            <div class="border-t border-white/15 pt-4 text-sm text-primary-100 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <p class="text-xs text-primary-200">Sumber</p>
                <p class="mt-1 font-semibold text-white">{{ $sahli->source === 'farmapol' ? 'Farmapol' : 'Lab luar' }}</p>
            </div>
        </div>
    </header>

    @if($errors->any())
        <div id="sahli-edit-errors" tabindex="-1" role="alert" class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-100">
            <h2 class="font-semibold">Periksa data yang diisi</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <section class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
            <div class="rounded-2xl border border-primary-200 bg-white p-5 shadow-sm sm:p-7 dark:border-accent-700 dark:bg-accent-900">
                <div class="border-b border-primary-100 pb-4 dark:border-accent-700">
                    <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">Identitas perkara</p>
                    <h2 class="mt-1 text-xl font-bold tracking-tight text-pd-text">Surat dan tersangka</h2>
                </div>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="letterNumber" class="block text-sm font-medium text-pd-body">Nomor surat <span class="text-danger-700">*</span></label>
                        <input id="letterNumber" wire:model="letterNumber" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-950 dark:text-white">
                        @error('letterNumber')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="letterDate" class="block text-sm font-medium text-pd-body">Tanggal surat <span class="text-danger-700">*</span></label>
                        <input id="letterDate" wire:model="letterDate" type="date" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-950 dark:text-white">
                        @error('letterDate')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="suspectName" class="block text-sm font-medium text-pd-body">Nama tersangka <span class="text-danger-700">*</span></label>
                        <input id="suspectName" wire:model="suspectName" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-950 dark:text-white">
                        <p class="mt-1 text-xs text-pd-text-muted">Gunakan nama yang tercantum pada surat pengajuan.</p>
                        @error('suspectName')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <aside class="rounded-2xl border border-primary-200 bg-primary-50/70 p-5 dark:border-accent-700 dark:bg-accent-950/60 sm:p-7">
                <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">Yang tetap aman</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-pd-text">BAP tidak ikut berubah</h2>
                <div class="mt-5 space-y-4 text-sm leading-6 text-pd-body">
                    <p>Perubahan hanya menyentuh identitas pengajuan. Dokumen surat, progres checklist, dan catatan audit tetap tersimpan.</p>
                    <dl class="space-y-3 border-t border-primary-200 pt-4 dark:border-accent-700">
                        <div class="flex items-center justify-between gap-4"><dt class="text-pd-text-muted">Tahap selesai</dt><dd class="font-semibold text-pd-text">{{ $sahli->milestones->whereNotNull('completed_at')->count() }}/{{ count(\App\Models\ExpertWitnessRequest::MILESTONES) }}</dd></div>
                        <div class="flex items-center justify-between gap-4"><dt class="text-pd-text-muted">Dokumen privat</dt><dd class="font-semibold text-pd-text">{{ $sahli->documents->count() }}</dd></div>
                    </dl>
                </div>
            </aside>
        </section>

        <section class="rounded-2xl border border-primary-200 bg-white p-5 shadow-sm sm:p-7 dark:border-accent-700 dark:bg-accent-900">
            <div class="border-b border-primary-100 pb-4 dark:border-accent-700">
                <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">Kontak kerja</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-pd-text">Identitas penyidik</h2>
            </div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="investigatorName" class="block text-sm font-medium text-pd-body">Nama penyidik <span class="text-danger-700">*</span></label>
                    <input id="investigatorName" wire:model="investigatorName" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-950 dark:text-white">
                    @error('investigatorName')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="investigatorRank" class="block text-sm font-medium text-pd-body">Pangkat penyidik <span class="text-danger-700">*</span></label>
                    <input id="investigatorRank" wire:model="investigatorRank" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-950 dark:text-white">
                    @error('investigatorRank')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="investigatorInstitution" class="block text-sm font-medium text-pd-body">Asal instansi <span class="text-danger-700">*</span></label>
                    <input id="investigatorInstitution" wire:model="investigatorInstitution" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-950 dark:text-white">
                    @error('investigatorInstitution')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="investigatorPhone" class="block text-sm font-medium text-pd-body">Nomor telepon <span class="text-danger-700">*</span></label>
                    <input id="investigatorPhone" wire:model="investigatorPhone" type="tel" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-950 dark:text-white">
                    @error('investigatorPhone')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-pd-body">Catatan</label>
                    <textarea id="notes" wire:model="notes" rows="4" class="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-950 dark:text-white"></textarea>
                    @error('notes')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('sahli.show', $sahli) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-accent-600 dark:text-gray-100 dark:hover:bg-accent-800">Batal</a>
            <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary-700 px-5 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="save">Simpan perubahan</span><span wire:loading wire:target="save">Menyimpan...</span></button>
        </div>
    </form>
</div>
