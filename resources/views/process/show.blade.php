<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Pengujian"
            :breadcrumbs="[]"
        />
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 items-start gap-6 lg:grid-cols-12" x-data="processQuickActions()" x-init="init()" data-api-base="{{ url('/api/processes') }}">
        @if(session('success'))
            <div class="col-span-full rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-success-700">
                {{ session('success') }}
            </div>
        @endif

        @if(session('info'))
            <div class="col-span-full rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-blue-900" role="status">
                {{ session('info') }}
            </div>
        @endif

        @if($errors->any())
            <div class="col-span-full rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-danger-700">
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($testRequest->parentTestRequest)
            <div class="col-span-full rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950" role="status">
                Pengujian ini merupakan suplemen tertaut ke resi {{ $testRequest->parentTestRequest->receipt_number }}.
                <a href="{{ route('requests.show', $testRequest->parentTestRequest) }}" class="ml-1 font-semibold underline underline-offset-2">Lihat permintaan awal</a>
            </div>
        @endif

        @if($handoverHistory->isNotEmpty())
            <section class="col-span-full rounded-lg border border-amber-200 bg-amber-50 p-5" aria-labelledby="reopened-handover-history-heading">
                <h2 id="reopened-handover-history-heading" class="text-sm font-semibold text-amber-950">Riwayat penyerahan sebelumnya</h2>
                <p class="mt-1 text-sm text-amber-900">Siklus sebelumnya disimpan sebagai riwayat dan tidak lagi mewakili daftar sampel yang sedang diuji.</p>
                <ul class="mt-3 space-y-3">
                    @foreach($handoverHistory as $cycle)
                        <li class="rounded-md border border-amber-200 bg-white p-3 text-sm">
                            <p class="font-semibold text-gray-800">Siklus {{ $cycle['cycle'] }} · Sampel {{ $cycle['sample_code'] }} · Dibuka kembali {{ $cycle['reopened_at']?->format('d M Y H:i') }} oleh {{ $cycle['reopened_by'] }}</p>
                            <p class="mt-1 text-gray-600">{{ $cycle['reason'] }}</p>
                            @if($cycle['sample_removed'])
                                <p class="mt-1 rounded-md bg-amber-50 p-2 text-amber-900">Sampel {{ $cycle['sample_code'] }} dihapus dari data aktif sebagai koreksi: {{ $cycle['sample_removal_reason'] }}. Snapshot audit tetap tersimpan.</p>
                            @endif
                            @foreach($cycle['documents'] as $document)
                                <a href="{{ route('delivery.handover.archive', [$cycle['delivery'], $document]) }}" class="mt-2 inline-flex min-h-11 items-center rounded-md border border-gray-300 px-3 py-2 font-medium text-primary-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Buka BA arsip · {{ $document->original_filename }}</a>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @php
            $investigator = $testRequest->investigator;
            $unit = $investigator?->jurisdiction ?? $investigator?->institution;
            $receivedAt = $testRequest->received_at ?? $testRequest->created_at;
        @endphp

        {{-- SIDEBAR: Context & Action --}}
        <div class="space-y-6 lg:sticky lg:top-24 lg:col-span-4">
            <div class="rounded-xl bg-gradient-to-br from-primary-50 to-white px-5 py-6 shadow-sm ring-1 ring-primary-100 flex flex-col gap-4">
                <div>
                    <div class="text-2xl font-semibold text-primary-900">
                        {{ $testRequest->receipt_number ?? $testRequest->request_number }}
                    </div>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ $investigator?->full_name ?? $investigator?->name ?? '-' }}
                        @if($unit)
                            / {{ $unit }}
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Diterima pada: {{ optional($receivedAt)->format('d F Y') ?? '-' }}
                    </div>
                </div>
                <div class="flex flex-col gap-3 border-t border-primary-100 pt-4">
                    @if($additionalReviewCount > 0)
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">
                            <p class="font-semibold">{{ $additionalReviewCount }} sampel menunggu kaji ulang</p>
                            <p class="mt-1">Sampel tambahan dikaji satu per satu. Sampel yang sudah diproses tidak berubah.</p>
                        </div>
                    @endif
                    @if($readyForDelivery)
                        <form action="{{ route('testing.ready-for-delivery', $testRequest) }}" method="POST" class="w-full"
                              x-data
                              @submit.prevent="confirm('Yakin kirim ke penyerahan? Status semua sampel akan berubah menjadi Siap Diserahkan.') && $el.submit()">
                            @csrf
                            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600">
                                <x-icon name="truck" size="sm" :decorative="true" />
                                Kirim ke Penyerahan
                            </button>
                        </form>
                    @endif
                    @if(in_array($testRequest->status, ['in_testing', 'analysis', 'quality_check'], true) && auth()->user()?->hasAnyPermission(['pengujian.create', 'pengujian.edit']))
                        <a
                            href="{{ route('testing.additional-samples.create', $testRequest) }}"
                            class="flex w-full items-center justify-center gap-2 rounded-md bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900 ring-1 ring-inset ring-amber-200 transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                            <x-icon name="plus" size="sm" :decorative="true" />
                            Tambah Sampel
                        </a>
                    @endif
                    <a
                        href="{{ route('testing.index') }}"
                        class="flex w-full items-center justify-center gap-2 rounded-md bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50">
                        <x-icon name="arrow-left" size="sm" :decorative="true" />
                        Kembali ke Daftar
                    </a>
                </div>
            </div>

            <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
                <h3 class="mb-4 text-sm font-semibold text-gray-900">Perkembangan Tahapan</h3>
                <div class="space-y-4">
                    @foreach($stepper as $step)
                        <div class="relative flex items-start gap-4">
                            @if(!$loop->last)
                                <div class="absolute left-4 top-8 bottom-[-1rem] -ml-px w-0.5 {{ $step['state'] === 'completed' ? 'bg-primary-500' : 'bg-gray-200' }}"></div>
                            @endif
                            <div
                                class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold bg-white
                                {{ $step['state'] === 'completed' ? 'border-primary-600 bg-primary-600 text-white' : ($step['state'] === 'active' ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-gray-300 text-gray-500') }}">
                                @if($step['state'] === 'completed')
                                    <x-icon name="check" size="sm" :decorative="true" />
                                @else
                                    <span>{{ $loop->iteration }}</span>
                                @endif
                            </div>
                            <div class="pt-1.5">
                                <div class="text-sm font-semibold {{ in_array($step['state'], ['completed', 'active'], true) ? 'text-primary-800' : 'text-gray-500' }}">
                                    {{ $step['label'] }}
                                </div>
                                @if(!empty($step['progress']))
                                    <div class="text-xs {{ $step['state'] === 'active' ? 'text-primary-600' : 'text-gray-400' }} mt-0.5">
                                        {{ $step['progress'] }} Sampel
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-8">
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                @if($samples->isNotEmpty())
                <table class="hidden w-full table-fixed divide-y divide-gray-200 md:table">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="w-[31%] px-4 py-3 text-left first:rounded-tl-lg">Sampel</th>
                            <th class="w-[24%] px-4 py-3 text-left">Tahap dan jadwal</th>
                            <th class="w-[21%] px-4 py-3 text-left">Status</th>
                            <th class="w-44 px-4 py-3 text-right last:rounded-tr-lg">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @foreach($samples as $sample)
                            @php
                                $lhuProcess = $sample->testProcesses
                                    ->filter(fn ($process) => ($process->stage?->value ?? $process->stage) === 'interpretation' && $process->completed_at)
                                    ->sortByDesc(fn ($process) => $process->completed_at?->timestamp ?? 0)
                                    ->first();
                                $lhuNumber = data_get($lhuProcess?->metadata ?? [], 'lhu_number')
                                    ?? data_get($lhuProcess?->metadata ?? [], 'report_number');
                                $lhuPreviewUrl = $lhuProcess ? route('testing.processes.lab-report', $lhuProcess) : null;
                                $lhuDownloadUrl = $lhuProcess ? route('testing.processes.lab-report', ['sample_process' => $lhuProcess, 'download' => 1]) : null;
                            @endphp
                            <tr 
                                class="hover:bg-gray-50/70 transition-colors duration-500" 
                                :class="{ 'bg-green-100 ring-2 ring-green-400 ring-inset': highlightedSample === {{ $sample->id }} }">
                                <td class="min-w-0 px-4 py-3 align-top">
                                    <div class="font-semibold text-gray-900">{{ $sample->sample_code ?? '-' }}</div>
                                    <div class="mt-1 break-words text-sm text-gray-600">{{ $sample->short_description ?? '-' }}</div>
                                    @if($lhuProcess && $lhuNumber)
                                        <div class="mt-2 flex min-w-0 items-center gap-2 text-xs text-gray-500">
                                            <x-icon name="document-text" size="sm" class="text-gray-400" :decorative="true" />
                                            <span class="min-w-0 truncate font-mono">{{ $lhuNumber }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium text-gray-800">{{ $sample->current_stage_label }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ optional($sample->current_schedule)->format('d M Y') ?? 'Jadwal belum ditetapkan' }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @php
                                        $statusColor = match ($sample->current_status_key) {
                                            'completed' => 'bg-green-100 text-green-700',
                                            'in_progress' => 'bg-yellow-100 text-yellow-700',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $statusColor }}">
                                        {{ $sample->current_status_label }}
                                    </span>
                                </td>
                                <td class="w-44 px-4 py-3 align-top text-right">
                                    @include('process.partials.sample-actions')
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="divide-y divide-gray-200 md:hidden">
                    @foreach($samples as $sample)
                        @php
                            $lhuProcess = $sample->testProcesses
                                ->filter(fn ($process) => ($process->stage?->value ?? $process->stage) === 'interpretation' && $process->completed_at)
                                ->sortByDesc(fn ($process) => $process->completed_at?->timestamp ?? 0)
                                ->first();
                            $lhuNumber = data_get($lhuProcess?->metadata ?? [], 'lhu_number')
                                ?? data_get($lhuProcess?->metadata ?? [], 'report_number');
                            $lhuPreviewUrl = $lhuProcess ? route('testing.processes.lab-report', $lhuProcess) : null;
                            $lhuDownloadUrl = $lhuProcess ? route('testing.processes.lab-report', ['sample_process' => $lhuProcess, 'download' => 1]) : null;
                            $statusColor = match ($sample->current_status_key) {
                                'completed' => 'bg-green-100 text-green-700',
                                'in_progress' => 'bg-yellow-100 text-yellow-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <article class="space-y-4 p-4" :class="{ 'bg-green-100 ring-2 ring-green-400 ring-inset': highlightedSample === {{ $sample->id }} }">
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-gray-900">{{ $sample->sample_code ?? '-' }}</h3>
                                    <p class="mt-1 break-words text-sm text-gray-600">{{ $sample->short_description ?? '-' }}</p>
                                </div>
                                <span class="inline-flex shrink-0 items-center rounded-full px-2 py-1 text-xs font-semibold {{ $statusColor }}">
                                    {{ $sample->current_status_label }}
                                </span>
                            </div>

                            <dl class="grid grid-cols-2 gap-3 rounded-md bg-gray-50 p-3 text-sm">
                                <div class="min-w-0">
                                    <dt class="text-xs font-medium text-gray-500">Tahap aktif</dt>
                                    <dd class="mt-1 break-words font-medium text-gray-800">{{ $sample->current_stage_label }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500">Jadwal</dt>
                                    <dd class="mt-1 font-medium text-gray-800">{{ optional($sample->current_schedule)->format('d M Y') ?? 'Belum ditetapkan' }}</dd>
                                </div>
                            </dl>

                            @if($lhuProcess && $lhuNumber)
                                <div class="flex min-w-0 items-center gap-2 text-xs text-gray-500">
                                    <x-icon name="document-text" size="sm" class="text-gray-400" :decorative="true" />
                                    <span class="min-w-0 truncate font-mono">{{ $lhuNumber }}</span>
                                </div>
                            @endif

                            <div aria-label="Aksi untuk sampel {{ $sample->sample_code }}">
                                @include('process.partials.sample-actions')
                            </div>
                        </article>
                    @endforeach
                </div>
                @else
                    <div class="px-4 py-8 text-center text-sm text-gray-500">
                        Belum ada sampel untuk resi ini.
                    </div>
                @endif
            </div>

            @if(!$hasProcesses)
                <div class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500">
                    Belum ada proses untuk resi ini.
                </div>
            @endif

            <div>
                {{ $samples->links() }}
            </div>

            {{-- Label Sisa Sampel Section --}}
            <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-100 mt-8 pt-2">
                <div class="border-t-2 border-gray-200 mx-4 mb-2"></div>
                @include('partials.remaining-label-section', [
                    'testRequestModel' => $testRequest
                ])
            </div>
        </div>
        </div>

        {{-- Quick View Modal --}}
        <div
            x-show="showModal"
            x-trap.noscroll.inert="showModal"
            @keydown.escape.window="if (showModal) closeModal()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="quick-view-modal-title"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-pd-modal overflow-y-auto"
            style="display: none;">
            <div class="flex min-h-dvh items-center justify-center p-4">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal()"></div>

                {{-- Modal Content --}}
                <div
                    x-show="showModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative w-full max-w-lg transform rounded-lg bg-white p-6 shadow-xl transition duration-300 ease-out">

                    {{-- Close Button --}}
                    <button
                        type="button"
                        @click="closeModal()"
                        aria-label="Tutup dialog"
                        class="absolute right-4 top-4 text-gray-400 hover:text-gray-500">
                        <x-icon name="x-mark" size="md" :decorative="true" />
                    </button>

                    {{-- Loading State --}}
                    <div x-show="modalLoading" class="flex items-center justify-center py-8" role="status" aria-label="Memuat data">
                        <svg class="h-8 w-8 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    {{-- Modal Body --}}
                    <div x-show="!modalLoading && processData">
                        <h3 id="quick-view-modal-title" class="text-lg font-semibold text-gray-900" x-text="'Detail Proses: ' + (processData?.stage_label || '')"></h3>

                        <dl class="mt-4 space-y-3">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Kode Sampel</dt>
                                <dd class="text-sm font-medium text-gray-900" x-text="processData?.sample_code || '-'"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Deskripsi</dt>
                                <dd class="text-sm font-medium text-gray-900" x-text="processData?.short_description || '-'"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Analis</dt>
                                <dd class="text-sm font-medium text-gray-900" x-text="processData?.analyst_name || 'Belum ditentukan'"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Dimulai</dt>
                                <dd class="text-sm font-medium" :class="processData?.is_started ? 'text-gray-900' : 'text-gray-400'" x-text="processData?.started_at_display || 'Belum dimulai'"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Selesai</dt>
                                <dd class="text-sm font-medium" :class="processData?.is_completed ? 'text-green-600' : 'text-gray-400'" x-text="processData?.completed_at_display || 'Belum selesai'"></dd>
                            </div>
                            <div x-show="processData?.notes">
                                <dt class="text-sm text-gray-500">Catatan</dt>
                                <dd class="mt-1 rounded-md bg-gray-50 p-2 text-sm text-gray-700" x-text="processData?.notes"></dd>
                            </div>
                        </dl>

                        {{-- Action Buttons --}}
                        <div class="mt-6 flex flex-wrap gap-2">
                            <template x-if="processData && !processData.is_started">
                                <button
                                    type="button"
                                    @click="startProcess(processData.id); closeModal()"
                                    class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700">
                                    <x-icon name="play" size="sm" :decorative="true" />
                                    Mulai Proses
                                </button>
                            </template>

                            <template x-if="processData && processData.is_started && !processData.is_completed">
                                <button
                                    type="button"
                                    @click="completeProcess(processData.id); closeModal()"
                                    class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                                    <x-icon name="check-circle" size="sm" :decorative="true" />
                                    Selesaikan
                                </button>
                            </template>

                            <button
                                type="button"
                                @click="closeModal()"
                                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Toast Notification --}}
        <div
            x-show="toast.show"
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="status"
            aria-live="polite"
            class="fixed bottom-4 right-4 z-pd-toast max-w-sm rounded-lg bg-white p-4 shadow-lg ring-1 ring-black ring-opacity-5"
            style="display: none;">
            <div class="flex items-start gap-3">
                <template x-if="toast.type === 'success'">
                    <div class="flex-shrink-0 text-green-500">
                        <x-icon name="check-circle" size="md" :decorative="true" />
                    </div>
                </template>
                <template x-if="toast.type === 'error'">
                    <div class="flex-shrink-0 text-red-500">
                        <x-icon name="x-circle" size="md" :decorative="true" />
                    </div>
                </template>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900" x-text="toast.message"></p>
                </div>
                <button type="button" @click="toast.show = false" class="text-gray-400 hover:text-gray-500">
                    <x-icon name="x-mark" size="sm" :decorative="true" />
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function processQuickActions() {
        return {
            showModal: false,
            modalLoading: false,
            processData: null,
            highlightedSample: null,
            toast: {
                show: false,
                type: 'success',
                message: ''
            },

            init() {
                const apiBase = this.$root.dataset.apiBase;
                this._apiBase = apiBase || '/api/processes';

                // Check for stored toast message after page reload
                const storedToast = sessionStorage.getItem('process_toast');
                const storedHighlight = sessionStorage.getItem('process_highlight');
                
                if (storedToast) {
                    const toastData = JSON.parse(storedToast);
                    sessionStorage.removeItem('process_toast');
                    setTimeout(() => {
                        this.showToast(toastData.type, toastData.message);
                    }, 100);
                }
                
                if (storedHighlight) {
                    this.highlightedSample = parseInt(storedHighlight);
                    sessionStorage.removeItem('process_highlight');
                    setTimeout(() => {
                        this.highlightedSample = null;
                    }, 5000);
                }

                // Listen for dispatched events from child components
                this.$root.addEventListener('process-start', (e) => {
                    this.startProcess(e.detail.processId, e.detail.sampleId);
                });
                this.$root.addEventListener('process-complete', (e) => {
                    this.completeProcess(e.detail.processId, e.detail.sampleId);
                });
                this.$root.addEventListener('process-quick-view', (e) => {
                    this.showQuickView(e.detail.processId);
                });
            },

            async parseApiResponse(response, fallbackMessage) {
                const contentType = response.headers.get('content-type') || '';

                if (!contentType.includes('application/json')) {
                    if (response.status === 401 || response.status === 419) {
                        throw new Error('Sesi Anda telah berakhir. Silakan muat ulang halaman dan masuk kembali.');
                    }

                    throw new Error(fallbackMessage);
                }

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || fallbackMessage);
                }

                return data;
            },

            async showQuickView(processId) {
                this.showModal = true;
                this.modalLoading = true;
                this.processData = null;

                try {
                    const response = await fetch(`${this._apiBase}/${processId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await this.parseApiResponse(response, 'Gagal memuat data proses.');

                    if (data.ok) {
                        this.processData = data.data;
                    } else {
                        this.showToast('error', data.message || 'Gagal memuat data proses.');
                        this.closeModal();
                    }
                } catch (err) {
                    this.showToast('error', 'Terjadi kesalahan: ' + err.message);
                    this.closeModal();
                } finally {
                    this.modalLoading = false;
                }
            },

            closeModal() {
                this.showModal = false;
                this.processData = null;
            },

            async startProcess(processId, sampleId = null) {
                try {
                    const response = await fetch(`${this._apiBase}/${processId}/start`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const data = await this.parseApiResponse(response, 'Gagal memulai proses.');

                    if (data.ok) {
                        sessionStorage.setItem('process_toast', JSON.stringify({
                            type: 'success',
                            message: data.message || 'Proses berhasil dimulai.'
                        }));
                        if (sampleId) {
                            sessionStorage.setItem('process_highlight', sampleId.toString());
                        }
                        window.location.reload();
                    } else {
                        this.showToast('error', data.message || 'Gagal memulai proses.');
                    }
                } catch (err) {
                    this.showToast('error', 'Terjadi kesalahan: ' + err.message);
                }
            },

            async completeProcess(processId, sampleId = null) {
                try {
                    const response = await fetch(`${this._apiBase}/${processId}/complete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const data = await this.parseApiResponse(response, 'Gagal menyelesaikan proses.');

                    if (data.ok) {
                        sessionStorage.setItem('process_toast', JSON.stringify({
                            type: 'success',
                            message: data.message || 'Proses berhasil diselesaikan.'
                        }));
                        if (sampleId) {
                            sessionStorage.setItem('process_highlight', sampleId.toString());
                        }
                        window.location.reload();
                    } else {
                        this.showToast('error', data.message || 'Gagal menyelesaikan proses.');
                    }
                } catch (err) {
                    this.showToast('error', 'Terjadi kesalahan: ' + err.message);
                }
            },

            showToast(type, message) {
                this.toast = { show: true, type, message };
                setTimeout(() => { this.toast.show = false; }, 4000);
            }
        };
    }
    </script>
    @endpush
</x-app-layout>
