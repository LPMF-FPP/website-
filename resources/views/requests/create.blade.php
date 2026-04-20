<x-app-layout>

    <x-slot name="header">

        <h2 class="font-semibold text-xl text-gray-800 leading-tight">

            Formulir Permintaan Pengujian Sampel

        </h2>

    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        <div class="bg-white shadow-sm sm:rounded-lg">

            <div class="p-6 bg-white border-b border-gray-200">

                @if ($errors->any())

                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">

                        <ul class="list-disc list-inside space-y-1">

                            @foreach ($errors->all() as $error)

                                <li class="text-sm">{{ $error }}</li>

                            @endforeach

                        </ul>

                    </div>

                @endif

                <form id="request-create-form" action="{{ route('requests.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8" x-data="{ isSubmitting: false }" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">

                    @csrf
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
                                       {{ old('is_investigator', '1') == '1' ? 'checked' : '' }}
                                       class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="text-gray-700 font-medium">Ya, saya penyidik/anggota Polri</span>
                            </label>
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input type="radio" name="is_investigator" value="0"
                                       {{ old('is_investigator') == '0' ? 'checked' : '' }}
                                       class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="text-gray-700 font-medium">Bukan anggota Polri</span>
                            </label>
                        </div>
                    </div>

                    {{-- Investigator Section (Polri) --}}
                    <div id="step-investigator-polri" class="block-investigator bg-blue-50 p-6 rounded-lg border border-blue-200 scroll-mt-24">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                            Data Penyidik
                        </h3>

                        {{-- Quick Select Existing Investigator --}}
                        @if(isset($existingInvestigators) && $existingInvestigators->count() > 0)
                        <div class="mb-6 p-4 bg-white rounded-lg border border-blue-100">
                            <label for="existing_investigator_select" class="block text-sm font-medium text-gray-700 mb-2">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                                </svg>
                                Pilih Data Penyidik yang Sudah Terdaftar
                            </label>
                            <select id="existing_investigator_select" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Input Data Baru --</option>
                                @foreach($existingInvestigators as $inv)
                                    <option value="{{ $inv->id }}" 
                                            data-name="{{ $inv->name }}"
                                            data-nrp="{{ $inv->nrp }}"
                                            data-rank="{{ $inv->rank }}"
                                            data-jurisdiction="{{ $inv->jurisdiction }}"
                                            data-phone="{{ $inv->phone }}"
                                            data-address="{{ $inv->address }}">
                                        {{ $inv->rank }} {{ $inv->name }} ({{ $inv->jurisdiction }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Pilih untuk auto-fill data, atau kosongkan untuk input data baru.</p>
                        </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="investigator_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama Penyidik <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="investigator_name" id="investigator_name"
                                       value="{{ old('investigator_name') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('investigator_name') border-red-500 @enderror"
                                       placeholder="Masukkan nama penyidik">
                                @error('investigator_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="investigator_nrp" class="block text-sm font-medium text-gray-700 mb-2">
                                    NRP Penyidik <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="investigator_nrp" id="investigator_nrp"
                                       value="{{ old('investigator_nrp') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('investigator_nrp') border-red-500 @enderror"
                                       placeholder="Contoh: 87010123" pattern="[0-9]{8}" maxlength="8">
                                @error('investigator_nrp')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Format: 8 digit angka</p>
                            </div>
                            <div>
                                <label for="investigator_rank" class="block text-sm font-medium text-gray-700 mb-2">
                                    Pangkat <span class="text-red-500">*</span>
                                </label>
                                <select name="investigator_rank" id="investigator_rank"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('investigator_rank') border-red-500 @enderror">
                                    <option value="">Pilih Pangkat</option>
                                    @foreach(['BRIPDA', 'BRIPTU', 'BRIGADIR', 'BRIPKA', 'AIPDA', 'AIPTU', 'IPDA', 'IPTU', 'AKP', 'KOMPOL'] as $rank)
                                        <option value="{{ $rank }}" {{ old('investigator_rank') == $rank ? 'selected' : '' }}>{{ $rank }}</option>
                                    @endforeach
                                </select>
                                @error('investigator_rank')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="investigator_jurisdiction" class="block text-sm font-medium text-gray-700 mb-2">
                                    Satuan / Wilayah Hukum <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="investigator_jurisdiction" id="investigator_jurisdiction"
                                       value="{{ old('investigator_jurisdiction') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('investigator_jurisdiction') border-red-500 @enderror"
                                       placeholder="Contoh: Polres Jakarta Pusat">
                                @error('investigator_jurisdiction')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="investigator_address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Alamat Penyidik
                                </label>
                                <textarea name="investigator_address" id="investigator_address" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('investigator_address') border-red-500 @enderror"
                                          placeholder="Alamat lengkap penyidik">{{ old('investigator_address') }}</textarea>
                                @error('investigator_address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="investigator_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nomor Telepon <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" name="investigator_phone" id="investigator_phone"
                                       value="{{ old('investigator_phone') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('investigator_phone') border-red-500 @enderror"
                                       placeholder="08XX-XXXX-XXXX">
                                @error('investigator_phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- External Section (Non-Polri) - Hidden by default --}}
                    <div id="step-investigator-external" class="block-external bg-green-50 p-6 rounded-lg border border-green-200 scroll-mt-24" style="display: none;">
                        <h3 class="text-lg font-semibold text-green-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                            Data Pemohon
                        </h3>

                        {{-- Quick Select Existing External --}}
                        @if(isset($existingExternals) && $existingExternals->count() > 0)
                        <div class="mb-6 p-4 bg-white rounded-lg border border-green-100">
                            <label for="existing_external_select" class="block text-sm font-medium text-gray-700 mb-2">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                                </svg>
                                Pilih Pemohon yang Sudah Terdaftar
                            </label>
                            <select id="existing_external_select" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="">-- Input Data Baru --</option>
                                @foreach($existingExternals as $ext)
                                    <option value="{{ $ext->id }}" 
                                            data-name="{{ $ext->name }}"
                                            data-institution="{{ $ext->institution }}"
                                            data-phone="{{ $ext->phone }}"
                                            data-alt-phone="{{ $ext->alt_phone }}"
                                            data-occupation="{{ $ext->occupation }}">
                                        {{ $ext->name }} ({{ $ext->institution }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Pilih untuk auto-fill data, atau kosongkan untuk input data baru.</p>
                        </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="external_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="external_name" id="external_name"
                                       value="{{ old('external_name') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('external_name') border-red-500 @enderror"
                                       placeholder="Nama lengkap">
                                @error('external_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="external_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nomor Telepon <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" name="external_phone" id="external_phone"
                                       value="{{ old('external_phone') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('external_phone') border-red-500 @enderror"
                                       placeholder="Nomor telepon kantor/instansi">
                                @error('external_phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="external_institution" class="block text-sm font-medium text-gray-700 mb-2">
                                    Instansi <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="external_institution" id="external_institution"
                                       value="{{ old('external_institution') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('external_institution') border-red-500 @enderror"
                                       placeholder="Nama instansi">
                                @error('external_institution')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="external_hp" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nomor HP <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" name="external_hp" id="external_hp"
                                       value="{{ old('external_hp') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('external_hp') border-red-500 @enderror"
                                       placeholder="08XX-XXXX-XXXX">
                                @error('external_hp')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="external_occupation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Pekerjaan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="external_occupation" id="external_occupation"
                                       value="{{ old('external_occupation') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('external_occupation') border-red-500 @enderror"
                                       placeholder="Pekerjaan/jabatan">
                                @error('external_occupation')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- 2. Informasi Surat Section (Direvisi) -->

                    <div id="step-letter" class="bg-primary-50 p-6 rounded-lg border border-primary-200 scroll-mt-24">

                        <h3 class="text-lg font-semibold text-primary-900 mb-4 flex items-center">

                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">

                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>

                            </svg>

                            Informasi Surat Permintaan

                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <!-- Ditujukan Kepada -->

                            <div>

                                <label for="to_office" class="block text-sm font-medium text-gray-700 mb-2">

                                    Ditujukan Kepada <span class="text-red-500">*</span>

                                </label>

                                <input type="text"

                                       name="to_office"

                                       id="to_office"

                                       required

                                       value="{{ old('to_office', 'KaPusdokkes Polri') }}"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('to_office') border-red-500 @enderror">

                                @error('to_office')

                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>

                                @enderror

                            </div>

                            <!-- Nomor Surat -->

                            <div>

                                <label for="case_number" class="block text-sm font-medium text-gray-700 mb-2">

                                    Nomor Surat

                                </label>

                                <input type="text"

                                       name="case_number"

                                       id="case_number"

                                       value="{{ old('case_number') }}"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('case_number') border-red-500 @enderror"

                                       placeholder="Contoh: S/123/IV/2025/RESKRIM">

                                @error('case_number')

                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>

                                @enderror

                            </div>

                            <!-- Tanggal Surat -->

                            <div>

                                <label for="letter_date" class="block text-sm font-medium text-gray-700 mb-2">

                                    Tanggal Surat

                                </label>

                                <input type="date"

                                       name="letter_date"

                                       id="letter_date"

                                       value="{{ old('letter_date') }}"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('letter_date') border-red-500 @enderror">

                                @error('letter_date')

                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>

                                @enderror

                        </div>

                            </div> {{-- End grid --}}
                        </div> {{-- End Informasi Surat Section --}}

                    {{-- 3. Data Tersangka Section --}}
                    <div id="step-suspects" class="bg-orange-50 p-6 rounded-lg border border-orange-200 scroll-mt-24">
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
                                $suspects = old('suspects', [['name' => '', 'gender' => '', 'age' => '']]);
                            @endphp
                            @foreach($suspects as $idx => $suspect)
                            <div class="suspect-row bg-white p-4 rounded-lg border border-gray-200 shadow-sm" data-index="{{ $idx }}">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="font-semibold text-gray-800 flex items-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-orange-600 text-white text-sm font-bold mr-2">{{ $idx + 1 }}</span>
                                        Tersangka {{ $idx + 1 }}
                                    </h4>
                                    @if($idx > 0)
                                    <button type="button" class="remove-suspect inline-flex items-center px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded text-sm font-medium transition" aria-label="Hapus tersangka {{ $idx + 1 }}">
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
                                        <input type="text"
                                               name="suspects[{{ $idx }}][name]"
                                               required
                                               value="{{ $suspect['name'] ?? '' }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                               placeholder="Nama lengkap tersangka">
                                        @error("suspects.{$idx}.name")
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
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
                                        <input type="number"
                                               name="suspects[{{ $idx }}][age]"
                                               min="0"
                                               max="120"
                                               value="{{ $suspect['age'] ?? '' }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                               placeholder="Umur">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- 4. Upload Dokumen Section --}}
                    <div id="step-documents" class="bg-gray-50 p-6 rounded-lg border border-gray-200 scroll-mt-24">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                            </svg>
                            Upload Dokumen
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="request_letter" class="block text-sm font-medium text-gray-700 mb-2">
                                    Surat Permintaan Pengujian <span class="text-red-500">*</span>
                                </label>

                            <div id="request_letter_dropzone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-blue-400 transition-colors duration-200">

                                <div class="space-y-1 text-center">

                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">

                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

                                    </svg>

                                    <div class="flex text-sm text-gray-600 justify-center">

                                        <label for="request_letter" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">

                                            <span>Upload file PDF</span>

                                            <input id="request_letter"

                                                   name="request_letter"

                                                   type="file"

                                                   class="sr-only"

                                                   accept=".pdf"

                                                   required

                                                   onchange="displayRequestLetterFileName(this)">

                                        </label>

                                        <p class="pl-1">atau drag and drop</p>

                                    </div>

                                    <p id="request_letter_filename" class="text-xs text-gray-500">PDF hingga 10MB</p>

                                </div>

                            </div>

                            @error('request_letter')

                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>

                            @enderror

                        </div>

                    </div>

                    <!-- 3. Daftar Sampel Section (Direvisi dengan Foto) -->

                    <div id="step-samples" class="bg-orange-50 p-6 rounded-lg border border-orange-200 scroll-mt-24">

                        <h3 class="text-lg font-semibold text-orange-900 mb-4 flex items-center">

                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">

                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>

                            </svg>

                            Daftar Sampel yang Diajukan

                        </h3>

                                                <div id="samples-container">

                            <!-- Sample Item Template -->

                            <div class="sample-item bg-white p-6 rounded-lg border border-gray-200 mb-4">

                                <div class="flex justify-between items-start mb-4">

                                    <h4 class="text-md font-medium text-gray-900">Sampel #1</h4>

                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                                    <!-- Deskripsi Singkat -->

                                    <div class="md:col-span-3">

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Deskripsi Singkat <span class="text-red-500">*</span>

                                        </label>

                                        <textarea

                                               name="samples[0][short_description]"

                                               required

                                               rows="2"

                                               maxlength="120"

                                               data-auto-resize="sample-short-description"

                                               class="w-full min-h-[44px] resize-y overflow-hidden px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"

                                               placeholder="Contoh: Tablet putih, logo tertentu, kondisi utuh, atau ciri visual singkat lainnya"></textarea>

                                        <p class="text-xs text-gray-500 mt-1">Gunakan ciri yang paling mudah dikenali. Kolom ini menyesuaikan tinggi otomatis saat Anda mengetik.</p>

                                    </div>

                                    <!-- Jumlah -->

                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Jumlah yang Diserahkan <span class="text-red-500">*</span>

                                        </label>

                                        <input type="number"

                                               name="samples[0][package_quantity]"

                                               required

                                               min="1"

                                               max="99999"

                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"

                                               placeholder="1">

                                    </div>

                                    <!-- Satuan -->

                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Satuan <span class="text-red-500">*</span>

                                        </label>

                                        <input type="text"

                                               name="samples[0][unit]"

                                               required

                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"

                                               placeholder="Contoh: tablet/kapsul/serbuk">

                                        <p class="text-xs text-gray-500 mt-1">Gunakan satuan yang konsisten untuk setiap sampel.</p>

                                    </div>

                                    <!-- Jenis Pengujian -->

                                    <div class="md:col-span-3">

                                        <fieldset>
                                            <legend class="block text-sm font-medium text-gray-700 mb-1">
                                                Jenis Pengujian <span class="text-red-500">*</span>
                                            </legend>

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sample-test-type-group" data-sample-index="0">

                                            <label class="flex items-center space-x-2 rounded-md border border-gray-200 px-3 py-2 bg-gray-50 hover:bg-gray-100">

                                                <input type="checkbox" name="samples[0][test_types][]" value="uv_vis" class="sample-test-type-checkbox">

                                                <span class="text-sm text-gray-700">Identifikasi Spektrofotometri UV-VIS</span>

                                            </label>

                                            <label class="flex items-center space-x-2 rounded-md border border-gray-200 px-3 py-2 bg-gray-50 hover:bg-gray-100">

                                                <input type="checkbox" name="samples[0][test_types][]" value="gc_ms" class="sample-test-type-checkbox">

                                                <span class="text-sm text-gray-700">Identifikasi GC-MS</span>

                                            </label>

                                            <label class="flex items-center space-x-2 rounded-md border border-gray-200 px-3 py-2 bg-gray-50 hover:bg-gray-100">

                                                <input type="checkbox" name="samples[0][test_types][]" value="lc_ms" class="sample-test-type-checkbox">

                                                <span class="text-sm text-gray-700">Identifikasi LC-MS</span>

                                            </label>

                                        </div>
                                        </fieldset>

                                        <p class="text-xs text-gray-500 mt-1">Pilih minimal satu jenis pengujian.</p>

                                    </div>

                                    <!-- Zat Aktif -->

                                    <div class="md:col-span-3">

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Zat Aktif <span class="text-red-500">*</span>

                                        </label>

                                        <input type="text"

                                               name="samples[0][active_substance]"

                                               required

                                               list="active_substance_list"

                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 active-substance-input"

                                               placeholder="Pilih atau ketik zat aktif baru..."

                                               autocomplete="off">

                                        <p class="text-xs text-gray-500 mt-1">Pilih dari daftar atau ketik zat aktif baru.</p>

                                    </div>

                                    <!-- Foto Sampel (BARU) -->

                                    <div class="md:col-span-3">

                                        <label class="block text-sm font-medium text-gray-700 mb-2">

                                            Foto Sampel <span class="text-gray-400 text-xs">(opsional)</span>

                                        </label>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                                            <!-- Upload Area -->

                                            <div class="md:col-span-2">

                                                <div class="border-2 border-gray-300 border-dashed rounded-md p-4 hover:border-blue-400 transition-colors duration-200">

                                                    <div class="text-center">

                                                        <svg class="mx-auto h-8 w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">

                                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

                                                        </svg>

                                                        <div class="mt-2">

                                                            <label for="sample_photos_0" class="cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500">

                                                                <span>Upload foto sampel</span>

                                                                <input id="sample_photos_0"

                                                                       name="samples[0][photos][]"

                                                                       type="file"

                                                                       class="sr-only"

                                                                       accept="image/*"

                                                                       multiple

                                                                       onchange="previewSampleImages(this, 0)">

                                                            </label>

                                                        </div>

                                                        <p class="text-xs text-gray-500 mt-1">JPG, PNG, max 5MB per file. Dapat unggah beberapa file sekaligus</p>

                                                    </div>

                                                </div>

                                            </div>

                                            <!-- Preview Area -->

                                            <div>

                                                <div id="sample_preview_0" class="grid grid-cols-2 gap-2 min-h-[100px] p-2 border border-gray-200 rounded-md bg-gray-50">

                                                    <div class="flex items-center justify-center text-gray-400 text-xs col-span-2">

                                                        Preview foto akan muncul di sini

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <button type="button"

                                id="add-sample"

                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">

                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">

                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>

                            </svg>

                            Tambah Sampel

                        </button>

                    </div>

                    <!-- Submit dan Cetak BA Button -->

                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">

                        <div class="flex space-x-3">
                            <button type="button"
                                    onclick="window.history.back()"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                Kembali
                            </button>
                            <a href="{{ route('dashboard') }}"
                               class="hidden sm:inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                Ke Dashboard
                            </a>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" name="action" value="save"
                                    :disabled="isSubmitting"
                                    :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                <template x-if="isSubmitting">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </template>
                                <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan'">Simpan</span>
                            </button>
                            <button type="submit" name="action" value="save_and_print"
                                    :disabled="isSubmitting"
                                    :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                <template x-if="isSubmitting">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </template>
                                <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan & Cetak BA'">Simpan & Cetak BA</span>
                            </button>
                        </div>
                    </div>

                </form>

            </div>

        </div>

    </div>

{{-- Datalist for Active Substances --}}
<datalist id="active_substance_list">
    @if(isset($existingActiveSubstances))
        @foreach($existingActiveSubstances as $substance)
            <option value="{{ $substance }}">
        @endforeach
    @endif
</datalist>

<script>
// Auto-fill existing investigator data
(function() {
    const existingInvestigatorSelect = document.getElementById('existing_investigator_select');
    if (existingInvestigatorSelect) {
        existingInvestigatorSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            
            if (this.value) {
                // Auto-fill from selected investigator
                document.getElementById('investigator_name').value = option.dataset.name || '';
                document.getElementById('investigator_nrp').value = option.dataset.nrp || '';
                document.getElementById('investigator_rank').value = option.dataset.rank || '';
                document.getElementById('investigator_jurisdiction').value = option.dataset.jurisdiction || '';
                document.getElementById('investigator_phone').value = option.dataset.phone || '';
                document.getElementById('investigator_address').value = option.dataset.address || '';
            } else {
                // Clear fields for new input
                document.getElementById('investigator_name').value = '';
                document.getElementById('investigator_nrp').value = '';
                document.getElementById('investigator_rank').value = '';
                document.getElementById('investigator_jurisdiction').value = '';
                document.getElementById('investigator_phone').value = '';
                document.getElementById('investigator_address').value = '';
            }
        });
    }

    // Auto-fill existing external (non-Polri) data
    const existingExternalSelect = document.getElementById('existing_external_select');
    if (existingExternalSelect) {
        existingExternalSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            
            if (this.value) {
                // Auto-fill from selected external
                document.getElementById('external_name').value = option.dataset.name || '';
                document.getElementById('external_institution').value = option.dataset.institution || '';
                document.getElementById('external_phone').value = option.dataset.altPhone || '';
                document.getElementById('external_hp').value = option.dataset.phone || '';
                document.getElementById('external_occupation').value = option.dataset.occupation || '';
            } else {
                // Clear fields for new input
                document.getElementById('external_name').value = '';
                document.getElementById('external_institution').value = '';
                document.getElementById('external_phone').value = '';
                document.getElementById('external_hp').value = '';
                document.getElementById('external_occupation').value = '';
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
        const fileSize = (file.size / (1024 * 1024)).toFixed(2); // Size in MB
        
        filenameDisplay.innerHTML = `<span class="text-green-600 font-medium">✓ ${file.name}</span> <span class="text-gray-500">(${fileSize} MB)</span>`;
        dropzone.classList.remove('border-gray-300');
        dropzone.classList.add('border-green-500', 'bg-green-50');
        
        console.warn('File selected:', file.name, 'Size:', fileSize, 'MB');
    } else {
        filenameDisplay.textContent = 'PDF hingga 10MB';
        dropzone.classList.remove('border-green-500', 'bg-green-50');
        dropzone.classList.add('border-gray-300');
    }
}

// Preview sample images
function previewSampleImages(input, sampleIndex) {
    const previewContainer = document.getElementById(`sample_preview_${sampleIndex}`);
    
    if (!previewContainer) {
        console.error('Preview container not found for sample index:', sampleIndex);
        return;
    }
    
    // Clear previous previews
    previewContainer.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imgWrapper = document.createElement('div');
                    imgWrapper.className = 'relative';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'h-20 w-20 object-cover rounded border border-gray-300';
                    img.alt = file.name;
                    
                    imgWrapper.appendChild(img);
                    previewContainer.appendChild(imgWrapper);
                };
                
                reader.readAsDataURL(file);
            }
        });
        
        console.warn(`Preview ${input.files.length} images for sample ${sampleIndex}`);
    } else {
        previewContainer.innerHTML = '<div class="flex items-center justify-center text-gray-400 text-xs col-span-2">Preview foto akan muncul di sini</div>';
    }
}

function autoResizeTextarea(textarea) {
    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
}

function bindSampleShortDescriptionAutoResize(scope = document) {
    const textareas = scope.querySelectorAll('textarea[data-auto-resize="sample-short-description"]');

    textareas.forEach(textarea => {
        if (textarea.dataset.autoResizeBound === 'true') {
            autoResizeTextarea(textarea);
            return;
        }

        textarea.dataset.autoResizeBound = 'true';
        textarea.addEventListener('input', () => autoResizeTextarea(textarea));
        autoResizeTextarea(textarea);
    });
}

// Drag and drop support for request letter
(function() {
    const dropzone = document.getElementById('request_letter_dropzone');
    const fileInput = document.getElementById('request_letter');
    
    if (!dropzone || !fileInput) return;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
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

// Sample management
let sampleIndex = 1;

document.getElementById('add-sample').addEventListener('click', function() {
    const container = document.getElementById('samples-container');
    const firstSample = container.querySelector('.sample-item');
    
    if (!firstSample) {
        console.error('Sample template not found');
        return;
    }
    
    // Clone the first sample
    const newSample = firstSample.cloneNode(true);
    
    // Update the sample number in the header
    const header = newSample.querySelector('h4');
    header.textContent = `Sampel #${sampleIndex + 1}`;
    
    // Add remove button if this is not the first sample
    const headerContainer = newSample.querySelector('.flex.justify-between');
    if (!headerContainer.querySelector('.remove-sample')) {
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-sample text-red-600 hover:text-red-800 text-sm font-medium';
        removeBtn.innerHTML = '✕ Hapus';
        removeBtn.onclick = function() {
            newSample.remove();
            updateSampleNumbers();
        };
        headerContainer.appendChild(removeBtn);
    }
    
    // Update all input names and IDs with new index
    const inputs = newSample.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        const oldId = input.id;
        
        // Update name attribute
        if (input.name) {
            input.name = input.name.replace(/\[0\]/g, `[${sampleIndex}]`);
        }
        
        // Update ID attribute
        if (input.id) {
            input.id = input.id.replace(/_0($|_)/, `_${sampleIndex}$1`);
        }
        
        // Clear values except for default values
        if (input.type === 'text' || input.type === 'number') {
            input.value = '';
        } else if (input.tagName === 'TEXTAREA') {
            input.value = '';
        } else if (input.type === 'checkbox') {
            input.checked = false;
        } else if (input.type === 'file') {
            input.value = '';
        }
        
        // Update onchange handlers
        if (input.onchange) {
            const onchangeStr = input.onchange.toString();
            if (onchangeStr.includes('previewSampleImages')) {
                input.setAttribute('onchange', `previewSampleImages(this, ${sampleIndex})`);
            }
        }
    });
    
    // Update all label 'for' attributes
    const labels = newSample.querySelectorAll('label[for]');
    labels.forEach(label => {
        if (label.htmlFor) {
            label.htmlFor = label.htmlFor.replace(/_0($|_)/, `_${sampleIndex}$1`);
        }
    });
    
    // Update preview container ID
    const previewContainer = newSample.querySelector('[id^="sample_preview_"]');
    if (previewContainer) {
        previewContainer.id = `sample_preview_${sampleIndex}`;
        previewContainer.innerHTML = '<div class="flex items-center justify-center text-gray-400 text-xs col-span-2">Preview foto akan muncul di sini</div>';
    }
    
    // Update test type group data attribute
    const testTypeGroup = newSample.querySelector('.sample-test-type-group');
    if (testTypeGroup) {
        testTypeGroup.setAttribute('data-sample-index', sampleIndex);
    }
    
    // Append to container
    container.appendChild(newSample);
    bindSampleShortDescriptionAutoResize(newSample);
    
    sampleIndex++;
    
    console.warn('Added new sample with index:', sampleIndex - 1);
});

function updateSampleNumbers() {
    const samples = document.querySelectorAll('.sample-item');
    samples.forEach((sample, idx) => {
        const header = sample.querySelector('h4');
        if (header) {
            header.textContent = `Sampel #${idx + 1}`;
        }
    });
}

// Add remove button to first sample if there are multiple samples
function checkRemoveButtons() {
    const samples = document.querySelectorAll('.sample-item');
    if (samples.length > 1) {
        samples.forEach(sample => {
            const headerContainer = sample.querySelector('.flex.justify-between');
            if (!headerContainer.querySelector('.remove-sample')) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-sample text-red-600 hover:text-red-800 text-sm font-medium';
                removeBtn.innerHTML = '✕ Hapus';
                removeBtn.onclick = function() {
                    if (document.querySelectorAll('.sample-item').length > 1) {
                        sample.remove();
                        updateSampleNumbers();
                        checkRemoveButtons();
                    } else {
                        alert('Minimal harus ada satu sampel');
                    }
                };
                headerContainer.appendChild(removeBtn);
            }
        });
    } else if (samples.length === 1) {
        // Remove the remove button if only one sample left
        const removeBtn = samples[0].querySelector('.remove-sample');
        if (removeBtn) {
            removeBtn.remove();
        }
    }
}

bindSampleShortDescriptionAutoResize();
</script>

@vite('resources/js/pages/requests-form.js')

</x-app-layout>
