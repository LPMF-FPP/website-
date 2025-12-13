<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="📊 Statistik dan Rekap Data"
            :breadcrumbs="[[ 'label' => 'Statistik' ]]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6" x-data="{ loading: false }">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-500 text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Permintaan Bulan Ini</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $requests_this_month }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-500 text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Sampel Tahun Ini</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $samples_this_year }}</p>
                            <p class="text-xs text-gray-500">Target IKU: 200/tahun</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-500 text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Zat Aktif Terdeteksi</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $active_substances_detected }}</p>
                            @php
                                $uniqueActiveSubstances = $activeSubstanceBreakdown['unique_total'] ?? count($activeSubstanceBreakdown['labels'] ?? []);
                            @endphp
                            <p class="text-xs text-gray-500 mt-1">{{ $uniqueActiveSubstances }} jenis unik</p>
                            @if ($activeSubstanceBreakdown['fallback'] ?? false)
                                <p class="text-xs text-yellow-600 mt-1">Menampilkan data simulasi karena belum ada input permintaan.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid with Responsive Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-if="loading">
                <div class="md:col-span-2 lg:col-span-3">
                    <x-skeleton-table :columns="3" :rows="6" />
                </div>
            </template>

            <!-- 1. Asal User (Pie Chart) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-full">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">🏢 Asal User</h3>
                        <button onclick="exportChart('user_origin')"
                                class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded">
                            📥 Export
                        </button>
                    </div>
                    <div class="relative flex-1 min-h-[400px]">
                        <canvas id="userOriginChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 2. Zat Aktif (Doughnut Chart) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-full">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">🧪 Jenis Zat Aktif</h3>
                        <button onclick="exportChart('active_substances')"
                                class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded">
                            📥 Export
                        </button>
                    </div>
                    <div class="relative flex-1 min-h-[400px]">
                        <canvas id="activeSubstancesChart"></canvas>
                        <p data-chart-error="activeSubstances" class="mt-4 text-sm text-red-600 text-center hidden">
                            Data tidak dapat dimuat. Silakan coba lagi.
                        </p>
                    </div>
                    <div class="mt-4 text-center text-sm text-gray-600">
                        @php
                            $topActiveSubstances = collect($activeSubstanceBreakdown['labels'] ?? [])->map(function ($label, $index) use ($activeSubstanceBreakdown) {
                                return [
                                    'label' => $label,
                                    'count' => $activeSubstanceBreakdown['data'][$index] ?? 0,
                                    'percentage' => $activeSubstanceBreakdown['percentages'][$index] ?? 0,
                                ];
                            })->take(3);
                        @endphp
                        @if ($topActiveSubstances->isNotEmpty())
                            <p>Zat aktif terbanyak: {{ $topActiveSubstances->pluck('label')->implode(', ') }}</p>
                            <p class="text-xs text-gray-400 mt-1">Sumber: data permintaan pengujian terbaru.</p>
                        @else
                            <p>Belum ada data zat aktif dari permintaan pengujian.</p>
                        @endif
                        @if ($activeSubstanceBreakdown['fallback'] ?? false)
                            <p class="text-xs text-yellow-600 mt-2">Menampilkan data simulasi karena belum ada input baru.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- 3. Gender Tersangka (Pie Chart) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-full">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">👥 Gender Tersangka</h3>
                        <button onclick="exportChart('suspect_gender')"
                                class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded">
                            📥 Export
                        </button>
                    </div>
                    <div class="relative flex-1 min-h-[400px]">
                        <canvas id="suspectGenderChart"></canvas>
                        <p data-chart-error="suspectGender" class="mt-4 text-sm text-red-600 text-center hidden">
                            Data tidak dapat dimuat. Silakan coba lagi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 4. Umur Tersangka (Bar Chart) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-full">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">📊 Rentang Umur Tersangka</h3>
                        <button onclick="exportChart('suspect_age')"
                                class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded">
                            📥 Export
                        </button>
                    </div>
                    <div class="relative flex-1 min-h-[400px]">
                        <canvas id="suspectAgeChart"></canvas>
                        <p data-chart-error="suspectAge" class="mt-4 text-sm text-red-600 text-center hidden">
                            Data tidak dapat dimuat. Silakan coba lagi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 5. Permintaan per Bulan (Line Chart) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-full">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">📈 Permintaan per Bulan</h3>
                        <button onclick="exportChart('monthly_requests')"
                                class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded">
                            📥 Export
                        </button>
                    </div>
                    <div class="relative flex-1 min-h-[400px]">
                        <canvas id="monthlyRequestsChart"></canvas>
                        <p data-chart-error="monthlyRequests" class="mt-4 text-sm text-red-600 text-center hidden">
                            Data tidak dapat dimuat. Silakan coba lagi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 6. Sampel per Bulan vs Target IKU -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-full">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">📊 Sampel vs Target IKU</h3>
                        <button onclick="exportChart('monthly_samples')"
                                class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded">
                            📥 Export
                        </button>
                    </div>
                    <div class="relative flex-1 min-h-[400px]">
                        <canvas id="monthlySamplesChart"></canvas>
                        <p data-chart-error="monthlySamples" class="mt-4 text-sm text-red-600 text-center hidden">
                            Data tidak dapat dimuat. Silakan coba lagi.
                        </p>
                    </div>
                    <div class="mt-4 bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-medium text-blue-900 mb-2">📋 Informasi IKU</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-blue-800 text-sm">
                            <div>
                                <strong>Target Tahunan:</strong> 200 sampel
                            </div>
                            <div>
                                <strong>Rata-rata:</strong> 16.7 sampel/bulan
                            </div>
                            <div id="samplesProgress">
                                <strong>Progress:</strong> <span id="currentProgress">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Summary Table -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 Ringkasan Data</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bulan Ini</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tahun Ini</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target/Rata-rata</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Permintaan Pengujian</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $requests_this_month }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ \App\Models\TestRequest::whereYear('created_at', now()->year)->count() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">32/bulan</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $requests_this_month >= 32 ? 'green' : 'yellow' }}-100 text-{{ $requests_this_month >= 32 ? 'green' : 'yellow' }}-800">
                                        {{ $requests_this_month >= 32 ? 'Di Atas Target' : 'Normal' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Jumlah Sampel</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ \App\Models\Sample::whereMonth('created_at', now()->month)->count() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $samples_this_year }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">200/tahun (IKU)</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $samples_this_year >= 160 ? 'green' : 'yellow' }}-100 text-{{ $samples_this_year >= 160 ? 'green' : 'yellow' }}-800">
                                        {{ $samples_this_year >= 160 ? 'Mendekati Target' : 'Perlu Peningkatan' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Zat Aktif Terdeteksi</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $active_substances_detected }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $active_substances_detected }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">240/bulan</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Di Atas Target
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Chart.js Datalabels Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <script>
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.position = 'bottom';

        let charts = {};

        function hideChartError(chartKey) {
            const element = document.querySelector(`[data-chart-error="${chartKey}"]`);
            if (element) {
                element.classList.add('hidden');
            }
        }

        function showChartError(chartKey, message) {
            const element = document.querySelector(`[data-chart-error="${chartKey}"]`);
            if (element) {
                element.textContent = message;
                element.classList.remove('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadUserOriginChart();
            loadActiveSubstancesChart();
            loadSuspectGenderChart();
            loadSuspectAgeChart();
            loadMonthlyRequestsChart();
            loadMonthlySamplesChart();
        });

        // 1. User Origin Pie Chart
        function loadUserOriginChart() {
            fetch('{{ route("statistics.data") }}?type=user_origin')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const ctx = document.getElementById('userOriginChart').getContext('2d');

                    if (charts.userOrigin) {
                        charts.userOrigin.destroy();
                    }

                    charts.userOrigin = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: data.colors,
                                borderWidth: 2,
                                borderColor: '#ffffff',
                                hoverBorderWidth: 4,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        boxWidth: 12,
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + ' (' + data.percentages[context.dataIndex] + '%)';
                                        }
                                    }
                                },
                                datalabels: {
                                    color: '#ffffff',
                                    font: {
                                        weight: 'bold',
                                        size: 12
                                    },
                                    formatter: function(value, context) {
                                        const percentage = data.percentages[context.dataIndex];
                                        return percentage > 3 ? percentage + '%' : '';
                                    }
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                })
                .catch(error => {
                    console.error('Error loading user origin chart:', error);

                    const dummyData = {
                        labels: ['Polda Metro Jaya', 'Polres Jakarta Selatan', 'Polres Jakarta Utara', 'Polres Jakarta Barat', 'Polres Bogor'],
                        data: [85, 42, 38, 28, 15],
                        percentages: [40.9, 20.2, 18.3, 13.5, 7.2],
                        colors: ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6']
                    };

                    const ctx = document.getElementById('userOriginChart').getContext('2d');
                    charts.userOrigin = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: dummyData.labels,
                            datasets: [{
                                data: dummyData.data,
                                backgroundColor: dummyData.colors
                            }]
                        },
                        options: {
                            plugins: {
                                legend: { position: 'right' }
                            }
                        }
                    });
                });
        }

        // 2. Active Substances Doughnut Chart
        function loadActiveSubstancesChart() {
            hideChartError('activeSubstances');

            fetch('{{ route("statistics.data") }}?type=active_substances')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const ctx = document.getElementById('activeSubstancesChart').getContext('2d');

                    if (charts.activeSubstances) {
                        charts.activeSubstances.destroy();
                    }

                    charts.activeSubstances = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: data.colors,
                                borderWidth: 3,
                                borderColor: '#ffffff',
                                hoverBorderWidth: 5,
                                hoverOffset: 15
                            }]
                        },
                        options: {
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + ' sampels (' + data.percentages[context.dataIndex] + '%)';
                                        }
                                    }
                                },
                                datalabels: {
                                    color: '#ffffff',
                                    font: {
                                        weight: 'bold',
                                        size: 14
                                    },
                                    formatter: function(value, context) {
                                        const percentage = data.percentages[context.dataIndex];
                                        return percentage + '%';
                                    }
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                })
                .catch(error => {
                    console.error('Error loading active substances chart:', error);

                    if (charts.activeSubstances) {
                        charts.activeSubstances.destroy();
                        delete charts.activeSubstances;
                    }

                    showChartError('activeSubstances', 'Data tidak dapat dimuat. Silakan coba lagi.');
                });
        }

        // 3. Suspect Gender Pie Chart
        function loadSuspectGenderChart() {
            hideChartError('suspectGender');

            fetch('{{ route("statistics.data") }}?type=suspect_gender')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const ctx = document.getElementById('suspectGenderChart').getContext('2d');

                    if (charts.suspectGender) {
                        charts.suspectGender.destroy();
                    }

                    charts.suspectGender = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: data.colors,
                                borderWidth: 2,
                                borderColor: '#ffffff',
                                hoverBorderWidth: 4,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 15,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + ' orang (' + data.percentages[context.dataIndex] + '%)';
                                        }
                                    }
                                },
                                datalabels: {
                                    color: '#ffffff',
                                    font: {
                                        weight: 'bold',
                                        size: 14
                                    },
                                    formatter: function(value, context) {
                                        const percentage = data.percentages[context.dataIndex];
                                        return percentage + '%';
                                    }
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                })
                .catch(error => {
                    console.error('Error loading suspect gender chart:', error);

                    if (charts.suspectGender) {
                        charts.suspectGender.destroy();
                        delete charts.suspectGender;
                    }

                    showChartError('suspectGender', 'Data tidak dapat dimuat. Silakan coba lagi.');
                });
        }

        // 4. Suspect Age Bar Chart
        function loadSuspectAgeChart() {
            hideChartError('suspectAge');

            fetch('{{ route("statistics.data") }}?type=suspect_age')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const ctx = document.getElementById('suspectAgeChart').getContext('2d');

                    if (charts.suspectAge) {
                        charts.suspectAge.destroy();
                    }

                    charts.suspectAge = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Jumlah Tersangka',
                                data: data.data,
                                backgroundColor: data.backgroundColor,
                                borderColor: data.borderColor,
                                borderWidth: 2
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Jumlah Tersangka',
                                        font: {
                                            weight: 'bold'
                                        }
                                    },
                                    ticks: {
                                        precision: 0,
                                        callback: function(value) {
                                            return value + ' orang';
                                        }
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Rentang Umur (Tahun)',
                                        font: {
                                            weight: 'bold'
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.parsed.y + ' orang';
                                        }
                                    }
                                },
                                datalabels: {
                                    anchor: 'end',
                                    align: 'top',
                                    color: '#374151',
                                    font: {
                                        weight: 'bold',
                                        size: 11
                                    },
                                    formatter: function(value) {
                                        return value > 0 ? value : '';
                                    }
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                })
                .catch(error => {
                    console.error('Error loading suspect age chart:', error);

                    if (charts.suspectAge) {
                        charts.suspectAge.destroy();
                        delete charts.suspectAge;
                    }

                    showChartError('suspectAge', 'Data tidak dapat dimuat. Silakan coba lagi.');
                });
        }

        // 5. Monthly Requests Line Chart
        function loadMonthlyRequestsChart() {
            hideChartError('monthlyRequests');

            fetch('{{ route("statistics.data") }}?type=monthly_requests')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const ctx = document.getElementById('monthlyRequestsChart').getContext('2d');

                    if (charts.monthlyRequests) {
                        charts.monthlyRequests.destroy();
                    }

                    charts.monthlyRequests = new Chart(ctx, {
                        type: 'line',
                        data: data,
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Jumlah Permintaan',
                                        font: {
                                            weight: 'bold'
                                        }
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value + ' permintaan';
                                        }
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Bulan',
                                        font: {
                                            weight: 'bold'
                                        }
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y + ' permintaan';
                                        }
                                    }
                                },
                                datalabels: {
                                    display: false
                                }
                            },
                            interaction: {
                                mode: 'nearest',
                                axis: 'x',
                                intersect: false
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading monthly requests chart:', error);

                    if (charts.monthlyRequests) {
                        charts.monthlyRequests.destroy();
                        delete charts.monthlyRequests;
                    }

                    showChartError('monthlyRequests', 'Data tidak dapat dimuat. Silakan coba lagi.');
                });
        }

        // 6. Monthly Samples Mixed Chart
        function loadMonthlySamplesChart() {
            hideChartError('monthlySamples');

            fetch('{{ route("statistics.data") }}?type=monthly_samples')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const ctx = document.getElementById('monthlySamplesChart').getContext('2d');

                    if (charts.monthlySamples) {
                        charts.monthlySamples.destroy();
                    }

                    const totalActual = data.datasets[0].data.reduce((a, b) => a + b, 0);
                    const yearlyTarget = data.targetInfo ? data.targetInfo.yearly_target : 200;
                    const progressPercentage = Math.round((totalActual / yearlyTarget) * 100);

                    const progressElement = document.getElementById('currentProgress');
                    if (progressElement) {
                        progressElement.textContent = `${totalActual}/${yearlyTarget} sampel (${progressPercentage}%)`;
                    }

                    charts.monthlySamples = new Chart(ctx, {
                        type: 'bar',
                        data: data,
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 30,
                                    title: {
                                        display: true,
                                        text: 'Jumlah Sampel per Bulan',
                                        font: {
                                            weight: 'bold'
                                        }
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value + ' sampel';
                                        }
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Bulan',
                                        font: {
                                            weight: 'bold'
                                        }
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            label += context.parsed.y + ' sampel';

                                            if (context.datasetIndex === 0) {
                                                const monthlyTarget = data.targetInfo ? data.targetInfo.monthly_average : 16.7;
                                                const difference = context.parsed.y - monthlyTarget;
                                                const status = difference >= 0 ?
                                                    `(+${difference.toFixed(1)} dari rata-rata)` :
                                                    `(${difference.toFixed(1)} dari rata-rata)`;
                                                label += ' ' + status;
                                            }

                                            return label;
                                        },
                                        afterBody: function(context) {
                                            if (context[0].datasetIndex === 0) {
                                                return `Total tahun ini: ${totalActual} sampel\nTarget IKU: ${yearlyTarget} sampel/tahun\nProgress: ${progressPercentage}%`;
                                            }
                                            return '';
                                        }
                                    }
                                },
                                datalabels: {
                                    display: false
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading monthly samples chart:', error);

                    if (charts.monthlySamples) {
                        charts.monthlySamples.destroy();
                        delete charts.monthlySamples;
                    }

                    showChartError('monthlySamples', 'Data tidak dapat dimuat. Silakan coba lagi.');

                    const progressElement = document.getElementById('currentProgress');
                    if (progressElement) {
                        progressElement.textContent = 'Data tidak tersedia.';
                    }
                });
        }

        function exportChart(type) {
            const url = '{{ route("statistics.export") }}?type=' + type;
            window.open(url, '_blank');
        }

        function refreshCharts() {
            console.log('Refreshing all charts...');
            loadUserOriginChart();
            loadActiveSubstancesChart();
            loadSuspectGenderChart();
            loadSuspectAgeChart();
            loadMonthlyRequestsChart();
            loadMonthlySamplesChart();
        }

        setInterval(refreshCharts, 300000);
        window.refreshCharts = refreshCharts;
    </script>
    @endpush
</x-app-layout>
