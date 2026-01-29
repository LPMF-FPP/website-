<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Kelola Lokasi Monitoring"
            :breadcrumbs="[['label' => 'Monitoring'], ['label' => 'Lingkungan', 'href' => route('monitoring.environment.index')], ['label' => 'Kelola Lokasi']]"
            description="Tambah dan edit lokasi untuk pencatatan suhu/kelembaban"
        />
    </x-slot>

    <div x-data="locationManager()" x-init="init()" class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6">

        <div class="flex justify-between items-center mb-6">
            <a href="{{ route('monitoring.environment.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Kembali ke Monitoring
            </a>
            <button @click="openAddModal()" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tambah Lokasi
            </button>
        </div>

        <div x-show="globalMessage.text" x-transition
             :class="globalMessage.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
             class="mb-6 p-4 border rounded-lg text-sm">
            <span x-text="globalMessage.text"></span>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 text-balance">Daftar Lokasi</h2>
            </div>

            <template x-if="loading">
                <div class="px-6 py-8 text-center text-gray-500">
                    <svg class="animate-spin h-6 w-6 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span role="status">Memuat data...</span>
                </div>
            </template>

            <template x-if="!loading && locations.length === 0">
                <div class="px-6 py-8 text-center text-gray-500">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <p>Belum ada lokasi. Klik "Tambah Lokasi" untuk menambahkan.</p>
                </div>
            </template>

            <template x-if="!loading && locations.length > 0">
                <div class="divide-y divide-gray-200">
                    <template x-for="location in locations" :key="location.id">
                        <div class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-sm font-medium text-pd-text" x-text="location.name"></h3>
                                        <span x-show="location.is_active" class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">Aktif</span>
                                        <span x-show="!location.is_active" class="px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">Nonaktif</span>
                                    </div>
                                    <div class="mt-1 text-sm text-pd-muted flex flex-wrap gap-x-4 gap-y-1">
                                        <span class="capitalize" x-text="'Tipe: ' + location.type"></span>
                                        <template x-if="location.target_temp_min !== null || location.target_temp_max !== null">
                                            <span>
                                                Suhu:
                                                <template x-if="location.target_temp_min !== null && location.target_temp_max !== null">
                                                    <span x-text="location.target_temp_min + '°C - ' + location.target_temp_max + '°C'"></span>
                                                </template>
                                                <template x-if="location.target_temp_min !== null && location.target_temp_max === null">
                                                    <span x-text="'Min ' + location.target_temp_min + '°C'"></span>
                                                </template>
                                                <template x-if="location.target_temp_min === null && location.target_temp_max !== null">
                                                    <span x-text="'Max ' + location.target_temp_max + '°C'"></span>
                                                </template>
                                            </span>
                                        </template>
                                        <template x-if="location.target_humidity_min !== null || location.target_humidity_max !== null">
                                            <span>
                                                Kelembaban:
                                                <template x-if="location.target_humidity_min !== null && location.target_humidity_max !== null">
                                                    <span x-text="location.target_humidity_min + '% - ' + location.target_humidity_max + '%'"></span>
                                                </template>
                                                <template x-if="location.target_humidity_min !== null && location.target_humidity_max === null">
                                                    <span x-text="'Min ' + location.target_humidity_min + '%'"></span>
                                                </template>
                                                <template x-if="location.target_humidity_min === null && location.target_humidity_max !== null">
                                                    <span x-text="'Max ' + location.target_humidity_max + '%'"></span>
                                                </template>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="openEditModal(location)" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <button @click="toggleActive(location)" 
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            :class="location.is_active ? 'text-gray-700' : 'text-green-700'">
                                        <template x-if="location.is_active">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-1.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Nonaktifkan
                                            </div>
                                        </template>
                                        <template x-if="!location.is_active">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-1.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Aktifkan
                                            </div>
                                        </template>
                                    </button>
                                    <button @click="confirmDelete(location)" 
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-lg shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                            :disabled="location.has_readings"
                                            :class="{'opacity-50 cursor-not-allowed': location.has_readings}">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div x-show="modal.open" x-cloak 
             x-trap.noscroll.inert="modal.open"
             @keydown.escape.window="if (modal.open) closeModal()"
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="location-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-dvh pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="modal.open" 
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                     @click="closeModal()"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-dvh" aria-hidden="true">&#8203;</span>

                <div x-show="modal.open"
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form @submit.prevent="submitForm()">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 id="location-modal-title" class="text-lg font-medium text-pd-text mb-4 text-balance" x-text="modal.isEdit ? 'Edit Lokasi' : 'Tambah Lokasi'"></h3>
                            
                            <div x-show="modal.error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700" x-text="modal.error"></div>

                            <div class="space-y-4">
                                <div>
                                    <label for="location-name" class="block text-sm font-medium text-pd-body mb-1">Nama Lokasi <span class="text-red-500">*</span></label>
                                    <input type="text" id="location-name" x-model="modal.form.name" 
                                           class="form-input"
                                           placeholder="Contoh: Ruang Lab Kimia">
                                </div>

                                <div>
                                    <label for="location-type" class="block text-sm font-medium text-pd-body mb-1">Tipe <span class="text-red-500">*</span></label>
                                    <select id="location-type" x-model="modal.form.type" 
                                            class="form-input">
                                        <option value="">Pilih tipe...</option>
                                        <option value="room">Room (Ruangan)</option>
                                        <option value="fridge">Fridge (Kulkas)</option>
                                        <option value="freezer">Freezer</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="temp-min" class="block text-sm font-medium text-pd-body mb-1">Suhu Min (°C)</label>
                                        <input type="number" id="temp-min" step="0.1" x-model.number="modal.form.target_temp_min" 
                                               class="form-input"
                                               placeholder="Contoh: 18">
                                    </div>
                                    <div>
                                        <label for="temp-max" class="block text-sm font-medium text-pd-body mb-1">Suhu Max (°C)</label>
                                        <input type="number" id="temp-max" step="0.1" x-model.number="modal.form.target_temp_max" 
                                               class="form-input"
                                               placeholder="Contoh: 25">
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-4">
                                    <div class="flex items-center gap-2 mb-3">
                                        <input type="checkbox" id="humidity_enabled" x-model="modal.form.humidity_enabled" 
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="humidity_enabled" class="text-sm font-medium text-pd-body">Monitor Kelembaban?</label>
                                    </div>

                                    <div x-show="modal.form.humidity_enabled" x-transition class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="humidity-min" class="block text-sm font-medium text-pd-body mb-1">Kelembaban Min (%)</label>
                                            <input type="number" id="humidity-min" step="0.1" x-model.number="modal.form.target_humidity_min" 
                                                   class="form-input"
                                                   placeholder="Contoh: 40">
                                        </div>
                                        <div>
                                            <label for="humidity-max" class="block text-sm font-medium text-pd-body mb-1">Kelembaban Max (%)</label>
                                            <input type="number" id="humidity-max" step="0.1" x-model.number="modal.form.target_humidity_max" 
                                                   class="form-input"
                                                   placeholder="Contoh: 60">
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 border-t border-gray-200 pt-4">
                                    <input type="checkbox" id="is_active" x-model="modal.form.is_active" 
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="is_active" class="text-sm text-pd-body">Lokasi aktif</label>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <button type="submit" 
                                    :disabled="modal.loading"
                                    class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm disabled:opacity-50">
                                <svg x-show="modal.loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="modal.loading ? 'Menyimpan...' : 'Simpan'"></span>
                            </button>
                            <button type="button" @click="closeModal()"
                                    class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="deleteModal.open" x-cloak 
             x-trap.noscroll.inert="deleteModal.open"
             @keydown.escape.window="if (deleteModal.open) closeDeleteModal()"
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="delete-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-dvh pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="deleteModal.open" 
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                     @click="closeDeleteModal()"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-dvh" aria-hidden="true">&#8203;</span>

                <div x-show="deleteModal.open"
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-pd-text" id="delete-modal-title">Hapus Lokasi</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-pd-muted">
                                        Apakah Anda yakin ingin menghapus lokasi <strong x-text="deleteModal.location?.name"></strong>? Tindakan ini tidak dapat dibatalkan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="button" @click="executeDelete()"
                                :disabled="deleteModal.loading"
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto sm:text-sm disabled:opacity-50">
                            <span x-text="deleteModal.loading ? 'Menghapus...' : 'Hapus'"></span>
                        </button>
                        <button type="button" @click="closeDeleteModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function locationManager() {
            return {
                loading: true,
                locations: [],
                globalMessage: { text: '', type: '' },
                modal: {
                    open: false,
                    isEdit: false,
                    editId: null,
                    loading: false,
                    error: '',
                    form: {
                        name: '',
                        type: '',
                        target_temp_min: '',
                        target_temp_max: '',
                        target_humidity_min: '',
                        target_humidity_max: '',
                        is_active: true,
                    }
                },
                deleteModal: {
                    open: false,
                    loading: false,
                    location: null,
                },

                init() {
                    this.loadLocations();
                },

                async loadLocations() {
                    this.loading = true;
                    try {
                        const response = await fetch('{{ route("monitoring.environment.locations.index") }}', {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                        });
                        const data = await response.json();
                        if (data.ok) {
                            this.locations = data.locations || [];
                        }
                    } catch (error) {
                        console.error('Load locations error:', error);
                        this.showGlobalMessage('Gagal memuat data lokasi.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                showGlobalMessage(text, type) {
                    this.globalMessage = { text, type };
                    setTimeout(() => {
                        this.globalMessage = { text: '', type: '' };
                    }, 5000);
                },

                openAddModal() {
                    this.modal.isEdit = false;
                    this.modal.editId = null;
                    this.modal.error = '';
                    this.modal.form = {
                        name: '',
                        type: '',
                        target_temp_min: '',
                        target_temp_max: '',
                        humidity_enabled: false,
                        target_humidity_min: '',
                        target_humidity_max: '',
                        is_active: true,
                    };
                    this.modal.open = true;
                },

                openEditModal(location) {
                    this.modal.isEdit = true;
                    this.modal.editId = location.id;
                    this.modal.error = '';
                    this.modal.form = {
                        name: location.name,
                        type: location.type,
                        target_temp_min: location.target_temp_min ?? '',
                        target_temp_max: location.target_temp_max ?? '',
                        humidity_enabled: (location.target_humidity_min !== null || location.target_humidity_max !== null),
                        target_humidity_min: location.target_humidity_min ?? '',
                        target_humidity_max: location.target_humidity_max ?? '',
                        is_active: location.is_active,
                    };
                    this.modal.open = true;
                },

                closeModal() {
                    this.modal.open = false;
                },

                async submitForm() {
                    if (!this.modal.form.name || !this.modal.form.type) {
                        this.modal.error = 'Nama dan tipe lokasi wajib diisi.';
                        return;
                    }

                    this.modal.loading = true;
                    this.modal.error = '';

                    const payload = {
                        name: this.modal.form.name,
                        type: this.modal.form.type,
                        target_temp_min: this.modal.form.target_temp_min ? parseFloat(this.modal.form.target_temp_min) : null,
                        target_temp_max: this.modal.form.target_temp_max ? parseFloat(this.modal.form.target_temp_max) : null,
                        target_humidity_min: this.modal.form.humidity_enabled && this.modal.form.target_humidity_min ? parseFloat(this.modal.form.target_humidity_min) : null,
                        target_humidity_max: this.modal.form.humidity_enabled && this.modal.form.target_humidity_max ? parseFloat(this.modal.form.target_humidity_max) : null,
                        is_active: this.modal.form.is_active,
                    };

                    try {
                        const url = this.modal.isEdit 
                            ? '{{ url("monitoring/environment/locations") }}/' + this.modal.editId
                            : '{{ route("monitoring.environment.locations.store") }}';
                        const method = this.modal.isEdit ? 'PUT' : 'POST';

                        const response = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify(payload),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            if (data.errors) {
                                this.modal.error = Object.values(data.errors).flat().join(' ');
                            } else {
                                this.modal.error = data.message || 'Gagal menyimpan lokasi.';
                            }
                            return;
                        }

                        this.closeModal();
                        this.showGlobalMessage(data.message || 'Lokasi berhasil disimpan.', 'success');
                        this.loadLocations();

                    } catch (error) {
                        console.error('Submit error:', error);
                        this.modal.error = 'Terjadi kesalahan. Silakan coba lagi.';
                    } finally {
                        this.modal.loading = false;
                    }
                },

                async toggleActive(location) {
                    try {
                        const response = await fetch('{{ url("monitoring/environment/locations") }}/' + location.id, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({
                                name: location.name,
                                type: location.type,
                                target_temp_min: location.target_temp_min,
                                target_temp_max: location.target_temp_max,
                                target_humidity_min: location.target_humidity_min,
                                target_humidity_max: location.target_humidity_max,
                                is_active: !location.is_active,
                            }),
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.showGlobalMessage(data.message || 'Status lokasi berhasil diubah.', 'success');
                            this.loadLocations();
                        } else {
                            this.showGlobalMessage(data.message || 'Gagal mengubah status.', 'error');
                        }

                    } catch (error) {
                        console.error('Toggle error:', error);
                        this.showGlobalMessage('Terjadi kesalahan.', 'error');
                    }
                },

                confirmDelete(location) {
                    this.deleteModal.location = location;
                    this.deleteModal.open = true;
                },

                closeDeleteModal() {
                    this.deleteModal.open = false;
                    this.deleteModal.location = null;
                },

                async executeDelete() {
                    if (!this.deleteModal.location) return;

                    this.deleteModal.loading = true;

                    try {
                        const response = await fetch('{{ url("monitoring/environment/locations") }}/' + this.deleteModal.location.id, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.closeDeleteModal();
                            this.showGlobalMessage(data.message || 'Lokasi berhasil dihapus.', 'success');
                            this.loadLocations();
                        } else {
                            this.closeDeleteModal();
                            this.showGlobalMessage(data.message || 'Gagal menghapus lokasi.', 'error');
                        }

                    } catch (error) {
                        console.error('Delete error:', error);
                        this.showGlobalMessage('Terjadi kesalahan.', 'error');
                    } finally {
                        this.deleteModal.loading = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
