<div class="space-y-6" x-data="{ copied: null, requestError: false, copyValue(value, key) { if (!navigator.clipboard) { this.copied = 'failed-' + key; return; } navigator.clipboard.writeText(value).then(() => this.copied = key).catch(() => this.copied = 'failed-' + key); } }" x-on:livewire-request-error.window="requestError = true">
    <section class="relative overflow-hidden rounded-2xl border border-primary-800 bg-primary-950 px-5 py-6 text-white shadow-pd-md sm:px-8 sm:py-7 dark:border-accent-700 dark:bg-accent-950">
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('sahli.index') }}" class="inline-flex min-h-10 items-center text-sm font-medium text-primary-100 hover:text-white hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Kembali ke Saksi Ahli</a>
                <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span class="rounded-md bg-white/15 px-2.5 py-1 text-primary-50">{{ $sahli->source === 'farmapol' ? 'Farmapol' : 'Lab luar' }}</span>
                    <span class="text-primary-200">{{ $sahli->reference_date?->translatedFormat('d F Y') ?? 'Tanggal acuan belum tersedia' }}</span>
                </div>
                <h1 class="mt-3 truncate text-2xl font-bold tracking-tight sm:text-3xl">{{ $sahli->letter_number }}</h1>
                <p class="mt-2 text-sm text-primary-100">{{ $sahli->investigator_name }} · {{ $sahli->investigator_institution }}</p>
            </div>
                    <div class="shrink-0 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <p class="text-xs text-primary-200">Status BAP</p>
                <p class="mt-1 text-xl font-bold">{{ $sahli->completed_at ? 'Selesai' : 'Dalam proses' }}</p>
                <p class="mt-1 text-xs text-primary-200">{{ $sahli->milestones->whereNotNull('completed_at')->count() }} dari {{ count(\App\Models\ExpertWitnessRequest::MILESTONES) }} tahap selesai</p>
                    </div>
                </div>
                @can('update', $sahli)
                    <a href="{{ route('sahli.edit', $sahli) }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg border border-white/30 px-4 text-sm font-semibold text-white hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Edit data pengajuan</a>
                @endcan
            </section>

    @if(session('success'))
        <div role="status" class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800 dark:border-success-800 dark:bg-success-950 dark:text-success-100">{{ session('success') }}</div>
    @endif

    <div x-show="requestError" x-cloak class="flex flex-col gap-3 rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-100 sm:flex-row sm:items-center sm:justify-between" role="alert">
        <span>Aksi Sahli gagal disimpan. Periksa koneksi lalu coba lagi.</span>
        <button type="button" x-on:click="requestError = false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-danger-300 px-3 font-semibold hover:bg-danger-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 dark:border-danger-700 dark:hover:bg-danger-900">Tutup</button>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <main class="min-w-0 space-y-6">
            <section class="border-b border-primary-200 pb-6 dark:border-accent-700">
                <div class="flex flex-col gap-2 border-b border-primary-100 pb-4 dark:border-accent-700 sm:flex-row sm:items-end sm:justify-between">
                    <h2 class="text-xl font-bold tracking-tight text-pd-text">Identitas surat dan penyidik</h2>
                    @if($sahli->documents->first())
                        <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('sahli.documents.download', ['document' => $sahli->documents->first()]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-primary-700 px-3 text-sm font-semibold text-primary-800 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-primary-200 dark:hover:bg-primary-950">Unduh surat PDF</a>
                    @endif
                </div>
                <dl class="mt-5 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    @foreach([['Nomor surat', $sahli->letter_number, 'letter'], ['Tanggal surat', $sahli->letter_date?->format('d-m-Y'), 'letter-date'], ['Nama tersangka', $sahli->suspect_name, 'suspect'], ['Nama penyidik', $sahli->investigator_name, 'investigator'], ['Pangkat penyidik', $sahli->investigator_rank, 'rank'], ['Asal instansi', $sahli->investigator_institution, 'institution'], ['Nomor telepon', $sahli->investigator_phone, 'phone']] as [$label, $value, $key])
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold text-pd-text-muted">{{ $label }}</dt>
                            <dd class="mt-1 flex min-w-0 items-start gap-2">
                                <span class="min-w-0 flex-1 break-words text-sm font-medium text-pd-text">{{ $value ?: 'Belum diisi' }}</span>
                                @if($value)
                                    <button type="button" x-on:click="copyValue(@js($value), '{{ $key }}')" class="inline-flex min-h-10 shrink-0 items-center rounded-md border border-gray-300 px-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-accent-600 dark:text-gray-100 dark:hover:bg-accent-800" aria-label="Salin {{ strtolower($label) }}"><span x-text="copied === '{{ $key }}' ? 'Tersalin' : 'Salin'"></span></button>
                                    <span x-show="copied === 'failed-{{ $key }}'" class="text-xs text-danger-700" role="alert">Pilih nilai lalu salin.</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @if($sahli->source === 'farmapol')
                <section class="border-b border-primary-200 pb-6 dark:border-accent-700">
                    <div class="border-b border-primary-100 pb-4 dark:border-accent-700">
                        <h2 class="text-xl font-bold tracking-tight text-pd-text">Kesimpulan resmi per sampel</h2>
                        <p class="mt-1 text-sm text-pd-text-muted">Sumber dibatasi pada hasil Farmapol dari permintaan yang sama.</p>
                    </div>
                    <div class="mt-5 space-y-5">
                        @forelse($references as $lhu => $samples)
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="h-2 w-2 rounded-full bg-primary-600"></span>
                                    <h3 class="text-sm font-bold text-pd-text">No. LHU: {{ $lhu }}</h3>
                                    @if($lhu !== 'LHU belum tersedia')
                                        @php($lhuKey = md5($lhu.'-number'))
                                        <button type="button" x-on:click="copyValue(@js($lhu), '{{ $lhuKey }}')" class="inline-flex min-h-10 items-center rounded-md border border-gray-300 px-2.5 text-xs font-semibold text-gray-700 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-accent-600 dark:text-gray-100 dark:hover:bg-accent-800" aria-label="Salin nomor LHU"><span x-text="copied === '{{ $lhuKey }}' ? 'Tersalin' : 'Salin'"></span></button>
                                        <span x-show="copied === 'failed-{{ $lhuKey }}'" class="text-xs text-danger-700" role="alert">Pilih nomor LHU lalu salin.</span>
                                    @endif
                                </div>
                                <div class="mt-3 space-y-3 border-l border-primary-200 pl-5 dark:border-accent-600">
                                    @foreach($samples as $sampleIndex => $sample)
                                        <div class="border-b border-primary-100 py-4 last:border-b-0 dark:border-accent-700">
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                @foreach([['Kode sampel', $sample['sample_code'], 'code'], ['Deskripsi sampel', $sample['description'], 'description'], ['Hasil pengujian', $sample['result'], 'result']] as [$label, $value, $field])
                                                    @php($key = md5($lhu.'-'.$sampleIndex.'-'.$field))
                                                    <div class="min-w-0 {{ $field === 'description' ? 'sm:col-span-2' : '' }}">
                                                        <p class="text-xs font-semibold text-pd-text-muted">{{ $label }}</p>
                                                        <div class="mt-1 flex min-w-0 items-start gap-2">
                                                            <p class="min-w-0 flex-1 break-words text-sm font-medium text-pd-text">{{ $value ?: 'Belum tersedia sebagai hasil resmi' }}</p>
                                                            @if($value)
                                                                <button type="button" x-on:click="copyValue(@js($value), '{{ $key }}')" class="inline-flex min-h-10 shrink-0 items-center rounded-md border border-gray-300 px-2.5 text-xs font-semibold text-gray-700 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-accent-600 dark:text-gray-100 dark:hover:bg-accent-800" aria-label="Salin {{ strtolower($label) }}"><span x-text="copied === '{{ $key }}' ? 'Tersalin' : 'Salin'"></span></button>
                                                                <span x-show="copied === 'failed-{{ $key }}'" class="text-xs text-danger-700" role="alert">Pilih nilai lalu salin.</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-pd-text-muted dark:border-accent-600">Kesimpulan resmi dan LHU belum tersedia.</div>
                        @endforelse
                    </div>
                </section>
            @endif
        </main>

        <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
            <section class="rounded-xl border border-primary-200 bg-white p-5 shadow-sm dark:border-accent-700 dark:bg-accent-900">
                <div class="flex items-start justify-between gap-3 border-b border-primary-100 pb-4 dark:border-accent-700">
                    <h2 class="text-xl font-bold tracking-tight text-pd-text">Checklist BAP</h2>
                    <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-bold text-primary-800 dark:bg-primary-950 dark:text-primary-100">{{ $sahli->milestones->whereNotNull('completed_at')->count() }}/{{ count(\App\Models\ExpertWitnessRequest::MILESTONES) }}</span>
                </div>

                <form wire:submit="saveSprin" class="mt-5 space-y-3 rounded-lg bg-primary-50/70 p-4 dark:bg-accent-950/60">
                    <div>
                        <h3 class="text-sm font-bold text-pd-text">Sprin sahli</h3>
                        <p class="mt-1 text-xs leading-5 text-pd-text-muted">Nomor dan tanggal wajib sebelum tahap pengesahan dapat dicentang.</p>
                    </div>
                    <div>
                        <label for="sprinNumber" class="block text-xs font-semibold text-pd-body">Nomor sprin</label>
                        <input id="sprinNumber" wire:model="sprinNumber" type="text" class="mt-1 min-h-10 w-full rounded-lg border-gray-300 bg-white text-sm shadow-none focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">
                        @error('sprinNumber')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="sprinDate" class="block text-xs font-semibold text-pd-body">Tanggal sprin</label>
                        <input id="sprinDate" wire:model="sprinDate" type="date" class="mt-1 min-h-10 w-full rounded-lg border-gray-300 bg-white text-sm shadow-none focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-900 dark:text-white">
                        @error('sprinDate')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
                    </div>
                    @error('sprin')<p class="text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                    <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-primary-700 px-3 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-60">Simpan sprin</button>
                </form>

                <div class="mt-5 space-y-2">
                    @foreach(\App\Models\ExpertWitnessRequest::MILESTONES as $index => $label)
                        @php($milestone = $sahli->milestones->firstWhere('code', $index))
                        <button type="button" role="checkbox" aria-checked="{{ $milestone?->completed_at ? 'true' : 'false' }}" aria-label="{{ $label }}" wire:click="toggleMilestone('{{ $index }}')" wire:loading.attr="disabled" wire:target="toggleMilestone('{{ $index }}')" class="flex min-h-14 w-full items-start gap-3 rounded-lg border p-3 text-left transition hover:-translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $milestone?->completed_at ? 'border-primary-200 bg-primary-50/70 dark:border-primary-800 dark:bg-primary-950/50' : 'border-gray-200 bg-white dark:border-accent-700 dark:bg-accent-900' }}">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $milestone?->completed_at ? 'bg-primary-700 text-white' : 'border border-gray-300 text-gray-500 dark:border-accent-500 dark:text-accent-300' }}">{{ $loop->iteration }}</span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-pd-text">{{ $label }}</span>
                                @if($milestone?->completed_at)
                                    <span class="mt-1 block text-xs text-pd-text-muted">Selesai {{ $milestone->completed_at->format('d-m-Y H:i') }} · {{ $milestone->completedBy?->name ?: 'Petugas' }}</span>
                                @else
                                    <span class="mt-1 block text-xs text-primary-700 dark:text-primary-300">Menunggu tindakan</span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>
                <p class="mt-5 border-t border-primary-100 pt-4 text-xs leading-5 text-pd-text-muted dark:border-accent-700">Proses dinyatakan selesai setelah BAP ditandatangani oleh ahli dan penyidik.</p>
            </section>
        </aside>
    </div>
</div>
