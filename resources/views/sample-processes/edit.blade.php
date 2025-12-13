<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Proses Pengujian</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <a href="{{ route('sample-processes.show', ['sample_process' => $process->id]) }}"
                class="inline-flex items-center text-sm font-semibold text-primary-700 hover:text-primary-800">&larr; Kembali ke detail</a>

            <form method="POST" action="{{ route('sample-processes.destroy', ['sample_process' => $process->id]) }}"
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
            <form method="POST" action="{{ route('sample-processes.update', ['sample_process' => $process->id]) }}" class="space-y-6" enctype="multipart/form-data">
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

                <div class="flex justify-end gap-3">
                    <a href="{{ route('sample-processes.show', ['sample_process' => $process->id]) }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">Batal</a>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
