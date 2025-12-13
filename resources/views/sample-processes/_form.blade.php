@php
    $stageOptions = $stages ?? [];
    $showNotes = $showNotes ?? true;
    $showMetadata = $showMetadata ?? true;
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Sampel</label>
            <select name="sample_id" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">-- pilih sampel --</option>
                @foreach($samples as $sample)
                    <option value="{{ $sample->id }}" @selected(old('sample_id', $process->sample_id ?? $selectedSample ?? null) == $sample->id)>
                        {{ $sample->sample_name }} ({{ $sample->testRequest?->request_number ?? 'Tanpa Permintaan' }})
                    </option>
                @endforeach
            </select>
            @error('sample_id')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Tahapan Proses</label>
            <select name="stage" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">-- pilih tahapan --</option>
                @php($currentStage = $process && $process->stage ? ($process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage) : null)
                @foreach($stageOptions as $stageKey => $stageLabel)
                    @continue($stageKey === 'administration')
                    <option value="{{ $stageKey }}" @selected(old('stage', $currentStage) === $stageKey)>{{ $stageLabel }}</option>
                @endforeach
            </select>
            @error('stage')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Pelaksana</label>
            <select name="performed_by"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">-- belum ditentukan --</option>
                @foreach($analysts as $analyst)
                    <option value="{{ $analyst->id }}" @selected((int) old('performed_by', $process?->performed_by) === $analyst->id)>
                        {{ $analyst->display_name_with_title }}
                    </option>
                @endforeach
            </select>
            @error('performed_by')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Mulai</label>
                <input type="datetime-local" name="started_at"
                    value="{{ old('started_at', $process?->started_at?->format('Y-m-d\TH:i')) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @error('started_at')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Selesai</label>
                <input type="datetime-local" name="completed_at"
                    value="{{ old('completed_at', $process?->completed_at?->format('Y-m-d\TH:i')) }}"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @error('completed_at')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    @if($showNotes)
        <div>
            <label class="block text-sm font-medium text-gray-700">Catatan</label>
            <textarea name="notes" rows="4"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                placeholder="Ringkasan progres, kondisi sampel, atau temuan penting">{{ old('notes', $process?->notes) }}</textarea>
            @error('notes')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @if($showMetadata)
        <div>
            <label class="block text-sm font-medium text-gray-700">Metadata Tambahan (opsional)</label>
            <p class="mt-1 text-xs text-gray-500">Isi sebagai pasangan kunci-nilai. Contoh: <code>{"suhu": "25&deg;C", "alat": "GC-MS"}</code></p>
            <textarea name="metadata_raw" rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                placeholder='{"parameter": "nilai"}'>{{ old('metadata_raw', $process?->metadata ? json_encode($process->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
            @error('metadata_raw')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

</div>

