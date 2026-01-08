<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Koreksi Data Monitoring"
            :breadcrumbs="[['label' => 'Monitoring'], ['label' => 'Lingkungan', 'url' => route('monitoring.environment.index')], ['label' => 'Koreksi']]"
            description="Koreksi data pembacaan suhu/kelembaban"
        />
    </x-slot>

    <div x-data="correctionForm()" class="max-w-3xl mx-auto sm:px-6 lg:px-8 py-6">

        <div class="mb-6">
            <a href="{{ route('monitoring.environment.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Kembali ke Monitoring
            </a>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Data Asli</h2>
            </div>

            <div class="px-6 py-4">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Lokasi</dt>
                        <dd class="font-medium text-gray-900">{{ $location->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tipe</dt>
                        <dd class="font-medium text-gray-900 capitalize">{{ $location->type->value }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Waktu Ukur</dt>
                        <dd class="font-medium text-gray-900">{{ $reading->measured_at->format('d M Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Diinput Oleh</dt>
                        <dd class="font-medium text-gray-900">{{ $reading->enteredBy?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Suhu</dt>
                        <dd class="font-medium text-gray-900">{{ $reading->temperature_c }}°C</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Kelembaban</dt>
                        <dd class="font-medium text-gray-900">{{ $reading->humidity_rh !== null ? $reading->humidity_rh . '%' : '-' }}</dd>
                    </div>
                    @if($reading->notes)
                    <div class="col-span-2">
                        <dt class="text-gray-500">Catatan</dt>
                        <dd class="font-medium text-gray-900">{{ $reading->notes }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="mt-6 bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Data Koreksi</h2>
                <p class="text-sm text-gray-500 mt-1">Isi data yang benar beserta alasan koreksi.</p>
            </div>

            <form @submit.prevent="submitCorrection()" class="px-6 py-4">
                <div x-show="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700" x-text="error"></div>
                <div x-show="success" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700" x-text="success"></div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Suhu (°C) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.1" x-model="form.temperature_c" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Contoh: 25.5">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kelembaban (%RH)</label>
                            <input type="number" step="0.1" x-model="form.humidity_rh" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Contoh: 65">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea x-model="form.notes" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Opsional"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Koreksi <span class="text-red-500">*</span></label>
                        <textarea x-model="form.correction_reason" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Jelaskan alasan mengapa data perlu dikoreksi"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('monitoring.environment.index') }}" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Batal
                    </a>
                    <button type="submit" 
                            :disabled="loading"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="loading ? 'Menyimpan...' : 'Simpan Koreksi'"></span>
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-sm text-yellow-800">
                    <p class="font-medium">Informasi Penting</p>
                    <p class="mt-1">Koreksi akan membuat record baru yang menggantikan data asli. Data asli tetap tersimpan untuk keperluan audit trail (ISO 17025).</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function correctionForm() {
            return {
                loading: false,
                error: '',
                success: '',
                form: {
                    temperature_c: @json($reading->temperature_c),
                    humidity_rh: @json($reading->humidity_rh ?? ''),
                    notes: @json($reading->notes ?? ''),
                    correction_reason: '',
                },

                async submitCorrection() {
                    if (!this.form.temperature_c) {
                        this.error = 'Suhu wajib diisi.';
                        return;
                    }
                    if (!this.form.correction_reason) {
                        this.error = 'Alasan koreksi wajib diisi.';
                        return;
                    }

                    this.loading = true;
                    this.error = '';
                    this.success = '';

                    try {
                        const response = await fetch('{{ route("monitoring.environment.readings.correction.store", $reading->id) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({
                                temperature_c: parseFloat(this.form.temperature_c),
                                humidity_rh: this.form.humidity_rh ? parseFloat(this.form.humidity_rh) : null,
                                notes: this.form.notes || null,
                                correction_reason: this.form.correction_reason,
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            if (data.errors) {
                                this.error = Object.values(data.errors).flat().join(' ');
                            } else {
                                this.error = data.message || 'Gagal menyimpan koreksi.';
                            }
                            return;
                        }

                        this.success = 'Koreksi berhasil disimpan.';
                        
                        setTimeout(() => {
                            window.location.href = '{{ route("monitoring.environment.index") }}';
                        }, 1500);

                    } catch (error) {
                        console.error('Submit error:', error);
                        this.error = 'Terjadi kesalahan. Silakan coba lagi.';
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>
