<div class="space-y-7" x-data="{ copied: null, requestError: false, copyValue(value, key) { if (!navigator.clipboard) { this.copied = 'failed-' + key; return; } navigator.clipboard.writeText(value).then(() => this.copied = key).catch(() => this.copied = 'failed-' + key); } }" x-on:livewire-request-error.window="requestError = true">
    <section class="relative overflow-hidden rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-950 via-primary-900 to-accent-900 px-5 py-6 text-white shadow-pd-md sm:px-8 sm:py-8 dark:border-primary-800">
        <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full border border-primary-400/20"></div>
        <div class="absolute -right-4 -top-8 h-32 w-32 rounded-full border border-primary-300/15"></div>
        <div class="relative grid gap-7 lg:grid-cols-[1.25fr_.75fr] lg:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-200">Ruang kerja BAP</p>
                <h1 class="mt-3 max-w-xl text-3xl font-bold tracking-tight sm:text-4xl">Saksi Ahli</h1>
                <p class="mt-3 max-w-xl text-sm leading-6 text-primary-100">Satu tempat untuk melihat surat yang masuk, mengetahui tahap yang tertunda, dan menyiapkan data BAP tanpa membuka banyak halaman.</p>
                @can('sahli.create')
                    <a href="{{ route('sahli.create') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-primary-900 shadow-sm transition hover:-translate-y-px hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary-900">
                        Tambah pengajuan
                    </a>
                @endcan
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 border-t border-white/15 pt-5 lg:border-l lg:border-t-0 lg:pl-7 lg:pt-0">
                <div>
                    <dt class="text-xs text-primary-200">Perlu ditindaklanjuti</dt>
                    <dd class="mt-1 text-3xl font-bold tracking-tight">{{ $summary['open'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-primary-200">Sudah selesai</dt>
                    <dd class="mt-1 text-3xl font-bold tracking-tight">{{ $summary['completed'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-primary-200">Farmapol</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $summary['farmapol'] }} pengajuan</dd>
                </div>
                <div>
                    <dt class="text-xs text-primary-200">Lab luar</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $summary['external'] }} pengajuan</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">Antrian pengajuan</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-pd-text">Yang perlu Anda lihat sekarang</h2>
            </div>
            <button type="button" wire:click="sortByDate" class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-lg border border-primary-200 bg-white px-3 text-sm font-semibold text-primary-800 shadow-sm transition hover:-translate-y-px hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-accent-600 dark:bg-accent-900 dark:text-primary-100 dark:hover:bg-accent-800 sm:self-auto">
                <span class="inline-block h-2 w-2 rounded-full bg-primary-500"></span>
                Acuan: {{ $sortDirection === 'desc' ? 'terbaru' : 'terlama' }}
            </button>
        </div>

        <div class="rounded-xl border border-primary-200 bg-white p-3 shadow-sm dark:border-accent-700 dark:bg-accent-900 sm:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <label class="min-w-0 flex-1">
                    <span class="sr-only">Cari pengajuan sahli</span>
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nomor surat, penyidik, atau instansi" class="min-h-11 w-full rounded-lg border-gray-300 bg-gray-50 text-sm shadow-none focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-950 dark:text-white">
                </label>
                <label class="lg:w-44">
                    <span class="sr-only">Status pengajuan</span>
                    <select wire:model.live="status" class="min-h-11 w-full rounded-lg border-gray-300 bg-gray-50 text-sm shadow-none focus:border-primary-600 focus:ring-primary-600 dark:border-accent-600 dark:bg-accent-950 dark:text-white">
                        <option value="open">Belum selesai</option>
                        <option value="completed">Selesai</option>
                        <option value="all">Semua status</option>
                    </select>
                </label>
            </div>
            <div wire:loading class="mt-3 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-100" role="status">Memuat antrian Sahli...</div>
            <div x-show="requestError" x-cloak class="mt-3 flex flex-col gap-3 rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-100 sm:flex-row sm:items-center sm:justify-between" role="alert">
                <span>Daftar Sahli gagal dimuat. Periksa koneksi lalu coba lagi.</span>
                <button type="button" wire:click="$refresh" x-on:click="requestError = false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-danger-300 px-3 font-semibold hover:bg-danger-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 dark:border-danger-700 dark:hover:bg-danger-900">Coba lagi</button>
            </div>
        </div>

        @if($requests->count())
            <div class="space-y-3">
                @foreach($requests as $request)
                    @php
                        $completedMilestones = $request->milestones->whereNotNull('completed_at')->count();
                        $milestoneTotal = count(\App\Models\ExpertWitnessRequest::MILESTONES);
                        $progressWidth = $milestoneTotal > 0 ? round(($completedMilestones / $milestoneTotal) * 100) : 0;
                    @endphp
                    <article wire:key="sahli-{{ $request->id }}" class="group overflow-hidden rounded-xl border border-primary-200 bg-white shadow-sm transition hover:-translate-y-px hover:border-primary-300 hover:shadow-pd-md dark:border-accent-700 dark:bg-accent-900 dark:hover:border-accent-500">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-primary-100 bg-primary-50/70 px-4 py-3 dark:border-accent-700 dark:bg-accent-950/60 sm:px-5">
                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                                <span class="rounded-md bg-primary-700 px-2.5 py-1 text-white">{{ $request->source === 'farmapol' ? 'Farmapol' : 'Lab luar' }}</span>
                                <span class="text-primary-800 dark:text-primary-200">Acuan {{ $request->reference_date?->translatedFormat('d F Y') ?? 'belum tersedia' }}</span>
                            </div>
                            <span class="text-xs text-pd-text-muted">{{ $completedMilestones }}/{{ $milestoneTotal }} tahap selesai</span>
                        </div>
                        <div class="grid gap-5 px-4 py-5 sm:px-5 lg:grid-cols-[1.05fr_1fr_.95fr_auto] lg:items-center">
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold text-pd-text">{{ $request->letter_number }}</p>
                                <p class="mt-1 text-sm text-pd-text-muted">Surat {{ $request->letter_date?->translatedFormat('d F Y') }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-pd-text">{{ $request->investigator_name }}</p>
                                <p class="truncate text-sm text-pd-text-muted">{{ $request->investigator_institution }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="text-sm text-pd-body">{{ $request->investigator_phone }}</span>
                                    <button type="button" x-on:click="copyValue(@js($request->investigator_phone), 'phone-{{ $request->id }}')" class="inline-flex min-h-10 items-center rounded-md border border-gray-300 px-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-accent-600 dark:text-gray-100 dark:hover:bg-accent-800" aria-label="Salin nomor telepon penyidik"><span x-text="copied === 'phone-{{ $request->id }}' ? 'Tersalin' : 'Salin'"></span></button>
                                    <span x-show="copied === 'failed-phone-{{ $request->id }}'" class="text-xs text-danger-700" role="alert">Pilih nomor lalu salin.</span>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-primary-800 dark:text-primary-200">{{ $request->next_milestone_label }}</p>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-primary-100 dark:bg-accent-700" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $milestoneTotal }}" aria-valuenow="{{ $completedMilestones }}" aria-label="Progress {{ $completedMilestones }} dari {{ $milestoneTotal }} tahap">
                                    <div class="h-full rounded-full bg-primary-600" style="width: {{ $progressWidth }}%"></div>
                                </div>
                            </div>
                            <a href="{{ route('sahli.show', $request) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary-700 px-3.5 text-sm font-semibold text-white transition hover:bg-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">Buka detail</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div>{{ $requests->links() }}</div>
        @else
            <div class="rounded-xl border border-dashed border-primary-300 bg-primary-50/60 px-6 py-12 text-center dark:border-accent-600 dark:bg-accent-900/60">
                <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">Antrian kosong</p>
                <h2 class="mt-2 text-lg font-semibold text-pd-text">Belum ada pengajuan Sahli yang cocok</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm text-pd-text-muted">Ubah kata pencarian atau status, atau tambahkan surat pengajuan baru untuk mulai mencatat BAP.</p>
                @can('sahli.create')
                    <a href="{{ route('sahli.create') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">Tambah pengajuan</a>
                @endcan
            </div>
        @endif
    </section>
</div>
