<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Proses Pengujian</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
        <a href="{{ route('process.processes.show', ['sample_process' => $process->id]) }}"
            class="inline-flex items-center text-sm font-semibold text-primary-700 hover:text-primary-800">&larr; Kembali ke detail</a>

            <form method="POST" action="{{ route('process.processes.destroy', ['sample_process' => $process->id]) }}"
                onsubmit="return confirm('Hapus proses ini?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700">Hapus</button>
            </form>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-gray-600">
                    @if(($process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage) === 'administration')
                        <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-inset ring-yellow-200">Tahap Administrasi tidak lagi digunakan</span>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('process.processes.update', ['sample_process' => $process->id]) }}" class="space-y-6" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @include('sample-processes._form', ['showNotes' => true, 'showMetadata' => false])

                @php
                    $currentStageValue = $process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage;
                    $selectedStage = old('stage', $currentStageValue);
                @endphp

                @if(isset($activeSubstances) && $selectedStage === 'interpretation')
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Data Interpretasi Hasil</h3>
                        <p class="mt-1 text-xs text-gray-500">Pilih instrumen pengujian, hasil interpretasi dan zat aktif yang terdeteksi.</p>

                        {{-- Instrumen Pengujian --}}
                        <div class="mt-4">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-700">Instrumen Pengujian yang Digunakan <span class="text-red-500">*</span></label>
                            @php
                                $currentInstrument = old('instrument', $currentInstrument ?? null);
                                $instrumentOptions = [
                                    'UV-VIS Spectrophotometer' => 'UV-VIS Spectrophotometer',
                                    'GC-MS (Gas Chromatography-Mass Spectrometry)' => 'GC-MS (Gas Chromatography-Mass Spectrometry)',
                                    'LC-MS (Liquid Chromatography-Mass Spectrometry)' => 'LC-MS (Liquid Chromatography-Mass Spectrometry)',
                                ];
                            @endphp
                            <select name="instrument"
                                class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">-- Pilih Instrumen Pengujian --</option>
                                @foreach($instrumentOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($currentInstrument === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('instrument')
                                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Pilih instrumen laboratorium yang digunakan untuk pengujian sampel ini.</p>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <span class="text-xs uppercase tracking-wide text-gray-500">Status Hasil</span>
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
                                    <select name="detected_substance"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
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

                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-semibold text-gray-900">Unggah Hasil Pengujian</h4>
                            <p class="mt-1 text-xs text-gray-500">Unggah dokumen pendukung hasil pengujian (PDF, DOCX, XLSX, atau gambar – maksimum 20 MB).</p>
                            @php
                                $resultAttachmentName = $currentResultAttachmentOriginal
                                    ?? ($currentResultAttachmentPath ? basename($currentResultAttachmentPath) : null);
                            @endphp
                            @if(!empty($currentResultAttachmentUrl))
                                <div class="mt-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600">
                                    <span class="font-medium text-gray-700">File saat ini:</span>
                                    <a href="{{ $currentResultAttachmentUrl }}" target="_blank" class="ml-1 text-primary-600 hover:text-primary-700 underline">
                                        {{ $resultAttachmentName ?? 'Lihat dokumen' }}
                                    </a>
                                </div>
                            @endif
                            <div class="mt-3">
                                <input type="file" name="test_result_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                    class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                                @error('test_result_file')
                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Secondary interpretation for multi-instrument requests --}}
                    <div class="mt-6 rounded-lg border border-dashed border-gray-300 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">Instrumen Ke-2 (Opsional)</h3>
                            <span class="text-xs text-gray-500">Untuk permintaan pengujian dengan lebih dari satu instrumen</span>
                        </div>
                        <div class="mt-4">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-700">Instrumen Pengujian</label>
                            @php
                                $currentInstrument2 = old('instrument_2', $secondaryInstrument ?? null);
                                $instrumentOptions = [
                                    'UV-VIS Spectrophotometer' => 'UV-VIS Spectrophotometer',
                                    'GC-MS (Gas Chromatography-Mass Spectrometry)' => 'GC-MS (Gas Chromatography-Mass Spectrometry)',
                                    'LC-MS (Liquid Chromatography-Mass Spectrometry)' => 'LC-MS (Liquid Chromatography-Mass Spectrometry)',
                                ];
                            @endphp
                            <select name="instrument_2"
                                class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">-- Pilih Instrumen Pengujian --</option>
                                @foreach($instrumentOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($currentInstrument2 === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <span class="text-xs uppercase tracking-wide text-gray-500">Status Hasil</span>
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
                            </div>

                            <div>
                                <label class="block text-xs uppercase tracking-wide text-gray-500">Zat Aktif Terdeteksi</label>
                                @php
                                    $detectedValue2 = old('detected_substance_2', $secondaryDetectedSubstance ?? '');
                                    $activeSubstanceList = $activeSubstances instanceof \Illuminate\Support\Collection
                                        ? $activeSubstances
                                        : collect($activeSubstances);
                                @endphp
                                @if($activeSubstanceList->isEmpty())
                                    <p class="mt-2 text-xs text-gray-500">Belum ada data zat aktif tersimpan.</p>
                                @else
                                    <select name="detected_substance_2"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">-- pilih zat aktif --</option>
                                        @foreach($activeSubstanceList as $substance)
                                            <option value="{{ $substance }}" @selected($detectedValue2 === $substance)>{{ $substance }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-semibold text-gray-900">Unggah Hasil Pengujian (Instrumen Ke-2)</h4>
                            @php
                                $resultAttachmentName2 = $secondaryResultAttachmentOriginal
                                    ?? ($secondaryResultAttachmentPath ? basename($secondaryResultAttachmentPath) : null);
                            @endphp
                            @if(!empty($secondaryResultAttachmentUrl))
                                <div class="mt-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600">
                                    <span class="font-medium text-gray-700">File saat ini:</span>
                                    <a href="{{ $secondaryResultAttachmentUrl }}" target="_blank" class="ml-1 text-primary-600 hover:text-primary-700 underline">
                                        {{ $resultAttachmentName2 ?? 'Lihat dokumen' }}
                                    </a>
                                </div>
                            @endif
                            <div class="mt-3">
                                <input type="file" name="test_result_file_2" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                    class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                            </div>
                        </div>
                    </div>
                @endif

                {{-- INSTRUMENTATION STAGE: Instrument Logging --}}
                @if($selectedStage === 'instrumentation')
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                        x-data="instrumentLogging({{ $process->sample_id }})"
                        x-init="loadRequirements()">
                        <h3 class="text-sm font-semibold text-gray-900">Pencatatan Instrumen</h3>
                        <p class="mt-1 text-xs text-gray-500">Pilih aset instrumen yang digunakan untuk setiap metode pengujian sampel ini.</p>

                        <template x-if="loading">
                            <div class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
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
                                                <svg class="h-3 w-3 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                                <span x-text="log.instrument_name + ' (' + log.asset_code + ') - ' + log.performed_by + ' @ ' + log.logged_at"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div x-show="error" class="mt-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700" x-text="error"></div>

                                <div class="mt-4 flex justify-end">
                                    <button type="button" @click="saveInstrumentUsage()"
                                        :disabled="saving"
                                        class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span x-show="!saving">Simpan Pencatatan Instrumen</span>
                                        <span x-show="saving" class="flex items-center gap-2">
                                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                        x-data="analyticalBalanceWeighing({{ $process->sample_id }})"
                        x-init="checkWeighingStatus()">
                        <h3 class="text-sm font-semibold text-gray-900">Penimbangan (Analytical Balance)</h3>
                        <p class="mt-1 text-xs text-gray-500">Catat data penimbangan sampel menggunakan Analytical Balance.</p>

                        <template x-if="loading">
                            <div class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Memeriksa status penimbangan...</span>
                            </div>
                        </template>

                        <template x-if="!loading && !requiresWeighing">
                            <div class="mt-4 rounded-md bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-700">
                                Sampel ini tidak memerlukan penimbangan (tidak ada requirement Analytical Balance pada metode pengujian yang dipilih).
                            </div>
                        </template>

                        <template x-if="!loading && requiresWeighing">
                            <div class="mt-4 space-y-4">
                                <template x-if="hasWeighing">
                                    <div class="rounded-md bg-green-50 border border-green-200 px-3 py-3">
                                        <div class="flex items-center gap-2 text-sm text-green-800">
                                            <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
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
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    placeholder="1">
                                                <p class="mt-1 text-xs text-gray-500">Berapa banyak sampel/aliquot yang ditimbang.</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Massa Terbaca <span class="text-red-500">*</span>
                                                </label>
                                                <input type="number" step="0.000001" min="0.000001" max="99999999.999999"
                                                    x-model="massValue"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    placeholder="0.000000">
                                                <p class="mt-1 text-xs text-gray-500">Nilai massa dari Analytical Balance.</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Unit <span class="text-red-500">*</span>
                                                </label>
                                                <select x-model="massUnit"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
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

                                        <div x-show="error" class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700" x-text="error"></div>

                                        <div class="flex justify-end">
                                            <button type="button" @click="saveWeighing()"
                                                :disabled="saving || !itemsCount || !massValue || !massUnit"
                                                class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                                <span x-show="!saving">Simpan Data Penimbangan</span>
                                                <span x-show="saving" class="flex items-center gap-2">
                                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
                @endif

                <div class="flex justify-end gap-3">
                    <a href="{{ route('process.processes.show', ['sample_process' => $process->id]) }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">Batal</a>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Perbarui</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    function instrumentLogging(sampleId) {
        return {
            sampleId: sampleId,
            loading: true,
            enabled: false,
            requirements: {},
            existingLogs: [],
            selections: {},
            saving: false,
            error: null,

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
                    alert('Pencatatan instrumen berhasil disimpan.');
                } catch (err) {
                    this.error = 'Terjadi kesalahan: ' + err.message;
                } finally {
                    this.saving = false;
                }
            }
        };
    }

    function analyticalBalanceWeighing(sampleId) {
        return {
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
                    alert('Data penimbangan berhasil disimpan.');
                } catch (err) {
                    this.error = 'Terjadi kesalahan: ' + err.message;
                } finally {
                    this.saving = false;
                }
            }
        };
    }
    </script>
    @endpush
</x-app-layout>
