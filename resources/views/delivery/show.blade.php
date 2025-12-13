<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Detail Penyerahan · ' . $request->request_number"
            :breadcrumbs="[[ 'label' => 'Penyerahan', 'href' => route('delivery.index') ], [ 'label' => 'Detail' ]]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
          <a href="{{ route('delivery.index') }}"
              class="inline-flex items-center text-sm font-semibold text-primary-700 transition hover:text-primary-800">
            &larr; Kembali ke daftar penyerahan
        </a>



        @if(session('success'))
            <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>{{ session('success') }}</div>
                    <div class="flex gap-2">
                        @if($delivery)
                            <a href="{{ route('delivery.handover.view', $delivery) }}" class="inline-flex items-center rounded border border-primary-600 px-3 py-1 text-sm font-semibold text-primary-700 hover:bg-primary-50">Buka BA</a>
                            <a href="{{ route('delivery.handover.download', $delivery) }}" class="inline-flex items-center rounded bg-primary-600 px-3 py-1 text-sm font-semibold text-white hover:bg-primary-700">Unduh PDF</a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($request->status === 'ready_for_delivery')
            <div class="rounded border border-blue-200 bg-blue-50 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-blue-900">Siap untuk Diserahkan</h3>
                        <p class="mt-1 text-sm text-blue-700">
                            Permintaan ini siap untuk diserahkan. Klik tombol di sebelah kanan setelah penyerahan selesai dilaksanakan.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('delivery.complete', $request) }}" class="flex-shrink-0">
                        @csrf
                        <button type="submit"
                            onclick="return confirm('Tandai penyerahan sebagai selesai?\n\nTanggal selesai akan diset ke waktu sekarang dan status akan berubah menjadi Selesai.')"
                            class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Tandai Penyerahan Selesai
                        </button>
                    </form>
                </div>
            </div>
        @elseif($request->status === 'completed')
            <div class="rounded border border-green-200 bg-green-50 p-4">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h3 class="text-sm font-semibold text-green-900">Penyerahan Selesai</h3>
                        <p class="mt-1 text-sm text-green-700">
                            Penyerahan untuk permintaan ini telah diselesaikan{{ $request->completed_at ? ' pada ' . $request->completed_at->format('d/m/Y H:i') : '' }}.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg bg-white shadow-sm">
                <div class="px-6 py-5 text-sm text-gray-700 space-y-4">
                    <x-page-section title="Ringkasan Permintaan">
                    <div>
                        <span class="font-semibold text-gray-900">Status</span>
                        @php
                            $statusBadges = [
                                'submitted' => 'bg-blue-100 text-blue-700',
                                'in_testing' => 'bg-yellow-100 text-yellow-700',
                                'analysis' => 'bg-orange-100 text-orange-700',
                                'ready_for_delivery' => 'bg-teal-100 text-teal-700',
                                'completed' => 'bg-green-100 text-green-700',
                            ];
                            $statusLabels = [
                                'submitted' => 'Diajukan',
                                'in_testing' => 'Sedang diuji',
                                'analysis' => 'Analisis',
                                'ready_for_delivery' => 'Siap diserahkan',
                                'completed' => 'Selesai',
                            ];
                            $statusClass = $statusBadges[$request->status] ?? 'bg-gray-100 text-gray-700';
                            $statusText = $statusLabels[$request->status] ?? ucfirst(str_replace('_', ' ', $request->status));
                        @endphp
                        <span class="ml-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                            {{ $statusText }}
                        </span>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Penyidik</div>
                            <div class="mt-1 font-semibold text-gray-900">{{ optional($request->investigator)->name ?? '-' }}</div>
                            @if($request->investigator)
                                <div class="text-xs text-gray-500">{{ $request->investigator->rank }} &middot; {{ $request->investigator->jurisdiction }}</div>
                            @endif
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Tersangka</div>
                            <div class="mt-1 font-semibold text-gray-900">{{ $request->suspect_name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Tanggal Selesai</div>
                            <div class="mt-1 text-gray-900">{{ optional($request->completed_at)->format('d/m/Y H:i') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Jumlah Sampel</div>
                            <div class="mt-1 text-gray-900">{{ $request->samples->count() }}</div>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500">Catatan Kasus</div>
                        <p class="mt-1 text-sm text-gray-700">{{ $request->case_description ?? 'Tidak ada catatan tambahan.' }}</p>
                    </div>
                    </x-page-section>
                </div>
            </div>

            <div class="rounded-lg bg-white shadow-sm">
                <div class="px-6 py-5 text-sm text-gray-700 space-y-4">
                    <x-page-section title="Berita Acara Penyerahan">
                        @if($delivery)
                            <div x-data="handoverCard()" x-init="init()" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-white text-blue-600 ring-1 ring-inset ring-blue-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M19.5 3.75h-15A1.5 1.5 0 003 5.25v13.5a1.5 1.5 0 001.5 1.5h15a1.5 1.5 0 001.5-1.5V5.25a1.5 1.5 0 00-1.5-1.5zm-13.5 3h12v9h-12v-9z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-blue-900">Berita Acara Penyerahan Hasil</div>
                                            <div class="mt-1 text-sm text-blue-800">
                                                <span>Dokumen resmi Penyerahan Hasil dari Farmapol Pusdokkes Polri.</span>
                                                <template x-if="loading">
                                                    <span class="ml-1 text-blue-700">Memeriksa status…</span>
                                                </template>
                                                <template x-if="!loading && !existsPdf">
                                                    <span class="ml-1 text-orange-700">Dokumen belum di-generate.</span>
                                                </template>
                                                <template x-if="!loading && existsPdf">
                                                    <span class="ml-1 text-green-700">Dokumen sudah tersedia.</span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <!-- When not exists: show Generate -->
                                        <template x-if="!loading && !existsPdf">
                                            <form method="POST" action="{{ route('delivery.handover.generate', $delivery) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">Generate Dokumen</button>
                                            </form>
                                        </template>

                                        <!-- When exists: show Open/Download and Regenerate small -->
                                        <template x-if="!loading && existsPdf">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('delivery.handover.view', $delivery) }}" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-blue-700 ring-1 ring-inset ring-blue-300 transition hover:bg-blue-50">Buka Dokumen</a>
                                                <a href="{{ route('delivery.handover.download', $delivery) }}" class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700">Unduh PDF</a>
                                                <form method="POST" action="{{ route('delivery.handover.generate', $delivery) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200 transition hover:text-blue-700">Regenerate</button>
                                                </form>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3">
                                <a href="{{ route('delivery.survey', $request) }}" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">Kirim Survei Layanan</a>
                            </div>

                            <script>
                                function handoverCard() {
                                    return {
                                        loading: true,
                                        existsPdf: false,
                                        async init() { await this.check(); },
                                        async check() {
                                            this.loading = true;
                                            try {
                                                const url = @json(route('delivery.handover.status', $request));
                                                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                                                if (res.ok) {
                                                    const data = await res.json();
                                                    this.existsPdf = !!(data && data.pdf && data.pdf.exists);
                                                } else {
                                                    this.existsPdf = false;
                                                }
                                            } catch (e) {
                                                this.existsPdf = false;
                                            } finally {
                                                this.loading = false;
                                            }
                                        }
                                    }
                                }
                            </script>
                        @else
                            <p class="text-gray-500">Delivery record not found for this request.</p>
                        @endif
                    </x-page-section>
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white shadow-sm">
            <div class="divide-y divide-gray-100">
                @foreach($request->samples as $sample)
                    <div class="px-6 py-5">
                        <x-page-section :title="$sample->sample_name">
                            <p class="text-sm text-gray-500">Kode Sampel: {{ $sample->sample_code }}</p>
                            @php
                                $sampleBadges = [
                                    'pending' => 'bg-gray-100 text-gray-700',
                                    'in_testing' => 'bg-yellow-100 text-yellow-700',
                                    'analysis' => 'bg-orange-100 text-orange-700',
                                    'ready_for_delivery' => 'bg-teal-100 text-teal-700',
                                ];
                                $sampleLabels = [
                                    'pending' => 'Belum diproses',
                                    'in_testing' => 'Sedang diuji',
                                    'analysis' => 'Analisis',
                                    'ready_for_delivery' => 'Siap diserahkan',
                                ];
                                $statusValue = is_object($sample->status) ? $sample->status->value : $sample->status;
                                $sampleClass = $sampleBadges[$statusValue] ?? 'bg-gray-100 text-gray-700';
                                $sampleText = $sampleLabels[$statusValue] ?? ucfirst(str_replace('_', ' ', $statusValue));
                            @endphp
                            <span class="mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $sampleClass }}">
                                {{ $sampleText }}
                            </span>

                        @php
                            $completedStages = $sample->testProcesses
                                ->where('completed_at', '!=', null)
                                ->groupBy('stage')
                                ->count();
                            $isFullyComplete = $completedStages === count($stages);
                        @endphp

                        @if($isFullyComplete)
                            <div class="mt-3 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">
                                ✓ Semua tahap pengujian telah selesai
                            </div>
                        @endif

                        @php
                            $deliveredDisplay = $sample->getAttribute('delivered_quantity_display');
                            $testingDisplay = $sample->getAttribute('testing_quantity_display');
                            $leftoverDisplay = $sample->getAttribute('leftover_quantity_display');
                        @endphp

                        @if($deliveredDisplay || $testingDisplay || $leftoverDisplay)
                            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                                @if($deliveredDisplay)
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-gray-500">Jumlah Sampel Diserahkan</div>
                                        <div class="mt-1 text-sm font-medium text-gray-900">{{ $deliveredDisplay }}</div>
                                    </div>
                                @endif
                                @if($testingDisplay)
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-gray-500">Jumlah Sampel untuk Pengujian</div>
                                        <div class="mt-1 text-sm font-medium text-gray-900">{{ $testingDisplay }}</div>
                                    </div>
                                @endif
                                @if($leftoverDisplay)
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-gray-500">Sisa Sampel</div>
                                        <div class="mt-1 text-sm font-medium text-gray-900">{{ $leftoverDisplay }}</div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            @foreach($stages as $stageKey => $stageLabel)
                                @php($process = $sample->testProcesses->firstWhere('stage', $stageKey))
                                <div class="rounded border {{ $process?->completed_at ? 'border-green-200 bg-green-50' : 'border-gray-200' }} p-4 text-sm text-gray-700">
                                    <div class="flex items-center justify-between">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $stageLabel }}</div>
                                        @if($process?->completed_at)
                                            <span class="inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Selesai</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 space-y-1">
                                        <div>
                                            <span class="text-xs text-gray-500">Mulai:</span>
                                            <span class="ml-1 text-gray-800">{{ optional($process?->started_at)->format('d/m/Y H:i') ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">Selesai:</span>
                                            <span class="ml-1 text-gray-800">{{ optional($process?->completed_at)->format('d/m/Y H:i') ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">Pelaksana:</span>
                                            <span class="ml-1 text-gray-800">{{ optional($process?->analyst)->display_name_with_title ?? 'Belum ditentukan' }}</span>
                                        </div>
                                    </div>
                                    @if($process?->notes)
                                        <div class="mt-2 rounded bg-{{ $process->completed_at ? 'green' : 'gray' }}-100 p-2 text-xs text-{{ $process->completed_at ? 'green' : 'gray' }}-600">{{ $process->notes }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        </x-page-section>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
