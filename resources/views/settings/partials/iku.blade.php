{{-- Partial for IKU (Indeks Kinerja Utama) Settings Section --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Perhitungan IKU</h2>
        <p class="text-sm text-gray-500 mt-1">
            Konfigurasi Indeks Kinerja Utama (IKU) untuk dashboard dan pelaporan.
        </p>
    </div>

    {{-- Status Message --}}
    <div x-show="client.state.sectionStatus?.iku?.message" x-cloak role="status">
        <div class="flex items-center gap-2 p-3 rounded-lg"
             :class="client.state.sectionStatus?.iku?.intentClass?.includes('green') ? 'bg-green-50 border border-green-200' : 
                    (client.state.sectionStatus?.iku?.intentClass?.includes('red') ? 'bg-red-50 border border-red-200' : 'bg-blue-50 border border-blue-200')">
            <span class="text-sm" :class="client.state.sectionStatus?.iku?.intentClass" x-text="client.state.sectionStatus?.iku?.message"></span>
        </div>
    </div>

    {{-- Error Message --}}
    <div x-show="client.state.sectionErrors?.iku" x-cloak role="alert">
        <div class="flex items-center gap-2 p-3 rounded-lg bg-red-50 border border-red-200">
            <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="text-sm text-red-700" x-text="client.state.sectionErrors?.iku"></span>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Enable/Disable IKU --}}
        <div class="flex items-center justify-between py-3 border-b border-gray-100">
            <div>
                <label class="text-sm font-medium text-gray-700">Aktifkan IKU</label>
                <p class="text-xs text-gray-500">Tampilkan IKU di dashboard</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" x-model="client.state.form.iku.enabled" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
        </div>

        {{-- Period Mode --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mode Periode</label>
                <select x-model="client.state.form.iku.period_mode" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="monthly">Bulanan</option>
                    <option value="quarterly">Triwulan</option>
                    <option value="yearly">Tahunan</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Periode default untuk perhitungan dashboard</p>
            </div>
        </div>

        {{-- Weight Configuration --}}
        <div class="border-t border-gray-100 pt-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Bobot Komponen IKU</h3>
            <p class="text-xs text-gray-500 mb-4">Total bobot harus sama dengan 100%</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Registrasi Permohonan</label>
                    <div class="relative">
                        <input type="number" min="0" max="100" step="1"
                               x-model.number="client.state.form.iku.weights.registration"
                               class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <span class="absolute right-3 top-2 text-gray-400">%</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pemeriksaan Lab</label>
                    <div class="relative">
                        <input type="number" min="0" max="100" step="1"
                               x-model.number="client.state.form.iku.weights.lab_exam"
                               class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <span class="absolute right-3 top-2 text-gray-400">%</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Laporan Hasil</label>
                    <div class="relative">
                        <input type="number" min="0" max="100" step="1"
                               x-model.number="client.state.form.iku.weights.report"
                               class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <span class="absolute right-3 top-2 text-gray-400">%</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Survei Kepuasan</label>
                    <div class="relative">
                        <input type="number" min="0" max="100" step="1"
                               x-model.number="client.state.form.iku.weights.survey"
                               class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <span class="absolute right-3 top-2 text-gray-400">%</span>
                    </div>
                </div>
            </div>

            {{-- Weight Sum Indicator --}}
            <div class="mt-3 flex items-center gap-2">
                <span class="text-sm text-gray-600">Total:</span>
                <span class="font-medium" 
                      :class="getIkuWeightSum() === 100 ? 'text-green-600' : 'text-red-600'"
                      x-text="getIkuWeightSum() + '%'"></span>
                <span x-show="getIkuWeightSum() !== 100" class="text-xs text-red-500">(harus 100%)</span>
            </div>
        </div>

        {{-- Target Samples Configuration --}}
        <div class="border-t border-gray-100 pt-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Target Sampel per Tahun</h3>
            <p class="text-xs text-gray-500 mb-4">Nilai D (target sampel dikerjakan) untuk perhitungan komponen Pemeriksaan Lab</p>
            
            <div class="space-y-3">
                <template x-for="(value, year) in client.state.form.iku.target_samples_by_year" :key="year">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-700 w-16" x-text="year"></span>
                        <input type="number" min="1" step="1"
                               x-model.number="client.state.form.iku.target_samples_by_year[year]"
                               class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <span class="text-sm text-gray-500">sampel</span>
                        <button type="button" @click="removeIkuTargetYear(year)" 
                                class="text-red-500 hover:text-red-700 p-1" title="Hapus tahun">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            {{-- Add Year --}}
            <div class="flex items-center gap-3 mt-3">
                <input type="number" x-model.number="ikuNewYear" placeholder="Tahun" min="2020" max="2099"
                       class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <input type="number" x-model.number="ikuNewTarget" placeholder="Target" min="1"
                       class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <button type="button" @click="addIkuTargetYear()"
                        class="px-3 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg">
                    + Tambah Tahun
                </button>
            </div>
        </div>

        {{-- Survey Required Toggle --}}
        <div class="flex items-center justify-between py-3 border-t border-gray-100">
            <div>
                <label class="text-sm font-medium text-gray-700">Survey Wajib untuk Penyerahan</label>
                <p class="text-xs text-gray-500">Pelanggan harus mengisi survey sebelum pengambilan hasil</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" x-model="client.state.form.iku.survey_required_for_delivery" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
        </div>

        {{-- Save Button --}}
        <div class="flex justify-end pt-4 border-t border-gray-100">
            <button type="button" @click="saveIkuSettings()"
                    :disabled="client.state.loadingSections?.iku || getIkuWeightSum() !== 100"
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed transition">
                <svg x-show="client.state.loadingSections?.iku" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="client.state.loadingSections?.iku ? 'Menyimpan...' : 'Simpan Bagian Ini'"></span>
            </button>
        </div>
    </div>
</div>

{{-- Survey Export Section --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6 mt-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Export Rekap Survey</h2>
        <p class="text-sm text-gray-500 mt-1">
            Download data survey kepuasan pelanggan dalam format Excel.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
            <input type="date" x-model="surveyExport.startDate"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
            <input type="date" x-model="surveyExport.endDate"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <button type="button" @click="downloadSurveyExport()"
                    :disabled="!surveyExport.startDate || !surveyExport.endDate || surveyExport.loading"
                    class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 disabled:opacity-50 disabled:cursor-not-allowed transition">
                <svg x-show="surveyExport.loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg x-show="!surveyExport.loading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <span x-text="surveyExport.loading ? 'Mengunduh...' : 'Download Excel'"></span>
            </button>
        </div>
    </div>

    <div x-show="surveyExport.error" x-cloak class="text-sm text-red-600" x-text="surveyExport.error"></div>
</div>

{{-- IKU Preview Card --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Preview IKU</h2>
            <p class="text-sm text-gray-500 mt-1">Lihat nilai IKU dengan konfigurasi saat ini</p>
        </div>
        <button type="button" @click="refreshIkuPreview()"
                :disabled="ikuPreview.loading"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50">
            <svg x-show="ikuPreview.loading" class="animate-spin -ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg x-show="!ikuPreview.loading" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Refresh
        </button>
    </div>

    <div x-show="ikuPreview.data" x-cloak class="space-y-4">
        {{-- IKU Value Display --}}
        <div class="flex items-center gap-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg">
            <div class="text-4xl font-bold text-blue-600" x-text="(ikuPreview.data?.iku_value ?? 0).toFixed(2)"></div>
            <div>
                <div class="text-lg font-semibold text-gray-800">
                    Kategori: <span class="text-blue-600" x-text="ikuPreview.data?.iku_category ?? '-'"></span>
                </div>
                <div class="text-sm text-gray-500">
                    Periode: <span x-text="ikuPreview.data?.period?.start"></span> s/d <span x-text="ikuPreview.data?.period?.end"></span>
                </div>
            </div>
        </div>

        {{-- Component Breakdown --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 uppercase">Registrasi (R)</div>
                <div class="text-lg font-semibold text-gray-800" x-text="((ikuPreview.data?.components?.R ?? 0) * 100).toFixed(1) + '%'"></div>
                <div class="text-xs text-gray-500">Index: <span x-text="(ikuPreview.data?.indexes?.registration ?? 0).toFixed(2)"></span></div>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 uppercase">Pem. Lab (P)</div>
                <div class="text-lg font-semibold text-gray-800" x-text="((ikuPreview.data?.components?.P ?? 0) * 100).toFixed(1) + '%'"></div>
                <div class="text-xs text-gray-500">Index: <span x-text="(ikuPreview.data?.indexes?.lab_exam ?? 0).toFixed(2)"></span></div>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 uppercase">Laporan (L)</div>
                <div class="text-lg font-semibold text-gray-800" x-text="((ikuPreview.data?.components?.L ?? 0) * 100).toFixed(1) + '%'"></div>
                <div class="text-xs text-gray-500">Index: <span x-text="(ikuPreview.data?.indexes?.report ?? 0).toFixed(2)"></span></div>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 uppercase">Survey (S)</div>
                <div class="text-lg font-semibold text-gray-800" x-text="((ikuPreview.data?.components?.S ?? 0) * 100).toFixed(1) + '%'"></div>
                <div class="text-xs text-gray-500">Index: <span x-text="(ikuPreview.data?.indexes?.survey ?? 0).toFixed(2)"></span></div>
            </div>
        </div>

        {{-- Raw Counts with Comprehensive Description --}}
        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
            <div class="font-medium text-gray-700 text-sm">Data Mentah & Rumus Perhitungan:</div>
            
            {{-- Variable Descriptions --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <div class="flex items-start gap-2 p-2 bg-white rounded border border-gray-200">
                    <span class="font-bold text-blue-600 w-6">A</span>
                    <div>
                        <div class="font-medium text-gray-800" x-text="ikuPreview.data?.raw_counts?.A ?? 0"></div>
                        <div class="text-xs text-gray-500">Jumlah permohonan dikerjakan (completed/ready_for_delivery)</div>
                    </div>
                </div>
                <div class="flex items-start gap-2 p-2 bg-white rounded border border-gray-200">
                    <span class="font-bold text-blue-600 w-6">B</span>
                    <div>
                        <div class="font-medium text-gray-800" x-text="ikuPreview.data?.raw_counts?.B ?? 0"></div>
                        <div class="text-xs text-gray-500">Jumlah permohonan diterima (submitted)</div>
                    </div>
                </div>
                <div class="flex items-start gap-2 p-2 bg-white rounded border border-gray-200">
                    <span class="font-bold text-blue-600 w-6">C</span>
                    <div>
                        <div class="font-medium text-gray-800" x-text="ikuPreview.data?.raw_counts?.C ?? 0"></div>
                        <div class="text-xs text-gray-500">Jumlah sampel selesai diuji</div>
                    </div>
                </div>
                <div class="flex items-start gap-2 p-2 bg-white rounded border border-gray-200">
                    <span class="font-bold text-green-600 w-6">D</span>
                    <div>
                        <div class="font-medium text-gray-800" x-text="ikuPreview.data?.raw_counts?.D ?? 0"></div>
                        <div class="text-xs text-gray-500">Target sampel per tahun (dari konfigurasi)</div>
                    </div>
                </div>
                <div class="flex items-start gap-2 p-2 bg-white rounded border border-gray-200">
                    <span class="font-bold text-blue-600 w-6">E</span>
                    <div>
                        <div class="font-medium text-gray-800" x-text="ikuPreview.data?.raw_counts?.E ?? 0"></div>
                        <div class="text-xs text-gray-500">Jumlah LHU diterbitkan</div>
                    </div>
                </div>
                <div class="flex items-start gap-2 p-2 bg-white rounded border border-gray-200">
                    <span class="font-bold text-blue-600 w-6">F</span>
                    <div>
                        <div class="font-medium text-gray-800" x-text="ikuPreview.data?.raw_counts?.F ?? 0"></div>
                        <div class="text-xs text-gray-500">Jumlah survey kepuasan diterima</div>
                    </div>
                </div>
            </div>

            {{-- Formula Explanation --}}
            <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-100">
                <div class="font-medium text-blue-800 text-sm mb-2">Rumus Komponen:</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                    <div class="bg-white p-2 rounded">
                        <span class="font-bold">R</span> = A / B × 100%
                        <div class="text-gray-500">Registrasi Permohonan</div>
                    </div>
                    <div class="bg-white p-2 rounded">
                        <span class="font-bold">P</span> = C / D × 100%
                        <div class="text-gray-500">Pemeriksaan Lab</div>
                    </div>
                    <div class="bg-white p-2 rounded">
                        <span class="font-bold">L</span> = E / A × 100%
                        <div class="text-gray-500">Laporan Hasil</div>
                    </div>
                    <div class="bg-white p-2 rounded">
                        <span class="font-bold">S</span> = F / A × 100%
                        <div class="text-gray-500">Survey Kepuasan</div>
                    </div>
                </div>
                <div class="mt-2 text-xs text-blue-700">
                    <strong>IKU</strong> = (R × Bobot_R + P × Bobot_P + L × Bobot_L + S × Bobot_S) × 5 &nbsp;→&nbsp; Skala 0-5
                </div>
            </div>

            {{-- Category Scale --}}
            <div class="mt-3 text-xs text-gray-600">
                <strong>Kategori:</strong> 
                A (Sangat Baik: 4.51-5) | 
                B (Baik: 3.51-4.50) | 
                C (Cukup: 2.51-3.50) | 
                D (Kurang: 1.51-2.50) | 
                E (Sangat Kurang: ≤1.50)
            </div>
        </div>
    </div>

    <div x-show="!ikuPreview.data && !ikuPreview.loading" class="text-center py-8 text-gray-500">
        <p>Klik "Refresh" untuk melihat preview IKU</p>
    </div>
</div>
