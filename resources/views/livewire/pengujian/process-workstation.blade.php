<div>
@if($isOpen && $process)
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
<div class="h-full flex flex-col bg-white overflow-y-auto">

    <div class="px-6 py-4 flex items-center justify-between border-b border-gray-200 bg-white sticky top-0 z-10">
        <h2 class="text-lg font-semibold text-gray-900">Proses Pengujian Sampel</h2>
        <button type="button" wire:click="closePanel" class="rounded-md text-gray-400 hover:text-gray-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
            <span class="sr-only">Tutup panel</span>
            <x-icon name="x-mark" class="h-6 w-6" />
        </button>
    </div>

    <div class="flex-1 p-6 space-y-6" x-data="{ ...processDetailActions({ processId: {{ $process->id }} }), activePanel: 'actions', togglePanel(panel) { this.activePanel = this.activePanel === panel ? null : panel } }" @workstation-loaded.window="init()">
        
        @if ($errors->any())
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-4">
                <div class="flex items-start gap-3">
                    <x-icon name="exclamation-triangle" size="sm" class="text-red-500 mt-0.5 shrink-0" :decorative="true" />
                    <div>
                        <h3 class="text-sm font-semibold text-red-800">Terdapat kesalahan pada formulir:</h3>
                        <ul class="mt-1.5 list-disc list-inside text-sm text-red-700 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded-xl bg-gradient-to-r from-primary-50 to-white p-5 shadow-sm ring-1 ring-primary-100">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-primary-700">
                        <x-icon name="folder-open" size="sm" class="text-primary-600" :decorative="true" />
                        Proses Pengujian Sampel
                    </p>
                    <h2 class="mt-1 text-xl font-semibold text-primary-900">{{ $process->sample?->sample_code ?? 'Tahap #'.$process->id }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $process->sample->short_description ?? 'Deskripsi belum tersedia' }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <span class="flex items-center gap-1 rounded-full px-2.5 py-1 font-semibold ring-1 {{ $statusBadgeClass }}">
                            <x-icon name="check-circle" size="sm" :decorative="true" />
                            {{ $statusLabel }} · {{ $process->stage_label }}
                        </span>
                        <span class="text-gray-500">
                            Resi: {{ $process->sample->testRequest?->request_number ?? '-' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 items-start">
            
            <div class="space-y-6 lg:col-span-8">

                {{-- Action Panel dari show.blade.php --}}
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <button type="button" @click="togglePanel('actions')" class="flex w-full items-center justify-between gap-3 px-6 py-4 text-left transition hover:bg-gray-50/80">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                            <x-icon name="play" size="sm" class="text-gray-500" :decorative="true" />
                            Aksi Tahap Saat Ini
                        </span>
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                            Klik untuk buka / tutup
                            <span class="inline-flex transition" :class="{ 'rotate-180': activePanel === 'actions' }">
                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                            </span>
                        </span>
                    </button>
                    <div x-show="activePanel === 'actions'" x-collapse class="border-t border-gray-100 px-6 py-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="play" size="sm" class="text-gray-500" :decorative="true" />
                                Aksi Tahap Saat Ini
                            </h3>
                            <div x-data="{ showRules: false }" class="relative">
                                <button type="button" @click="showRules = !showRules" class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600 transition">
                                    <x-icon name="info" size="sm" :decorative="true" />
                                    Aturan workflow
                                </button>
                                <div x-show="showRules" x-transition @click.outside="showRules = false"
                                    class="absolute right-0 z-10 mt-2 w-72 rounded-lg bg-white p-4 shadow-lg ring-1 ring-gray-200 text-xs text-gray-600"
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
                                wire:click="startProcess"
                                @disabled(! $actionState['can_start'])
                                class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition {{ $actionState['can_start'] ? 'bg-green-600 text-white hover:bg-green-700' : 'cursor-not-allowed bg-gray-100 text-gray-400' }}">
                                <x-icon name="play" size="sm" :decorative="true" />
                                Mulai Tahap
                            </button>

                            <button type="button"
                                wire:click="completeProcess"
                                @disabled(! $actionState['can_complete'])
                                class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition {{ $actionState['can_complete'] ? 'bg-primary-600 text-white hover:bg-primary-700' : 'cursor-not-allowed bg-gray-100 text-gray-400' }}">
                                <x-icon name="check-circle" size="sm" :decorative="true" />
                                Selesaikan Tahap
                            </button>

                            <button type="button"
                                @click="openUnlockModal()"
                                @disabled(! $actionState['can_unlock'])
                                class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition {{ $actionState['can_unlock'] ? 'bg-amber-500 text-white hover:bg-amber-600' : 'cursor-not-allowed bg-gray-100 text-gray-400' }}">
                                <x-icon name="folder-open" size="sm" :decorative="true" />
                                Perbaiki Tahap
                            </button>
                        </div>

                        <div class="mt-4 space-y-2 text-xs text-gray-600">
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
                    <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                        <button type="button" @click="togglePanel('summary')" class="flex w-full items-center justify-between gap-3 px-6 py-4 text-left transition hover:bg-gray-50/80">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="check-circle" size="sm" class="text-gray-500" :decorative="true" />
                                Interpretasi Hasil
                            </span>
                            <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                Klik untuk buka / tutup
                                <span class="inline-flex transition" :class="{ 'rotate-180': activePanel === 'summary' }">
                                    <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                                </span>
                            </span>
                        </button>
                        <div x-show="activePanel === 'summary'" x-collapse class="border-t border-gray-100 px-6 py-6">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                    <x-icon name="check-circle" size="sm" class="text-gray-500" :decorative="true" />
                                    Interpretasi Hasil
                                </h3>
                                <span class="text-xs text-gray-500">Lampiran hasil: opsional</span>
                            </div>

                            <div class="mt-3 text-sm">
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

                            <div class="mt-4 overflow-hidden rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Instrumen</th>
                                            <th class="px-4 py-3 text-left">Hasil</th>
                                            <th class="px-4 py-3 text-left">Zat Aktif Terdeteksi</th>
                                            <th class="px-4 py-3 text-left">Lampiran</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 text-gray-700">
                                        @foreach ($rows as $r)
                                            @php
                                                $resultClass = match ($r['result_raw']) {
                                                    'positive' => 'bg-red-100 text-red-700',
                                                    'negative' => 'bg-green-100 text-green-700',
                                                    default => 'bg-gray-100 text-gray-700',
                                                };
                                            @endphp
                                            <tr class="odd:bg-white even:bg-gray-50/50">
                                                <td class="px-4 py-3 font-medium text-gray-900">{{ $r['instrument'] }}</td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $resultClass }}">
                                                        {{ $r['result'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">{{ $r['detected'] }}</td>
                                                <td class="px-4 py-3">
                                                    @if (! empty($r['attachment_url']))
                                                        <a href="{{ $r['attachment_url'] }}" target="_blank" class="text-primary-700 underline hover:text-primary-800">
                                                            {{ $r['attachment_original'] ?? 'Lihat dokumen' }}
                                                        </a>
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
        
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <button type="button" @click="togglePanel('form')" class="flex w-full items-center justify-between gap-3 px-6 py-4 text-left transition hover:bg-gray-50/80">
                        <span>
                            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="pencil" size="sm" class="text-gray-500" :decorative="true" />
                                Formulir Edit Lanjutan
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">
                                @if(($process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage) === 'administration')
                                    <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-inset ring-yellow-200">Tahap Administrasi tidak lagi digunakan</span>
                                @endif
                            </span>
                        </span>
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                            Klik untuk buka / tutup
                            <span class="inline-flex transition" :class="{ 'rotate-180': activePanel === 'form' }">
                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                            </span>
                        </span>
                    </button>
                    <div x-show="activePanel === 'form'" x-collapse class="border-t border-gray-100 px-6 py-6">
            {{-- We wire:submit.prevent to "save" method --}}
            <form wire:submit="save" class="space-y-6">

                {{-- Ported _form fields --}}
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                    <div>
                        <label for="sample_id" class="block text-sm font-medium text-gray-700">Sampel</label>
                        <select wire:model="sample_id" id="sample_id" disabled class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                            <option value="{{ $process->sample_id }}">{{ $process->sample->sample_code }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="stage" class="block text-sm font-medium text-gray-700">Tahapan Proses <span class="text-red-500">*</span></label>
                        <select wire:model="stage" id="stage" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @foreach($stages as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="performed_by" class="block text-sm font-medium text-gray-700">Dilakukan Oleh (Analis/Pemeriksa)</label>
                        <select wire:model="performed_by" id="performed_by" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <option value="">-- Pilih Jika Sudah Ditentukan --</option>
                            @foreach($analysts as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="started_at" class="block text-sm font-medium text-gray-700">Mulai Dikerjakan (Waktu Mulai)</label>
                            <input type="datetime-local" wire:model="started_at" id="started_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="completed_at" class="block text-sm font-medium text-gray-700">Selesai (Waktu Berakhir)</label>
                            <input type="datetime-local" wire:model="completed_at" id="completed_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">Isi ini jika tahap pengujian untuk sampel ini di tahap ini sudah rampung.</p>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-gray-700">Catatan</label>
                        <textarea wire:model="notes" id="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" placeholder="Catatan tambahan (opsional)"></textarea>
                    </div>
                </div>
                {{-- End of Ported _form fields --}}

                @if($stage === 'interpretation')
                    <div class="rounded-lg border border-gray-200 bg-gray-50 mt-6"
                        x-data="{ showInstrumentLogging: true, ...instrumentLogging({{ $process->sample_id }}) }"
                        x-init="loadRequirements()">
                        <button type="button" @click="showInstrumentLogging = !showInstrumentLogging" class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left transition hover:bg-white/60">
                            <span>
                                <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                                    <x-icon name="document-duplicate" size="sm" class="text-gray-500" :decorative="true" />
                                    Pencatatan Instrumen
                                </span>
                                <span class="mt-1 block text-xs text-gray-500">Pilih aset instrumen yang digunakan untuk setiap metode pengujian sampel ini.</span>
                            </span>
                            <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                Klik untuk buka / tutup
                                <span class="inline-flex transition" :class="{ 'rotate-180': showInstrumentLogging }">
                                    <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                                </span>
                            </span>
                        </button>
                        <div x-show="showInstrumentLogging" x-collapse class="border-t border-gray-200 px-4 py-4">
                            <template x-if="loading">
                                <div class="mt-4 flex items-center gap-2 text-sm text-gray-500" role="status">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>Memuat data instrumen...</span>
                                </div>
                            </template>

                            <template x-if="!loading && !enabled">
                                <div class="mt-4 rounded-md bg-yellow-50 border border-yellow-200 px-3 py-2 text-xs text-yellow-700">
                                    Pencatatan instrumen tidak diaktifkan. Aktifkan melalui Pengaturan &gt; Monitoring dan Pencatatan.
                                </div>
                            </template>

                            <template x-if="!loading && enabled && Object.keys(requirements).length === 0">
                                <div class="mt-4 rounded-md bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-700">
                                    Tidak ada instrumen yang perlu dicatat untuk metode pengujian sampel ini.
                                </div>
                            </template>

                            <template x-if="!loading && enabled && Object.keys(requirements).length > 0">
                                <div class="mt-4 space-y-4">
                                    <template x-for="(methodReqs, methodCode) in requirements" :key="methodCode">
                                        <div class="rounded-md border border-gray-200 bg-white p-3">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600" x-text="getMethodLabel(methodCode)"></h4>
                                            <div class="mt-3 space-y-3">
                                                <template x-for="req in methodReqs" :key="req.id">
                                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                                        <div class="flex-1">
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
                                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-500">
                                                                <option value="">-- Pilih Aset --</option>
                                                                <template x-for="asset in req.available_assets" :key="asset.id">
                                                                    <option :value="asset.id" x-text="asset.asset_code + (asset.location ? ' (' + asset.location + ')' : '')" :selected="req.selected_asset_id == asset.id"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <div x-show="existingLogs.length > 0" class="mt-4">
                                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600">Log Tercatat</h4>
                                        <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                            <template x-for="log in existingLogs" :key="log.id">
                                                <li class="flex items-center gap-2">
                                                    <svg class="h-3 w-3 text-green-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                                    <span x-text="log.instrument_name + ' (' + log.asset_code + ') - ' + log.performed_by + ' @ ' + log.logged_at"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    <div x-show="error" class="mt-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700" x-text="error" role="alert"></div>
                                    <div x-show="success" class="mt-3 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-xs text-green-700" x-text="success" role="status" aria-live="polite"></div>

                                    <div class="mt-4 flex justify-end">
                                        <button type="button" @click="saveInstrumentUsage()"
                                            :disabled="saving"
                                            class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                            <span x-show="!saving">Simpan Pencatatan Instrumen</span>
                                            <span x-show="saving" class="flex items-center gap-2">
                                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                Menyimpan...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif

                {{-- PREPARATION STAGE: Weighing (Analytical Balance) --}}
                @if($stage === 'preparation')
                    <div class="rounded-lg border border-gray-200 bg-gray-50 mt-6"
                        x-data="{ showWeighing: true, ...analyticalBalanceWeighing({{ $process->sample_id }}) }"
                        x-init="checkWeighingStatus()">
                        <button type="button" @click="showWeighing = !showWeighing" class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left transition hover:bg-white/60">
                            <span>
                                <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                                    <x-icon name="document" size="sm" class="text-gray-500" :decorative="true" />
                                    Penimbangan (Analytical Balance)
                                </span>
                                <span class="mt-1 block text-xs text-gray-500">Catat data penimbangan sampel menggunakan Analytical Balance.</span>
                            </span>
                            <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                Klik untuk buka / tutup
                                <span class="inline-flex transition" :class="{ 'rotate-180': showWeighing }">
                                    <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                                </span>
                            </span>
                        </button>
                        <div x-show="showWeighing" x-collapse class="border-t border-gray-200 px-4 py-4">
                            <template x-if="loading">
                                <div class="mt-4 flex items-center gap-2 text-sm text-gray-500" role="status">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>Memeriksa status penimbangan...</span>
                                </div>
                            </template>

                            <template x-if="!loading && !requiresWeighing">
                                <div class="mt-4 rounded-md bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-700">
                                    Sampel ini tidak memerlukan penimbangan.
                                </div>
                            </template>

                            <template x-if="!loading && requiresWeighing">
                                <div class="mt-4 space-y-4">
                                    <template x-if="hasWeighing">
                                        <div class="rounded-md bg-green-50 border border-green-200 px-3 py-3">
                                            <div class="flex items-center gap-2 text-sm text-green-800">
                                                <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
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
                                                    <label class="block text-sm font-medium text-gray-700">Jumlah Item</label>
                                                    <input type="number" x-model="itemsCount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Massa Terbaca</label>
                                                    <input type="number" x-model="massValue" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Unit</label>
                                                    <select x-model="massUnit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                        <option value="ug">Mikrogram (μg)</option>
                                                        <option value="mg">Miligram (mg)</option>
                                                        <option value="g">Gram (g)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="flex justify-end">
                                                <button type="button" @click="saveWeighing()" :disabled="saving || !itemsCount || !massValue || !massUnit" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                                    <span x-show="!saving">Simpan Penimbangan</span>
                                                    <span x-show="saving">Menyimpan...</span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" wire:click="closePanel"
                        class="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">
                        <x-icon name="x-mark" size="sm" :decorative="true" />
                        Batal
                    </button>
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                        <x-icon name="check-circle" size="sm" :decorative="true" />
                        Perbarui Form
                    </button>
                </div>
            </form>
            </div>
            
            <div class="space-y-6 lg:col-span-4">
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <button type="button" @click="togglePanel('audit')" class="flex w-full items-center justify-between gap-3 px-6 py-4 text-left transition hover:bg-gray-50/80">
                        <span>
                            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="document" size="sm" class="text-gray-500" :decorative="true" />
                                Audit Trail Tahap
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">Riwayat start, selesai, dan perbaikan tahap terbaru.</span>
                        </span>
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                            Klik untuk buka / tutup
                            <span class="inline-flex transition" :class="{ 'rotate-180': activePanel === 'audit' }">
                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                            </span>
                        </span>
                    </button>
                    <div x-show="activePanel === 'audit'" x-collapse class="border-t border-gray-100 px-6 py-6">
                        <h3 class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <x-icon name="document" size="sm" class="text-gray-500" :decorative="true" />
                            Audit Trail Tahap
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">Riwayat start/complete/perbaiki tahap terbaru.</p>

                        <div class="mt-4 space-y-3">
                            @forelse ($recentWorkflowEvents as $event)
                                @php
                                    $eventAction = $event->action;
                                    $eventLabel = $eventLabels[$eventAction] ?? $eventAction;
                                    $eventClass = $eventClasses[$eventAction] ?? 'bg-gray-50 text-gray-700 ring-gray-200';
                                @endphp
                                <div class="rounded-md p-3 ring-1 {{ $eventClass }}">
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
                                <div class="rounded-md border border-dashed border-gray-200 p-3 text-xs text-gray-500">
                                    Belum ada audit trail aksi cepat untuk tahap ini.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
                
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <button type="button" @click="togglePanel('timing')" class="flex w-full items-center justify-between gap-3 px-6 py-4 text-left transition hover:bg-gray-50/80">
                        <span>
                            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                <x-icon name="information-circle" size="sm" class="text-gray-500" :decorative="true" />
                                Informasi Waktu
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">Timestamp proses dan data pemeliharaan panel.</span>
                        </span>
                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                            Klik untuk buka / tutup
                            <span class="inline-flex transition" :class="{ 'rotate-180': activePanel === 'timing' }">
                                <x-icon name="chevron-down" size="sm" class="text-gray-400" :decorative="true" />
                            </span>
                        </span>
                    </button>
                    <div x-show="activePanel === 'timing'" x-collapse class="border-t border-gray-100 px-6 py-6">
                        <h3 class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                            <x-icon name="information-circle" size="sm" class="text-gray-500" :decorative="true" />
                            Informasi Waktu
                        </h3>
                        <div class="mt-4 flex flex-col gap-2 text-xs text-gray-600">
                            <p>Dibuat: {{ $process->created_at->format('d/m/Y H:i') }}</p>
                            <p>Diperbarui: {{ $process->updated_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>
</div>

        <!-- Unlock Modal & Toast dari show.blade.php -->
        <div x-show="unlockModal" x-trap.noscroll.inert="unlockModal" @keydown.escape.window="unlockModal = false"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display: none;">
            <div class="absolute inset-0 bg-gray-900/40" @click="unlockModal = false" x-transition.opacity></div>
            <div class="relative z-10 w-full max-w-lg rounded-lg bg-white p-6 shadow-xl ring-1 ring-gray-200" x-transition>
                <h3 class="text-base font-semibold text-gray-900">Perbaiki Tahap</h3>
                <p class="mt-1 text-sm text-gray-600">Isi alasan perbaikan tahap. Alasan ini akan log di audit trail.</p>

                <label for="unlock_reason" class="mt-4 block text-sm font-medium text-gray-700">Alasan <span class="text-red-500">*</span></label>
                <textarea id="unlock_reason" x-model="unlockReason" @input="$wire.set('unlockReason', unlockReason)" rows="4"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    placeholder="Contoh: koreksi data instrumen karena salah input pada tahap sebelumnya."></textarea>

                @error('unlockReason')
                    <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p>
                @enderror

                <p class="mt-2 text-xs text-gray-500">Minimal 10 karakter.</p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="unlockModal = false"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Batal
                    </button>
                    {{-- Alpine calls Livewire via $wire --}}
                    <button type="button" @click="$wire.set('unlockReason', unlockReason); $wire.revertProcess();"
                        class="inline-flex items-center gap-2 rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60">
                        <x-icon name="folder-open" size="sm" :decorative="true" />
                        Simpan Perbaikan
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- Alpine Data for forms, extracted from edit blade -->
    @script
    <script>
        Alpine.data('processDetailActions', (config) => ({
            processId: config.processId,
            unlockModal: false,
            unlockReason: '',
            busy: false,
            
            init() {
                this.$watch('unlockModal', (open) => {
                    if (!open) {
                        this.unlockReason = '';
                        this.$wire.set('unlockReason', '');
                    }
                });
            },

            openUnlockModal() {
                this.unlockReason = '';
                this.$wire.set('unlockReason', '');
                this.unlockModal = true;
            },
        }));

        // Keeping these from original file as they hit other endpoints
        Alpine.data('instrumentLogging', (sampleId) => ({
            sampleId: sampleId,
            loading: true,
            enabled: false,
            requirements: {},
            selections: {},
            existingLogs: [],
            saving: false,
            error: null,
            success: null,

            loadRequirements() {
                this.loading = true;
                this.error = null;
                
                fetch(`/api/instruments/requirements/${this.sampleId}`)
                    .then(res => {
                        if (!res.ok) {
                            if (res.status === 404) {
                                this.enabled = false;
                                return null;
                            }
                            throw new Error('Gagal memuat data instrumen');
                        }
                        return res.json();
                    })
                    .then(data => {
                        if (data) {
                            this.enabled = data.status === 'success';
                            if (this.enabled) {
                                this.requirements = data.data.requirements || {};
                                this.existingLogs = data.data.existing_logs || [];
                                
                                // set default selections
                                for (const method in this.requirements) {
                                    this.requirements[method].forEach(req => {
                                        if (req.selected_asset_id) {
                                            this.selections[`${method}_${req.instrument_id}`] = req.selected_asset_id;
                                        }
                                    });
                                }
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        this.error = err.message;
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },

            getMethodLabel(methodCode) {
                const labels = {
                    'uv_vis': 'UV-VIS Spectrophotometer',
                    'gc_ms': 'GC-MS',
                    'lc_ms': 'LC-MS',
                    'ftir': 'FTIR'
                };
                return labels[methodCode] || methodCode;
            },

            saveInstrumentUsage() {
                this.saving = true;
                this.error = null;
                this.success = null;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                fetch(`/api/instruments/log-usage/${this.sampleId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ selections: this.selections })
                })
                .then(res => {
                    if (!res.ok) throw new Error('Gagal menyimpan pencatatan instrumen');
                    return res.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        this.success = 'Pencatatan instrumen berhasil disimpan';
                        this.loadRequirements(); // reload to get updated logs and set already_logged to true
                        setTimeout(() => this.success = null, 3000);
                    } else {
                        throw new Error(data.message || 'Terjadi kesalahan sistem');
                    }
                })
                .catch(err => {
                    this.error = err.message;
                    setTimeout(() => this.error = null, 5000);
                })
                .finally(() => {
                    this.saving = false;
                });
            }
        }));

        Alpine.data('analyticalBalanceWeighing', (sampleId) => ({
            sampleId: sampleId,
            loading: true,
            requiresWeighing: false,
            hasWeighing: false,
            weighingData: null,
            itemsCount: null,
            massValue: null,
            massUnit: 'mg',
            saving: false,
            error: null,
            success: null,

            checkWeighingStatus() {
                this.loading = true;
                this.error = null;

                fetch(`/api/weighing/analytical-balance/status/${this.sampleId}`)
                    .then(res => {
                        if (!res.ok) {
                            if (res.status === 404) {
                                this.requiresWeighing = false;
                                return null;
                            }
                            throw new Error('Gagal memeriksa status penimbangan');
                        }
                        return res.json();
                    })
                    .then(data => {
                        if (data) {
                            this.requiresWeighing = data.requires_weighing;
                            this.hasWeighing = data.has_weighing;
                            this.weighingData = data.weighing_data;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        this.error = err.message;
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },

            saveWeighing() {
                this.saving = true;
                this.error = null;
                this.success = null;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                fetch(`/api/weighing/analytical-balance/${this.sampleId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        items_count: this.itemsCount,
                        mass_value: this.massValue,
                        mass_unit: this.massUnit
                    })
                })
                .then(res => {
                    if (!res.ok) throw new Error('Gagal menyimpan data penimbangan');
                    return res.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        this.success = 'Data penimbangan berhasil disimpan';
                        this.checkWeighingStatus();
                        setTimeout(() => this.success = null, 3000);
                    } else {
                        throw new Error(data.message || 'Terjadi kesalahan sistem');
                    }
                })
                .catch(err => {
                    this.error = err.message;
                    setTimeout(() => this.error = null, 5000);
                })
                .finally(() => {
                    this.saving = false;
                });
            }
        }));
    </script>
    @endscript

</div>
@endif
</div>
