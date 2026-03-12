@php
    $stageValue = $process->stage instanceof \App\Enums\TestProcessStage
        ? $process->stage->value
        : $process->stage;

    $isStarted = $process->started_at !== null;
    $isCompleted = $process->completed_at !== null;

    $statusBadgeClass = $isCompleted
        ? 'bg-green-100 text-green-700 ring-green-200'
        : ($isStarted ? 'bg-amber-100 text-amber-700 ring-amber-200' : 'bg-gray-100 text-gray-700 ring-gray-200');

    $statusLabel = $isCompleted ? 'Selesai' : ($isStarted ? 'Berjalan' : 'Menunggu');

    $eventLabels = [
        'process_started' => 'Tahap dimulai',
        'process_completed' => 'Tahap diselesaikan',
        'process_unlocked' => 'Tahap diperbaiki (unlock)',
    ];

    $eventClasses = [
        'process_started' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'process_completed' => 'bg-green-50 text-green-700 ring-green-200',
        'process_unlocked' => 'bg-amber-50 text-amber-700 ring-amber-200',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Proses Pengujian Sampel" :breadcrumbs="[]" />
    </x-slot>

    <div class="mx-auto max-w-[1480px] space-y-8 px-4 sm:px-6 lg:px-8" x-data="{ ...processDetailActions({ processId: {{ $process->id }} }), activeSection: 'actions', toggleSection(section) { this.activeSection = this.activeSection === section ? null : section } }" x-init="init()">
        
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error') || $errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @if (session('error'))
                    <p>{{ session('error') }}</p>
                @endif
                @if ($errors->any())
                    <ul class="mt-1 list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="rounded-[2rem] bg-gradient-to-r from-primary-50 via-white to-white px-6 py-6 shadow-[0_20px_40px_-15px_rgba(15,23,42,0.08)] ring-1 ring-primary-100 sm:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-primary-700">
                        <x-icon name="folder-open" size="sm" class="text-primary-600" :decorative="true" />
                        Proses Pengujian Sampel
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-primary-950 sm:text-3xl">{{ $process->sample?->sample_code ?? 'Tahap #'.$process->id }}</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-gray-600">{{ $process->sample->short_description ?? 'Deskripsi belum tersedia' }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        <span class="flex items-center gap-1 rounded-full px-2.5 py-1 font-semibold ring-1 {{ $statusBadgeClass }}">
                            <x-icon name="check-circle" size="sm" :decorative="true" />
                            {{ $statusLabel }} · {{ $process->stage_label }}
                        </span>
                        <span class="text-gray-500">
                            Resi: {{ $process->sample->testRequest?->request_number ?? '-' }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-end sm:items-center gap-2">
                    <a href="{{ route('testing.show', $process->sample->testRequest) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50">
                        <x-icon name="arrow-left" size="sm" :decorative="true" />
                        Kembali ke Resi
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-12 lg:items-start">
            
            <div class="min-w-0 space-y-5 lg:col-span-8 xl:col-span-8">
                {{-- Action Panel dari show.blade.php --}}
                <div class="overflow-hidden rounded-[1.75rem] border border-primary-100/70 bg-gradient-to-r from-primary-50/55 via-white to-white shadow-[0_20px_45px_-24px_rgba(15,23,42,0.08)]">
                    <button type="button" @click="toggleSection('actions')" class="flex w-full items-center justify-between gap-3 px-6 py-4.5 text-left transition hover:bg-gray-50/80 sm:px-7">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                            <x-icon name="play" size="sm" class="text-gray-500" :decorative="true" />
                            Aksi Tahap Saat Ini
                        </span>
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                            Klik untuk buka / tutup
                            <span class="inline-flex transition" :class="{ 'rotate-180': activeSection === 'actions' }">
                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                            </span>
                        </span>
                    </button>
                    <div x-show="activeSection === 'actions'" x-collapse class="border-t border-primary-100/70 px-6 py-5 sm:px-7">
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-gray-50/70 px-4 py-2.5">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Kontrol Tahap</p>
                                <p class="mt-1 text-xs text-gray-500">Jalankan aksi cepat sesuai aturan workflow aktif.</p>
                            </div>
                            <div x-data="{ showRules: false }" class="relative">
                                <button type="button" @click="showRules = !showRules" class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600 transition">
                                    <x-icon name="info" size="sm" :decorative="true" />
                                    Aturan workflow
                                </button>
                                <div x-show="showRules" x-transition @click.outside="showRules = false"
                                    class="absolute right-0 z-10 mt-2 w-72 rounded-2xl bg-white p-4 shadow-lg ring-1 ring-gray-200/80 text-xs text-gray-600"
                                    style="display: none;">
                                    <p class="font-semibold text-gray-700 mb-2">Validasi Workflow</p>
                                    <ul class="space-y-1.5">
                                        <li>• Urutan: Submitted → Preparation → Instrumentation → Interpretation → Ready for Delivery</li>
                                        <li>• Tahap berikutnya hanya legal jika tahap sebelumnya selesai</li>
                                        <li>• Perbaiki Tahap wajib isi alasan dan tercatat audit trail</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <button type="button"
                                @click="startProcess()"
                                @disabled(! $actionState['can_start'])
                                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold shadow-sm transition active:scale-[0.99] {{ $actionState['can_start'] ? 'bg-green-600 text-white hover:bg-green-700' : 'cursor-not-allowed bg-gray-100 text-gray-400' }}">
                                <x-icon name="play" size="sm" :decorative="true" />
                                Mulai Tahap
                            </button>

                            <button type="button"
                                @click="completeProcess()"
                                @disabled(! $actionState['can_complete'])
                                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold shadow-sm transition active:scale-[0.99] {{ $actionState['can_complete'] ? 'bg-primary-600 text-white hover:bg-primary-700' : 'cursor-not-allowed bg-gray-100 text-gray-400' }}">
                                <x-icon name="check-circle" size="sm" :decorative="true" />
                                Selesaikan Tahap
                            </button>

                            <button type="button"
                                @click="openUnlockModal()"
                                @disabled(! $actionState['can_unlock'])
                                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold shadow-sm transition active:scale-[0.99] {{ $actionState['can_unlock'] ? 'bg-amber-500 text-white hover:bg-amber-600' : 'cursor-not-allowed bg-gray-100 text-gray-400' }}">
                                <x-icon name="folder-open" size="sm" :decorative="true" />
                                Perbaiki Tahap
                            </button>
                        </div>

                        <div class="mt-4 rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-3 space-y-1.5 text-xs leading-relaxed text-gray-600">
                            @if (! $actionState['can_start'] && $actionState['start_reason'])
                                <p>• Mulai Tahap: {{ $actionState['start_reason'] }}</p>
                            @endif
                            @if (! $actionState['can_complete'] && $actionState['complete_reason'])
                                <p>• Selesaikan Tahap: {{ $actionState['complete_reason'] }}</p>
                            @endif
                            @if (! $actionState['can_unlock'] && $actionState['unlock_reason'])
                                <p>• Perbaiki Tahap: {{ $actionState['unlock_reason'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($interpretationDetails)
                    <div class="overflow-hidden rounded-[1.75rem] border border-primary-100/70 bg-gradient-to-r from-primary-50/45 via-white to-white shadow-[0_20px_45px_-24px_rgba(15,23,42,0.08)]">
                        <button type="button" @click="toggleSection('summary')" class="flex w-full items-center justify-between gap-3 px-6 py-4.5 text-left transition hover:bg-gray-50/80 sm:px-7">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="check-circle" size="sm" class="text-gray-500" :decorative="true" />
                                Interpretasi Hasil
                            </span>
                            <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                Klik untuk buka / tutup
                                <span class="inline-flex transition" :class="{ 'rotate-180': activeSection === 'summary' }">
                                    <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                                </span>
                            </span>
                        </button>
                        <div x-show="activeSection === 'summary'" x-collapse class="border-t border-primary-100/70 px-6 py-5 sm:px-7">
                            <div class="rounded-2xl border border-gray-100 bg-gray-50/70 px-4 py-3 text-sm">
                                <span class="text-gray-500">Nomor LHU:</span>
                                <span class="font-semibold text-gray-900">{{ $interpretationDetails['report_number'] }}</span>
                            </div>

                            @php
                                $rows = [];
                                $rows[] = [
                                    'instrument' => $interpretationDetails['instrument'] ?? '-',
                                    'result_raw' => $interpretationDetails['test_result_raw'] ?? null,
                                    'result' => $interpretationDetails['test_result'] ?? 'Belum ditentukan',
                                    'detected' => $interpretationDetails['detected_substance'] ?? '-',
                                    'attachment_url' => $interpretationDetails['attachment_url'] ?? null,
                                    'attachment_original' => $interpretationDetails['attachment_original'] ?? null,
                                ];
                                if (! empty($interpretationDetails['multi'])) {
                                    foreach ($interpretationDetails['multi'] as $mi) {
                                        $rows[] = [
                                            'instrument' => $mi['instrument'] ?? '-',
                                            'result_raw' => $mi['test_result_raw'] ?? null,
                                            'result' => $mi['test_result'] ?? 'Belum ditentukan',
                                            'detected' => $mi['detected_substance'] ?? '-',
                                            'attachment_url' => $mi['attachment_url'] ?? null,
                                            'attachment_original' => $mi['attachment_original'] ?? null,
                                        ];
                                    }
                                }
                            @endphp

                            <div class="mt-4 overflow-hidden rounded-[1.9rem] border border-white/70 bg-gradient-to-br from-white via-white to-primary-50/35 shadow-[0_24px_60px_-30px_rgba(15,23,42,0.12)] ring-1 ring-slate-200/60">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full border-separate border-spacing-0 text-sm text-gray-700">
                                        <thead>
                                            <tr class="bg-slate-50/75 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                                <th class="px-5 py-4 text-left first:rounded-tl-[1.4rem]">Instrumen</th>
                                                <th class="px-5 py-4 text-left">Hasil</th>
                                                <th class="px-5 py-4 text-left">Zat Aktif Terdeteksi</th>
                                                <th class="px-5 py-4 text-left last:rounded-tr-[1.4rem]">Lampiran</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($rows as $r)
                                            @php
                                                $resultClass = match ($r['result_raw']) {
                                                    'positive' => 'bg-red-100 text-red-700 ring-1 ring-red-200/70',
                                                    'negative' => 'bg-green-100 text-green-700 ring-1 ring-green-200/70',
                                                    default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/70',
                                                };
                                            @endphp
                                            <tr class="group transition-colors duration-200 hover:bg-white/70">
                                                <td class="border-t border-slate-200/60 px-5 py-4 font-medium text-slate-900 group-first:border-t-0">{{ $r['instrument'] }}</td>
                                                <td class="border-t border-slate-200/60 px-5 py-4 group-first:border-t-0">
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] {{ $resultClass }}">
                                                        {{ $r['result'] }}
                                                    </span>
                                                </td>
                                                <td class="border-t border-slate-200/60 px-5 py-4 text-slate-600 group-first:border-t-0">{{ $r['detected'] }}</td>
                                                <td class="border-t border-slate-200/60 px-5 py-4 group-first:border-t-0">
                                                    @if (! empty($r['attachment_url']))
                                                        <a href="{{ $r['attachment_url'] }}" target="_blank" class="inline-flex items-center rounded-full bg-white/85 px-3 py-1.5 text-xs font-medium text-primary-700 ring-1 ring-primary-100 transition hover:bg-primary-50/70 hover:text-primary-800">
                                                            {{ $r['attachment_original'] ?? 'Lihat dokumen' }}
                                                        </a>
                                                    @else
                                                        <span class="text-slate-400">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
        
                <div class="overflow-hidden rounded-[1.9rem] border border-primary-100/70 bg-gradient-to-r from-primary-50/50 via-white to-white shadow-[0_24px_55px_-28px_rgba(15,23,42,0.08)]">
                    <button type="button" @click="toggleSection('form')" class="flex w-full items-center justify-between gap-3 px-6 py-4.5 text-left transition hover:bg-white/80 sm:px-7">
                        <span>
                            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="pencil" size="sm" class="text-gray-500" :decorative="true" />
                                Formulir Edit Lanjutan
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">Kelola data tahap, hasil interpretasi, dan lampiran kerja.</span>
                        </span>
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                            Klik untuk buka / tutup
                            <span class="inline-flex transition" :class="{ 'rotate-180': activeSection === 'form' }">
                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                            </span>
                        </span>
                    </button>
                    <div x-show="activeSection === 'form'" x-collapse class="border-t border-primary-100/70 px-6 py-5 sm:px-7">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary-100/60 bg-white/85 px-4 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.75)]">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">Area Kerja Utama</p>
                                <p class="mt-1 text-xs text-gray-500">Fokus pada pembaruan data tahap, interpretasi hasil, dan lampiran kerja.</p>
                            </div>
                            <div class="text-sm text-gray-600">
                                @if(($process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage) === 'administration')
                                    <span class="inline-flex items-center rounded-xl bg-yellow-50 px-2.5 py-1 text-xs font-medium text-yellow-800 ring-1 ring-inset ring-yellow-200">Tahap Administrasi tidak lagi digunakan</span>
                                @endif
                            </div>
                        </div>
                        <form id="sample-process-edit-form" method="POST" action="{{ route('testing.processes.update', ['sample_process' => $process->id]) }}" class="space-y-6" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            @include('sample-processes._form', ['showNotes' => true, 'showMetadata' => false])

                            @php
                                $currentStageValue = $process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage;
                                $selectedStage = old('stage', $currentStageValue);
                            @endphp

                            @if(isset($activeSubstances) && $selectedStage === 'interpretation')
                                <div x-data="{ open: true }" class="overflow-hidden rounded-[1.9rem] border border-white/70 bg-gradient-to-br from-white via-white to-primary-50/40 shadow-[0_24px_60px_-30px_rgba(15,23,42,0.12)] ring-1 ring-slate-200/60">
                                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition hover:bg-white/75 sm:px-6">
                                        <span>
                                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                                                <x-icon name="check-circle" size="sm" class="text-primary-600" :decorative="true" />
                                                Data Interpretasi Hasil
                                            </span>
                                            <span class="mt-1 block text-xs text-gray-500">Pilih instrumen pengujian, hasil interpretasi dan zat aktif yang terdeteksi.</span>
                                        </span>
                                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                            Klik untuk buka / tutup
                                            <span class="inline-flex transition" :class="{ 'rotate-180': open }">
                                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                                            </span>
                                        </span>
                                    </button>
                                    <div x-show="open" x-collapse class="border-t border-slate-200/70 px-5 py-5 sm:px-6">
                                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <fieldset>
                                                    <legend class="text-xs uppercase tracking-wide text-gray-500">Status Hasil</legend>
                                                    @php $testResultValue = old('test_result', $currentTestResult ?? null); @endphp
                                                    <div class="mt-2 flex flex-wrap gap-3">
                                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                            <input type="radio" name="test_result" value="positive" @checked($testResultValue === 'positive')
                                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                            Positif
                                                        </label>
                                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                            <input type="radio" name="test_result" value="negative" @checked($testResultValue === 'negative')
                                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                            Negatif
                                                        </label>
                                                    </div>
                                                </fieldset>
                                                @error('test_result')
                                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs uppercase tracking-wide text-gray-500">Zat Aktif Terdeteksi</label>
                                                @php
                                                    $detectedValue = old('detected_substance', $currentDetectedSubstance ?? '');
                                                    $activeSubstanceList = $activeSubstances instanceof \Illuminate\Support\Collection
                                                        ? $activeSubstances
                                                        : collect($activeSubstances);
                                                @endphp
                                                @if($activeSubstanceList->isEmpty())
                                                    <p class="mt-2 text-xs text-gray-500">Belum ada data zat aktif tersimpan. Tambahkan melalui permintaan sampel terlebih dahulu.</p>
                                                @else
                                                    <select name="detected_substance" id="detected-substance"
                                                            class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                            <option value="">-- pilih zat aktif --</option>
                                                            @foreach($activeSubstanceList as $substance)
                                                                <option value="{{ $substance }}" @selected($detectedValue === $substance)>{{ $substance }}</option>
                                                            @endforeach
                                                        </select>
                                                @endif
                                                @error('detected_substance')
                                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="mt-6 border-t border-slate-200/70 pt-5">
                                            <h4 class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                                                <x-icon name="document" size="sm" class="text-gray-500" :decorative="true" />
                                                Unggah Hasil Pengujian
                                            </h4>
                                            <p class="mt-1 text-xs text-gray-500">Unggah dokumen pendukung hasil pengujian (PDF, DOCX, XLSX, atau gambar – maksimum 20 MB).</p>
                                            @php
                                                $resultAttachmentName = $currentResultAttachmentOriginal
                                                    ?? ($currentResultAttachmentPath ? basename($currentResultAttachmentPath) : null);
                                            @endphp
                                            @if(!empty($currentResultAttachmentUrl))
                                                <div class="mt-3 rounded-2xl border border-white/80 bg-slate-50/80 px-3.5 py-2.5 text-xs text-slate-600 ring-1 ring-slate-200/60">
                                                    <span class="font-medium text-gray-700">File saat ini:</span>
                                                    <a href="{{ $currentResultAttachmentUrl }}" target="_blank" class="ml-1 text-primary-600 transition hover:text-primary-700">
                                                        {{ $resultAttachmentName ?? 'Lihat dokumen' }}
                                                    </a>
                                                </div>
                                            @endif
                                            <div class="mt-3">
                                                <input type="file" name="test_result_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                                        class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-xl file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                                                @error('test_result_file')
                                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Secondary interpretation for multi-instrument requests --}}
                                <div x-data="{ open: false }" class="mt-6 overflow-hidden rounded-[1.75rem] border border-slate-200/70 bg-slate-50/55 shadow-[0_18px_45px_-32px_rgba(15,23,42,0.14)] ring-1 ring-white/80">
                                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition hover:bg-white/70 sm:px-6">
                                        <span>
                                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                                                <x-icon name="document-duplicate" size="sm" class="text-gray-500" :decorative="true" />
                                                Instrumen Ke-2 (Opsional)
                                            </span>
                                            <span class="mt-1 block text-xs text-gray-500">Untuk permintaan pengujian dengan lebih dari satu instrumen</span>
                                        </span>
                                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                            Klik untuk buka / tutup
                                            <span class="inline-flex transition" :class="{ 'rotate-180': open }">
                                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                                            </span>
                                        </span>
                                    </button>
                                    <div x-show="open" x-collapse class="border-t border-slate-200/70 px-5 py-5 sm:px-6">
                                        <div class="mt-4">
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-700">Instrumen Pengujian</label>
                                            @php
                                                $currentInstrument2 = old('instrument_2', $secondaryInstrument ?? null);
                                                // instrumentOptions passed from controller
                                            @endphp
                                            <select name="instrument_2"
                                                    class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                    <option value="">-- Pilih Instrumen Pengujian --</option>
                                                    @foreach($instrumentOptions as $value => $label)
                                                        <option value="{{ $value }}" @selected($currentInstrument2 === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                        </div>

                                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <fieldset>
                                                    <legend class="text-xs uppercase tracking-wide text-gray-500">Status Hasil</legend>
                                                    @php $testResultValue2 = old('test_result_2', $secondaryTestResult ?? null); @endphp
                                                    <div class="mt-2 flex flex-wrap gap-3">
                                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                            <input type="radio" name="test_result_2" value="positive" @checked($testResultValue2 === 'positive')
                                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                            Positif
                                                        </label>
                                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                            <input type="radio" name="test_result_2" value="negative" @checked($testResultValue2 === 'negative')
                                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                            Negatif
                                                        </label>
                                                    </div>
                                                </fieldset>
                                            </div>

                                            <div>
                                                <label for="detected-substance-2" class="block text-xs uppercase tracking-wide text-gray-700">Zat Aktif Terdeteksi</label>
                                                @php
                                                    $detectedValue2 = old('detected_substance_2', $secondaryDetectedSubstance ?? '');
                                                    $activeSubstanceList = $activeSubstances instanceof \Illuminate\Support\Collection
                                                        ? $activeSubstances
                                                        : collect($activeSubstances);
                                                @endphp
                                                @if($activeSubstanceList->isEmpty())
                                                    <p class="mt-2 text-xs text-gray-500">Belum ada data zat aktif tersimpan.</p>
                                                @else
                                                    <select name="detected_substance_2" id="detected-substance-2"
                                                            class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                            <option value="">-- pilih zat aktif --</option>
                                                            @foreach($activeSubstanceList as $substance)
                                                                <option value="{{ $substance }}" @selected($detectedValue2 === $substance)>{{ $substance }}</option>
                                                            @endforeach
                                                        </select>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-6 border-t border-slate-200/70 pt-5">
                                            <h4 class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                                                <x-icon name="document" size="sm" class="text-gray-500" :decorative="true" />
                                                Unggah Hasil Pengujian (Instrumen Ke-2)
                                            </h4>
                                            @php
                                                $resultAttachmentName2 = $secondaryResultAttachmentOriginal
                                                    ?? ($secondaryResultAttachmentPath ? basename($secondaryResultAttachmentPath) : null);
                                            @endphp
                                            @if(!empty($secondaryResultAttachmentUrl))
                                                <div class="mt-3 rounded-2xl border border-white/80 bg-white/90 px-3.5 py-2.5 text-xs text-slate-600 ring-1 ring-slate-200/60">
                                                    <span class="font-medium text-gray-700">File saat ini:</span>
                                                    <a href="{{ $secondaryResultAttachmentUrl }}" target="_blank" class="ml-1 text-primary-600 transition hover:text-primary-700">
                                                        {{ $resultAttachmentName2 ?? 'Lihat dokumen' }}
                                                    </a>
                                                </div>
                                            @endif
                                            <div class="mt-3">
                                                <input type="file" name="test_result_file_2" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                                        class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-xl file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- INSTRUMENTATION STAGE: Instrument Logging --}}
                            @if($selectedStage === 'instrumentation')
                                <div class="overflow-hidden rounded-[1.9rem] border border-white/70 bg-gradient-to-br from-white via-white to-primary-50/35 shadow-[0_24px_60px_-30px_rgba(15,23,42,0.12)] ring-1 ring-slate-200/60"
                                    x-data="{ open: true, ...instrumentLogging({{ $process->sample_id }}) }"
                                    x-init="loadRequirements()">
                                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition hover:bg-white/75 sm:px-6">
                                        <span>
                                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                                                <x-icon name="document-duplicate" size="sm" class="text-gray-500" :decorative="true" />
                                                Pencatatan Instrumen
                                            </span>
                                            <span class="mt-1 block text-xs text-gray-500">Pilih aset instrumen yang digunakan untuk setiap metode pengujian sampel ini.</span>
                                        </span>
                                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                            Klik untuk buka / tutup
                                            <span class="inline-flex transition" :class="{ 'rotate-180': open }">
                                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                                            </span>
                                        </span>
                                    </button>
                                    <div x-show="open" x-collapse class="border-t border-slate-200/70 px-5 py-5 sm:px-6">
                                        <template x-if="loading">
                                            <div class="flex items-center gap-2 rounded-2xl border border-slate-200/70 bg-slate-50/70 px-4 py-3 text-sm text-gray-500" role="status">
                                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <span>Memuat data instrumen...</span>
                                            </div>
                                        </template>

                                        <template x-if="!loading && !enabled">
                                            <div class="rounded-2xl border border-yellow-200/80 bg-yellow-50/85 px-3.5 py-2.5 text-xs text-yellow-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                                                Pencatatan instrumen tidak diaktifkan. Aktifkan melalui Pengaturan &gt; Monitoring dan Pencatatan.
                                            </div>
                                        </template>

                                        <template x-if="!loading && enabled && Object.keys(requirements).length === 0">
                                            <div class="rounded-2xl border border-blue-200/80 bg-blue-50/80 px-3.5 py-2.5 text-xs text-blue-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                                                Tidak ada instrumen yang perlu dicatat untuk metode pengujian sampel ini.
                                            </div>
                                        </template>

                                        <template x-if="!loading && enabled && Object.keys(requirements).length > 0">
                                            <div class="space-y-4">
                                                <template x-for="(methodReqs, methodCode) in requirements" :key="methodCode">
                                                    <div class="rounded-[1.4rem] border border-white/80 bg-slate-50/70 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)] ring-1 ring-slate-200/60">
                                                        <div class="flex flex-col gap-1 border-b border-slate-200/70 pb-3 sm:flex-row sm:items-start sm:justify-between">
                                                            <div>
                                                                <h4 class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600" x-text="getMethodLabel(methodCode)"></h4>
                                                                <p class="mt-1 text-xs text-gray-500">Tetapkan aset instrumen yang digunakan untuk metode ini sebelum melanjutkan.</p>
                                                            </div>
                                                            <span class="inline-flex w-fit items-center rounded-full bg-white/85 px-2.5 py-1 text-[11px] font-medium text-slate-500 ring-1 ring-slate-200/70">
                                                                Kelompok metode
                                                            </span>
                                                        </div>
                                                        <div class="mt-3 space-y-3">
                                                            <template x-for="req in methodReqs" :key="req.id">
                                                                <div class="rounded-2xl border border-white/85 bg-white/80 p-3.5 ring-1 ring-slate-200/60 transition group-hover:bg-white sm:p-4">
                                                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                                    <div class="flex-1 min-w-0">
                                                                        <label class="block text-sm font-medium text-gray-700">
                                                                            <span x-text="req.instrument_name"></span>
                                                                            <span x-show="req.mandatory" class="text-red-500">*</span>
                                                                            <span x-show="req.already_logged" class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Tercatat</span>
                                                                        </label>
                                                                        <p class="text-xs text-gray-500" x-text="'Kode: ' + req.instrument_code + ' | Tipe: ' + req.usage_type"></p>
                                                                    </div>
                                                                    <div class="sm:w-64">
                                                                        <select :name="'selections[' + methodCode + '][' + req.instrument_id + ']'"
                                                                                x-model="selections[methodCode + '_' + req.instrument_id]"
                                                                                :disabled="req.already_logged"
                                                                                class="block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-500">
                                                                                <option value="">-- Pilih Aset --</option>
                                                                                <template x-for="asset in req.available_assets" :key="asset.id">
                                                                                    <option :value="asset.id" x-text="asset.asset_code + (asset.location ? ' (' + asset.location + ')' : '')" :selected="req.selected_asset_id == asset.id"></option>
                                                                                </template>
                                                                            </select>
                                                                    </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>

                                                <div x-show="existingLogs.length > 0" class="mt-4 rounded-[1.4rem] border border-white/80 bg-slate-50/70 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)] ring-1 ring-slate-200/60">
                                                    <div class="flex flex-col gap-1 border-b border-slate-200/70 pb-3 sm:flex-row sm:items-start sm:justify-between">
                                                        <div>
                                                            <h4 class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">Log Tercatat</h4>
                                                            <p class="mt-1 text-xs text-gray-500">Riwayat aset instrumen yang sudah disimpan pada tahap ini.</p>
                                                        </div>
                                                        <span class="inline-flex w-fit items-center rounded-full bg-white/85 px-2.5 py-1 text-[11px] font-medium text-slate-500 ring-1 ring-slate-200/70">
                                                            Audit penggunaan
                                                        </span>
                                                    </div>
                                                    <ul class="mt-3 space-y-2 text-xs text-gray-600">
                                                        <template x-for="log in existingLogs" :key="log.id">
                                                            <li class="flex items-center gap-2 rounded-xl border border-white/80 bg-white/85 px-3 py-2 ring-1 ring-slate-200/50">
                                                                <svg class="h-3 w-3 text-green-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                                </svg>
                                                                <span x-text="log.instrument_name + ' (' + log.asset_code + ') - ' + log.performed_by + ' @ ' + log.logged_at"></span>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                </div>

                                                <div x-show="error" class="mt-3 rounded-2xl border border-red-200/80 bg-red-50/80 px-3.5 py-2.5 text-xs text-red-700" x-text="error" role="alert"></div>
                                                <div x-show="success" class="mt-3 rounded-2xl border border-green-200/80 bg-green-50/80 px-3.5 py-2.5 text-xs text-green-700" x-text="success" role="status" aria-live="polite"></div>

                                                <div class="mt-4 flex justify-end">
                                                    <button type="button" @click="saveInstrumentUsage()"
                                                            :disabled="saving"
                                                            class="inline-flex items-center rounded-xl bg-primary-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                                            <span x-show="!saving">Simpan Pencatatan Instrumen</span>
                                                            <span x-show="saving" class="flex items-center gap-2">
                                                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                </svg>
                                                                Menyimpan...
                                                            </span>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                </div>
                            @endif

                            {{-- PREPARATION STAGE: Weighing (Analytical Balance) --}}
                            @if($selectedStage === 'preparation')
                                <div class="overflow-hidden rounded-[1.9rem] border border-white/70 bg-gradient-to-br from-white via-white to-primary-50/35 shadow-[0_24px_60px_-30px_rgba(15,23,42,0.12)] ring-1 ring-slate-200/60"
                                    x-data="{ open: true, ...analyticalBalanceWeighing({{ $process->sample_id }}) }"
                                    x-init="checkWeighingStatus()">
                                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition hover:bg-white/75 sm:px-6">
                                        <span>
                                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                                                <x-icon name="document" size="sm" class="text-gray-500" :decorative="true" />
                                                Penimbangan (Analytical Balance)
                                            </span>
                                            <span class="mt-1 block text-xs text-gray-500">Catat data penimbangan sampel menggunakan Analytical Balance.</span>
                                        </span>
                                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                            Klik untuk buka / tutup
                                            <span class="inline-flex transition" :class="{ 'rotate-180': open }">
                                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                                            </span>
                                        </span>
                                    </button>
                                    <div x-show="open" x-collapse class="border-t border-slate-200/70 px-5 py-5 sm:px-6">
                                        <template x-if="loading">
                                            <div class="mt-4 flex items-center gap-2 text-sm text-gray-500" role="status">
                                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <span>Memeriksa status penimbangan...</span>
                                            </div>
                                        </template>

                                        <template x-if="!loading && !requiresWeighing">
                                            <div class="mt-4 rounded-2xl border border-blue-200/80 bg-blue-50/80 px-3.5 py-2.5 text-xs text-blue-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                                                Sampel ini tidak memerlukan penimbangan (tidak ada requirement Analytical Balance pada metode pengujian yang dipilih).
                                            </div>
                                        </template>

                                        <template x-if="!loading && requiresWeighing">
                                            <div class="mt-4 space-y-4">
                                                <template x-if="hasWeighing">
                                                    <div class="rounded-[1.4rem] border border-green-200/80 bg-green-50/80 px-3.5 py-3.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)]">
                                                        <div class="flex items-center gap-2 text-sm text-green-800">
                                                            <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                            </svg>
                                                            <span class="font-medium">Data penimbangan sudah tercatat</span>
                                                        </div>
                                                        <dl class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                                            <div>
                                                                <dt class="text-gray-500">Jumlah Item</dt>
                                                                <dd class="font-medium text-gray-900" x-text="weighingData.items_count"></dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-gray-500">Massa Terbaca</dt>
                                                                <dd class="font-medium text-gray-900" x-text="weighingData.mass_display"></dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-gray-500">Ditimbang oleh</dt>
                                                                <dd class="font-medium text-gray-900" x-text="weighingData.weighed_by"></dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-gray-500">Waktu</dt>
                                                                <dd class="font-medium text-gray-900" x-text="weighingData.weighed_at"></dd>
                                                            </div>
                                                        </dl>
                                                    </div>
                                                </template>

                                                <template x-if="!hasWeighing">
                                                    <div class="space-y-3">
                                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                            <div>
                                                                <label class="block text-sm font-medium text-gray-700">
                                                                    Jumlah Item <span class="text-red-500">*</span>
                                                                </label>
                                                                <input type="number" step="1" min="1" max="999"
                                                                        x-model="itemsCount"
                                                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                        placeholder="1">
                                                                <p class="mt-1 text-xs text-gray-500">Berapa banyak sampel/aliquot yang ditimbang.</p>
                                                            </div>
                                                            <div>
                                                                <label class="block text-sm font-medium text-gray-700">
                                                                    Massa Terbaca <span class="text-red-500">*</span>
                                                                </label>
                                                                <input type="number" step="0.000001" min="0.000001" max="99999999.999999"
                                                                        x-model="massValue"
                                                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                        placeholder="0.000000">
                                                                <p class="mt-1 text-xs text-gray-500">Nilai massa dari Analytical Balance.</p>
                                                            </div>
                                                            <div>
                                                                <label class="block text-sm font-medium text-gray-700">
                                                                    Unit <span class="text-red-500">*</span>
                                                                </label>
                                                                <select x-model="massUnit"
                                                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                                        <option value="">-- Pilih Unit --</option>
                                                                        <option value="ug">Mikrogram (μg)</option>
                                                                        <option value="mg">Miligram (mg)</option>
                                                                        <option value="g">Gram (g)</option>
                                                                    </select>
                                                            </div>
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                                            <div>
                                                                <span class="text-xs text-gray-500">Ditimbang oleh</span>
                                                                <p class="font-medium text-gray-900">{{ auth()->user()->name ?? 'Anda' }}</p>
                                                            </div>
                                                            <div>
                                                                <span class="text-xs text-gray-500">Waktu pencatatan</span>
                                                                <p class="font-medium text-gray-900">{{ now()->format('d M Y H:i') }}</p>
                                                            </div>
                                                        </div>

                                                        <div x-show="error" class="rounded-2xl border border-red-200/80 bg-red-50/80 px-3.5 py-2.5 text-xs text-red-700" x-text="error" role="alert"></div>
                                                        <div x-show="success" class="rounded-2xl border border-green-200/80 bg-green-50/80 px-3.5 py-2.5 text-xs text-green-700" x-text="success" role="status" aria-live="polite"></div>

                                                        <div class="flex justify-end">
                                                            <button type="button" @click="saveWeighing()"
                                                                    :disabled="saving || !itemsCount || !massValue || !massUnit"
                                                                    class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                                                    <span x-show="!saving">Simpan Data Penimbangan</span>
                                                                    <span x-show="saving" class="flex items-center gap-2">
                                                                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                        </svg>
                                                                        Menyimpan...
                                                                    </span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            @endif

                        </form>
                    </div>
                </div>

                <div class="mt-2 rounded-[1.35rem] border border-primary-100/60 bg-gradient-to-r from-primary-50/45 via-white to-white px-4 py-4 sm:px-5">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <a href="{{ route('testing.processes.show', ['sample_process' => $process->id]) }}"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">
                            <x-icon name="x-mark" size="sm" :decorative="true" />
                            Batal
                        </a>
                        <button type="submit" form="sample-process-edit-form"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                            <x-icon name="check-circle" size="sm" :decorative="true" />
                            Perbarui
                        </button>
                    </div>
                </div>
            </div>
        </div>

            <aside class="space-y-5 self-start lg:col-span-4 xl:col-span-4 lg:sticky lg:top-24">
                <div class="space-y-3.5 rounded-[1.75rem] border border-primary-100/70 bg-gradient-to-r from-primary-50/55 via-white to-white px-4 py-4 shadow-[0_20px_45px_-24px_rgba(15,23,42,0.08)] sm:px-5 sm:py-5">
                    <div class="overflow-hidden rounded-2xl border border-primary-100/60 bg-white/90 shadow-sm">
                            <button type="button" @click="toggleSection('audit')" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition hover:bg-gray-50/80">
                        <span>
                            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="document" size="sm" class="text-gray-500" :decorative="true" />
                                Audit Trail Tahap
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">Riwayat start, selesai, dan perbaikan tahap terbaru.</span>
                        </span>
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                            Klik untuk buka / tutup
                            <span class="inline-flex transition" :class="{ 'rotate-180': activeSection === 'audit' }">
                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                            </span>
                        </span>
                            </button>
                            <div x-show="activeSection === 'audit'" x-collapse class="border-t border-primary-100/60 px-5 py-4.5">
                                <div class="space-y-3">
                            @forelse ($recentWorkflowEvents as $event)
                                @php
                                    $eventAction = $event->action;
                                    $eventLabel = $eventLabels[$eventAction] ?? $eventAction;
                                    $eventClass = $eventClasses[$eventAction] ?? 'bg-gray-50 text-gray-700 ring-gray-200';
                                @endphp
                                <div class="rounded-xl p-3 ring-1 {{ $eventClass }}">
                                    <p class="text-xs font-semibold uppercase tracking-wide">{{ $eventLabel }}</p>
                                    <p class="mt-1 text-xs">Pelaku: {{ $event->actor_name ?? 'Sistem' }}</p>
                                    <p class="mt-1 text-xs">
                                        {{ optional($event->changed_at)->format('d/m/Y H:i') ?? optional($event->created_at)->format('d/m/Y H:i') }}
                                    </p>
                                    @if (! empty($event->change_reason))
                                        <p class="mt-1 text-xs">Alasan: {{ $event->change_reason }}</p>
                                    @endif
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/60 p-3 text-xs text-gray-500">
                                    Belum ada audit trail aksi cepat untuk tahap ini.
                                </div>
                            @endforelse
                                </div>
                            </div>
                        </div>

                    <div class="overflow-hidden rounded-2xl border border-primary-100/60 bg-white/90 shadow-sm">
                            <button type="button" @click="toggleSection('meta')" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition hover:bg-gray-50/80">
                        <span>
                            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="information-circle" size="sm" class="text-gray-500" :decorative="true" />
                                Informasi Waktu & Aksi Bahaya
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">Timestamp tahap dan aksi destruktif untuk kontrol manual.</span>
                        </span>
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                            Klik untuk buka / tutup
                            <span class="inline-flex transition" :class="{ 'rotate-180': activeSection === 'meta' }">
                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                            </span>
                        </span>
                            </button>
                            <div x-show="activeSection === 'meta'" x-collapse class="border-t border-primary-100/60 px-5 py-4.5">
                                <div class="grid gap-3">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Dibuat</p>
                                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $process->created_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Diperbarui</p>
                                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $process->updated_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-xl border border-red-100 bg-red-50/70 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-700">Aksi Bahaya</p>
                                    <p class="mt-1 text-xs leading-relaxed text-red-600">Gunakan hanya jika tahap dibuat keliru dan tidak boleh dipulihkan melalui alur perbaikan biasa.</p>
                                    <form method="POST" action="{{ route('testing.processes.destroy', ['sample_process' => $process->id]) }}" x-data class="mt-4">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-200 transition hover:bg-red-50 active:scale-[0.99]"
                                            @click.prevent="showConfirmDialog({
                                                type: 'danger',
                                                title: 'Hapus Tahap',
                                                message: 'Hapus tahap ini secara permanen?',
                                                confirmButtonText: 'Ya, Hapus',
                                                onConfirm: () => $el.closest('form').submit()
                                            })">
                                            <x-icon name="trash" size="sm" :decorative="true" />
                                            Hapus Tahap Permanen
                                        </button>
                                    </form>
                                </div>
                            </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Unlock Modal & Toast dari show.blade.php -->
        <div x-show="unlockModal" x-trap.noscroll.inert="unlockModal" @keydown.escape.window="unlockModal = false"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display: none;">
            <div class="absolute inset-0 bg-gray-900/40" @click="unlockModal = false" x-transition.opacity></div>
            <div class="relative z-10 w-full max-w-lg rounded-[1.35rem] bg-white p-6 shadow-xl ring-1 ring-gray-200" x-transition>
                <h3 class="text-base font-semibold text-gray-900">Perbaiki Tahap</h3>
                <p class="mt-1 text-sm text-gray-600">Isi alasan perbaikan tahap. Alasan ini akan masuk audit trail.</p>

                <label for="unlock_reason" class="mt-4 block text-sm font-medium text-gray-700">Alasan <span class="text-red-500">*</span></label>
                <textarea id="unlock_reason" x-model="unlockReason" rows="4"
                    class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    placeholder="Contoh: koreksi data instrumen karena salah input pada tahap sebelumnya."></textarea>

                <p class="mt-2 text-xs text-gray-500">Minimal 10 karakter.</p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="unlockModal = false"
                        class="inline-flex items-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="button" @click="submitUnlock()" :disabled="busy"
                        class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60">
                        <x-icon name="folder-open" size="sm" :decorative="true" />
                        Simpan Perbaikan
                    </button>
                </div>
            </div>
        </div>

        <div x-show="toast.show"
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed bottom-4 right-4 z-[110] max-w-sm rounded-lg bg-gray-800 p-4 shadow-lg ring-1 ring-black/5" style="display: none;">
            <p class="text-sm font-medium text-white" x-text="toast.message"></p>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('instrumentLogging', (sampleId) => ({
            sampleId: sampleId,
            loading: true,
            enabled: false,
            requirements: {},
            existingLogs: [],
            selections: {},
            saving: false,
            error: null,
            success: null,

            async loadRequirements() {
                try {
                    const response = await fetch(`/api/samples/${this.sampleId}/instrument-requirements`);
                    const data = await response.json();
                    this.enabled = data.enabled;
                    this.requirements = data.requirements || {};
                    this.existingLogs = data.existing_logs || [];

                    // Pre-fill selections with already logged assets
                    for (const [methodCode, reqs] of Object.entries(this.requirements)) {
                        for (const req of reqs) {
                            if (req.selected_asset_id) {
                                this.selections[`${methodCode}_${req.instrument_id}`] = req.selected_asset_id;
                            }
                        }
                    }
                } catch (err) {
                    this.error = 'Gagal memuat data instrumen: ' + err.message;
                } finally {
                    this.loading = false;
                }
            },

            getMethodLabel(methodCode) {
                const labels = {
                    'uv_vis': 'UV-VIS Spectrophotometry',
                    'gc_ms': 'GC-MS (Gas Chromatography-Mass Spectrometry)',
                    'lc_ms': 'LC-MS (Liquid Chromatography-Mass Spectrometry)'
                };
                return labels[methodCode] || methodCode.toUpperCase();
            },

            async saveInstrumentUsage() {
                this.saving = true;
                this.error = null;
                this.success = null;

                try {
                    // Transform selections into the expected format
                    const formattedSelections = {};
                    for (const [key, assetId] of Object.entries(this.selections)) {
                        if (!assetId) continue;
                        const [methodCode, instrumentId] = key.split('_');
                        if (!formattedSelections[methodCode]) {
                            formattedSelections[methodCode] = {};
                        }
                        formattedSelections[methodCode][instrumentId] = assetId;
                    }

                    const response = await fetch(`/api/samples/${this.sampleId}/instrument-usage`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ selections: formattedSelections })
                    });

                    const data = await response.json();
                    if (!data.ok) {
                        this.error = data.message || 'Gagal menyimpan pencatatan instrumen.';
                        return;
                    }

                    // Reload requirements to show updated status
                    await this.loadRequirements();
                    this.success = 'Pencatatan instrumen berhasil disimpan.';
                    // Auto-clear success message after 5 seconds
                    setTimeout(() => { this.success = null; }, 5000);
                } catch (err) {
                    this.error = 'Terjadi kesalahan: ' + err.message;
                } finally {
                    this.saving = false;
                }
            }
        }));

        Alpine.data('analyticalBalanceWeighing', (sampleId) => ({
            sampleId: sampleId,
            loading: true,
            requiresWeighing: false,
            hasWeighing: false,
            weighingData: {},
            itemsCount: 1,
            massValue: '',
            massUnit: '',
            saving: false,
            error: null,
            success: null,

            async checkWeighingStatus() {
                try {
                    const response = await fetch(`/api/samples/${this.sampleId}/weighing`);
                    const data = await response.json();
                    this.requiresWeighing = data.requires_weighing;
                    this.hasWeighing = data.has_weighing;
                    this.weighingData = data.weighing_data || {};
                } catch (err) {
                    this.error = 'Gagal memeriksa status penimbangan: ' + err.message;
                } finally {
                    this.loading = false;
                }
            },

            async saveWeighing() {
                if (!this.itemsCount || parseInt(this.itemsCount) < 1) {
                    this.error = 'Jumlah item minimal 1.';
                    return;
                }
                if (!this.massValue || parseFloat(this.massValue) <= 0) {
                    this.error = 'Masukkan nilai massa yang valid.';
                    return;
                }
                if (!this.massUnit) {
                    this.error = 'Pilih unit massa.';
                    return;
                }

                this.saving = true;
                this.error = null;

                try {
                    const response = await fetch(`/api/samples/${this.sampleId}/weighing`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            items_count: parseInt(this.itemsCount),
                            mass_value: parseFloat(this.massValue),
                            mass_unit: this.massUnit
                        })
                    });

                    const data = await response.json();
                    if (!data.ok) {
                        this.error = data.message || 'Gagal menyimpan data penimbangan.';
                        return;
                    }

                    this.hasWeighing = true;
                    this.weighingData = data.weighing_data;
                    this.success = 'Data penimbangan berhasil disimpan.';
                    // Auto-clear success message after 5 seconds
                    setTimeout(() => { this.success = null; }, 5000);
                } catch (err) {
                    this.error = 'Terjadi kesalahan: ' + err.message;
                } finally {
                    this.saving = false;
                }
            }
        }));
    });
    </script>
        <script>
            // Script untuk Process Detail Actions (dari show.blade.php)
            function processDetailActions(config) {
                return {
                    busy: false,
                    unlockModal: false,
                    unlockReason: '',
                    toast: { show: false, message: '' },

                    openUnlockModal() {
                        this.unlockReason = '';
                        this.unlockModal = true;
                    },

                    async startProcess() {
                        await this.postAction(`/api/processes/${config.processId}/start`, null, 'Tahap berhasil dimulai.');
                    },

                    async completeProcess() {
                        await this.postAction(
                            `/api/processes/${config.processId}/complete`,
                            null,
                            'Tahap berhasil diselesaikan.',
                            (data) => {
                                const nextId = data?.data?.next_process_id;
                                if (nextId) {
                                    window.location.href = `/pengujian/processes/${nextId}/edit`;
                                    return true;
                                }

                                return false;
                            }
                        );
                    },

                    async submitUnlock() {
                        const reason = (this.unlockReason || '').trim();
                        if (reason.length < 10) {
                            this.showToast('Alasan minimal 10 karakter.');
                            return;
                        }

                        const ok = await this.postAction(
                            `/api/processes/${config.processId}/unlock`,
                            { reason },
                            'Tahap berhasil diperbaiki.'
                        );

                        if (ok) {
                            this.unlockModal = false;
                        }
                    },

                    async postAction(url, payload = null, fallbackSuccess = 'Berhasil.', onSuccess = null) {
                        if (this.busy) return false;
                        this.busy = true;

                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: payload ? JSON.stringify(payload) : null,
                            });

                            const data = await response.json();
                            if (data.ok) {
                                if (typeof onSuccess === 'function') {
                                    const handled = onSuccess(data);
                                    if (handled) {
                                        return true;
                                    }
                                }

                                sessionStorage.setItem('process_detail_toast', data.message || fallbackSuccess);
                                window.location.reload();
                                return true;
                            }

                            this.showToast(data.message || 'Aksi gagal dijalankan.');
                            return false;
                        } catch (error) {
                            this.showToast('Terjadi kesalahan saat menjalankan aksi.');
                            return false;
                        } finally {
                            this.busy = false;
                        }
                    },

                    showToast(message) {
                        this.toast = { show: true, message };
                        setTimeout(() => (this.toast.show = false), 3500);
                    },

                    init() {
                        const persisted = sessionStorage.getItem('process_detail_toast');
                        if (persisted) {
                            sessionStorage.removeItem('process_detail_toast');
                            this.showToast(persisted);
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
