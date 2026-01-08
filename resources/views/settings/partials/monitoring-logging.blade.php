{{-- Partial for Monitoring & Logging Settings Section --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Monitoring Lingkungan & Log Instrumen</h2>
        <p class="text-sm text-gray-500 mt-1">
            Konfigurasi pencatatan suhu/kelembaban dan penggunaan instrumen untuk ISO 17025/QMS.
        </p>
    </div>

    {{-- Status Message --}}
    <div x-show="client.state.sectionStatus?.monitoring_logging?.message" x-cloak>
        <div class="flex items-center gap-2 p-3 rounded-lg"
             :class="client.state.sectionStatus?.monitoring_logging?.intentClass?.includes('green') ? 'bg-green-50 border border-green-200' : 
                    (client.state.sectionStatus?.monitoring_logging?.intentClass?.includes('red') ? 'bg-red-50 border border-red-200' : 'bg-blue-50 border border-blue-200')">
            <span class="text-sm" :class="client.state.sectionStatus?.monitoring_logging?.intentClass" x-text="client.state.sectionStatus?.monitoring_logging?.message"></span>
        </div>
    </div>

    {{-- Error Message --}}
    <div x-show="client.state.sectionErrors?.monitoring_logging" x-cloak>
        <div class="flex items-center gap-2 p-3 rounded-lg bg-red-50 border border-red-200">
            <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-sm text-red-700" x-text="client.state.sectionErrors?.monitoring_logging"></span>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Environment Monitoring Section --}}
        <div class="border-b border-gray-100 pb-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Monitoring Lingkungan (Suhu/Kelembaban)</h3>
            
            {{-- Enable/Disable --}}
            <div class="flex items-center justify-between py-3">
                <div>
                    <label class="text-sm font-medium text-gray-700">Aktifkan Monitoring Lingkungan</label>
                    <p class="text-xs text-gray-500">Tampilkan notifikasi dan log suhu/kelembaban</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" x-model="client.state.form.monitoring_logging.environment.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>

            <div x-show="client.state.form.monitoring_logging?.environment?.enabled" x-cloak class="space-y-4 mt-4">
                {{-- Work Hours --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jam Kerja Mulai</label>
                        <input type="time" x-model="client.state.form.monitoring_logging.environment.work_start"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Default: 07:00</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jam Kerja Selesai</label>
                        <input type="time" x-model="client.state.form.monitoring_logging.environment.work_end"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Default: 15:00</p>
                    </div>
                </div>

                {{-- Work Days --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Hari Kerja</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(day, index) in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']" :key="index">
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border cursor-pointer transition"
                                   :class="client.state.form.monitoring_logging?.environment?.work_days?.includes(index + 1) ? 'bg-blue-100 border-blue-300 text-blue-800' : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'">
                                <input type="checkbox" :value="index + 1" 
                                       @change="toggleWorkDay(index + 1)"
                                       :checked="client.state.form.monitoring_logging?.environment?.work_days?.includes(index + 1)"
                                       class="sr-only">
                                <span class="text-sm" x-text="day"></span>
                            </label>
                        </template>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">1=Senin, 7=Minggu. Default: Senin-Jumat</p>
                </div>

                {{-- Recording Windows --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Window Pencatatan</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707"></path>
                                </svg>
                                <span class="text-sm font-medium text-yellow-800">Pagi</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs text-gray-600">Mulai</label>
                                    <input type="time" x-model="client.state.form.monitoring_logging.environment.window_morning_start"
                                           class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600">Selesai</label>
                                    <input type="time" x-model="client.state.form.monitoring_logging.environment.window_morning_end"
                                           class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>
                        <div class="p-3 bg-orange-50 rounded-lg border border-orange-100">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707"></path>
                                </svg>
                                <span class="text-sm font-medium text-orange-800">Siang</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs text-gray-600">Mulai</label>
                                    <input type="time" x-model="client.state.form.monitoring_logging.environment.window_afternoon_start"
                                           class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600">Selesai</label>
                                    <input type="time" x-model="client.state.form.monitoring_logging.environment.window_afternoon_end"
                                           class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <strong>Penting:</strong> Window pagi tertutup jika sudah lewat waktu selesai - tidak bisa input retroaktif untuk window yang terlewat.
                    </p>
                </div>

                {{-- Humidity Toggle --}}
                <div class="flex items-center justify-between py-3 border-t border-gray-100">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Catat Kelembaban</label>
                        <p class="text-xs text-gray-500">Opsional: catat kelembaban bersama suhu</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="client.state.form.monitoring_logging.environment.humidity_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>
        </div>

        {{-- Instrument Logging Section --}}
        <div class="border-b border-gray-100 pb-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Pencatatan Penggunaan Instrumen</h3>
            
            <div class="flex items-center justify-between py-3">
                <div>
                    <label class="text-sm font-medium text-gray-700">Aktifkan Log Instrumen</label>
                    <p class="text-xs text-gray-500">Wajibkan pencatatan instrumen saat tahap INSTRUMENTATION</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" x-model="client.state.form.monitoring_logging.instrument.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>

            <div x-show="client.state.form.monitoring_logging?.instrument?.enabled" x-cloak class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <p class="text-sm text-blue-800">
                    <strong>Cara Kerja:</strong> Sistem akan memvalidasi bahwa instrumen yang diperlukan oleh metode pengujian (UV-VIS, GC-MS, LC-MS) 
                    sudah dicatat sebelum tahap INSTRUMENTATION dapat diselesaikan.
                </p>
                <ul class="text-xs text-blue-700 mt-2 space-y-1 list-disc list-inside">
                    <li>Persyaratan instrumen per metode diatur di tabel <code>method_instrument_requirements</code></li>
                    <li>Pencatatan instrumen dilakukan di halaman proses pengujian</li>
                    <li>Log tidak dapat dihapus/diubah - koreksi membuat record baru</li>
                </ul>
            </div>
        </div>

        {{-- UV-VIS Weighing Section --}}
        <div class="pb-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Penimbangan UV-VIS</h3>
            
            <div class="flex items-center justify-between py-3">
                <div>
                    <label class="text-sm font-medium text-gray-700">Wajibkan Penimbangan untuk UV-VIS</label>
                    <p class="text-xs text-gray-500">Validasi gram ditimbang sebelum tahap PREPARATION selesai</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" x-model="client.state.form.monitoring_logging.uvvis_weighing.enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>

            <div x-show="client.state.form.monitoring_logging?.uvvis_weighing?.enabled" x-cloak class="mt-4 p-4 bg-purple-50 rounded-lg border border-purple-100">
                <p class="text-sm text-purple-800">
                    <strong>Validasi:</strong> Sampel dengan metode UV-VIS wajib memiliki data penimbangan 
                    (gram, teknisi, tanggal/waktu) sebelum tahap PREPARATION dapat diselesaikan.
                </p>
                <ul class="text-xs text-purple-700 mt-2 space-y-1 list-disc list-inside">
                    <li>Field penimbangan muncul hanya untuk sampel dengan metode UV-VIS</li>
                    <li>Data tersimpan di kolom <code>uvvis_weighed_grams</code>, <code>uvvis_weighed_by</code>, <code>uvvis_weighed_at</code></li>
                </ul>
            </div>
        </div>

        {{-- Save Button --}}
        <div class="flex justify-end pt-4 border-t border-gray-100">
            <button type="button" @click="saveMonitoringLoggingSettings()"
                    :disabled="client.state.loadingSections?.monitoring_logging"
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed transition">
                <svg x-show="client.state.loadingSections?.monitoring_logging" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="client.state.loadingSections?.monitoring_logging ? 'Menyimpan...' : 'Simpan Bagian Ini'"></span>
            </button>
        </div>
    </div>
</div>

{{-- Quick Links Section --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mt-6">
    <h3 class="text-sm font-semibold text-gray-800 mb-4">Akses Cepat</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('monitoring.environment.index') }}" 
           class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
            <div class="p-2 bg-blue-100 rounded-lg">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-900">Monitoring Lingkungan</div>
                <div class="text-xs text-gray-500">Input & lihat data suhu/kelembaban</div>
            </div>
        </a>
        <a href="{{ route('monitoring.instruments.index') }}" 
           class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
            <div class="p-2 bg-green-100 rounded-lg">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                </svg>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-900">Master Instrumen</div>
                <div class="text-xs text-gray-500">Kelola daftar instrumen & aset</div>
            </div>
        </a>
        <a href="{{ route('reports.monthly-logs') }}" 
           class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
            <div class="p-2 bg-purple-100 rounded-lg">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-900">Laporan Bulanan</div>
                <div class="text-xs text-gray-500">Download PDF log bulanan</div>
            </div>
        </a>
    </div>
</div>
