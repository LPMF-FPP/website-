<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Status Pelacakan - ' . ($trackingData['receipt_number'] ?? $trackingData['request_number'])"
            :breadcrumbs="[[ 'label' => 'Pelacakan', 'href' => route('tracking.index') ], [ 'label' => 'Hasil' ]]"
        >
            <x-slot name="actions">
                <a href="{{ route('tracking.index') }}" class="btn-sem inline-flex items-center">Lacak Lagi</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <section class="relative overflow-hidden bg-stone-50">
        <div class="absolute inset-0 pointer-events-none bg-[radial-gradient(circle_at_top_left,rgba(13,106,74,0.05),transparent_34%),radial-gradient(circle_at_85%_18%,rgba(178,137,23,0.05),transparent_26%)]"></div>

        <div class="relative mx-auto max-w-[1780px] px-3 py-8 sm:px-5 xl:px-6 2xl:px-8 lg:py-10"
             data-tracking='@json(["initial" => $condensed, "requestNumber" => $trackingData["request_number"]])'
             x-data="trackingProgress(JSON.parse($el.dataset.tracking))">

            <div class="space-y-6">
                    @php
                        $currentStage = collect($trackingData['tracking_stages'] ?? [])->firstWhere('status', 'current')
                            ?? collect($trackingData['tracking_stages'] ?? [])->lastWhere('status', 'completed')
                            ?? null;
                        $statusLabel = $currentStage['title'] ?? 'Status layanan belum tersedia';
                        $statusDescription = $currentStage['description'] ?? 'Silakan gunakan nomor resi untuk memantau pembaruan layanan.';
                        $stageBadge = match ($currentStage['status'] ?? null) {
                            'completed' => 'Selesai',
                            'current' => 'Berjalan',
                            default => 'Menunggu',
                        };
                    @endphp

                    <div class="overflow-hidden rounded-[1.5rem] border border-slate-200/65 bg-white/74 shadow-[0_18px_45px_-34px_rgba(15,23,42,0.12)] backdrop-blur-[2px]">
                        <div class="border-b border-slate-200/70 px-5 py-4 sm:px-6 xl:px-7">
                            <div class="grid gap-3 lg:grid-cols-[minmax(0,1.3fr)_repeat(3,minmax(150px,0.38fr))] lg:items-center">
                                <div class="min-w-0">
                                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-emerald-800">Status Layanan Saat Ini</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2.5">
                                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">{{ $statusLabel }}</h2>
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800">
                                            {{ $stageBadge }}
                                        </span>
                                    </div>
                                </div>

                                <div class="rounded-[1.05rem] border border-slate-200 bg-slate-50/70 px-3.5 py-3">
                                    <p class="text-[0.64rem] font-semibold uppercase tracking-[0.16em] text-slate-400">Progres</p>
                                    <p class="mt-1.5 text-base font-semibold text-slate-900" x-text="data.progress_percent + '% selesai'"></p>
                                </div>

                                <div class="rounded-[1.05rem] border border-slate-200 bg-slate-50/70 px-3.5 py-3">
                                    <p class="text-[0.64rem] font-semibold uppercase tracking-[0.16em] text-slate-400">Estimasi</p>
                                    <p class="mt-1.5 text-base font-semibold text-slate-900">{{ !empty($trackingData['estimated_completion']) ? date('d M Y', strtotime($trackingData['estimated_completion'])) : '-' }}</p>
                                </div>

                                <div class="rounded-[1.05rem] border border-slate-200 bg-slate-50/70 px-3.5 py-3">
                                    <p class="text-[0.64rem] font-semibold uppercase tracking-[0.16em] text-slate-400">Sampel</p>
                                    <p class="mt-1.5 text-base font-semibold text-slate-900">{{ $trackingData['samples_count'] }} sampel</p>
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-4 sm:px-6 xl:px-7">
                            <div class="grid gap-4 lg:grid-cols-[minmax(320px,0.82fr)_minmax(0,1.18fr)] lg:items-stretch">
                                <div class="rounded-[1.25rem] border border-slate-200/75 bg-white/86 px-4 py-4 sm:px-5">
                                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-emerald-700">Tahap yang Sedang Dipantau</p>
                                    <p class="mt-2 text-xl font-semibold tracking-tight text-slate-950">{{ $statusLabel }}</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $statusDescription }}</p>

                                    <div class="mt-4 flex items-center justify-between gap-4">
                                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400">Progres Tahap</p>
                                        <p class="text-sm font-semibold text-slate-900" x-text="data.progress_percent + '% selesai'"></p>
                                    </div>

                                    <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-200/90 ring-1 ring-slate-200/80">
                                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-700 via-emerald-600 to-amber-500 transition-all duration-500" :style="`width: ${data.progress_percent}%`"></div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center gap-2.5 text-sm text-slate-500">
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-medium text-slate-700">
                                            Resi: {{ $trackingData['receipt_number'] ?? 'N/A' }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-medium text-slate-700">
                                            Permintaan: {{ $trackingData['request_number'] }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-medium text-slate-700" x-text="lastUpdatedDisplay() || 'Pembaruan belum tersedia'"></span>
                                        <button type="button" @click="manualRefresh()" class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1 font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-100">
                                            Muat Ulang
                                        </button>
                                    </div>
                                </div>

                                <div class="rounded-[1.25rem] border border-slate-200/75 bg-white/86 px-4 py-4 sm:px-5">
                                    <div class="mb-3">
                                        <h3 class="text-lg font-semibold tracking-tight text-slate-950">Alur Proses</h3>
                                        <p class="mt-1.5 text-sm leading-6 text-slate-500">Tahapan utama ditampilkan berurutan dari penerimaan sampai penyerahan hasil.</p>
                                    </div>

                                    <ol class="space-y-1">
                                @foreach($trackingData['tracking_stages'] as $stage)
                                    @php
                                        $statusClasses = match ($stage['status']) {
                                            'completed' => 'border-emerald-200/80 bg-emerald-50/55 text-emerald-900',
                                            'current' => 'border-amber-200/80 bg-amber-50/75 text-amber-900',
                                            default => 'border-slate-200/80 bg-slate-50/65 text-slate-600',
                                        };
                                        $dotClasses = match ($stage['status']) {
                                            'completed' => 'bg-emerald-600',
                                            'current' => 'bg-amber-500',
                                            default => 'bg-slate-300',
                                        };
                                        $railClasses = match ($stage['status']) {
                                            'completed' => 'bg-emerald-500/70',
                                            'current' => 'bg-amber-400/80',
                                            default => 'bg-slate-200',
                                        };
                                    @endphp
                                    <li class="relative pl-12 {{ !$loop->last ? 'pb-3.5' : '' }}">
                                        @if (! $loop->last)
                                            <span class="absolute left-[0.95rem] top-8.5 bottom-0 w-px {{ $railClasses }}"></span>
                                        @endif

                                        <span class="absolute left-0 top-0 flex h-8 w-8 items-center justify-center rounded-full border border-white/80 bg-white text-[0.95rem] shadow-sm ring-4 ring-stone-50">
                                            {{ $stage['icon'] }}
                                        </span>

                                        <div class="rounded-[1.05rem] border px-4 py-3 {{ $statusClasses }}">
                                            <div class="space-y-2">
                                                <div class="flex flex-col gap-2 xl:flex-row xl:items-start xl:justify-between xl:gap-4">
                                                    <h4 class="min-w-0 text-[0.95rem] font-semibold tracking-tight leading-6">{{ $stage['title'] }}</h4>

                                                    <div class="flex flex-wrap items-center gap-2 xl:justify-end xl:text-right">
                                                        <span class="inline-flex items-center gap-2 rounded-full border border-current/15 bg-white/75 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.16em]">
                                                            <span class="h-2 w-2 rounded-full {{ $dotClasses }}"></span>
                                                            {{ $stage['status'] === 'completed' ? 'Selesai' : ($stage['status'] === 'current' ? 'Berjalan' : 'Menunggu') }}
                                                        </span>
                                                        @if($stage['timestamp'])
                                                            <span class="text-[0.72rem] font-medium leading-5 opacity-75">{{ date('d M Y H:i', strtotime($stage['timestamp'])) }}</span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <p class="max-w-3xl text-[0.82rem] leading-6 opacity-90">{{ $stage['description'] }}</p>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(320px,0.75fr)]">
                        <div class="overflow-hidden rounded-[1.5rem] border border-slate-200/70 bg-slate-950 text-white shadow-[0_18px_45px_-34px_rgba(15,23,42,0.34)]">
                            <div class="border-b border-white/10 px-5 py-4 sm:px-6">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-amber-300/90">Pemohon & Kontak</p>
                            </div>
                            <div class="space-y-4 px-5 py-5 sm:px-6">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.22em] text-slate-400">Penyidik / Pengirim</p>
                                    <p class="mt-2 text-lg font-semibold text-white">{{ trim(($trackingData['investigator']['rank'] ?? '').' '.($trackingData['investigator']['name'] ?? '-')) }}</p>
                                    <p class="mt-1 text-sm leading-6 text-slate-300">{{ $trackingData['investigator']['jurisdiction'] ?? '-' }}</p>
                                </div>
                                <div class="rounded-[1.35rem] border border-white/10 bg-white/5 px-4 py-3.5 text-sm leading-6 text-slate-300">
                                    <p><span class="font-semibold text-white">Telepon:</span> {{ $trackingData['investigator']['phone'] ?? '-' }}</p>
                                    <p><span class="font-semibold text-white">Nama tersangka:</span> {{ $trackingData['suspect_name'] ?? '-' }}</p>
                                    <p><span class="font-semibold text-white">Tanggal pengajuan:</span> {{ !empty($trackingData['submit_date']) ? date('d M Y H:i', strtotime($trackingData['submit_date'])) : '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[1.5rem] border border-slate-200/60 bg-white/72 p-5 shadow-[0_18px_45px_-34px_rgba(15,23,42,0.14)] backdrop-blur-[2px] sm:p-6">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-slate-400">Informasi Laboratorium</p>
                            <div class="mt-4 space-y-2.5 text-sm leading-6 text-slate-600">
                                <p class="font-semibold text-slate-900">Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri</p>
                                <p>Jl. Cipinang Baru Raya No.3B 11, RT.11/RW.6, Cipinang, Kec. Pulo Gadung, Kota Jakarta Timur, Daerah Khusus Ibukota Jakarta 13240</p>
                                <p>(021) 720-0461</p>
                                <p>labmutufarmapol@gmail.com</p>
                            </div>
                        </div>

                        <div class="rounded-[1.5rem] border border-amber-200/80 bg-amber-50/70 p-5 shadow-[0_18px_45px_-34px_rgba(180,83,9,0.12)] sm:p-6">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-amber-700">Catatan</p>
                            <p class="mt-3 text-sm leading-6 text-amber-900/85">
                                Apabila status belum berubah dalam waktu yang cukup lama, simpan nomor resi ini dan hubungi unit laboratorium untuk konfirmasi lanjutan.
                            </p>
                            <a href="{{ route('tracking.index') }}" class="mt-4 inline-flex items-center rounded-full border border-amber-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-800 transition hover:bg-amber-100">
                                Cari Resi Lain
                            </a>
                        </div>
                    </div>
            </div>
        </div>
    </section>

    @push('scripts')
    <script>
        function trackingProgress({ initial, requestNumber }) {
            return {
                data: initial || { stages: [], progress_percent: 0, last_updated: null, request_number: requestNumber },
                pollInterval: null,
                pollingMs: 45000,
                init() { this.startPolling(); },
                startPolling() { if (!this.shouldPoll()) return; this.pollInterval = setInterval(() => { this.fetchUpdate(); }, this.pollingMs); },
                shouldPoll() { return this.data.current_stage_index !== 3; },
                async fetchUpdate(force = false) {
                    try {
                        const url = `/track/${this.data.request_number}.json` + (force ? '?nocache=1' : '');
                        const resp = await fetch(url, { headers: { 'Accept': 'application/json' }});
                        if (!resp.ok) return;
                        const json = await resp.json();
                        this.data = json;
                        if (!this.shouldPoll() && this.pollInterval) { clearInterval(this.pollInterval); }
                    } catch (e) { console.warn('Tracking poll failed', e); }
                },
                manualRefresh() { this.fetchUpdate(true); },
                lastUpdatedDisplay() {
                    if (!this.data.last_updated) return '';
                    const d = new Date(this.data.last_updated.replace(' ', 'T'));
                    return 'Pembaruan terakhir: ' + d.toLocaleString('id-ID');
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
