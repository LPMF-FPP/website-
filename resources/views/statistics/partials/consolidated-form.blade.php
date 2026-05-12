<div x-data="{
    step: 'form',
    periodType: 'biweekly',
    startDate: '',
    endDate: '',
    signers: {{ json_encode($defaultSigners ?? \App\Services\ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE) }},
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
    },

    chartColor(index) {
        const colors = ['#1d4ed8', '#dc2626', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d'];
        return colors[index % colors.length];
    },

    topRows(rows, limit = 5) {
        return (rows || []).slice(0, limit);
    },

    topRowsWithOther(rows, limit = 5) {
        const safeRows = rows || [];
        const top = safeRows.slice(0, limit);
        const rest = safeRows.slice(limit);

        if (rest.length === 0) {
            return top;
        }

        const count = rest.reduce((sum, row) => sum + Number(row.count || 0), 0);
        const percentage = rest.reduce((sum, row) => sum + Number(row.percentage || 0), 0);

        return [
            ...top,
            {
                label: 'Lainnya',
                count,
                percentage: Math.round(percentage * 10) / 10,
            },
        ];
    },

    pieGradient(rows) {
        const safeRows = this.topRowsWithOther(rows, 5);
        const total = safeRows.reduce((sum, row) => sum + Number(row.count || 0), 0);

        if (total <= 0) {
            return 'conic-gradient(#e5e7eb 0 360deg)';
        }

        let cursor = 0;
        const segments = safeRows.map((row, index) => {
            const start = cursor;
            cursor += (Number(row.count || 0) / total) * 360;
            return `${this.chartColor(index)} ${start}deg ${cursor}deg`;
        });

        return `conic-gradient(${segments.join(', ')})`;
    },

    maxValue(rows, key) {
        return Math.max(1, ...(rows || []).map(row => Number(row[key] || 0)));
    },

    maxValueForKeys(rows, keys) {
        const values = [];

        (rows || []).forEach(row => {
            keys.forEach(key => values.push(Number(row[key] || 0)));
        });

        return Math.max(1, ...values);
    },

    hasPositiveValue(rows, key) {
        return (rows || []).some(row => Number(row[key] || 0) > 0);
    },

    percentOf(value, max) {
        const numberValue = Number(value || 0);

        if (numberValue <= 0) {
            return 0;
        }

        return Math.max(3, Math.round((numberValue / Math.max(1, Number(max || 1))) * 100));
    },

    linePoints(rows, key, width = 300, height = 118, padX = 28, padY = 28, scaleKeys = null) {
        const safeRows = rows || [];
        const max = Array.isArray(scaleKeys) ? this.maxValueForKeys(safeRows, scaleKeys) : this.maxValue(safeRows, key);
        const step = safeRows.length > 1 ? width / (safeRows.length - 1) : width;

        return safeRows.map((row, index) => {
            const x = Math.round(padX + (index * step));
            const y = Math.round(padY + height - ((Number(row[key] || 0) / max) * height));

            return `${x},${y}`;
        }).join(' ');
    },

    linePoint(rows, index, key, width = 300, height = 118, padX = 28, padY = 28, scaleKeys = null) {
        const safeRows = rows || [];
        const max = Array.isArray(scaleKeys) ? this.maxValueForKeys(safeRows, scaleKeys) : this.maxValue(safeRows, key);
        const step = safeRows.length > 1 ? width / (safeRows.length - 1) : width;
        const row = safeRows[index] || {};

        return {
            x: Math.round(padX + (index * step)),
            y: Math.round(padY + height - ((Number(row[key] || 0) / max) * height)),
        };
    },

    lineSvgLabels(rows, key, color, offsetY = -16, width = 300, height = 118, padX = 28, padY = 28, scaleKeys = null, minY = 12, maxY = 176) {
        return this.topRows(rows, 12).map((row, index) => {
            const point = this.linePoint(rows, index, key, width, height, padX, padY, scaleKeys);
            const value = String(row[key] ?? 0);
            const labelWidth = Math.max(14, value.length * 5.6 + 8);
            const labelHeight = 12;
            const x = Math.max(2, Math.min(354 - labelWidth, point.x - (labelWidth / 2)));
            const y = Math.max(minY, Math.min(maxY, point.y + offsetY)) - (labelHeight / 2);
            const textY = y + 8.5;

            return `<circle cx='${point.x}' cy='${point.y}' r='2.4' fill='${color}' />`
                + `<rect x='${x}' y='${y}' width='${labelWidth}' height='${labelHeight}' rx='3' fill='#ffffff' stroke='${color}' stroke-width='0.8' />`
                + `<text x='${x + (labelWidth / 2)}' y='${textY}' text-anchor='middle' font-size='8.5' font-weight='700' fill='${color}'>${value}</text>`;
        }).join('');
    },

    lineSvgEndpointLabel(rows, key, color, offsetY = -16, width = 300, height = 118, padX = 28, padY = 28, scaleKeys = null, side = 'right', minY = 12, maxY = 176) {
        const safeRows = this.topRows(rows, 12);
        if (safeRows.length === 0) {
            return '';
        }

        const index = side === 'left' ? 0 : safeRows.length - 1;
        const row = safeRows[index];
        const point = this.linePoint(safeRows, index, key, width, height, padX, padY, scaleKeys);
        const value = String(row[key] ?? 0);
        const labelWidth = Math.max(18, value.length * 5.6 + 8);
        const labelHeight = 12;
        const x = Math.max(2, Math.min(354 - labelWidth, point.x - (labelWidth / 2)));
        const y = Math.max(minY, Math.min(maxY, point.y + offsetY)) - (labelHeight / 2);
        const textY = y + 8.5;

        return `<rect x='${x}' y='${y}' width='${labelWidth}' height='${labelHeight}' rx='3' fill='#ffffff' stroke='${color}' stroke-width='0.8' />`
            + `<text x='${x + (labelWidth / 2)}' y='${textY}' text-anchor='middle' font-size='8.5' font-weight='700' fill='${color}'>${value}</text>`;
    },

    labelY(point, offset = -10, min = 12, max = 178) {
        return Math.max(min, Math.min(max, point.y + offset));
    },

    pointLabelStyle(point, offsetY = -18, width = 356, height = 188) {
        const y = this.labelY(point, offsetY, 12, 176);

        return `left: ${(point.x / width) * 100}%; top: ${(y / height) * 100}%; transform: translate(-50%, -50%);`;
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
    <template x-if="step === 'preview' && previewData">
        <div class="bg-white rounded-lg shadow">
            @include('statistics.partials.consolidated-preview')
        </div>
    </template>

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
