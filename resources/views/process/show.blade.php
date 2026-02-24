<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Pengujian"
            :breadcrumbs="[]"
        />
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6" x-data="processQuickActions()" x-init="init()">
        @if(session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-success-700">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-danger-700">
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $investigator = $testRequest->investigator;
            $unit = $investigator?->jurisdiction ?? $investigator?->institution;
            $receivedAt = $testRequest->received_at ?? $testRequest->created_at;
        @endphp

        <div class="flex flex-wrap items-start justify-between gap-4">
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
            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('testing.index') }}"
                    class="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50">
                    <x-icon name="arrow-left" size="sm" :decorative="true" />
                    Kembali ke Daftar
                </a>
                @if(($remainingLabelCount ?? 0) > 0)
                    <a
                        href="{{ route('labels.remaining.sheet', $testRequest->id) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700">
                        <x-icon name="download" size="sm" :decorative="true" />
                        Cetak Label Sisa
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white/90 px-1.5 text-xs font-bold text-amber-700">
                            {{ $remainingLabelCount }}
                        </span>
                    </a>
                @endif
                @if($readyForDelivery)
                    <form action="{{ route('testing.ready-for-delivery', $testRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700">
                            <x-icon name="truck" size="sm" :decorative="true" />
                            Kirim ke Penyerahan
                        </button>
                    </form>
                @endif
                <a
                    href="{{ route('testing.processes.create', ['request_id' => $testRequest->id]) }}"
                    class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                    <x-icon name="plus" size="sm" :decorative="true" />
                    Tambah Proses
                </a>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                @foreach($stepper as $step)
                    <div class="flex flex-1 flex-col items-center">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-semibold
                            {{ $step['state'] === 'completed' ? 'border-primary-600 bg-primary-600 text-white' : ($step['state'] === 'active' ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-400') }}">
                            @if($step['state'] === 'completed')
                                <x-icon name="check" size="sm" :decorative="true" />
                            @else
                                <span>{{ $loop->iteration }}</span>
                            @endif
                        </div>
                        <div class="mt-2 text-xs font-semibold {{ in_array($step['state'], ['completed', 'active'], true) ? 'text-primary-700' : 'text-gray-400' }}">
                            {{ $step['label'] }}
                        </div>
                    </div>
                    @if(!$loop->last)
                        <div class="mx-2 h-0.5 flex-1 {{ $step['state'] === 'completed' ? 'bg-primary-500' : 'bg-gray-200' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm space-y-4">
            <form method="GET" action="{{ route('testing.show', $testRequest) }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="filter_stage" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Tahapan</label>
                    <select id="filter_stage" name="stage" class="mt-1 block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Tahapan</option>
                        @foreach($stageOptions as $stage)
                            <option value="{{ $stage->value }}" @selected(($filters['stage'] ?? '') === $stage->value)>{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter_short_description" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Deskripsi Singkat</label>
                    <select id="filter_short_description" name="short_description" class="mt-1 block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Deskripsi</option>
                        @forelse($shortDescriptions as $desc)
                            <option value="{{ $desc }}" @selected(($filters['short_description'] ?? '') === $desc)>{{ $desc }}</option>
                        @empty
                            <option disabled>Tidak ada deskripsi</option>
                        @endforelse
                    </select>
                </div>

                <div>
                    <label for="filter_status" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
                    <select id="filter_status" name="status" class="mt-1 block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Status</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-600 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">
                    Terapkan
                </button>
            </form>

            <div class="rounded-lg border border-gray-100 bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left first:rounded-tl-lg">Sampel</th>
                            <th class="px-4 py-3 text-left">Deskripsi Singkat</th>
                            <th class="px-4 py-3 text-left">Tahapan</th>
                            <th class="px-4 py-3 text-left">Jadwal</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right last:rounded-tr-lg">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse($samples as $sample)
                            <tr 
                                class="hover:bg-gray-50/70 transition-colors duration-500" 
                                :class="{ 'bg-green-100 ring-2 ring-green-400 ring-inset': highlightedSample === {{ $sample->id }} }">
                                <td class="px-4 py-3 font-semibold text-gray-900">
                                    {{ $sample->sample_code ?? '-' }}
                                    @if(isset($remainingLabelSampleLookup[(int) $sample->id]))
                                        <div class="mt-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-200">
                                            Label sisa tersedia
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $sample->short_description ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $sample->current_stage_label }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ optional($sample->current_schedule)->format('d M Y') ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
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
                                <td class="px-4 py-3 text-right">
                                    @if($sample->current_process)
                                        <div class="relative inline-block text-left" x-data="{ open: false }">
                                            <div class="inline-flex overflow-hidden rounded-md border border-gray-200 shadow-sm">
                                                <a
                                                    href="{{ route('testing.processes.show', $sample->current_process) }}"
                                                    class="inline-flex items-center px-3 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50">
                                                    Lihat
                                                </a>
                                                <button
                                                    type="button"
                                                    @click="open = !open"
                                                    :aria-expanded="open"
                                                    aria-label="Tampilkan menu aksi lainnya"
                                                    class="inline-flex items-center border-l border-gray-200 px-2 text-gray-400 hover:bg-gray-50">
                                                    <x-icon name="chevron-down" size="sm" :decorative="true" />
                                                </button>
                                            </div>

                                            {{-- Dropdown Menu --}}
                                            <div
                                                x-show="open"
                                                @click.outside="open = false"
                                                x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="transform opacity-0 scale-95"
                                                x-transition:enter-end="transform opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-75"
                                                x-transition:leave-start="transform opacity-100 scale-100"
                                                x-transition:leave-end="transform opacity-0 scale-95"
                                                class="absolute right-0 z-pd-overlay mt-2 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                                style="display: none;">
                                                <div class="py-1">
                                                    @php
                                                        $process = $sample->current_process;
                                                        $isStarted = $process->started_at !== null;
                                                        $isCompleted = $process->completed_at !== null;
                                                    @endphp

                                                    @if(!$isStarted)
                                                        <button
                                                            type="button"
                                                            @click="window.processActions.start({{ $process->id }}, {{ $sample->id }}); open = false"
                                                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            <x-icon name="play" size="sm" class="text-green-600" :decorative="true" />
                                                            Mulai Proses
                                                        </button>
                                                    @endif

                                                    @if($isStarted && !$isCompleted)
                                                        <button
                                                            type="button"
                                                            @click="window.processActions.complete({{ $process->id }}, {{ $sample->id }}); open = false"
                                                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            <x-icon name="check-circle" size="sm" class="text-primary-600" :decorative="true" />
                                                            Selesaikan Proses
                                                        </button>
                                                    @endif

                                                    <a
                                                        href="{{ route('testing.processes.edit', $process) }}"
                                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <x-icon name="pencil" size="sm" class="text-gray-500" :decorative="true" />
                                                        Edit Detail
                                                    </a>

                                                    <button
                                                        type="button"
                                                        @click="window.processActions.quickView({{ $process->id }}); open = false"
                                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <x-icon name="eye" size="sm" class="text-gray-500" :decorative="true" />
                                                        Quick View
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center px-3 py-2 text-sm font-semibold text-gray-400">
                                            Tidak ada proses
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Belum ada sampel untuk resi ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(!$hasProcesses)
                <div class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500">
                    Belum ada proses untuk resi ini.
                </div>
            @endif

            <div>
                {{ $samples->links() }}
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
                    class="relative w-full max-w-lg transform rounded-lg bg-white p-6 shadow-xl transition-all">

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
    // Global object for process actions (to be called from nested Alpine.js components)
    window.processActions = {
        _component: null,
        start: function(processId, sampleId) {
            if (this._component) {
                this._component.startProcess(processId, sampleId);
            }
        },
        complete: function(processId, sampleId) {
            if (this._component) {
                this._component.completeProcess(processId, sampleId);
            }
        },
        quickView: function(processId) {
            if (this._component) {
                this._component.showQuickView(processId);
            }
        }
    };

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
                // Register this component for global access
                window.processActions._component = this;

                // Check for stored toast message after page reload
                const storedToast = sessionStorage.getItem('process_toast');
                const storedHighlight = sessionStorage.getItem('process_highlight');
                
                if (storedToast) {
                    const toastData = JSON.parse(storedToast);
                    sessionStorage.removeItem('process_toast');
                    // Show toast after a brief delay to ensure page is rendered
                    setTimeout(() => {
                        this.showToast(toastData.type, toastData.message);
                    }, 100);
                }
                
                if (storedHighlight) {
                    this.highlightedSample = parseInt(storedHighlight);
                    sessionStorage.removeItem('process_highlight');
                    // Auto-clear highlight after 5 seconds
                    setTimeout(() => {
                        this.highlightedSample = null;
                    }, 5000);
                }
            },

            async showQuickView(processId) {
                this.showModal = true;
                this.modalLoading = true;
                this.processData = null;

                try {
                    const response = await fetch(`/api/processes/${processId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();
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
                    const response = await fetch(`/api/processes/${processId}/start`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const data = await response.json();
                    if (data.ok) {
                        // Store toast and highlight in sessionStorage before reload
                        sessionStorage.setItem('process_toast', JSON.stringify({
                            type: 'success',
                            message: data.message || 'Proses berhasil dimulai.'
                        }));
                        if (sampleId) {
                            sessionStorage.setItem('process_highlight', sampleId.toString());
                        }
                        // Reload page to refresh status
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
                    const response = await fetch(`/api/processes/${processId}/complete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const data = await response.json();
                    if (data.ok) {
                        // Store toast and highlight in sessionStorage before reload
                        sessionStorage.setItem('process_toast', JSON.stringify({
                            type: 'success',
                            message: data.message || 'Proses berhasil diselesaikan.'
                        }));
                        if (sampleId) {
                            sessionStorage.setItem('process_highlight', sampleId.toString());
                        }
                        // Reload page to refresh status
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
