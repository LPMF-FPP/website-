<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('sahli.index') }}" class="text-sm font-medium text-primary-700 hover:underline dark:text-primary-300">Kembali ke Saksi Ahli</a>
        <h1 class="mt-3 text-2xl font-bold text-pd-text">Pengajuan Sahli</h1>
        <p class="mt-1 text-sm text-pd-text-muted">Upload surat pengajuan, lalu catat penyidik yang perlu dihubungi.</p>
    </div>

    @if($errors->any())
        <div id="sahli-form-errors" tabindex="-1" role="alert" class="rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-100">
            <h2 class="font-semibold">Periksa isian pengajuan</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form wire:submit="save" class="card space-y-8 p-5 sm:p-7">
        <section class="space-y-4">
            <div><h2 class="text-lg font-semibold text-pd-text">Surat pengajuan</h2><p class="text-sm text-pd-text-muted">Surat disimpan privat dan hanya dapat diakses petugas berizin.</p></div>
            <div>
                <label for="submissionLetter" class="block text-sm font-medium text-pd-body">Dokumen surat PDF <span class="text-danger-700">*</span></label>
                <input id="submissionLetter" wire:model="submissionLetter" type="file" accept="application/pdf,.pdf" class="mt-2 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:font-semibold file:text-primary-800 focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">
                <p class="mt-1 text-xs text-pd-text-muted">PDF maksimal 10 MB.</p>
                @error('submissionLetter')<p class="mt-1 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                <div wire:loading wire:target="submissionLetter" class="mt-2 text-sm text-primary-700" role="status">Mengunggah berkas...</div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="letterNumber" class="block text-sm font-medium text-pd-body">Nomor surat <span class="text-danger-700">*</span></label><input id="letterNumber" wire:model="letterNumber" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">@error('letterNumber')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror</div>
                <div><label for="letterDate" class="block text-sm font-medium text-pd-body">Tanggal surat <span class="text-danger-700">*</span></label><input id="letterDate" wire:model="letterDate" type="date" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">@error('letterDate')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section class="space-y-4 border-t border-primary-100 pt-6 dark:border-accent-700">
            <div><h2 class="text-lg font-semibold text-pd-text">Identitas penyidik</h2><p class="text-sm text-pd-text-muted">Gunakan kontak penyidik yang menangani BAP sahli ini.</p></div>
            <div class="grid gap-4 sm:grid-cols-2"><div><label for="investigatorName" class="block text-sm font-medium text-pd-body">Nama penyidik <span class="text-danger-700">*</span></label><input id="investigatorName" wire:model="investigatorName" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">@error('investigatorName')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror</div><div><label for="investigatorRank" class="block text-sm font-medium text-pd-body">Pangkat penyidik <span class="text-danger-700">*</span></label><input id="investigatorRank" wire:model="investigatorRank" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">@error('investigatorRank')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror</div></div>
            <div class="grid gap-4 sm:grid-cols-2"><div><label for="investigatorInstitution" class="block text-sm font-medium text-pd-body">Asal instansi <span class="text-danger-700">*</span></label><input id="investigatorInstitution" wire:model="investigatorInstitution" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">@error('investigatorInstitution')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror</div><div><label for="investigatorPhone" class="block text-sm font-medium text-pd-body">Nomor telepon <span class="text-danger-700">*</span></label><input id="investigatorPhone" wire:model="investigatorPhone" type="tel" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">@error('investigatorPhone')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror</div></div>
            <div><label for="suspectName" class="block text-sm font-medium text-pd-body">Nama tersangka <span class="text-danger-700">*</span></label><input id="suspectName" wire:model="suspectName" type="text" class="mt-2 min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">@error('suspectName')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror</div>
            <div><label for="notes" class="block text-sm font-medium text-pd-body">Catatan</label><textarea id="notes" wire:model="notes" rows="3" class="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white"></textarea>@error('notes')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror</div>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-primary-100 pt-6 sm:flex-row sm:justify-end dark:border-accent-700"><a href="{{ route('sahli.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-accent-600 dark:text-gray-100 dark:hover:bg-accent-800">Batal</a><button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary-700 px-5 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="save">Simpan pengajuan</span><span wire:loading wire:target="save">Menyimpan...</span></button></div>
    </form>
</div>
