<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pemusnahan Sisa Sampel"
            :breadcrumbs="[['label' => 'Inventori', 'route' => 'inventory.dashboard'], ['label' => 'Pemusnahan Sampel']]"
        >
            <x-slot name="actions">
                <div class="flex gap-2">
                    <a href="{{ route('inventory.dashboard') }}"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                        ← Kembali
                    </a>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8" x-data="disposalIndex()">
        <section class="overflow-hidden rounded-[28px] border border-rose-100 bg-gradient-to-br from-white via-rose-50 to-orange-50 shadow-[0_24px_60px_-32px_rgba(190,24,93,0.35)]">
            <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.3fr_0.7fr] lg:px-8 lg:py-8">
                <div class="space-y-5">
                    <div class="inline-flex items-center rounded-full border border-rose-200 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-rose-700">
                        Disposal Command Center
                    </div>
                    <div class="space-y-3">
                        <h2 class="max-w-3xl text-3xl font-semibold tracking-tight text-slate-900 md:text-4xl">
                            Kelola pemusnahan sampel lebih cepat, terstruktur, dan siap audit.
                        </h2>
                        <p class="max-w-2xl text-sm leading-7 text-slate-600 md:text-base">
                            Pilih batch secara manual, eksekusi seluruh sampel eligible, atau langsung proses berdasarkan bulan selesai uji untuk kebutuhan operasional rutin.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Eligible Saat Ini</div>
                            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $eligibleOverview['total'] }}</div>
                            <p class="mt-1 text-xs text-slate-500">Sampel siap dimusnahkan</p>
                        </div>
                        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Kelompok Bulanan</div>
                            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $eligibleOverview['monthly_count'] }}</div>
                            <p class="mt-1 text-xs text-slate-500">Bulan tersedia untuk disposal</p>
                        </div>
                        <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Pilihan Aktif</div>
                            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900" x-text="selectedIds.length"></div>
                            <p class="mt-1 text-xs text-slate-500">Disimpan lintas pagination</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[24px] border border-slate-200/70 bg-slate-950 p-5 text-slate-50 shadow-[0_20px_45px_-25px_rgba(15,23,42,0.8)]">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Eksekusi Cepat</div>
                            <h3 class="mt-2 text-lg font-semibold text-white">Mode disposal langsung</h3>
                        </div>
                        <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300">
                            Live
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        <button type="button" @click="processAllEligible"
                            class="flex w-full items-center justify-between rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-left text-sm font-semibold text-rose-100 transition hover:bg-rose-500/20">
                            <span>Proses Semua Sampel Eligible</span>
                            <span class="rounded-full bg-rose-500/20 px-2 py-1 text-xs">{{ $eligibleOverview['total'] }}</span>
                        </button>
                        <button type="button" @click="processSelected" :disabled="selectedIds.length === 0"
                            class="flex w-full items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left text-sm font-semibold text-white transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40">
                            <span>Proses Sampel Terpilih</span>
                            <span class="rounded-full bg-white/10 px-2 py-1 text-xs" x-text="selectedIds.length"></span>
                        </button>
                    </div>

                    <p class="mt-4 text-xs leading-6 text-slate-400">
                        Gunakan kartu bulanan di bawah untuk memusnahkan semua sampel eligible berdasarkan bulan selesai uji.
                    </p>
                </div>
            </div>
        </section>

        <div class="border-b border-slate-200">
            <nav class="-mb-px flex flex-wrap gap-6" aria-label="Tabs">
                <a href="{{ route('inventory.disposal.index', ['tab' => 'eligible']) }}"
                    class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium {{ $tab === 'eligible' ? 'border-rose-500 text-rose-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                    Siap Musnah
                </a>
                <a href="{{ route('inventory.disposal.index', ['tab' => 'history']) }}"
                    class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium {{ $tab === 'history' ? 'border-rose-500 text-rose-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                    Riwayat Pemusnahan
                </a>
            </nav>
        </div>

        @if($tab === 'eligible')
            <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Pemusnahan Per Bulan</h3>
                            <p class="mt-1 text-sm text-slate-500">Kelompokkan sampel eligible berdasarkan bulan selesai interpretasi.</p>
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ $monthlySummaries->count() }} bulan
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse($monthlySummaries as $summary)
                            <a href="{{ route('inventory.disposal.create', ['month' => $summary['key']]) }}"
                                class="group flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-rose-200 hover:bg-rose-50">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $summary['label'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $summary['count'] }} sampel eligible siap diproses dalam satu batch bulanan.</div>
                                </div>
                                <div class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-rose-600 transition group-hover:border-rose-200 group-hover:bg-rose-100">
                                    Proses
                                </div>
                            </a>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                Belum ada kelompok bulan yang siap diproses.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Sampel Eligible</h3>
                            <p class="mt-1 text-sm text-slate-500">Pilih sampel satu per satu atau lintas halaman, lalu lanjutkan ke eksekusi.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="processAllEligible"
                                class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                                Proses Semua
                            </button>
                            <button type="button" x-show="selectedIds.length > 0" @click="processSelected"
                                class="inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                                <span x-text="`Proses Pilihan (${selectedIds.length})`"></span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200">
                        @if($eligibleSamples->isEmpty())
                            <div class="px-6 py-14 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-2xl text-emerald-600">OK</div>
                                <h4 class="mt-4 text-lg font-semibold text-slate-900">Tidak ada sampel siap musnah</h4>
                                <p class="mt-2 text-sm text-slate-500">Semua sampel yang memenuhi retensi sudah diproses atau belum memenuhi syarat disposal.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50/80 text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left">
                                                <input type="checkbox"
                                                    :checked="allVisibleSelected()"
                                                    @change="toggleAllVisible($event.target.checked)"
                                                    class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                            </th>
                                            <th class="px-4 py-3 text-left font-semibold">Kode Sampel</th>
                                            <th class="px-4 py-3 text-left font-semibold">No. LHU</th>
                                            <th class="px-4 py-3 text-left font-semibold">Tersangka</th>
                                            <th class="px-4 py-3 text-left font-semibold">Jenis</th>
                                            <th class="px-4 py-3 text-right font-semibold">Umur (hari)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach($eligibleSamples as $sample)
                                            @php
                                                $lhuProcess = $sample->testProcesses->where('stage', 'interpretation')->whereNotNull('completed_at')->first();
                                                $lhuNumber = $lhuProcess?->metadata['lhu_number'] ?? '-';
                                                $completedAt = $lhuProcess?->completed_at;
                                                $age = $completedAt ? now()->diffInDays($completedAt) : '-';
                                            @endphp
                                            <tr class="transition hover:bg-rose-50/40">
                                                <td class="px-4 py-3">
                                                    <input type="checkbox"
                                                        value="{{ $sample->id }}"
                                                        :checked="isSelected('{{ $sample->id }}')"
                                                        @change="toggleOne('{{ $sample->id }}', $event.target.checked)"
                                                        class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                                </td>
                                                <td class="px-4 py-3 font-medium text-slate-900">{{ $sample->sample_code }}</td>
                                                <td class="px-4 py-3 text-slate-600">{{ $lhuNumber }}</td>
                                                <td class="px-4 py-3 text-slate-600">{{ $sample->testRequest?->suspect_name ?? '-' }}</td>
                                                <td class="px-4 py-3 text-slate-600">{{ $sample->short_description ?? $sample->sample_form }}</td>
                                                <td class="px-4 py-3 text-right text-slate-600">{{ $age }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="border-t border-slate-200 px-4 py-4">
                                {{ $eligibleSamples->withQueryString()->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if($tab === 'history')
            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Riwayat Pemusnahan</h3>
                        <p class="mt-1 text-sm text-slate-500">Pantau batch yang sudah dieksekusi, jumlah sampel, dan akses PDF berita acara.</p>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200">
                    @if($disposals->isEmpty())
                        <div class="px-6 py-14 text-center text-sm text-slate-500">Belum ada riwayat pemusnahan.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50/80 text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">Batch No</th>
                                        <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                                        <th class="px-4 py-3 text-center font-semibold">Jumlah</th>
                                        <th class="px-4 py-3 text-left font-semibold">Metode</th>
                                        <th class="px-4 py-3 text-left font-semibold">Pelaksana</th>
                                        <th class="px-4 py-3 text-center font-semibold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($disposals as $disposal)
                                        <tr class="transition hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-900">
                                                <a href="{{ route('inventory.disposal.show', $disposal) }}" class="text-rose-600 hover:text-rose-700 hover:underline">
                                                    {{ $disposal->batch_number }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">{{ $disposal->executed_at->format('d M Y H:i') }}</td>
                                            <td class="px-4 py-3 text-center text-slate-600">{{ $disposal->samples->count() }} sampel</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $disposal->method->label() }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $disposal->executed_by_name ?: ($disposal->executedBy?->display_name_with_title ?? $disposal->executedBy?->name ?? '-') }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <a href="{{ route('inventory.disposal.pdf', $disposal) }}" class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                                    PDF
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-slate-200 px-4 py-4">
                            {{ $disposals->withQueryString()->links() }}
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>

    <script type="application/json" id="disposal-selected-sample-ids">@json($selectedSampleIds)</script>
    <script type="application/json" id="disposal-visible-sample-ids">@json($eligibleSamples->pluck('id')->map(fn($id) => (string) $id)->toArray())</script>
    <script type="application/json" id="disposal-all-sample-ids">@json($eligibleSampleIds)</script>
    <script type="application/json" id="disposal-create-url">@json(route('inventory.disposal.create'))</script>

    @push('scripts')
        <script>
            function disposalIndex() {
                const selectedSeed = JSON.parse(document.getElementById('disposal-selected-sample-ids').textContent || '[]');
                const visibleSeed = JSON.parse(document.getElementById('disposal-visible-sample-ids').textContent || '[]');
                const allEligibleIds = JSON.parse(document.getElementById('disposal-all-sample-ids').textContent || '[]');
                const createUrl = JSON.parse(document.getElementById('disposal-create-url').textContent || '""');
                const storageKey = 'inventory.disposal.selected_sample_ids';
                const storedSelection = (() => {
                    try {
                        return JSON.parse(window.localStorage.getItem(storageKey) || 'null');
                    } catch (error) {
                        return null;
                    }
                })();

                return {
                    selectedIds: Array.isArray(storedSelection) ? storedSelection : selectedSeed,
                    visibleIds: visibleSeed,
                    persistSelection() {
                        window.localStorage.setItem(storageKey, JSON.stringify(this.selectedIds));
                    },
                    isSelected(id) {
                        return this.selectedIds.includes(String(id));
                    },
                    allVisibleSelected() {
                        return this.visibleIds.length > 0 && this.visibleIds.every(id => this.selectedIds.includes(id));
                    },
                    toggleOne(id, checked) {
                        id = String(id);

                        if (checked && !this.selectedIds.includes(id)) {
                            this.selectedIds.push(id);
                            this.persistSelection();
                        }

                        if (!checked) {
                            this.selectedIds = this.selectedIds.filter(selectedId => selectedId !== id);
                            this.persistSelection();
                        }
                    },
                    toggleAllVisible(checked) {
                        if (checked) {
                            this.visibleIds.forEach(id => {
                                if (!this.selectedIds.includes(id)) {
                                    this.selectedIds.push(id);
                                }
                            });

                            this.persistSelection();

                            return;
                        }

                        this.selectedIds = this.selectedIds.filter(id => !this.visibleIds.includes(id));
                        this.persistSelection();
                    },
                    processSelected() {
                        if (this.selectedIds.length === 0) {
                            alert('Pilih minimal 1 sampel untuk dimusnahkan.');
                            return;
                        }

                        this.persistSelection();
                        window.location.href = createUrl + '?sample_ids=' + this.selectedIds.join(',');
                    },
                    processAllEligible() {
                        if (allEligibleIds.length === 0) {
                            alert('Tidak ada sampel eligible untuk diproses.');
                            return;
                        }

                        this.selectedIds = [...allEligibleIds];
                        this.persistSelection();
                        window.location.href = createUrl + '?all=1';
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>
