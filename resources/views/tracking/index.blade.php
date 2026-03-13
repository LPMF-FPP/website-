<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pelacakan Status Layanan"
            :breadcrumbs="[['label' => 'Pelacakan']]"
        />
    </x-slot>

    <section class="relative overflow-hidden bg-stone-50">
        <div class="absolute inset-0 pointer-events-none bg-[radial-gradient(circle_at_top_left,rgba(13,106,74,0.05),transparent_34%),radial-gradient(circle_at_85%_20%,rgba(178,137,23,0.05),transparent_26%)]"></div>

        <div class="relative mx-auto max-w-[1780px] px-3 py-8 sm:px-5 xl:px-6 2xl:px-8 lg:py-10">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(340px,0.5fr)] lg:gap-7 xl:gap-8">
                <div class="overflow-hidden rounded-[1.5rem] bg-transparent">
                    <div class="px-4 py-4 sm:px-5 sm:py-5 xl:px-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="max-w-2xl">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.32em] text-amber-700">Layanan Publik</p>
                                <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">
                                    Masukkan nomor resi untuk melihat status layanan.
                                </h2>
                                <p class="mt-3 text-sm leading-7 text-slate-600 sm:text-base">
                                    Prioritas informasi yang ditampilkan mencakup status terkini, tahapan proses yang sedang berjalan, dan estimasi waktu selesai.
                                </p>
                            </div>

                            <div class="grid gap-2.5 sm:grid-cols-3 lg:w-[24rem] lg:grid-cols-1 xl:w-[28rem] xl:grid-cols-3">
                                <div class="rounded-[1.1rem] border border-slate-200/80 bg-white/70 px-4 py-3">
                                    <p class="text-[0.64rem] font-semibold uppercase tracking-[0.22em] text-slate-400">Fokus 01</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">Status saat ini</p>
                                </div>
                                <div class="rounded-[1.1rem] border border-slate-200/80 bg-white/70 px-4 py-3">
                                    <p class="text-[0.64rem] font-semibold uppercase tracking-[0.22em] text-slate-400">Fokus 02</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">Tahap proses</p>
                                </div>
                                <div class="rounded-[1.1rem] border border-slate-200/80 bg-white/70 px-4 py-3">
                                    <p class="text-[0.64rem] font-semibold uppercase tracking-[0.22em] text-slate-400">Fokus 03</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">Estimasi selesai</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-4 sm:px-5 sm:py-5 xl:px-6">
                        @if ($errors->any())
                            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50/80 px-4 py-4 text-rose-800 shadow-sm">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="space-y-1 text-sm leading-6">
                                        @foreach ($errors->all() as $error)
                                            <p>{{ $error }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <form action="{{ route('public.track') }}" method="POST" class="space-y-5 rounded-[1.35rem] border border-slate-200/80 bg-white/85 p-4 sm:p-5">
                            @csrf

                            <div class="space-y-2">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                                    <label for="tracking_number" class="block text-sm font-semibold text-slate-900">
                                        Nomor resi atau nomor permintaan
                                    </label>
                                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
                                        Wajib diisi
                                    </p>
                                </div>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input
                                        type="text"
                                        name="tracking_number"
                                        id="tracking_number"
                                        required
                                        value="{{ old('tracking_number') }}"
                                        placeholder="Contoh: DUMMY-RECEIPT-002"
                                        class="block w-full rounded-2xl border border-slate-300 bg-white py-4 pl-12 pr-4 text-base text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-700/10 sm:text-lg"
                                    >
                                </div>
                                <p class="text-sm leading-6 text-slate-500">
                                    Gunakan nomor yang tercantum pada tanda terima atau dokumen pengajuan. Hasil akan memprioritaskan status aktif dan tahap layanan yang sedang berlangsung.
                                </p>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <button
                                    type="submit"
                                    class="inline-flex min-h-[52px] items-center justify-center rounded-2xl bg-emerald-800 px-6 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-emerald-900 focus:outline-none focus:ring-4 focus:ring-emerald-800/20 active:translate-y-px"
                                >
                                    Lacak Sekarang
                                </button>
                                <p class="text-sm leading-6 text-slate-500">
                                    Anda akan langsung melihat status utama, progres tahapan, dan estimasi penyelesaian.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>

                <aside class="space-y-6">
                    <div class="overflow-hidden rounded-[1.5rem] border border-slate-200/70 bg-slate-950 text-white shadow-[0_18px_45px_-34px_rgba(15,23,42,0.38)]">
                        <div class="border-b border-white/10 px-6 py-5 sm:px-7">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-amber-300/90">Tahapan Layanan</p>
                        </div>
                        <ol class="space-y-5 px-6 py-6 sm:px-7 sm:py-7">
                            @foreach ([
                                ['label' => 'Penerimaan', 'desc' => 'Permintaan diverifikasi dan dicatat oleh petugas administrasi.'],
                                ['label' => 'Kaji Ulang Sampel', 'desc' => 'Kelengkapan administrasi dan kesesuaian sampel dikaji ulang sebelum proses teknis.'],
                                ['label' => 'Preparasi Sampel', 'desc' => 'Sampel disiapkan untuk proses pengujian teknis.'],
                                ['label' => 'Pengujian Instrumen', 'desc' => 'Analisa dilakukan menggunakan instrumen laboratorium.'],
                                ['label' => 'Interpretasi Hasil', 'desc' => 'Hasil pengujian ditelaah dan disusun sebagai keluaran resmi layanan.'],
                                ['label' => 'Penyerahan', 'desc' => 'Hasil resmi tersedia untuk diserahkan kepada pemohon.'],
                            ] as $index => $stage)
                                <li class="flex gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/15 bg-white/5 text-sm font-semibold text-amber-200">
                                        {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-white/95">{{ $stage['label'] }}</h3>
                                        <p class="mt-1 text-sm leading-6 text-slate-300">{{ $stage['desc'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    <div class="rounded-[1.5rem] border border-slate-200/80 bg-white/80 p-5 shadow-[0_18px_45px_-34px_rgba(15,23,42,0.16)] sm:p-6">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-slate-400">Panduan Input</p>
                        <div class="mt-5 space-y-4 text-sm leading-7 text-slate-600">
                            <p>Nomor resi umumnya tercetak pada tanda terima yang Anda terima saat penyerahan sampel.</p>
                            <p>Sistem mendukung pencarian dengan format huruf besar/kecil tanpa membedakan kapitalisasi.</p>
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 font-mono text-sm text-slate-700">
                                DUMMY-RECEIPT-002<br>
                                DUMMY-PENGUJIAN-002
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</x-app-layout>
