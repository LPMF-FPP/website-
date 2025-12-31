<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Edit Permintaan #' . $request->request_number"
            :breadcrumbs="[[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Edit' ]]"
        />
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                    <p class="font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <p class="font-semibold">{{ session('error') }}</p>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('requests.update', $request) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        {{-- Info Warning --}}
                        <div class="bg-yellow-50 border border-yellow-200 text-yellow-900 px-4 py-3 rounded">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <p class="text-sm">
                                    Setelah edit, Berita Acara Penerimaan perlu di-generate ulang dengan data terbaru.
                                </p>
                            </div>
                        </div>

                        {{-- Section 1: Info Surat --}}
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">
                                Informasi Surat
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {{-- Nomor Surat --}}
                                <div>
                                    <label for="case_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        Nomor Surat Permintaan Pengujian
                                    </label>
                     <input type="text"
                         name="case_number"
                         id="case_number"
                         value="{{ old('case_number', $request->case_number) }}"
                         class="@class(['w-full px-3 py-2 border rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500', $errors->has('case_number') ? 'border-red-500' : 'border-gray-300'])"
                         placeholder="Contoh: S/123/IV/2025/RESKRIM">
                                    @error('case_number')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Ditujukan Kepada --}}
                                <div>
                                    <label for="to_office" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ditujukan Kepada <span class="text-red-500">*</span>
                                    </label>
                     <input type="text"
                         name="to_office"
                         id="to_office"
                         required
                         value="{{ old('to_office', $request->to_office) }}"
                         class="@class(['w-full px-3 py-2 border rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500', $errors->has('to_office') ? 'border-red-500' : 'border-gray-300'])">
                                    @error('to_office')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Is Investigator Toggle --}}
                        @php
                            $isPolri = $request->investigator->is_polri ?? true;
                        @endphp
                        <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                            <h4 class="text-md font-semibold text-indigo-900 mb-3">Apakah Anda penyidik?</h4>
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
                        <div class="block-investigator space-y-4" style="{{ $isPolri ? '' : 'display: none;' }}">
                            <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Informasi Penyidik</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="investigator_rank" class="block text-sm font-medium text-gray-700 mb-2">Pangkat</label>
                                    <input type="text" name="investigator_rank" id="investigator_rank"
                                           value="{{ old('investigator_rank', $request->investigator->rank) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="investigator_name" class="block text-sm font-medium text-gray-700 mb-2">Nama</label>
                                    <input type="text" name="investigator_name" id="investigator_name"
                                           value="{{ old('investigator_name', $request->investigator->name) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="investigator_nrp" class="block text-sm font-medium text-gray-700 mb-2">NRP</label>
                                    <input type="text" name="investigator_nrp" id="investigator_nrp"
                                           value="{{ old('investigator_nrp', $request->investigator->nrp) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="investigator_jurisdiction" class="block text-sm font-medium text-gray-700 mb-2">Satuan</label>
                                    <input type="text" name="investigator_jurisdiction" id="investigator_jurisdiction"
                                           value="{{ old('investigator_jurisdiction', $request->investigator->jurisdiction) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="investigator_phone" class="block text-sm font-medium text-gray-700 mb-2">No. HP</label>
                                    <input type="text" name="investigator_phone" id="investigator_phone"
                                           value="{{ old('investigator_phone', $request->investigator->phone) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>

                        {{-- External Section (Non-Polri) --}}
                        <div class="block-external space-y-4" style="{{ $isPolri ? 'display: none;' : '' }}">
                            <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Informasi Pemohon</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="external_name" class="block text-sm font-medium text-gray-700 mb-2">Nama <span class="text-red-500">*</span></label>
                                    <input type="text" name="external_name" id="external_name"
                                           value="{{ old('external_name', !$isPolri ? $request->investigator->name : '') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="external_phone" class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon <span class="text-red-500">*</span></label>
                                    <input type="tel" name="external_phone" id="external_phone"
                                           value="{{ old('external_phone', !$isPolri ? $request->investigator->alt_phone : '') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="external_institution" class="block text-sm font-medium text-gray-700 mb-2">Instansi <span class="text-red-500">*</span></label>
                                    <input type="text" name="external_institution" id="external_institution"
                                           value="{{ old('external_institution', !$isPolri ? $request->investigator->institution : '') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="external_hp" class="block text-sm font-medium text-gray-700 mb-2">Nomor HP <span class="text-red-500">*</span></label>
                                    <input type="tel" name="external_hp" id="external_hp"
                                           value="{{ old('external_hp', !$isPolri ? $request->investigator->phone : '') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="external_occupation" class="block text-sm font-medium text-gray-700 mb-2">Pekerjaan <span class="text-red-500">*</span></label>
                                    <input type="text" name="external_occupation" id="external_occupation"
                                           value="{{ old('external_occupation', !$isPolri ? $request->investigator->occupation : '') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>

                        {{-- Multi-Suspect Section --}}
                        <div class="space-y-4">
                            <div class="flex justify-between items-center border-b pb-2">
                                <h3 class="text-lg font-semibold text-gray-900">Informasi Tersangka</h3>
                                <button type="button" id="add-suspect"
                                        class="inline-flex items-center px-3 py-1.5 border border-yellow-600 text-sm font-medium rounded-md text-yellow-700 bg-yellow-100 hover:bg-yellow-200">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    Tambah Tersangka
                                </button>
                            </div>
                            <div id="suspects-container">
                                @php
                                    $suspects = old('suspects') 
                                        ?? ($request->suspects->count() > 0 
                                            ? $request->suspects->map(fn($s) => ['name' => $s->name, 'gender' => $s->gender, 'age' => $s->age])->toArray() 
                                            : [['name' => $request->suspect_name, 'gender' => $request->suspect_gender, 'age' => $request->suspect_age]]);
                                @endphp
                                @foreach($suspects as $idx => $suspect)
                                <div class="suspect-row bg-gray-50 p-4 rounded-lg border border-gray-200 mb-3" data-index="{{ $idx }}">
                                    <div class="flex justify-between items-start mb-3">
                                        <h5 class="font-medium text-gray-800">Tersangka #{{ $idx + 1 }}</h5>
                                        @if($idx > 0)
                                        <button type="button" class="remove-suspect text-red-600 hover:text-red-800 text-sm font-medium">✕ Hapus</button>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Tersangka <span class="text-red-500">*</span></label>
                                            <input type="text" name="suspects[{{ $idx }}][name]" required
                                                   value="{{ $suspect['name'] ?? '' }}"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin</label>
                                            <select name="suspects[{{ $idx }}][gender]"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">- Pilih -</option>
                                                <option value="male" {{ ($suspect['gender'] ?? '') == 'male' ? 'selected' : '' }}>Laki-laki</option>
                                                <option value="female" {{ ($suspect['gender'] ?? '') == 'female' ? 'selected' : '' }}>Perempuan</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Umur (tahun)</label>
                                            <input type="number" name="suspects[{{ $idx }}][age]" min="0" max="120"
                                                   value="{{ $suspect['age'] ?? '' }}"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div>
                                <label for="suspect_address" class="block text-sm font-medium text-gray-700 mb-2">Alamat Tersangka</label>
                                <textarea name="suspect_address" id="suspect_address" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('suspect_address', $request->suspect_address) }}</textarea>
                            </div>
                        </div>

                        {{-- Info Sampel --}}
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">
                                Daftar Sampel
                            </h3>

                            <div class="space-y-4" id="samples-container">
                                @foreach($request->samples as $index => $sample)
                                    <div class="border border-gray-300 rounded-lg p-4 sample-item" data-index="{{ $index }}">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-medium text-gray-900">Sampel {{ $index + 1 }}</h4>
                                            <button type="button" class="text-red-600 hover:text-red-800 text-sm font-medium remove-sample">
                                                Hapus
                                            </button>
                                        </div>

                                        <input type="hidden" name="samples[{{ $index }}][id]" value="{{ $sample->id }}">

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {{-- Deskripsi Singkat --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Deskripsi Singkat
                                                </label>
                                                <input type="text"
                                                       name="samples[{{ $index }}][short_description]"
                                                       value="{{ old('samples.'.$index.'.short_description', $sample->short_description) }}"
                                                       required
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            </div>

                                            {{-- Zat Aktif --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Zat Aktif
                                                </label>
                                                <input type="text"
                                                       name="samples[{{ $index }}][active_substance]"
                                                       value="{{ old('samples.'.$index.'.active_substance', $sample->active_substance) }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            </div>

                                            {{-- Jumlah yang Diserahkan --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Jumlah yang Diserahkan
                                                </label>
                                                <input type="number"
                                                       name="samples[{{ $index }}][package_quantity]"
                                                       value="{{ old('samples.'.$index.'.package_quantity', $sample->package_quantity) }}"
                                                       required
                                                       min="0"
                                                       step="0.01"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            </div>

                                            {{-- Satuan --}}
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Satuan
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

                            <button type="button" id="add-sample" class="mt-3 px-4 py-2 border border-blue-600 rounded-md shadow-sm text-sm font-medium text-blue-600 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                + Tambah Sampel
                            </button>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            let sampleIndex = {{ $request->samples->count() }};

                            document.getElementById('add-sample').addEventListener('click', function() {
                                const container = document.getElementById('samples-container');
                                const newSample = document.createElement('div');
                                newSample.className = 'border border-gray-300 rounded-lg p-4 sample-item';
                                newSample.dataset.index = sampleIndex;
                                newSample.innerHTML = `
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="font-medium text-gray-900">Sampel ${sampleIndex + 1}</h4>
                                        <button type="button" class="text-red-600 hover:text-red-800 text-sm font-medium remove-sample">
                                            Hapus
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Deskripsi Singkat
                                            </label>
                                            <input type="text"
                                                   name="samples[${sampleIndex}][short_description]"
                                                   required
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Zat Aktif
                                            </label>
                                            <input type="text"
                                                   name="samples[${sampleIndex}][active_substance]"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Jumlah yang Diserahkan
                                            </label>
                                            <input type="number"
                                                   name="samples[${sampleIndex}][package_quantity]"
                                                   required
                                                   min="0"
                                                   step="0.01"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Satuan
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

                        {{-- Action Buttons --}}
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                            <a href="{{ route('requests.show', $request) }}"
                               class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Batal
                            </a>
                            <button type="submit"
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@vite('resources/js/pages/requests-form.js')

</x-app-layout>
