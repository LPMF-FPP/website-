<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Monitoring Lingkungan"
            :breadcrumbs="[['label' => 'Monitoring'], ['label' => 'Lingkungan']]"
            description="Pencatatan suhu dan kelembaban untuk ISO 17025/QMS"
        />
    </x-slot>

    <div x-data="environmentMonitoring()" x-init="init()" class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6">
        
        @if(!$settings['enabled'])
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <p class="text-sm text-yellow-800">
                    Monitoring lingkungan belum diaktifkan. <a href="{{ route('settings.index') }}" class="font-medium underline hover:text-yellow-900">Aktifkan di Settings</a>
                </p>
            </div>
        </div>
        @endif

        @if(!$isWorkDay)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm text-blue-800">
                    Hari ini bukan hari kerja. Pencatatan monitoring tidak diperlukan.
                </p>
            </div>
        </div>
        @endif

        @if($activeWindow)
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm text-green-800">
                        <strong>Window Aktif:</strong> {{ $activeWindow['label'] }} ({{ $activeWindow['start'] }} - {{ $activeWindow['end'] }})
                    </p>
                </div>
                <span class="text-xs text-green-600">{{ $today->format('d M Y H:i') }}</span>
            </div>
        </div>
        @endif

        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-semibold text-gray-900">Daftar Lokasi</h2>
            @can('manage-settings')
            <a href="{{ route('monitoring.environment.manage') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Kelola Lokasi
            </a>
            @endcan
        </div>

        @if($locations->isEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <p class="text-gray-600 mb-4">Belum ada lokasi monitoring yang dikonfigurasi.</p>
            @can('manage-settings')
            <a href="{{ route('monitoring.environment.manage') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                Tambah Lokasi
            </a>
            @endcan
        </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($locations as $item)
            @php
                $location = $item['location'];
                $status = $item['status'];
                $statusColors = [
                    'complete' => 'bg-green-50 border-green-200',
                    'due' => 'bg-yellow-50 border-yellow-200',
                    'overdue' => 'bg-red-50 border-red-200',
                    'pending' => 'bg-gray-50 border-gray-200',
                ];
                $statusLabels = [
                    'complete' => 'Lengkap',
                    'due' => 'Perlu Input',
                    'overdue' => 'Terlambat',
                    'pending' => 'Menunggu',
                ];
                $statusBadgeColors = [
                    'complete' => 'bg-green-100 text-green-800',
                    'due' => 'bg-yellow-100 text-yellow-800',
                    'overdue' => 'bg-red-100 text-red-800',
                    'pending' => 'bg-gray-100 text-gray-800',
                ];
            @endphp
            <div class="rounded-lg border p-4 {{ $statusColors[$status] ?? 'bg-white border-gray-200' }}">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="font-medium text-gray-900">{{ $location->name }}</h3>
                        <p class="text-xs text-gray-500 capitalize">{{ $location->type->value }}</p>
                    </div>
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $statusBadgeColors[$status] ?? '' }}">
                        {{ $statusLabels[$status] ?? $status }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full {{ $item['morning_filled'] ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                        <span class="text-gray-600">Pagi</span>
                        @if($item['morning_reading'])
                        <span class="text-xs text-gray-500">{{ $item['morning_reading']->temperature_c }}°C</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full {{ $item['afternoon_filled'] ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                        <span class="text-gray-600">Siang</span>
                        @if($item['afternoon_reading'])
                        <span class="text-xs text-gray-500">{{ $item['afternoon_reading']->temperature_c }}°C</span>
                        @endif
                    </div>
                </div>

                @if($location->target_temp_min !== null || $location->target_temp_max !== null)
                <div class="text-xs text-gray-500 mb-3">
                    Target: 
                    @if($location->target_temp_min !== null && $location->target_temp_max !== null)
                        {{ $location->target_temp_min }}°C - {{ $location->target_temp_max }}°C
                    @elseif($location->target_temp_min !== null)
                        Min {{ $location->target_temp_min }}°C
                    @else
                        Max {{ $location->target_temp_max }}°C
                    @endif
                </div>
                @endif

                <button 
                    @click="openInputModal({{ $location->id }}, '{{ $location->name }}')"
                    class="w-full px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition"
                    :disabled="loading"
                >
                    Input Data
                </button>
            </div>
            @endforeach
        </div>
        @endif

        <div x-show="inputModal.open" x-cloak 
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-dvh pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="inputModal.open" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                      class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                      @click="closeInputModal()"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-dvh" aria-hidden="true">&#8203;</span>

                <div x-show="inputModal.open"

                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form @submit.prevent="submitReading()">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="'Input Data: ' + inputModal.locationName"></h3>
                            
                            <div x-show="inputModal.error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700" x-text="inputModal.error"></div>
                            <div x-show="inputModal.success" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700" x-text="inputModal.success"></div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Suhu (°C) <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.1" x-model.number="inputModal.form.temperature_c" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Contoh: 25.5">
                                </div>

                                @if($settings['humidity_enabled'])
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kelembaban (%RH)</label>
                                    <input type="number" step="0.1" x-model.number="inputModal.form.humidity_rh" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Contoh: 65">
                                </div>
                                @endif

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                                    <textarea x-model.lazy="inputModal.form.notes" rows="2"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                              placeholder="Opsional"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <button type="submit" 
                                    :disabled="inputModal.loading"
                                    class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm disabled:opacity-50">
                                <svg x-show="inputModal.loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="inputModal.loading ? 'Menyimpan...' : 'Simpan'"></span>
                            </button>
                            <button type="button" @click="closeInputModal()"
                                    class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function environmentMonitoring() {
            return {
                loading: false,
                inputModal: {
                    open: false,
                    locationId: null,
                    locationName: '',
                    loading: false,
                    error: '',
                    success: '',
                    form: {
                        temperature_c: '',
                        humidity_rh: '',
                        notes: '',
                    }
                },

                init() {
                    console.log('Environment monitoring initialized');
                },

                openInputModal(locationId, locationName) {
                    this.inputModal.open = true;
                    this.inputModal.locationId = locationId;
                    this.inputModal.locationName = locationName;
                    this.inputModal.error = '';
                    this.inputModal.success = '';
                    this.inputModal.form = {
                        temperature_c: '',
                        humidity_rh: '',
                        notes: '',
                    };
                },

                closeInputModal() {
                    this.inputModal.open = false;
                    this.inputModal.locationId = null;
                    this.inputModal.locationName = '';
                },

                async submitReading() {
                    if (!this.inputModal.form.temperature_c) {
                        this.inputModal.error = 'Suhu wajib diisi.';
                        return;
                    }

                    this.inputModal.loading = true;
                    this.inputModal.error = '';
                    this.inputModal.success = '';

                    try {
                        const response = await fetch('{{ route("monitoring.environment.readings.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({
                                location_id: this.inputModal.locationId,
                                temperature_c: parseFloat(this.inputModal.form.temperature_c),
                                humidity_rh: this.inputModal.form.humidity_rh ? parseFloat(this.inputModal.form.humidity_rh) : null,
                                notes: this.inputModal.form.notes || null,
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            if (data.errors) {
                                this.inputModal.error = Object.values(data.errors).flat().join(' ');
                            } else {
                                this.inputModal.error = data.message || 'Gagal menyimpan data.';
                            }
                            return;
                        }

                        this.inputModal.success = 'Data berhasil disimpan.';
                        
                        if (data.out_of_range && data.out_of_range.messages && data.out_of_range.messages.length > 0) {
                            this.inputModal.success += ' Peringatan: ' + data.out_of_range.messages.join(' ');
                        }

                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);

                    } catch (error) {
                        console.error('Submit error:', error);
                        this.inputModal.error = 'Terjadi kesalahan. Silakan coba lagi.';
                    } finally {
                        this.inputModal.loading = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>
