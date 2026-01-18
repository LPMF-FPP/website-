<x-app-layout>
    @if(!isset($firstSampleId))
        @php
            $firstSampleId = optional($selectedRequest?->samples->first())->id;
        @endphp
    @endif


    <x-slot name="header">
        <x-page-header
            title="Form Kaji Ulang Permintaan"
            :breadcrumbs="[[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Kaji Ulang Permintaan' ]]"
        />
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200 space-y-6">
                @if(session('success'))
                    <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $selectedId = old('request_id', $selectedRequestId);
                    $firstSampleId = optional($selectedRequest?->samples->first())->id;
                @endphp

                <form action="{{ route('review.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="request_id" class="block text-sm font-medium text-gray-700">Pilih Permintaan</label>
                            <select id="request_id" name="request_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">-- pilih --</option>
                                @foreach($requests as $req)
                                    <option value="{{ $req->id }}" @selected($selectedId == $req->id)>
                                        {{ $req->receipt_number ?? $req->request_number }} - {{ $req->investigator->name ?? 'Tanpa Penyidik' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="test_date" class="block text-sm font-medium text-gray-700">Tanggal Pengujian</label>
                            <input id="test_date" name="test_date" type="date" required
                                value="{{ old('test_date') ?? optional($selectedRequest?->test_date)->format('Y-m-d') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    @if($selectedRequest)
                        @php
                            $requestSamples = $selectedRequest->samples;
                        @endphp
                        @if($requestSamples->isEmpty())
                            <div class="rounded border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                                Tidak ada sampel yang terdaftar pada permintaan ini.
                            </div>
                        @else
                            <div class="space-y-6">
                                @foreach($requestSamples as $sample)
                                    @php
                                        $sampleIndex = $loop->index;
                                        $selectedMethodsRaw = old("samples.$sampleIndex.test_methods", $sample->test_methods ?? []);
                                    @endphp
                                    @php
                                        if (is_string($selectedMethodsRaw)) {
                                            $selectedMethods = json_decode($selectedMethodsRaw, true) ?? [];
                                        } else {
                                            $selectedMethods = $selectedMethodsRaw ?? [];
                                        }
                                        if (!is_array($selectedMethods)) {
                                            $selectedMethods = [];
                                        }

                                        $requestedMethodsRaw = $sample->requested_test_methods;
                                        if (is_string($requestedMethodsRaw)) {
                                            $requestedMethods = json_decode($requestedMethodsRaw, true) ?? [];
                                        } else {
                                            $requestedMethods = $requestedMethodsRaw ?? $sample->test_methods ?? [];
                                        }
                                        if (!is_array($requestedMethods)) {
                                            $requestedMethods = [];
                                        }
                                    @endphp
                                    @php
                                        $selectedOtherCategory = old("samples.$sampleIndex.other_sample_category", $sample->other_sample_category);
                                    @endphp
                                    <div class="rounded-lg border border-gray-200 p-5 shadow-sm">
                                        <div class="flex flex-col gap-2 border-b border-gray-100 pb-3 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900">{{ $sample->short_description ?? '—' }}</h3>
                                                <p class="text-sm text-gray-500">Kode Sampel: <span class="font-medium text-primary-700">{{ $sample->sample_code }}</span></p>
                                            </div>
                                                <div class="mt-2">
                                                    <label class="block text-xs font-medium text-gray-600">Kategori Sampel</label>
                                                    <select name="samples[{{ $sampleIndex }}][other_sample_category]"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        required>
                                                        <option value="">-- pilih kategori --</option>
                                                        @foreach($otherSampleOptions as $optionValue => $optionLabel)
                                                            <option value="{{ $optionValue }}" @selected($selectedOtherCategory === $optionValue)>{{ $optionLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('samples.' . $sampleIndex . '.other_sample_category')
                                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                        </div>

                                        <input type="hidden" name="samples[{{ $sampleIndex }}][id]" value="{{ $sample->id }}">

                                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Penguji / Analis</label>

                                                @if($analysts->isEmpty())
                                                    <p class="mt-2 rounded border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
                                                        Belum ada data analis yang tersedia. Silakan tambah pengguna dengan peran analis terlebih dahulu.
                                                    </p>
                                                @else
                                                    @php
                                                        $selectedAnalystId = (int) old("samples.$sampleIndex.assigned_analyst_id", $sample->assigned_analyst_id);
                                                    @endphp
                                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                        @foreach($analysts as $analyst)
                                                            @php
                                                                $inputId = 'sample-' . $sample->id . '-analyst-' . $analyst->id;
                                                            @endphp
                                                            <label for="{{ $inputId }}" class="relative block cursor-pointer">
                                                                <input type="radio"
                                                                    id="{{ $inputId }}"
                                                                    name="samples[{{ $sampleIndex }}][assigned_analyst_id]"
                                                                    value="{{ $analyst->id }}"
                                                                    class="peer sr-only"
                                                                    @checked($selectedAnalystId === $analyst->id)
                                                                    required>
                                                                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-500 hover:shadow-md peer-checked:border-primary-600 peer-checked:ring-2 peer-checked:ring-primary-200">
                                                                    <p class="text-sm font-semibold text-gray-900">
                                                                        {{ $analyst->display_name_with_title }}
                                                                    </p>
                                                                    <div class="mt-2 space-y-1 text-xs text-gray-600">
                                                                        <div><span class="font-medium text-gray-500">Pangkat:</span> {{ $analyst->rank ?? '-' }}</div>
                                                                        <div><span class="font-medium text-gray-500">NRP/NIP:</span> {{ $analyst->identification_number ?? '-' }}</div>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                @error("samples.$sampleIndex.assigned_analyst_id")
                                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Metode Pengujian</label>
                                                <div class="mt-2 flex flex-wrap gap-3">
                                                    @foreach($methodOptions as $methodKey => $methodLabel)
                                                        @php
                                                            $isRequested = in_array($methodKey, $requestedMethods);
                                                            $isChecked = ($isRequested || in_array($methodKey, $selectedMethods, true));
                                                        @endphp
                                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                            <input type="checkbox"
                                                                name="samples[{{ $sampleIndex }}][test_methods][]"
                                                                value="{{ $methodKey }}"
                                                                {{ $isChecked ? 'checked' : '' }}
                                                                {{ $isRequested ? 'disabled' : '' }}
                                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 {{ $isRequested ? 'bg-gray-100 cursor-not-allowed' : '' }}">
                                                            {{ $methodLabel }}
                                                        </label>
                                                        @if($isRequested)
                                                            <input type="hidden" name="samples[{{ $sampleIndex }}][test_methods][]" value="{{ $methodKey }}">
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Identifikasi Sampel / Barang Bukti</label>
                                                
                                                {{-- Toggle between existing and new --}}
                                                <div class="mt-1 flex items-center gap-4 text-sm">
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" name="samples[{{ $sampleIndex }}][physical_id_mode]" value="existing" 
                                                            class="physical-id-mode-radio text-primary-600 focus:ring-primary-500"
                                                            data-sample-index="{{ $sampleIndex }}"
                                                            @checked($existingPhysicalIdentifications->isNotEmpty() && !$sample->physical_identification)>
                                                        <span class="ml-2 text-gray-700">Pilih yang sudah ada</span>
                                                    </label>
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" name="samples[{{ $sampleIndex }}][physical_id_mode]" value="new" 
                                                            class="physical-id-mode-radio text-primary-600 focus:ring-primary-500"
                                                            data-sample-index="{{ $sampleIndex }}"
                                                            @checked($existingPhysicalIdentifications->isEmpty() || $sample->physical_identification)>
                                                        <span class="ml-2 text-gray-700">Input baru</span>
                                                    </label>
                                                </div>

                                                {{-- Dropdown for existing identifications --}}
                                                <div id="physical-id-existing-{{ $sampleIndex }}" class="mt-2 {{ ($existingPhysicalIdentifications->isEmpty() || $sample->physical_identification) ? 'hidden' : '' }}">
                                                    <select id="physical-id-select-{{ $sampleIndex }}"
                                                        class="physical-id-select block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        data-sample-index="{{ $sampleIndex }}"
                                                        {{ ($existingPhysicalIdentifications->isNotEmpty() && !$sample->physical_identification) ? 'required' : '' }}>
                                                        <option value="">-- Pilih identifikasi yang sudah ada --</option>
                                                        @foreach($existingPhysicalIdentifications as $identification)
                                                            <option value="{{ $identification }}">{{ Str::limit($identification, 100) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                {{-- Textarea for new identification --}}
                                                <div id="physical-id-new-{{ $sampleIndex }}" class="mt-2 {{ ($existingPhysicalIdentifications->isNotEmpty() && !$sample->physical_identification) ? 'hidden' : '' }}">
                                                    <textarea id="physical-id-textarea-{{ $sampleIndex }}" rows="3"
                                                        class="physical-id-textarea block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        data-sample-index="{{ $sampleIndex }}"
                                                        placeholder="Contoh: Tablet putih dalam kemasan blister dengan garis hijau ..."
                                                        {{ ($existingPhysicalIdentifications->isEmpty() || $sample->physical_identification) ? 'required' : '' }}>{{ old("samples.$sampleIndex.physical_identification", $sample->physical_identification) }}</textarea>
                                                </div>

                                                {{-- Hidden input that will be submitted --}}
                                                <input type="hidden" name="samples[{{ $sampleIndex }}][physical_identification]" 
                                                    id="physical-id-hidden-{{ $sampleIndex }}"
                                                    value="{{ old("samples.$sampleIndex.physical_identification", $sample->physical_identification) }}">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Jumlah Sampel untuk Pengujian</label>
                                                <div class="mt-1 text-sm text-gray-500">
                                                    Jumlah sampel yang diberikan (input pada permintaan): 
                                                    <span class="font-medium text-gray-700">{{ $sample->package_quantity ?? '—' }} {{ $sample->unit ?? '' }}</span>
                                                </div>
                                                <div class="mt-2 grid grid-cols-2 gap-3">
                                                    <div>
                                                        <input type="number" name="samples[{{ $sampleIndex }}][quantity]" step="0.01" min="0.01" max="{{ $sample->package_quantity }}" required
                                                            value="{{ old("samples.$sampleIndex.quantity", $sample->quantity) }}"
                                                            placeholder="Jumlah"
                                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                    </div>
                                                    <div>
                                                        <input type="text" 
                                                            value="{{ $sample->unit ?? '—' }}"
                                                            readonly
                                                            tabindex="-1"
                                                            class="block w-full rounded-md border-gray-300 bg-gray-50 text-gray-600 shadow-sm cursor-not-allowed">
                                                        <input type="hidden" name="samples[{{ $sampleIndex }}][quantity_unit]" value="{{ $sample->unit ?? '' }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">No Batch</label>
                                                <input type="text" name="samples[{{ $sampleIndex }}][batch_number]"
                                                    value="{{ old('samples.$sampleIndex.batch_number', $sample->batch_number) }}"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Exp Date</label>
                                                <input type="date" name="samples[{{ $sampleIndex }}][expiry_date]"
                                                    value="{{ old('samples.$sampleIndex.expiry_date', optional($sample->expiry_date)->format('Y-m-d')) }}"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Jenis / Fokus Pengujian</label>
                                                <select name="samples[{{ $sampleIndex }}][test_type]"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                    <option value="">-- pilih --</option>
                                                    @foreach([
                                                        'kualitatif' => 'Analisis Kualitatif',
                                                        'kuantitatif' => 'Analisis Kuantitatif',
                                                        'both' => 'Kualitatif & Kuantitatif',
                                                    ] as $key => $label)
                                                        <option value="{{ $key }}" @selected(old("samples.$sampleIndex.test_type", $sample->test_type) === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Catatan Tambahan</label>
                                                <textarea name="samples[{{ $sampleIndex }}][notes]" rows="2"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    placeholder="Catatan khusus pengujian jika diperlukan">{{ old("samples.$sampleIndex.notes", $sample->notes) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="rounded border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                            Tidak ada permintaan yang tersedia. Silakan buat permintaan baru terlebih dahulu.
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center rounded-md px-6 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ $selectedRequest ? 'bg-primary-600 hover:bg-primary-700' : 'bg-primary-600/60 cursor-not-allowed' }}"
                            aria-disabled="{{ $selectedRequest ? 'false' : 'true' }}"
                            {{ $selectedRequest ? '' : 'disabled' }}>
                            Simpan Kaji Ulang
                        </button>
                    </div>
                </form>

                @if($selectedRequest)
                <div class="mt-8 border-t pt-6">
                    <h3 class="text-lg font-medium text-red-600">Tolak Permintaan</h3>
                    <p class="text-sm text-gray-500 mb-4">Jika permintaan tidak memenuhi syarat, Anda dapat menolaknya. Aksi ini tidak dapat dibatalkan.</p>
                    <form action="{{ route('review.reject', $selectedRequest->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menolak permintaan ini?');">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="rejection_reason" class="block text-sm font-medium text-gray-700">Alasan Penolakan</label>
                                <textarea id="rejection_reason" name="rejection_reason" rows="3" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                    placeholder="Jelaskan alasan penolakan..."></textarea>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:w-auto">
                                Tolak Permintaan
                            </button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const reviewBaseUrl = "{{ url('/kaji-ulang-permintaan') }}";
            document.getElementById('request_id').addEventListener('change', function () {
                const value = this.value;
                if (!value) {
                    window.location.href = reviewBaseUrl;
                    return;
                }
                const url = new URL(reviewBaseUrl, window.location.origin);
                url.searchParams.set('request_id', value);
                window.location.href = url.toString();
            });

            // Physical Identification toggle handler
            document.querySelectorAll('.physical-id-mode-radio').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    const sampleIndex = this.dataset.sampleIndex;
                    const mode = this.value;
                    const existingDiv = document.getElementById('physical-id-existing-' + sampleIndex);
                    const newDiv = document.getElementById('physical-id-new-' + sampleIndex);
                    const selectEl = document.getElementById('physical-id-select-' + sampleIndex);
                    const textareaEl = document.getElementById('physical-id-textarea-' + sampleIndex);
                    const hiddenInput = document.getElementById('physical-id-hidden-' + sampleIndex);

                    if (mode === 'existing') {
                        existingDiv.classList.remove('hidden');
                        newDiv.classList.add('hidden');
                        // Set hidden value from select
                        hiddenInput.value = selectEl.value;
                        
                        // Handle required attributes
                        selectEl.setAttribute('required', 'required');
                        textareaEl.removeAttribute('required');
                    } else {
                        existingDiv.classList.add('hidden');
                        newDiv.classList.remove('hidden');
                        // Set hidden value from textarea
                        hiddenInput.value = textareaEl.value;
                        
                        // Handle required attributes
                        textareaEl.setAttribute('required', 'required');
                        selectEl.removeAttribute('required');
                    }
                });
            });

            // Sync select value to hidden input
            document.querySelectorAll('.physical-id-select').forEach(function(select) {
                select.addEventListener('change', function() {
                    const sampleIndex = this.dataset.sampleIndex;
                    const hiddenInput = document.getElementById('physical-id-hidden-' + sampleIndex);
                    hiddenInput.value = this.value;
                });
            });

            // Sync textarea value to hidden input
            document.querySelectorAll('.physical-id-textarea').forEach(function(textarea) {
                textarea.addEventListener('input', function() {
                    const sampleIndex = this.dataset.sampleIndex;
                    const hiddenInput = document.getElementById('physical-id-hidden-' + sampleIndex);
                    hiddenInput.value = this.value;
                });
            });
        </script>
    @endpush
</x-app-layout>
