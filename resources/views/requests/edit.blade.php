<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Permintaan Pengujian #{{ $request->request_number }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">

                @if (session('success'))
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li class="text-sm">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Warning BA --}}
                <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-900 px-4 py-3 rounded-lg flex items-start">
                    <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm">
                        Perhatian: Setelah mengedit data, Berita Acara Penerimaan mungkin perlu di-generate ulang agar sesuai dengan perubahan terbaru.
                    </p>
                </div>

                <form id="request-edit-form" action="{{ route('requests.update', $request) }}" method="POST" enctype="multipart/form-data" class="space-y-8" x-data="{ isSubmitting: false }" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_submission_token" value="{{ Str::random(40) }}">

                    {{-- Form Progress Stepper --}}
                    <x-form-stepper :steps="[
                        ['id' => 'step-investigator', 'label' => 'Data Penyidik'],
                        ['id' => 'step-letter', 'label' => 'Info Surat'],
                        ['id' => 'step-suspects', 'label' => 'Tersangka'],
                        ['id' => 'step-documents', 'label' => 'Dokumen'],
                        ['id' => 'step-samples', 'label' => 'Sampel']
                    ]" />

                    {{-- Investigator Type Question --}}
                    @php
                        $persistedIsPolri = $request->investigator?->is_polri ?? true;
                        $isPolri = old('is_investigator', $persistedIsPolri ? '1' : '0') === '1';
                    @endphp
                    <div class="bg-indigo-50 p-6 rounded-lg border border-indigo-200 mb-6">
                        <h3 class="text-lg font-semibold text-indigo-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                            </svg>
                            Apakah Anda penyidik?
                        </h3>
                        <div class="flex gap-6">
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input type="radio" name="is_investigator" value="1" 
                                       {{ old('is_investigator', $isPolri ? '1' : '0') == '1' ? 'checked' : '' }}
                                       class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="text-gray-700 font-medium">Ya, saya penyidik/anggota Polri</span>
                            </label>
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input type="radio" name="is_investigator" value="0"
                                       {{ old('is_investigator', $isPolri ? '1' : '0') == '0' ? 'checked' : '' }}
                                       class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="text-gray-700 font-medium">Bukan anggota Polri</span>
                            </label>
                        </div>
                    </div>

                    {{-- Investigator Section (Polri) --}}
                    <div id="step-investigator-polri" class="block-investigator bg-blue-50 p-6 rounded-lg border border-blue-200 scroll-mt-24" style="{{ $isPolri ? '' : 'display: none;' }}">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                            Data Penyidik
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="investigator_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama Penyidik <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="investigator_name" id="investigator_name"
                                       value="{{ old('investigator_name', $request->investigator->name) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="investigator_nrp" class="block text-sm font-medium text-gray-700 mb-2">
                                    NRP Penyidik <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="investigator_nrp" id="investigator_nrp"
                                       value="{{ old('investigator_nrp', $request->investigator->nrp) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Contoh: 87010123">
                            </div>
                            <div>
                                <label for="investigator_rank" class="block text-sm font-medium text-gray-700 mb-2">
                                    Pangkat <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="investigator_rank" id="investigator_rank"
                                       value="{{ old('investigator_rank', $request->investigator->rank) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="investigator_jurisdiction" class="block text-sm font-medium text-gray-700 mb-2">
                                    Satuan / Wilayah Hukum <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="investigator_jurisdiction" id="investigator_jurisdiction"
                                       value="{{ old('investigator_jurisdiction', $request->investigator->jurisdiction) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label for="investigator_address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Alamat Penyidik
                                </label>
                                <textarea name="investigator_address" id="investigator_address" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('investigator_address', $request->investigator->address ?? '') }}</textarea>
                            </div>
                            <div>
                                <label for="investigator_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nomor Telepon <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="investigator_phone" id="investigator_phone"
                                       value="{{ old('investigator_phone', $request->investigator->phone) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    {{-- External Section (Non-Polri) --}}
                    <div id="step-investigator-external" class="block-external bg-green-50 p-6 rounded-lg border border-green-200 scroll-mt-24" style="{{ $isPolri ? 'display: none;' : '' }}">
                        <h3 class="text-lg font-semibold text-green-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                            Data Pemohon
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="external_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="external_name" id="external_name"
                                       value="{{ old('external_name', !$isPolri ? $request->investigator->name : '') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            </div>
                            <div>
                                <label for="external_institution" class="block text-sm font-medium text-gray-700 mb-2">
                                    Instansi <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="external_institution" id="external_institution"
                                       value="{{ old('external_institution', !$isPolri ? $request->investigator->institution : '') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="external_hp" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nomor HP <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" name="external_hp" id="external_hp"
                                       value="{{ old('external_hp', !$isPolri ? $request->investigator->phone : '') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label for="external_occupation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Pekerjaan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="external_occupation" id="external_occupation"
                                       value="{{ old('external_occupation', !$isPolri ? $request->investigator->occupation : '') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label for="tujuan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tujuan Pengujian <span class="text-red-500">*</span>
                                </label>
                                <textarea name="tujuan" id="tujuan" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('tujuan') border-red-500 @enderror"
                                          placeholder="Jelaskan tujuan Anda mengajukan pengujian sampel ke LPMF...">{{ old('tujuan', !$isPolri ? $request->tujuan : '') }}</textarea>
                                @error('tujuan')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Info Surat --}}
                    <div id="step-letter" class="bg-primary-50 p-6 rounded-lg border border-primary-200 scroll-mt-24">
                        <h3 class="text-lg font-semibold text-primary-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                            </svg>
                            Informasi Surat Permintaan
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="case_number" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nomor Surat
                                </label>
                                <input type="text" name="case_number" id="case_number"
                                       value="{{ old('case_number', $request->case_number) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Contoh: S/123/IV/2025/RESKRIM">
                            </div>
                            <div>
                                <label for="letter_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tanggal Surat
                                </label>
                                <input type="date" name="letter_date" id="letter_date"
                                       value="{{ old('letter_date', optional($request->letter_date)->format('Y-m-d')) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('letter_date') border-red-500 @enderror">
                                @error('letter_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: Data Tersangka --}}
                    <div id="step-suspects" data-investigator-only class="bg-orange-50 p-6 rounded-lg border border-orange-200 scroll-mt-24">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-orange-900 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                                </svg>
                                Data Tersangka
                            </h3>
                            <button type="button" id="add-suspect"
                                    class="inline-flex items-center px-3 py-1.5 border border-orange-600 text-sm font-medium rounded-md text-orange-700 bg-orange-100 hover:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                                </svg>
                                Tambah Tersangka
                            </button>
                        </div>
                        <div id="suspects-container" class="space-y-4">
                            @php
                                $suspects = old('suspects') 
                                    ?? ($request->suspects->count() > 0 
                                        ? $request->suspects->map(fn($s) => ['name' => $s->name, 'gender' => $s->gender, 'age' => $s->age])->toArray() 
                                        : [['name' => $request->suspect_name, 'gender' => $request->suspect_gender, 'age' => $request->suspect_age]]);
                            @endphp
                            @foreach($suspects as $idx => $suspect)
                            <div class="suspect-row bg-white p-4 rounded-lg border border-gray-200 shadow-sm" data-index="{{ $idx }}">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="font-semibold text-gray-800 flex items-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-orange-600 text-white text-sm font-bold mr-2">{{ $idx + 1 }}</span>
                                        Tersangka {{ $idx + 1 }}
                                    </h4>
                                    @if($idx > 0)
                                    <button type="button" class="remove-suspect inline-flex items-center px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded text-sm font-medium transition">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                        Hapus
                                    </button>
                                    @endif
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Nama Lengkap <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="suspects[{{ $idx }}][name]" required
                                               value="{{ $suspect['name'] ?? '' }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Jenis Kelamin
                                        </label>
                                        <select name="suspects[{{ $idx }}][gender]"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                                            <option value="">Pilih</option>
                                            <option value="male" {{ ($suspect['gender'] ?? '') == 'male' ? 'selected' : '' }}>Laki-laki</option>
                                            <option value="female" {{ ($suspect['gender'] ?? '') == 'female' ? 'selected' : '' }}>Perempuan</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Umur (tahun)
                                        </label>
                                        <input type="number" name="suspects[{{ $idx }}][age]" min="0" max="120"
                                               value="{{ $suspect['age'] ?? '' }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Step 4: Upload Dokumen --}}
                    @php
                        $expertWitnessDocument = $request->documents->where('document_type', 'expert_witness_request')->sortByDesc('id')->first();
                        $hasExpertWitnessRequest = old('has_expert_witness_request', $request->has_expert_witness_request || $expertWitnessDocument ? '1' : '0') === '1';
                    @endphp
                    <div id="step-documents" class="bg-gray-50 p-6 rounded-lg border border-gray-200 scroll-mt-24">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                            </svg>
                            Upload Dokumen
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Request Letter --}}
                            <div>
                                <label for="request_letter" class="block text-sm font-medium text-gray-700 mb-2">
                                    Surat Permintaan Pengujian (PDF)
                                </label>
                                
                                @if($request->official_letter_path)
                                    <div class="mb-3 flex items-center p-2 bg-white rounded border border-blue-100 text-blue-700 text-sm">
                                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        <span class="truncate flex-1">File saat ini tersimpan</span>
                                        <a href="{{ route('requests.documents.download', [$request, 'request_letter']) }}" target="_blank" class="text-blue-500 hover:text-blue-700 font-medium ml-2">Lihat</a>
                                    </div>
                                @endif

                                <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-blue-400 transition-colors duration-200">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 justify-center">
                                            <label for="request_letter" class="relative cursor-pointer bg-gray-50 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                                <span>Upload file PDF</span>
                                                <input id="request_letter" name="request_letter" type="file" class="sr-only" accept=".pdf">
                                            </label>
                                            <p class="pl-1">atau drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">Maks. 10MB</p>
                                    </div>
                                </div>
                                @error('request_letter')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Evidence Photo --}}
                            <div>
                                <label for="evidence_photo" class="block text-sm font-medium text-gray-700 mb-2">
                                    Foto Barang Bukti (Opsional)
                                </label>

                                @if($request->evidence_photo_path)
                                    <div class="mb-3 flex items-center p-2 bg-white rounded border border-blue-100 text-blue-700 text-sm">
                                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <span class="truncate flex-1">Foto saat ini tersimpan</span>
                                        <a href="{{ route('requests.documents.download', [$request, 'evidence_photo']) }}" target="_blank" class="text-blue-500 hover:text-blue-700 font-medium ml-2">Lihat</a>
                                    </div>
                                @endif

                                <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-blue-400 transition-colors duration-200">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 justify-center">
                                            <label for="evidence_photo" class="relative cursor-pointer bg-gray-50 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                                <span>Upload foto</span>
                                                <input id="evidence_photo" name="evidence_photo" type="file" class="sr-only" accept="image/*">
                                            </label>
                                            <p class="pl-1">JPG, PNG</p>
                                        </div>
                                        <p class="text-xs text-gray-500">Maks. 5MB</p>
                                    </div>
                                </div>
                                @error('evidence_photo')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <fieldset>
                                    <legend class="block text-sm font-medium text-gray-800 mb-3">
                                        Apakah meliputi permintaan saksi ahli? <span class="text-red-500">*</span>
                                    </legend>
                                    <div class="flex flex-col gap-3 sm:flex-row sm:gap-6">
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="radio"
                                                   name="has_expert_witness_request"
                                                   value="1"
                                                   {{ $hasExpertWitnessRequest ? 'checked' : '' }}
                                                   class="h-4 w-4 border-gray-300 text-amber-600 focus:ring-amber-500">
                                            <span>Ya, sertakan permintaan saksi ahli</span>
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="radio"
                                                   name="has_expert_witness_request"
                                                   value="0"
                                                   {{ $hasExpertWitnessRequest ? '' : 'checked' }}
                                                   class="h-4 w-4 border-gray-300 text-amber-600 focus:ring-amber-500">
                                            <span>Tidak, lanjut tanpa dokumen saksi ahli</span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div id="expert_witness_request_upload" class="mt-4 {{ $hasExpertWitnessRequest ? '' : 'hidden' }} space-y-4">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label for="expert_witness_letter_number" class="block text-sm font-medium text-gray-700 mb-2">
                                                Nomor Surat Saksi Ahli
                                            </label>
                                            <input type="text"
                                                   name="expert_witness_letter_number"
                                                   id="expert_witness_letter_number"
                                                   value="{{ old('expert_witness_letter_number', $request->expert_witness_letter_number) }}"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500 @error('expert_witness_letter_number') border-red-500 @enderror"
                                                   placeholder="Contoh: B/123/IV/2026/Reskrim">
                                            @error('expert_witness_letter_number')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="expert_witness_letter_date" class="block text-sm font-medium text-gray-700 mb-2">
                                                Tanggal Surat Saksi Ahli
                                            </label>
                                            <input type="date"
                                                   name="expert_witness_letter_date"
                                                   id="expert_witness_letter_date"
                                                   value="{{ old('expert_witness_letter_date', optional($request->expert_witness_letter_date)->format('Y-m-d')) }}"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500 @error('expert_witness_letter_date') border-red-500 @enderror">
                                            @error('expert_witness_letter_date')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    @if($expertWitnessDocument)
                                        <div class="mb-3 flex items-center rounded border border-amber-100 bg-white p-2 text-sm text-amber-800">
                                            <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            <span class="flex-1 truncate">File permintaan saksi ahli saat ini tersimpan</span>
                                            <a href="{{ route('requests.documents.download', [$request, 'expert_witness_request']) }}" target="_blank" class="ml-2 font-medium text-amber-700 hover:text-amber-900">Lihat</a>
                                        </div>
                                    @endif

                                    <label for="expert_witness_request_file" class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ $expertWitnessDocument ? 'Ganti File Permintaan Saksi Ahli (PDF)' : 'File Permintaan Saksi Ahli (PDF)' }}
                                    </label>
                                    <div class="flex justify-center rounded-md border-2 border-dashed border-amber-300 bg-white px-6 pb-6 pt-5 transition-colors duration-200 hover:border-amber-400">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-10 w-10 text-amber-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex justify-center text-sm text-gray-600">
                                                <label for="expert_witness_request_file" class="relative cursor-pointer rounded-md bg-white font-medium text-amber-700 hover:text-amber-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-amber-500 focus-within:ring-offset-2">
                                                    <span>Upload file PDF</span>
                                                    <input id="expert_witness_request_file" name="expert_witness_request_file" type="file" class="sr-only" accept=".pdf" {{ $expertWitnessDocument ? 'data-has-existing-document=1' : '' }}>
                                                </label>
                                            </div>
                                            <p id="expert_witness_request_filename" class="text-xs text-gray-500">{{ $expertWitnessDocument ? 'Kosongkan jika tidak ingin mengganti file.' : 'PDF hingga 10MB' }}</p>
                                        </div>
                                    </div>
                                    @error('expert_witness_request_file')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 5: Daftar Sampel --}}
                    <div id="step-samples" class="bg-orange-50 p-6 rounded-lg border border-orange-200 scroll-mt-24">
                        <h3 class="text-lg font-semibold text-orange-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            Daftar Sampel
                        </h3>

                        <div class="space-y-4" id="samples-container">
                             @foreach($request->samples as $index => $sample)
                                 <div class="sample-item bg-white p-6 rounded-lg border border-gray-200 mb-4" data-index="{{ $index }}">
                                     <div class="flex items-center justify-between mb-4">
                                         <h4 class="text-md font-medium text-gray-900">Sampel #{{ $index + 1 }}</h4>
                                         <button type="button" class="text-red-600 hover:text-red-800 text-sm font-medium remove-sample">
                                             Hapus
                                         </button>
                                     </div>
 
                                     <input type="hidden" name="samples[{{ $index }}][id]" value="{{ $sample->id }}">

                                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                         <div>
                                             <label class="block text-sm font-medium text-gray-700 mb-1">
                                                 Deskripsi Singkat <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text"
                                                   name="samples[{{ $index }}][short_description]"
                                                   value="{{ old('samples.'.$index.'.short_description', $sample->short_description) }}"
                                                   required
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>

                                          <div>
                                             <label class="block text-sm font-medium text-gray-700 mb-1">
                                                 Jumlah yang Diserahkan <span class="text-red-500">*</span>
                                             </label>
                                             <input type="number"
                                                    name="samples[{{ $index }}][package_quantity]"
                                                    value="{{ old('samples.'.$index.'.package_quantity', $sample->package_quantity) }}"
                                                    required
                                                    min="1"
                                                    step="1"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                         </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Satuan <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text"
                                                   name="samples[{{ $index }}][unit]"
                                                   value="{{ old('samples.'.$index.'.unit', $sample->unit) }}"
                                                   required
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" id="add-sample" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                            </svg>
                            Tambah Sampel
                        </button>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                        <a href="{{ route('requests.show', $request) }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Batal
                        </a>
                        <button type="submit"
                                :disabled="isSubmitting"
                                :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <template x-if="isSubmitting">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </template>
                            <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan'">Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite('resources/js/pages/requests-form.js')

    <script>
    // Auto-fill existing investigator data
    (function() {
        const existingInvestigatorSelect = document.getElementById('existing_investigator_select');
        if (existingInvestigatorSelect) {
            existingInvestigatorSelect.addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                
                if (this.value) {
                    document.getElementById('investigator_name').value = option.dataset.name || '';
                    document.getElementById('investigator_nrp').value = option.dataset.nrp || '';
                    document.getElementById('investigator_rank').value = option.dataset.rank || '';
                    document.getElementById('investigator_jurisdiction').value = option.dataset.jurisdiction || '';
                    document.getElementById('investigator_phone').value = option.dataset.phone || '';
                    document.getElementById('investigator_address').value = option.dataset.address || '';
                }
            });
        }

        const existingExternalSelect = document.getElementById('existing_external_select');
        if (existingExternalSelect) {
            existingExternalSelect.addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                
                if (this.value) {
                    document.getElementById('external_name').value = option.dataset.name || '';
                    document.getElementById('external_institution').value = option.dataset.institution || '';
                    document.getElementById('external_hp').value = option.dataset.phone || '';
                    document.getElementById('external_occupation').value = option.dataset.occupation || '';
                }
            });
        }
    })();

    // Display filename when file is selected for request letter
    function displayRequestLetterFileName(input) {
        const filenameDisplay = document.getElementById('request_letter_filename');
        const dropzone = document.getElementById('request_letter_dropzone');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileSize = (file.size / (1024 * 1024)).toFixed(2);
            
            // Create preview if it doesn't exist or update existing
            let preview = document.getElementById('request_letter_filename');
            if (!preview) {
                preview = document.createElement('p');
                preview.id = 'request_letter_filename';
                preview.className = 'text-xs text-gray-500 mt-1';
                dropzone.parentElement.appendChild(preview);
            }
            
            preview.innerHTML = `<span class="text-green-600 font-medium">✓ ${file.name}</span> <span class="text-gray-500">(${fileSize} MB)</span>`;
            dropzone.classList.remove('border-gray-300');
            dropzone.classList.add('border-green-500', 'bg-green-50');
        }
    }

    // Drag and drop support
    (function() {
        const dropzone = document.getElementById('request_letter_dropzone');
        const fileInput = document.getElementById('request_letter');
        
        if (!dropzone || !fileInput) return;
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => {
                dropzone.classList.add('border-blue-500', 'bg-blue-50');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => {
                dropzone.classList.remove('border-blue-500', 'bg-blue-50');
            }, false);
        });
        
        dropzone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                displayRequestLetterFileName(fileInput);
            }
        }, false);
    })();

    document.addEventListener('DOMContentLoaded', function() {
        let sampleIndex = {{ $request->samples->count() }};

        document.getElementById('add-sample').addEventListener('click', function() {
            const container = document.getElementById('samples-container');
            // We create a fresh template string instead of cloning to avoid copying existing values/IDs messily
            // Using the same structure as the create page
            const newSample = document.createElement('div');
            newSample.className = 'sample-item bg-white p-6 rounded-lg border border-gray-200 mb-4';
            newSample.dataset.index = sampleIndex;
            
             newSample.innerHTML = `
                 <div class="flex items-center justify-between mb-4">
                     <h4 class="text-md font-medium text-gray-900">Sampel #${sampleIndex + 1}</h4>
                     <button type="button" class="text-red-600 hover:text-red-800 text-sm font-medium remove-sample">
                         Hapus
                     </button>
                 </div>
 
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="md:col-span-2">
                          <label class="block text-sm font-medium text-gray-700 mb-1">
                              Deskripsi Singkat <span class="text-red-500">*</span>
                          </label>
                          <input type="text"
                                 name="samples[${sampleIndex}][short_description]"
                                 required
                                 class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                     </div>

                     <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">
                             Jumlah yang Diserahkan <span class="text-red-500">*</span>
                         </label>
                         <input type="number"
                                name="samples[${sampleIndex}][package_quantity]"
                                required
                                min="1"
                                step="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                     </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Satuan <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="samples[${sampleIndex}][unit]"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            `;
            
            container.appendChild(newSample);
            sampleIndex++;
        });

        document.getElementById('samples-container').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-sample')) {
                e.target.closest('.sample-item').remove();
            }
        });
    });
    </script>
</x-app-layout>
