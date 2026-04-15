<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Pemusnahan"
            :breadcrumbs="[
                ['label' => 'Inventori', 'route' => 'inventory.dashboard'],
                ['label' => 'Pemusnahan Sampel', 'route' => 'inventory.disposal.index'],
                ['label' => $disposal->batch_number]
            ]"
        >
            <x-slot name="actions">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('inventory.disposal.index', ['tab' => 'history']) }}"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 transition hover:bg-gray-50 active:translate-y-[1px]">
                        Kembali
                    </a>
                    <a href="{{ route('inventory.disposal.pdf', $disposal) }}"
                        class="inline-flex items-center rounded-md bg-rose-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 active:translate-y-[1px]">
                        Download PDF
                    </a>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $sampleCount = $disposal->samples->count();
        $witnessEntries = $disposal->witness_entries_for_display;
        $documentationPhotos = $disposal->documentation_photos_for_display;
        $executedBy = $disposal->executed_by_name ?: ($disposal->executedBy?->display_name_with_title ?? $disposal->executedBy?->name ?? '-');
        $executorIdentity = trim(($disposal->executed_by_role ? $disposal->executed_by_role.' ' : '').($disposal->executed_by_identity ?: ''));
        $approverIdentity = trim(($disposal->approver_role ? $disposal->approver_role.' ' : '').($disposal->approver_identity ?: ''));
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <section class="rounded-[24px] border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-900 shadow-sm">
                <p class="text-sm font-medium">{{ session('success') }}</p>
            </section>
        @endif

        <section class="overflow-hidden rounded-[32px] border border-rose-100 bg-gradient-to-br from-white via-rose-50 to-orange-50 shadow-[0_24px_60px_-32px_rgba(190,24,93,0.26)]">
            <div class="grid gap-5 px-5 py-5 lg:grid-cols-[1.1fr_0.9fr] lg:px-7 lg:py-7">
                <div class="space-y-5">
                    <div class="inline-flex items-center rounded-full border border-rose-200 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-rose-700">
                        Disposal Record
                    </div>

                    <div class="space-y-3">
                        <h2 class="max-w-3xl text-[2rem] font-semibold tracking-tight text-slate-900 md:text-[2.6rem] md:leading-[1.05]">
                            Batch {{ $disposal->batch_number }} sudah tercatat dan siap ditelusuri untuk kebutuhan audit maupun cetak berita acara.
                        </h2>
                        <p class="max-w-2xl text-sm leading-7 text-slate-600 md:text-base">
                            Gunakan halaman ini untuk memverifikasi eksekusi, meninjau penandatangan, mengecek dokumentasi, dan menelusuri sampel yang dimusnahkan dalam satu batch.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Nomor Batch</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900">{{ $disposal->batch_number }}</div>
                            <p class="mt-1 text-xs text-slate-500">Identitas utama batch pemusnahan</p>
                        </div>
                        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Tanggal Eksekusi</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $disposal->executed_at->format('d M Y, H:i') }} WIB</div>
                            <p class="mt-1 text-xs text-slate-500">Waktu eksekusi tercatat</p>
                        </div>
                        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Metode</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $disposal->method->label() }}</div>
                            <p class="mt-1 text-xs text-slate-500">Metode yang dipakai dalam berita acara</p>
                        </div>
                        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Jumlah Sampel</div>
                            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $sampleCount }}</div>
                            <p class="mt-1 text-xs text-slate-500">Sampel termuat dalam batch ini</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="rounded-[24px] border border-slate-200/70 bg-slate-950 p-5 text-slate-50 shadow-[0_20px_45px_-25px_rgba(15,23,42,0.8)]">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Status Rekaman</div>
                                <h3 class="mt-2 text-lg font-semibold text-white">Batch sudah tereksekusi</h3>
                            </div>
                            <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300">
                                Audit Ready
                            </div>
                        </div>

                        <div class="mt-4 space-y-2.5 text-sm leading-6 text-slate-300">
                            <p>Pelaksana: {{ $executedBy }}</p>
                            <p>Saksi tercatat: {{ count($witnessEntries) }} orang</p>
                            <p>Dokumentasi foto: {{ count($documentationPhotos) }} lampiran</p>
                        </div>

                        <div class="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm leading-6 text-rose-100">
                            Jika diperlukan salinan resmi, gunakan tombol Download PDF untuk mengambil berita acara batch ini.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.96fr_1.04fr]">
            <div class="space-y-6">
                <details class="group rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]">
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Pelaksana & Persetujuan</div>
                            <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Data penandatangan</h3>
                            <p class="mt-1 text-sm text-slate-500">Ringkasan pihak yang tercantum pada eksekusi dan berita acara pemusnahan.</p>
                        </div>
                        <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                    </summary>

                    <div class="mt-6 grid gap-4 border-t border-slate-100 pt-6 md:grid-cols-2">
                        <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-5">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Pelaksana</div>
                            <div class="mt-5 flex min-h-[8rem] flex-col justify-end rounded-2xl border border-slate-200/80 bg-white px-4 py-5 text-center shadow-sm">
                                <div class="mx-auto inline-block border-b border-slate-900 px-4 pb-1 text-lg font-semibold uppercase tracking-[0.04em] text-slate-900">
                                    {{ $executedBy }}
                                </div>
                                <p class="mt-3 text-sm text-slate-600">{{ $executorIdentity !== '' ? $executorIdentity : '-' }}</p>
                            </div>
                        </div>

                        <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-5">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Kepala Farmapol</div>
                            <div class="mt-5 flex min-h-[8rem] flex-col justify-end rounded-2xl border border-slate-200/80 bg-white px-4 py-5 text-center shadow-sm">
                                <div class="mx-auto inline-block border-b border-slate-900 px-4 pb-1 text-lg font-semibold uppercase tracking-[0.04em] text-slate-900">
                                    {{ $disposal->approver_name ?: '-' }}
                                </div>
                                <p class="mt-3 text-sm text-slate-600">{{ $approverIdentity !== '' ? $approverIdentity : '-' }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Dicatat oleh {{ $disposal->createdBy?->name ?? '-' }}</p>
                        </div>
                    </div>
                </details>

                <details class="group rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]" open>
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Saksi</div>
                            <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Daftar saksi batch</h3>
                            <p class="mt-1 text-sm text-slate-500">Snapshot saksi disimpan permanen untuk menjaga konsistensi audit.</p>
                        </div>
                        <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                    </summary>

                    <div class="mt-6 space-y-3 border-t border-slate-100 pt-6">
                        @foreach($witnessEntries as $witness)
                            <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 px-5 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $witness['name'] }}</div>
                                <div class="mt-1 text-sm text-slate-600">{{ $witness['role'] }}</div>
                                @if(!empty($witness['identity']))
                                    <div class="mt-1 text-xs text-slate-500">{{ $witness['identity'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </details>

                @if($disposal->notes)
                    <details class="group rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Catatan</div>
                                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Catatan operasional</h3>
                                <p class="mt-1 text-sm text-slate-500">Informasi tambahan yang dicatat saat eksekusi disposal.</p>
                            </div>
                            <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                        </summary>

                        <div class="mt-6 rounded-[24px] border border-slate-200 bg-slate-50/70 p-5 text-sm leading-7 text-slate-700">
                            {{ $disposal->notes }}
                        </div>
                    </details>
                @endif
            </div>

            <div class="space-y-6 xl:sticky xl:top-6 xl:self-start">
                <details class="group rounded-[28px] border border-slate-200 bg-white p-5 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]" open>
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Dokumentasi</div>
                            <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Lampiran foto pemusnahan</h3>
                            <p class="mt-1 text-sm text-slate-500">Dokumentasi visual yang dilampirkan untuk kebutuhan pembuktian dan pelaporan.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ count($documentationPhotos) }} file
                            </div>
                            <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                        </div>
                    </summary>

                    <div class="mt-5 border-t border-slate-100 pt-5">
                        @if($documentationPhotos !== [])
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach($documentationPhotos as $photo)
                                    <a href="{{ $photo['url'] }}" target="_blank" rel="noopener noreferrer"
                                        class="group block overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm transition hover:border-rose-200 hover:shadow-[0_20px_45px_-30px_rgba(225,29,72,0.22)]">
                                        <div class="aspect-[4/3] bg-slate-100">
                                            <img src="{{ $photo['url'] }}" alt="Dokumentasi pemusnahan {{ $loop->iteration }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]">
                                        </div>
                                        <div class="px-4 py-3 text-xs text-slate-600">{{ $photo['original_name'] }}</div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                                Belum ada foto dokumentasi yang dilampirkan pada batch ini.
                            </div>
                        @endif
                    </div>
                </details>

                <details class="group rounded-[28px] border border-slate-200 bg-white p-5 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]" open>
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Manifest Batch</div>
                            <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Daftar sampel yang dimusnahkan</h3>
                            <p class="mt-1 text-sm text-slate-500">Seluruh sampel yang termuat dalam batch ini, lengkap dengan LHU dan data perkara.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ $sampleCount }} sampel
                            </div>
                            <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                        </div>
                    </summary>

                    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 border-t border-slate-100 pt-5">
                        <div class="max-h-[760px] overflow-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="sticky top-0 z-10 bg-slate-50/95 text-slate-500 backdrop-blur">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">No</th>
                                        <th class="px-4 py-3 text-left font-semibold">Kode Sampel</th>
                                        <th class="px-4 py-3 text-left font-semibold">No. LHU</th>
                                        <th class="px-4 py-3 text-left font-semibold">No. LP / Tgl</th>
                                        <th class="px-4 py-3 text-left font-semibold">Tersangka</th>
                                        <th class="px-4 py-3 text-left font-semibold">Jenis Bukti</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($disposal->samples as $index => $sample)
                                        @php
                                            $lhuProcess = $sample->testProcesses->where('stage', 'interpretation')->whereNotNull('completed_at')->first();
                                            $lhuNumber = $lhuProcess?->metadata['lhu_number'] ?? '-';
                                            $testRequest = $sample->testRequest;
                                        @endphp
                                        <tr class="align-top transition hover:bg-rose-50/40">
                                            <td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3 font-medium text-slate-900">{{ $sample->sample_code }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $lhuNumber }}</td>
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ $testRequest?->case_number ?? '-' }}
                                                @if($testRequest?->case_date)
                                                    <div class="mt-1 text-xs text-slate-400">{{ $testRequest->case_date->format('d/m/Y') }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">{{ $testRequest?->suspect_name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $sample->short_description ?? $sample->sample_form }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
        window.localStorage.removeItem('inventory.disposal.selected_sample_ids');
    </script>
    @endpush
</x-app-layout>
