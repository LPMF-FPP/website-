<x-app-layout>
    @if(!isset($firstSampleId))
    @php($firstSampleId = optional($selectedRequest?->samples->first())->id)
@endif


    <x-slot name="header">
        <x-page-header
            title="Form Pengujian Sampel"
            :breadcrumbs="[[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Pengujian' ]]"
        />
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('requests.index') }}"
               class="inline-flex items-center rounded-md border border-transparent bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:text-primary-700">
                Daftar Permintaan
            </a>
            <a href="{{ route('samples.test.create') }}"
               class="inline-flex items-center rounded-md border border-primary-600 bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                Pengujian Sampel
            </a>
            <a href="{{ $firstSampleId ? route('sample-processes.index', ['sample_id' => $firstSampleId]) : route('sample-processes.index') }}"
               class="inline-flex items-center rounded-md border border-transparent bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:text-primary-700">
                Proses Pengujian
            </a>
            <a href="{{ route('delivery.index') }}"
               class="inline-flex items-center rounded-md border border-transparent bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:text-primary-700">
                Penyerahan
            </a>
        </div>

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

                @php($selectedId = old('request_id', $selectedRequestId))
                @php($firstSampleId = optional($selectedRequest?->samples->first())->id)

                <form action="{{ route('samples.test.store') }}" method="POST" class="space-y-6">
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
                        @php($requestSamples = $selectedRequest->samples)
                        @if($requestSamples->isEmpty())
                            <div class="rounded border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                                Tidak ada sampel yang terdaftar pada permintaan ini.
                            </div>
                        @else
                            <div class="space-y-6">
                                @foreach($requestSamples as $sample)
                                    @php($sampleIndex = $loop->index)
                                    @php($selectedMethods = collect(old("samples.$sampleIndex.test_methods", $sample->test_methods ?? []))->filter()->all())
                                    @php($selectedOtherCategory = old("samples.$sampleIndex.other_sample_category", $sample->other_sample_category))
                                    <div class="rounded-lg border border-gray-200 p-5 shadow-sm">
                                        <div class="flex flex-col gap-2 border-b border-gray-100 pb-3 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900">{{ $sample->sample_name }}</h3>
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
                                                    @php($selectedAnalystId = (int) old("samples.$sampleIndex.assigned_analyst_id", $sample->assigned_analyst_id))
                                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                        @foreach($analysts as $analyst)
                                                            @php($inputId = 'sample-' . $sample->id . '-analyst-' . $analyst->id)
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
                                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                            <input type="checkbox"
                                                                name="samples[{{ $sampleIndex }}][test_methods][]"
                                                                value="{{ $methodKey }}"
                                                                @checked(in_array($methodKey, $selectedMethods, true))
                                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                            {{ $methodLabel }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Identifikasi Sampel / Barang Bukti</label>
                                                <textarea name="samples[{{ $sampleIndex }}][physical_identification]" rows="3" required
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    placeholder="Contoh: Tablet putih dalam kemasan blister dengan garis hijau ...">{{ old("samples.$sampleIndex.physical_identification", $sample->physical_identification) }}</textarea>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Jumlah Sampel untuk Pengujian</label>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <input type="number" name="samples[{{ $sampleIndex }}][quantity]" step="0.01" min="0.01" required
                                                        value="{{ old('samples.$sampleIndex.quantity', $sample->quantity) }}"
                                                        class="block w-1/2 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                    <input type="text" name="samples[{{ $sampleIndex }}][quantity_unit]" placeholder="satuan"
                                                        value="{{ old('samples.$sampleIndex.quantity_unit', $sample->quantity_unit) }}"
                                                        class="block w-1/2 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
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
                            Simpan Pengujian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('request_id').addEventListener('change', function () {
                const value = this.value;
                if (!value) {
                    window.location.href = '{{ url('/samples/test') }}';
                    return;
                }
                const url = new URL('{{ url('/samples/test') }}', window.location.origin);
                url.searchParams.set('request_id', value);
                window.location.href = url.toString();
            });
        </script>
    @endpush
</x-app-layout>


