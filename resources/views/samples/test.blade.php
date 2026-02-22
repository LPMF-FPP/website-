<x-app-layout>
    @php
        $selectedId = (string) old('request_id', $selectedRequestId);
        $requestSamples = $selectedRequest?->samples ?? collect();
        $hasRequests = $requests->isNotEmpty();
        $canSubmit = $selectedRequest && $requestSamples->isNotEmpty() && $analysts->isNotEmpty();
    @endphp

    <x-slot name="header">
        <x-page-header
            title="Form Kaji Ulang"
            :breadcrumbs="[]"
        />
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <a
            href="#review-form"
            class="sr-only rounded-md bg-white px-3 py-2 text-sm font-semibold text-primary-700 focus:not-sr-only focus:absolute focus:z-30 focus:ring-2 focus:ring-primary-500"
        >
            Lewati ke formulir kaji ulang
        </a>

        @if (session('success'))
            <x-alert type="success" title="Berhasil" class="rounded-lg border">
                {{ session('success') }}
            </x-alert>
        @endif

        @if ($errors->any())
            <x-alert type="error" title="Data belum lengkap" class="rounded-lg border">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <form id="review-form" action="{{ route('review.store') }}" method="POST" class="space-y-6" autocomplete="off" data-review-form>
            @csrf

            <div class="grid gap-6 xl:grid-cols-12">
                <aside class="space-y-6 xl:col-span-4">
                    <section class="rounded-lg border border-primary-100 bg-gradient-to-br from-primary-50/70 via-white to-sky-50 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-primary-900">Antrian Kaji Ulang 📋</h2>
                                <p class="mt-1 text-sm text-gray-600">Pilih permintaan, atur tanggal pengujian, lalu lengkapi data setiap sampel.</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-primary-300 bg-primary-600 px-3 py-1 text-sm font-bold text-white shadow-sm">
                                {{ $requests->count() }} permintaan
                            </span>
                        </div>

                        @if ($hasRequests)
                            <div class="mt-5 space-y-4">
                                <div>
                                    <label for="request_filter" class="block text-sm font-medium text-gray-700">Cari cepat permintaan</label>
                                    <input
                                        id="request_filter"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                        placeholder="Cari nomor resi, nomor permintaan, atau penyidik"
                                        autocomplete="off"
                                        aria-describedby="request_filter_help"
                                    >
                                    <p id="request_filter_help" class="mt-1 text-xs text-gray-500">Gunakan untuk mempersempit pilihan pada dropdown.</p>
                                </div>

                                <div>
                                    <label for="request_id" class="block text-sm font-medium text-gray-700">Pilih Permintaan</label>
                                    <select
                                        id="request_id"
                                        name="request_id"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                        aria-describedby="request_id_help"
                                    >
                                        <option value="">-- pilih --</option>
                                        @foreach ($requests as $req)
                                            @php
                                                $requestLabel = ($req->receipt_number ?? $req->request_number) . ' - ' . ($req->investigator->name ?? 'Tanpa Penyidik');
                                            @endphp
                                            <option
                                                value="{{ $req->id }}"
                                                data-search="{{ Str::lower($requestLabel . ' ' . ($req->status ?? '')) }}"
                                                @selected($selectedId === (string) $req->id)
                                            >
                                                {{ $requestLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p id="request_id_help" class="mt-1 text-xs text-gray-500">Daftar menampilkan permintaan dengan status diajukan, terverifikasi, dan diterima.</p>
                                    @error('request_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="test_date" class="block text-sm font-medium text-gray-700">Tanggal Pengujian</label>
                                    <input
                                        id="test_date"
                                        name="test_date"
                                        type="date"
                                        required
                                        value="{{ old('test_date') ?? optional($selectedRequest?->test_date)->format('Y-m-d') }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    >
                                    @error('test_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @else
                            <div class="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-4">
                                <x-empty-state
                                    size="sm"
                                    title="Belum ada permintaan"
                                    description="Silakan buat permintaan baru terlebih dahulu agar dapat dikaji ulang."
                                    :actionHref="route('requests.create')"
                                    actionText="Buat Permintaan"
                                    icon="document"
                                />
                            </div>
                        @endif
                    </section>

                    @if ($selectedRequest)
                        <section class="rounded-lg border border-teal-100 bg-gradient-to-br from-teal-50/70 via-white to-primary-50/50 p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="text-base font-semibold text-primary-900">Ringkasan Permintaan Aktif 🧾</h2>
                                <x-status-badge :status="$selectedRequest->status" subtle showIcon />
                            </div>

                            <dl class="mt-4 space-y-3 text-sm text-gray-700">
                                <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-2">
                                    <dt class="text-gray-500">Nomor Resi</dt>
                                    <dd class="font-semibold text-primary-900">{{ $selectedRequest->receipt_number ?? '-' }}</dd>
                                </div>
                                <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-2">
                                    <dt class="text-gray-500">Nomor Permintaan</dt>
                                    <dd class="font-medium text-gray-800">{{ $selectedRequest->request_number ?? '-' }}</dd>
                                </div>
                                <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-2">
                                    <dt class="text-gray-500">Penyidik</dt>
                                    <dd class="text-right font-medium text-gray-800">{{ $selectedRequest->investigator->name ?? 'Tanpa Penyidik' }}</dd>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <dt class="text-gray-500">Jumlah Sampel</dt>
                                    <dd class="font-semibold text-primary-900">{{ $requestSamples->count() }} sampel</dd>
                                </div>
                            </dl>
                        </section>
                    @endif
                </aside>

                <section class="space-y-6 xl:col-span-8" aria-label="Detail kaji ulang">
                    @if (! $hasRequests)
                        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <x-empty-state
                                title="Belum ada data untuk dikaji ulang"
                                description="Saat ini belum ada permintaan pada status yang bisa diproses ke tahap pengujian."
                                icon="folder-open"
                            />
                        </div>
                    @elseif (! $selectedRequest)
                        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <x-empty-state
                                title="Pilih satu permintaan"
                                description="Pilih data dari panel kiri untuk menampilkan detail sampel dan formulir pengujian."
                                icon="document-duplicate"
                            />
                        </div>
                    @elseif ($requestSamples->isEmpty())
                        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-6 shadow-sm">
                            <x-alert type="warning" title="Tidak ada sampel untuk diproses" :bordered="false">
                                Permintaan ini tidak memiliki sampel aktif. Silakan pilih permintaan lain.
                            </x-alert>
                        </div>
                    @else
                        <div class="rounded-lg border border-primary-100 bg-gradient-to-br from-white via-primary-50/40 to-sky-50/70 p-6 shadow-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-primary-900">Detail Sampel untuk Kaji Ulang 🧪</h2>
                                    <p class="text-sm text-gray-600">Lengkapi data berikut untuk setiap sampel sebelum melanjutkan ke tahap pengujian.</p>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-700">
                                    {{ $requestSamples->count() }} sampel aktif
                                </span>
                            </div>

                            <div class="mt-6 space-y-6">
                                @foreach ($requestSamples as $sample)
                                    @php
                                        $sampleIndex = $loop->index;

                                        $selectedMethodsRaw = old("samples.$sampleIndex.test_methods", $sample->test_methods ?? []);
                                        if (is_string($selectedMethodsRaw)) {
                                            $selectedMethods = json_decode($selectedMethodsRaw, true) ?? [];
                                        } else {
                                            $selectedMethods = $selectedMethodsRaw ?? [];
                                        }
                                        if (! is_array($selectedMethods)) {
                                            $selectedMethods = [];
                                        }

                                        $requestedMethodsRaw = $sample->requested_test_methods;
                                        if (is_string($requestedMethodsRaw)) {
                                            $requestedMethods = json_decode($requestedMethodsRaw, true) ?? [];
                                        } else {
                                            $requestedMethods = $requestedMethodsRaw ?? $sample->test_methods ?? [];
                                        }
                                        if (! is_array($requestedMethods)) {
                                            $requestedMethods = [];
                                        }
                                        $requestedMethods = array_values(array_unique($requestedMethods));
                                        $optionalSelectedMethods = array_values(array_diff($selectedMethods, $requestedMethods));
                                        $methodSelectValues = ! empty($optionalSelectedMethods)
                                            ? $optionalSelectedMethods
                                            : (empty($requestedMethods) ? [''] : []);

                                        $selectedOtherCategory = old("samples.$sampleIndex.other_sample_category", $sample->other_sample_category);
                                        $isOtherSample = $sample->sample_type === 'other';
                                        $physicalIdentificationValue = old("samples.$sampleIndex.physical_identification", $sample->physical_identification);

                                        $selectedPhysicalMode = old("samples.$sampleIndex.physical_id_mode");
                                        if (! in_array($selectedPhysicalMode, ['existing', 'new'], true)) {
                                            $selectedPhysicalMode = ($existingPhysicalIdentifications->isNotEmpty() && ! $sample->physical_identification) ? 'existing' : 'new';
                                        }

                                        $selectedAnalystId = (int) old("samples.$sampleIndex.assigned_analyst_id", $sample->assigned_analyst_id);
                                        $maxQuantity = is_numeric($sample->package_quantity) ? (float) $sample->package_quantity : null;

                                        $selectedQuantity = old("samples.$sampleIndex.quantity", $sample->quantity);
                                        $selectedBatchNumber = old("samples.$sampleIndex.batch_number", $sample->batch_number);
                                        $selectedTestType = old("samples.$sampleIndex.test_type", $sample->test_type);
                                        $selectedNotes = old("samples.$sampleIndex.notes", $sample->notes);
                                        $resolvedMethods = array_values(array_unique(array_filter(array_merge($requestedMethods, $optionalSelectedMethods))));

                                        $isSampleComplete =
                                            $selectedAnalystId > 0 &&
                                            count($resolvedMethods) > 0 &&
                                            filled($physicalIdentificationValue) &&
                                            is_numeric($selectedQuantity) &&
                                            (float) $selectedQuantity > 0 &&
                                            filled($selectedBatchNumber) &&
                                            filled($selectedTestType) &&
                                            filled($selectedNotes) &&
                                            (! $isOtherSample || filled($selectedOtherCategory));
                                    @endphp

                                    <details class="group rounded-xl border border-primary-100 bg-white p-5 shadow-sm" data-sample-panel>
                                        <summary class="cursor-pointer list-none rounded-lg border border-primary-100 bg-gradient-to-r from-primary-50/80 via-white to-sky-50/70 p-3 transition hover:from-primary-100 hover:to-sky-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 [&::-webkit-details-marker]:hidden">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-700">Sampel {{ $loop->iteration }} 🧪</p>
                                                    <h3 class="text-base font-semibold text-gray-900">{{ $sample->short_description ?? 'Tanpa deskripsi' }}</h3>
                                                    <p class="text-sm text-gray-500">Kode sampel: <span class="font-medium text-primary-700">{{ $sample->sample_code }}</span></p>
                                                </div>
                                                <div class="flex flex-col items-end gap-1">
                                                    <span
                                                        data-sample-status
                                                        class="rounded-md border px-2 py-1 text-xs font-semibold {{ $isSampleComplete ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}"
                                                    >
                                                        {{ $isSampleComplete ? 'Siap ✅' : 'Belum lengkap ⚠️' }}
                                                    </span>
                                                    <span class="rounded-md border border-primary-200 bg-white px-2 py-1 text-xs font-medium text-primary-700">Klik untuk buka</span>
                                                </div>
                                            </div>
                                            <p class="mt-2 text-xs text-gray-500">Persediaan pada permintaan: {{ $sample->package_quantity ?? '—' }} {{ $sample->unit ?? '' }}</p>
                                        </summary>

                                        <div class="mt-4 border-t border-gray-100 pt-4">
                                            <input type="hidden" name="samples[{{ $sampleIndex }}][id]" value="{{ $sample->id }}">

                                        @if ($isOtherSample)
                                            <div class="mt-4">
                                                <label class="block text-sm font-medium text-gray-700" for="sample-{{ $sample->id }}-other-category">Kategori Sampel</label>
                                                <select
                                                    id="sample-{{ $sample->id }}-other-category"
                                                    name="samples[{{ $sampleIndex }}][other_sample_category]"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    data-validate="other-category"
                                                    required
                                                >
                                                    <option value="">-- pilih kategori --</option>
                                                    @foreach ($otherSampleOptions as $optionValue => $optionLabel)
                                                        <option value="{{ $optionValue }}" @selected($selectedOtherCategory === $optionValue)>{{ $optionLabel }}</option>
                                                    @endforeach
                                                </select>
                                                @error('samples.' . $sampleIndex . '.other_sample_category')
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endif

                                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700" for="sample-{{ $sample->id }}-analyst">Penguji / Analis</label>

                                                @if ($analysts->isEmpty())
                                                    <p class="mt-2 rounded border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
                                                        Belum ada data analis yang tersedia. Silakan tambah pengguna dengan peran analis terlebih dahulu.
                                                    </p>
                                                @else
                                                    <select
                                                        id="sample-{{ $sample->id }}-analyst"
                                                        name="samples[{{ $sampleIndex }}][assigned_analyst_id]"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        data-validate="analyst"
                                                        required
                                                    >
                                                        <option value="">-- pilih analis --</option>
                                                        @foreach ($analysts as $analyst)
                                                            <option value="{{ $analyst->id }}" @selected($selectedAnalystId === $analyst->id)>
                                                                {{ $analyst->display_name_with_title }}{{ $analyst->identification_number ? ' - ' . $analyst->identification_number : '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endif

                                                @error("samples.$sampleIndex.assigned_analyst_id")
                                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700" for="sample-{{ $sample->id }}-methods">Metode Pengujian</label>

                                                @if (! empty($requestedMethods))
                                                    <div class="mt-1 rounded-md border border-primary-100 bg-primary-50 px-3 py-2 text-xs text-primary-800">
                                                        Metode wajib dari permintaan:
                                                        <span class="font-medium">
                                                            {{ collect($requestedMethods)->map(fn ($method) => $methodOptions[$method] ?? $method)->implode(', ') }}
                                                        </span>
                                                    </div>
                                                @endif

                                                @foreach ($requestedMethods as $requestedMethod)
                                                    <input type="hidden" name="samples[{{ $sampleIndex }}][test_methods][]" value="{{ $requestedMethod }}" data-required-method>
                                                @endforeach

                                                <div
                                                    class="mt-2 space-y-2"
                                                    data-methods-wrapper
                                                    data-has-required="{{ empty($requestedMethods) ? 'false' : 'true' }}"
                                                >
                                                    <div data-method-rows>
                                                        @foreach ($methodSelectValues as $methodValue)
                                                            <div class="flex items-center gap-2" data-method-row>
                                                                <select
                                                                    @if ($loop->first) id="sample-{{ $sample->id }}-methods" @endif
                                                                    name="samples[{{ $sampleIndex }}][test_methods][]"
                                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                    data-method-select
                                                                    @if ($loop->first && empty($requestedMethods)) required @endif
                                                                >
                                                                    <option value="">-- pilih metode --</option>
                                                                    @foreach ($methodOptions as $methodKey => $methodLabel)
                                                                        @if (! in_array($methodKey, $requestedMethods, true))
                                                                            <option value="{{ $methodKey }}" @selected($methodValue === $methodKey)>{{ $methodLabel }}</option>
                                                                        @endif
                                                                    @endforeach
                                                                </select>
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                                                    data-method-remove
                                                                >
                                                                    Hapus
                                                                </button>
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    <template data-method-template>
                                                        <div class="flex items-center gap-2" data-method-row>
                                                            <select
                                                                name="samples[{{ $sampleIndex }}][test_methods][]"
                                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                data-method-select
                                                            >
                                                                <option value="">-- pilih metode --</option>
                                                                @foreach ($methodOptions as $methodKey => $methodLabel)
                                                                    @if (! in_array($methodKey, $requestedMethods, true))
                                                                        <option value="{{ $methodKey }}">{{ $methodLabel }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                                                data-method-remove
                                                            >
                                                                Hapus
                                                            </button>
                                                        </div>
                                                    </template>

                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center rounded-md border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 transition hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                                        data-method-add
                                                    >
                                                        Tambah Metode
                                                    </button>
                                                </div>

                                                @error("samples.$sampleIndex.test_methods")
                                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Identifikasi Sampel / Barang Bukti</label>

                                                <div class="mt-1 flex flex-wrap items-center gap-4 text-sm">
                                                    <label class="inline-flex items-center gap-2">
                                                        <input
                                                            type="radio"
                                                            name="samples[{{ $sampleIndex }}][physical_id_mode]"
                                                            value="existing"
                                                            class="physical-id-mode-radio rounded-full border-gray-300 text-primary-600 focus:ring-primary-500"
                                                            data-sample-index="{{ $sampleIndex }}"
                                                            @checked($selectedPhysicalMode === 'existing')
                                                        >
                                                        <span class="text-gray-700">Pilih yang sudah ada</span>
                                                    </label>
                                                    <label class="inline-flex items-center gap-2">
                                                        <input
                                                            type="radio"
                                                            name="samples[{{ $sampleIndex }}][physical_id_mode]"
                                                            value="new"
                                                            class="physical-id-mode-radio rounded-full border-gray-300 text-primary-600 focus:ring-primary-500"
                                                            data-sample-index="{{ $sampleIndex }}"
                                                            @checked($selectedPhysicalMode === 'new')
                                                        >
                                                        <span class="text-gray-700">Input baru</span>
                                                    </label>
                                                </div>

                                                <div id="physical-id-existing-{{ $sampleIndex }}" class="mt-2 {{ $selectedPhysicalMode === 'existing' ? '' : 'hidden' }}">
                                                    <label for="physical-id-select-{{ $sampleIndex }}" class="sr-only">Daftar identifikasi yang tersedia</label>
                                                    <select
                                                        id="physical-id-select-{{ $sampleIndex }}"
                                                        class="physical-id-select block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        data-sample-index="{{ $sampleIndex }}"
                                                        @if ($selectedPhysicalMode === 'existing') required @endif
                                                    >
                                                        <option value="">-- Pilih identifikasi yang sudah ada --</option>
                                                        @foreach ($existingPhysicalIdentifications as $identification)
                                                            <option value="{{ $identification }}" @selected($physicalIdentificationValue === $identification)>{{ Str::limit($identification, 100) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div id="physical-id-new-{{ $sampleIndex }}" class="mt-2 {{ $selectedPhysicalMode === 'new' ? '' : 'hidden' }}">
                                                    <label for="physical-id-textarea-{{ $sampleIndex }}" class="sr-only">Input identifikasi baru</label>
                                                    <textarea
                                                        id="physical-id-textarea-{{ $sampleIndex }}"
                                                        rows="3"
                                                        class="physical-id-textarea block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        data-sample-index="{{ $sampleIndex }}"
                                                        placeholder="Contoh: Tablet putih dalam kemasan blister dengan garis hijau…"
                                                        @if ($selectedPhysicalMode === 'new') required @endif
                                                    >{{ $physicalIdentificationValue }}</textarea>
                                                </div>

                                                    <input
                                                        type="hidden"
                                                        name="samples[{{ $sampleIndex }}][physical_identification]"
                                                        id="physical-id-hidden-{{ $sampleIndex }}"
                                                        data-validate="physical-identification"
                                                        value="{{ $physicalIdentificationValue }}"
                                                    >

                                                @error("samples.$sampleIndex.physical_identification")
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700" for="sample-{{ $sample->id }}-quantity">Jumlah Sampel untuk Pengujian</label>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    Persediaan pada permintaan:
                                                    <span class="font-medium text-gray-700">{{ $sample->package_quantity ?? '—' }} {{ $sample->unit ?? '' }}</span>
                                                </p>
                                                <div class="mt-2 grid grid-cols-2 gap-3">
                                                    <div>
                                                        <input
                                                            id="sample-{{ $sample->id }}-quantity"
                                                            type="number"
                                                            name="samples[{{ $sampleIndex }}][quantity]"
                                                            step="0.01"
                                                            min="0.01"
                                                            @if ($maxQuantity) max="{{ $maxQuantity }}" @endif
                                                            required
                                                            value="{{ $selectedQuantity }}"
                                                            data-validate="quantity"
                                                            placeholder="Jumlah"
                                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        >
                                                    </div>
                                                    <div>
                                                        <input
                                                            type="text"
                                                            value="{{ $sample->unit ?? '—' }}"
                                                            readonly
                                                            tabindex="-1"
                                                            class="block w-full cursor-not-allowed rounded-md border-gray-300 bg-gray-50 text-gray-600 shadow-sm"
                                                        >
                                                        <input type="hidden" name="samples[{{ $sampleIndex }}][quantity_unit]" value="{{ $sample->unit ?? '' }}">
                                                    </div>
                                                </div>
                                                @error("samples.$sampleIndex.quantity")
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700" for="sample-{{ $sample->id }}-batch-number">No Batch</label>
                                                <input
                                                    id="sample-{{ $sample->id }}-batch-number"
                                                    type="text"
                                                    name="samples[{{ $sampleIndex }}][batch_number]"
                                                    value="{{ $selectedBatchNumber }}"
                                                    required
                                                    data-validate="batch-number"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                >
                                                @error("samples.$sampleIndex.batch_number")
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700" for="sample-{{ $sample->id }}-expiry-date">Exp Date</label>
                                                <input
                                                    id="sample-{{ $sample->id }}-expiry-date"
                                                    type="date"
                                                    name="samples[{{ $sampleIndex }}][expiry_date]"
                                                    value="{{ old("samples.$sampleIndex.expiry_date", optional($sample->expiry_date)->format('Y-m-d')) }}"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                >
                                                @error("samples.$sampleIndex.expiry_date")
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700" for="sample-{{ $sample->id }}-test-type">Jenis / Fokus Pengujian</label>
                                                <select
                                                    id="sample-{{ $sample->id }}-test-type"
                                                    name="samples[{{ $sampleIndex }}][test_type]"
                                                    required
                                                    data-validate="test-type"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                >
                                                    <option value="">-- pilih --</option>
                                                    @foreach (['kualitatif' => 'Analisis Kualitatif', 'kuantitatif' => 'Analisis Kuantitatif', 'both' => 'Kualitatif & Kuantitatif'] as $key => $label)
                                                        <option value="{{ $key }}" @selected($selectedTestType === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700" for="sample-{{ $sample->id }}-notes">Catatan Tambahan</label>
                                                <textarea
                                                    id="sample-{{ $sample->id }}-notes"
                                                    name="samples[{{ $sampleIndex }}][notes]"
                                                    rows="2"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    placeholder="Catatan khusus pengujian jika diperlukan"
                                                >{{ $selectedNotes }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                                @endforeach
                            </div>
                        </div>

                        <div class="sticky bottom-4 z-10">
                            <div class="rounded-xl border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm text-gray-600">Aksi utama akan menyimpan hasil kaji ulang dan mengarahkan ke tahap pengujian.</p>
                                    <button
                                        type="submit"
                                        data-submit-review
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-primary-600 px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-primary-400"
                                        @disabled(! $canSubmit)
                                        aria-disabled="{{ $canSubmit ? 'false' : 'true' }}"
                                    >
                                        <x-icon name="loading" size="sm" class="hidden" data-submit-spinner spin :decorative="true" />
                                        <span data-submit-label>{{ $canSubmit ? 'Simpan Kaji Ulang' : 'Lengkapi data terlebih dahulu' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </section>
            </div>
        </form>

        @if ($selectedRequest)
            <section class="rounded-lg border border-red-200 bg-red-50/50 p-5 shadow-sm" aria-labelledby="reject-request-title">
                <h2 id="reject-request-title" class="text-base font-semibold text-red-700">Aksi Sekunder: Tolak Permintaan ⚠️</h2>
                <p class="mt-1 text-sm text-red-600">Gunakan hanya jika permintaan tidak memenuhi syarat. Aksi ini tidak dapat dibatalkan.</p>

                <form
                    action="{{ route('review.reject', $selectedRequest->id) }}"
                    method="POST"
                    class="mt-4 space-y-4"
                    autocomplete="off"
                    data-reject-form
                >
                    @csrf
                    <div>
                        <label for="rejection_reason" class="block text-sm font-medium text-gray-700">Alasan Penolakan</label>
                        <textarea
                            id="rejection_reason"
                            name="rejection_reason"
                            rows="3"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                            placeholder="Jelaskan alasan penolakan…"
                        >{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        data-reject-submit
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                    >
                        <x-icon name="x" size="sm" :decorative="true" />
                        Tolak Permintaan
                    </button>
                </form>
            </section>
        @endif

        <div id="review-feedback" class="sr-only" aria-live="polite"></div>

        <div
            id="review-loading-state"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-white/60 backdrop-blur-sm"
            role="status"
            aria-live="assertive"
            aria-label="Memuat data permintaan"
        >
            <div class="inline-flex items-center gap-3 rounded-full border border-primary-100 bg-white px-4 py-2 text-sm font-medium text-primary-700 shadow-sm">
                <x-icon name="loading" size="sm" spin :decorative="true" />
                <span>Memuat data permintaan…</span>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const reviewBaseUrl = "{{ url('/kaji-ulang-permintaan') }}";
                const requestSelect = document.getElementById('request_id');

                if (!requestSelect) {
                    return;
                }

                const loadingState = document.getElementById('review-loading-state');
                const feedback = document.getElementById('review-feedback');
                const requestFilterInput = document.getElementById('request_filter');
                const reviewForm = document.querySelector('[data-review-form]');
                const submitButton = document.querySelector('[data-submit-review]');
                const submitLabel = submitButton?.querySelector('[data-submit-label]');
                const submitSpinner = submitButton?.querySelector('[data-submit-spinner]');
                const rejectForm = document.querySelector('[data-reject-form]');
                const rejectSubmit = document.querySelector('[data-reject-submit]');

                const optionEntries = Array.from(requestSelect.options).map((option) => {
                    const label = option.textContent ?? '';

                    return {
                        option,
                        value: option.value,
                        search: (option.dataset.search ?? label).toLowerCase(),
                    };
                });

                const setLoading = (isLoading, message = 'Memuat data permintaan…') => {
                    if (!loadingState) {
                        return;
                    }

                    loadingState.classList.toggle('hidden', !isLoading);
                    loadingState.classList.toggle('flex', isLoading);

                    const messageTarget = loadingState.querySelector('span');
                    if (messageTarget) {
                        messageTarget.textContent = message;
                    }
                };

                const navigateToSelectedRequest = (selectedValue) => {
                    if (!selectedValue) {
                        window.location.href = reviewBaseUrl;
                        return;
                    }

                    const url = new URL(reviewBaseUrl, window.location.origin);
                    url.searchParams.set('request_id', selectedValue);
                    setLoading(true, 'Memuat detail permintaan…');
                    window.location.href = url.toString();
                };

                requestSelect.addEventListener('change', () => {
                    navigateToSelectedRequest(requestSelect.value);
                });

                const applyRequestFilter = () => {
                    const keyword = (requestFilterInput?.value ?? '').trim().toLowerCase();

                    optionEntries.forEach((entry) => {
                        if (entry.value === '') {
                            entry.option.hidden = false;
                            return;
                        }

                        const match = keyword === '' || entry.search.includes(keyword);
                        const keepVisible = entry.value === requestSelect.value;
                        entry.option.hidden = !match && !keepVisible;
                    });

                    if (feedback) {
                        feedback.textContent = keyword === ''
                            ? 'Semua permintaan ditampilkan.'
                            : 'Filter daftar permintaan diperbarui.';
                    }
                };

                if (requestFilterInput) {
                    requestFilterInput.addEventListener('input', applyRequestFilter);
                }

                const setSampleStatusBadge = (badge, isComplete) => {
                    badge.textContent = isComplete ? 'Siap ✅' : 'Belum lengkap ⚠️';
                    badge.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-700', 'border-amber-200', 'bg-amber-50', 'text-amber-700');
                    if (isComplete) {
                        badge.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
                    } else {
                        badge.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-700');
                    }
                };

                const refreshSamplePanelStatus = (panel) => {
                    if (!(panel instanceof HTMLElement)) {
                        return;
                    }

                    const badge = panel.querySelector('[data-sample-status]');
                    if (!(badge instanceof HTMLElement)) {
                        return;
                    }

                    const analyst = panel.querySelector('[data-validate="analyst"]');
                    const physical = panel.querySelector('[data-validate="physical-identification"]');
                    const quantity = panel.querySelector('[data-validate="quantity"]');
                    const batch = panel.querySelector('[data-validate="batch-number"]');
                    const testType = panel.querySelector('[data-validate="test-type"]');
                    const otherCategory = panel.querySelector('[data-validate="other-category"]');

                    const requiredMethodHidden = panel.querySelectorAll('input[data-required-method]');
                    const methodSelects = Array.from(panel.querySelectorAll('[data-method-select]'));

                    const hasMethods = requiredMethodHidden.length > 0 || methodSelects.some((select) => {
                        return select instanceof HTMLSelectElement && select.value !== '';
                    });

                    const quantityValue = quantity instanceof HTMLInputElement ? Number.parseFloat(quantity.value || '0') : 0;

                    const isComplete =
                        analyst instanceof HTMLSelectElement && analyst.value !== '' &&
                        hasMethods &&
                        physical instanceof HTMLInputElement && physical.value.trim() !== '' &&
                        Number.isFinite(quantityValue) && quantityValue > 0 &&
                        batch instanceof HTMLInputElement && batch.value.trim() !== '' &&
                        testType instanceof HTMLSelectElement && testType.value !== '' &&
                        (!(otherCategory instanceof HTMLSelectElement) || otherCategory.value !== '');

                    setSampleStatusBadge(badge, isComplete);
                };

                const refreshAllSamplePanelStatus = () => {
                    document.querySelectorAll('[data-sample-panel]').forEach((panel) => {
                        refreshSamplePanelStatus(panel);
                    });
                };

                document.querySelectorAll('[data-methods-wrapper]').forEach((wrapper) => {
                    const rowsContainer = wrapper.querySelector('[data-method-rows]');
                    const template = wrapper.querySelector('template[data-method-template]');
                    const addButton = wrapper.querySelector('[data-method-add]');
                    const hasRequiredMethods = wrapper.dataset.hasRequired === 'true';
                    const samplePanel = wrapper.closest('[data-sample-panel]');

                    if (!rowsContainer || !template || !addButton) {
                        return;
                    }

                    const updateMethodRows = () => {
                        const rows = Array.from(rowsContainer.querySelectorAll('[data-method-row]'));
                        const selects = rowsContainer.querySelectorAll('[data-method-select]');

                        selects.forEach((select, index) => {
                            if (!hasRequiredMethods && index === 0) {
                                select.setAttribute('required', 'required');
                            } else {
                                select.removeAttribute('required');
                            }
                        });

                        rows.forEach((row) => {
                            const removeButton = row.querySelector('[data-method-remove]');
                            if (!removeButton) {
                                return;
                            }

                            const disableRemove = !hasRequiredMethods && rows.length <= 1;
                            removeButton.disabled = disableRemove;
                            removeButton.classList.toggle('opacity-50', disableRemove);
                            removeButton.classList.toggle('cursor-not-allowed', disableRemove);
                        });

                        refreshSamplePanelStatus(samplePanel);
                    };

                    const addMethodRow = () => {
                        const fragment = template.content.cloneNode(true);
                        const row = fragment.firstElementChild;

                        if (!row) {
                            return;
                        }

                        rowsContainer.appendChild(row);
                        updateMethodRows();
                    };

                    addButton.addEventListener('click', () => {
                        addMethodRow();
                    });

                    rowsContainer.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLElement)) {
                            return;
                        }

                        const removeButton = target.closest('[data-method-remove]');
                        if (!removeButton) {
                            return;
                        }

                        const rows = rowsContainer.querySelectorAll('[data-method-row]');
                        if (!hasRequiredMethods && rows.length <= 1) {
                            return;
                        }

                        removeButton.closest('[data-method-row]')?.remove();
                        updateMethodRows();
                    });

                    rowsContainer.addEventListener('change', () => {
                        refreshSamplePanelStatus(samplePanel);
                    });

                    updateMethodRows();
                });

                const pruneEmptyMethodRows = () => {
                    document.querySelectorAll('[data-methods-wrapper]').forEach((wrapper) => {
                        const rowsContainer = wrapper.querySelector('[data-method-rows]');
                        if (!rowsContainer) {
                            return;
                        }

                        const hasRequiredMethods = wrapper.dataset.hasRequired === 'true';
                        const rows = Array.from(rowsContainer.querySelectorAll('[data-method-row]'));

                        rows.forEach((row) => {
                            const select = row.querySelector('[data-method-select]');
                            if (!(select instanceof HTMLSelectElement)) {
                                return;
                            }

                            if (select.value !== '') {
                                return;
                            }

                            if (!hasRequiredMethods && rowsContainer.querySelectorAll('[data-method-row]').length <= 1) {
                                return;
                            }

                            row.remove();
                        });
                    });
                };

                if (reviewForm && submitButton && submitLabel && submitSpinner) {
                    reviewForm.addEventListener('submit', () => {
                        refreshAllSamplePanelStatus();
                        pruneEmptyMethodRows();
                        submitButton.disabled = true;
                        submitSpinner.classList.remove('hidden');
                        submitLabel.textContent = 'Menyimpan kaji ulang…';
                        submitButton.classList.add('opacity-80', 'cursor-not-allowed');
                        setLoading(true, 'Menyimpan data kaji ulang…');

                        if (feedback) {
                            feedback.textContent = 'Sistem sedang menyimpan data kaji ulang.';
                        }
                    });
                }

                if (rejectForm && rejectSubmit) {
                    rejectForm.addEventListener('submit', (event) => {
                        if (!window.confirm('Apakah Anda yakin ingin menolak permintaan ini?')) {
                            event.preventDefault();
                            return;
                        }

                        rejectSubmit.disabled = true;
                        rejectSubmit.classList.add('opacity-80', 'cursor-not-allowed');
                        setLoading(true, 'Memproses penolakan permintaan…');

                        if (feedback) {
                            feedback.textContent = 'Sistem sedang memproses penolakan permintaan.';
                        }
                    });
                }

                const syncPhysicalIdentificationMode = (sampleIndex, mode) => {
                    const existingContainer = document.getElementById(`physical-id-existing-${sampleIndex}`);
                    const newContainer = document.getElementById(`physical-id-new-${sampleIndex}`);
                    const selectEl = document.getElementById(`physical-id-select-${sampleIndex}`);
                    const textareaEl = document.getElementById(`physical-id-textarea-${sampleIndex}`);
                    const hiddenInput = document.getElementById(`physical-id-hidden-${sampleIndex}`);

                    if (!existingContainer || !newContainer || !selectEl || !textareaEl || !hiddenInput) {
                        return;
                    }

                    const useExisting = mode === 'existing';

                    existingContainer.classList.toggle('hidden', !useExisting);
                    newContainer.classList.toggle('hidden', useExisting);

                    if (useExisting) {
                        hiddenInput.value = selectEl.value;
                        selectEl.setAttribute('required', 'required');
                        textareaEl.removeAttribute('required');
                    } else {
                        hiddenInput.value = textareaEl.value;
                        textareaEl.setAttribute('required', 'required');
                        selectEl.removeAttribute('required');
                    }

                    refreshSamplePanelStatus(hiddenInput.closest('[data-sample-panel]'));
                };

                document.querySelectorAll('.physical-id-mode-radio').forEach((radio) => {
                    radio.addEventListener('change', () => {
                        syncPhysicalIdentificationMode(radio.dataset.sampleIndex, radio.value);
                    });
                });

                document.querySelectorAll('.physical-id-select').forEach((selectEl) => {
                    selectEl.addEventListener('change', () => {
                        const sampleIndex = selectEl.dataset.sampleIndex;
                        const hiddenInput = document.getElementById(`physical-id-hidden-${sampleIndex}`);

                        if (hiddenInput) {
                            hiddenInput.value = selectEl.value;
                            refreshSamplePanelStatus(hiddenInput.closest('[data-sample-panel]'));
                        }
                    });
                });

                document.querySelectorAll('.physical-id-textarea').forEach((textareaEl) => {
                    textareaEl.addEventListener('input', () => {
                        const sampleIndex = textareaEl.dataset.sampleIndex;
                        const hiddenInput = document.getElementById(`physical-id-hidden-${sampleIndex}`);

                        if (hiddenInput) {
                            hiddenInput.value = textareaEl.value;
                            refreshSamplePanelStatus(hiddenInput.closest('[data-sample-panel]'));
                        }
                    });
                });

                document.querySelectorAll('[data-sample-panel]').forEach((panel) => {
                    panel.addEventListener('input', () => {
                        refreshSamplePanelStatus(panel);
                    });

                    panel.addEventListener('change', () => {
                        refreshSamplePanelStatus(panel);
                    });
                });

                const modePerSample = new Map();
                document.querySelectorAll('.physical-id-mode-radio:checked').forEach((radio) => {
                    modePerSample.set(radio.dataset.sampleIndex, radio.value);
                });
                modePerSample.forEach((mode, sampleIndex) => {
                    syncPhysicalIdentificationMode(sampleIndex, mode);
                });

                refreshAllSamplePanelStatus();

            })();
        </script>
    @endpush
</x-app-layout>
