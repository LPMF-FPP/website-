<div x-data="{
    step: 'form',
    periodType: 'biweekly',
    startDate: '',
    endDate: '',
    signers: {{ json_encode(
        \App\Models\SystemSetting::where('key', 'consolidated_report.default_signers')->value('value') 
        ?? [
            ['role' => 'Pembuat', 'name' => '', 'position' => '', 'nip' => ''],
            ['role' => 'Pemeriksa', 'name' => '', 'position' => '', 'nip' => ''],
            ['role' => 'Pengesah', 'name' => '', 'position' => '', 'nip' => ''],
        ]
    ) }},
    previewData: null,
    loading: false,
    errorMessage: '',
    successMessage: '',
    downloadUrl: '',
    savingDefaults: false,
    defaultsSavedMessage: '',

    init() {
        this.updateDateRange();
        this.$watch('periodType', () => this.updateDateRange());
    },

    updateDateRange() {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth(); // 0-11

        if (this.periodType === 'biweekly') {
            if (today.getDate() > 15) {
                // 1-15 this month
                this.startDate = this.formatDate(new Date(year, month, 1));
                this.endDate = this.formatDate(new Date(year, month, 15));
            } else {
                // 16-end last month
                this.startDate = this.formatDate(new Date(year, month - 1, 16));
                this.endDate = this.formatDate(new Date(year, month, 0));
            }
        } else if (this.periodType === 'monthly') {
            // Current month
            this.startDate = this.formatDate(new Date(year, month, 1));
            this.endDate = this.formatDate(new Date(year, month + 1, 0));
        } else if (this.periodType === 'quarterly') {
            // Current quarter
            const quarter = Math.floor(month / 3);
            const startMonth = quarter * 3;
            this.startDate = this.formatDate(new Date(year, startMonth, 1));
            this.endDate = this.formatDate(new Date(year, startMonth + 3, 0));
        }
    },

    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    },

    loadPreview() {
        this.loading = true;
        this.errorMessage = '';

        axios.post('{{ route('consolidated-reports.preview') }}', {
            period_type: this.periodType,
            period_start: this.startDate,
            period_end: this.endDate,
            signers: this.signers
        })
        .then(response => {
            this.previewData = response.data.data;
            this.step = 'preview';
        })
        .catch(error => {
            this.errorMessage = error.response?.data?.message || 'Gagal memuat preview.';
        })
        .finally(() => {
            this.loading = false;
        });
    },

    generateReport() {
        this.loading = true;
        this.errorMessage = '';

        axios.post('{{ route('consolidated-reports.store') }}', {
            period_type: this.periodType,
            period_start: this.startDate,
            period_end: this.endDate,
            signers: this.signers,
            narratives: this.previewData.narratives
        })
        .then(response => {
            this.successMessage = response.data.message;
            this.downloadUrl = response.data.data.download_url;
            this.step = 'success';
        })
        .catch(error => {
            this.errorMessage = error.response?.data?.message || 'Gagal generate laporan.';
        })
        .finally(() => {
            this.loading = false;
        });
    },

    saveAsDefaults() {
        this.savingDefaults = true;
        this.defaultsSavedMessage = '';
        this.errorMessage = '';

        axios.put('{{ route('consolidated-reports.save-default-signers') }}', {
            signers: this.signers
        })
        .then(response => {
            this.defaultsSavedMessage = response.data.message;
            setTimeout(() => { this.defaultsSavedMessage = ''; }, 3000);
        })
        .catch(error => {
            this.errorMessage = error.response?.data?.message || 'Gagal menyimpan default.';
        })
        .finally(() => {
            this.savingDefaults = false;
        });
    }
}">

    <!-- STEP 1: FORM -->
    <div x-show="step === 'form'" class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-6">Generate Laporan Baru</h3>

        <!-- Error Alert -->
        <div x-show="errorMessage" class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700" x-text="errorMessage"></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
            <!-- Period Type -->
            <div class="sm:col-span-3">
                <label for="period_type" class="block text-sm font-medium text-gray-700">Jenis Periode</label>
                <select id="period_type" x-model="periodType" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="biweekly">Bi-weekly (2 Mingguan)</option>
                    <option value="monthly">Bulanan</option>
                    <option value="quarterly">Triwulan</option>
                </select>
            </div>

            <!-- Date Range -->
            <div class="sm:col-span-3 grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Tanggal Awal</label>
                    <input type="date" id="start_date" x-model="startDate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">Tanggal Akhir</label>
                    <input type="date" id="end_date" x-model="endDate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>

            <div class="sm:col-span-6">
                <div class="border-t border-gray-200 my-4"></div>
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-md font-medium text-gray-900">Penandatangan (Opsional)</h4>
                    <button 
                        @click="saveAsDefaults()" 
                        :disabled="savingDefaults"
                        type="button"
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                        <svg x-show="savingDefaults" class="animate-spin -ml-0.5 mr-1.5 h-3 w-3 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg x-show="!savingDefaults" class="-ml-0.5 mr-1.5 h-3 w-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        <span x-text="savingDefaults ? 'Menyimpan...' : 'Simpan Sebagai Default'"></span>
                    </button>
                </div>
                <!-- Success message for defaults saved -->
                <div x-show="defaultsSavedMessage" x-transition class="mb-4 bg-green-50 border-l-4 border-green-400 p-3">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700" x-text="defaultsSavedMessage"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signers -->
            <template x-for="(signer, index) in signers" :key="index">
                <div class="sm:col-span-2 bg-gray-50 p-4 rounded-lg">
                    <div class="mb-2 font-medium text-gray-700" x-text="signer.role"></div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-500">Nama</label>
                            <input type="text" x-model="signer.name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-xs">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Jabatan</label>
                            <input type="text" x-model="signer.position" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-xs">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">NIP / NRP</label>
                            <input type="text" x-model="signer.nip" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-xs">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-6 flex justify-end">
            <button @click="loadPreview()" 
                :disabled="loading"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="loading ? 'Memuat Preview...' : 'Lanjut ke Preview'"></span>
            </button>
        </div>
    </div>

    <!-- STEP 2: PREVIEW -->
    <div x-show="step === 'preview'" class="bg-white rounded-lg shadow">
        @include('statistics.partials.consolidated-preview')
    </div>

    <!-- STEP 3: SUCCESS -->
    <div x-show="step === 'success'" class="bg-white rounded-lg shadow p-10 text-center">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h3 class="mt-3 text-lg leading-6 font-medium text-gray-900">Laporan Berhasil Dibuat!</h3>
        <div class="mt-2 px-7 py-3">
            <p class="text-sm text-gray-500" x-text="successMessage"></p>
        </div>
        <div class="mt-5 sm:mt-6 space-x-3">
            <a :href="downloadUrl" target="_blank" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                ⬇️ Download PDF
            </a>
            <button @click="step = 'form'; loadHistory && loadHistory()" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                Buat Laporan Lain
            </button>
        </div>
    </div>

    <!-- HISTORY TABLE -->
    <div class="mt-8 bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">Riwayat Laporan</h3>
            <button @click="loadHistory()" class="text-sm text-indigo-600 hover:text-indigo-900">Refresh</button>
        </div>
        @include('statistics.partials.consolidated-history')
    </div>
</div>
