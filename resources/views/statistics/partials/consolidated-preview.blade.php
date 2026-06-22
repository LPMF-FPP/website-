<div class="space-y-6">
    <!-- Header Controls -->
    <div class="flex justify-between items-center bg-gray-50 p-4 rounded-lg border border-gray-200">
        <div>
            <h4 class="text-lg font-medium text-gray-900" x-text="previewData.period_label"></h4>
            <p class="text-sm text-gray-500">Preview Laporan sebelum digenerate menjadi PDF.</p>
        </div>
        <div class="space-x-3">
            <button @click="step = 'form'" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                ← Kembali Edit
            </button>
            <button @click="generateReport()" :disabled="loading" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="loading ? 'Generating...' : '✅ Finalisasi & Generate PDF'"></span>
            </button>
        </div>
    </div>

    <!-- Paper Preview Container -->
    <div class="bg-gray-100 p-8 rounded-lg overflow-x-auto">
        <div class="bg-white shadow-lg mx-auto max-w-[210mm] p-[8mm] text-black text-[10pt] leading-normal font-serif">
            
            <!-- KOP SURAT -->
            <div class="flex items-start justify-between border-b-2 border-black pb-2 mb-4">
                <div class="w-20">
                    <img src="{{ asset('images/logo-tribrata-polri.png') }}" alt="Logo Polri" class="h-16 mx-auto">
                </div>
                <div class="flex-1 text-center px-4">
                    <h2 class="font-bold text-lg uppercase">Pusat Kedokteran dan Kesehatan Polri</h2>
                    <h3 class="font-bold text-xl uppercase">Laboratorium Pengujian Mutu Farmasi Kepolisian</h3>
                    <p class="text-xs">Jl. Cipinang Baru Raya No. 3B, Jakarta Timur 13240 • Telp/Fax: 021-4700921 • Email: labmutufarmapol@gmail.com</p>
                </div>
                <div class="w-20">
                    <img src="{{ asset('images/logo-pusdokkes-polri.svg') }}" alt="Logo Pusdokkes" class="h-16 mx-auto">
                </div>
            </div>

            <!-- JUDUL -->
            <div class="text-center mb-4">
                <h1 class="font-bold text-lg underline uppercase mb-1">Laporan Gabungan Periodik</h1>
                <p class="font-bold" x-text="'Periode: ' + previewData.period_label"></p>
            </div>

            <!-- NARASI PEMBUKA -->
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1 bg-gray-50 p-1 border border-dashed border-gray-300">Editable Area</label>
                <textarea x-model="previewData.narratives.opening" rows="3" class="w-full border-gray-300 rounded shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-justify"></textarea>
            </div>

            <!-- I. STATISTIK OPERASIONAL -->
            <div class="mb-4">
                <h3 class="font-bold mb-2 text-sm">I. STATISTIK OPERASIONAL</h3>
                <table class="w-full border-collapse border border-black text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-black p-2 text-left">Metrik</th>
                            <th class="border border-black p-2 text-center">Nilai Periode Ini</th>
                            <th class="border border-black p-2 text-center">Periode Sebelumnya</th>
                            <th class="border border-black p-2 text-center">Perubahan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="border border-black p-2">Permintaan Masuk</td>
                            <td class="border border-black p-2 text-center font-bold" x-text="previewData.statistics.total_requests_received"></td>
                            <td class="border border-black p-2 text-center text-gray-600" x-text="previewData.comparison.changes.total_requests_received.previous"></td>
                            <td class="border border-black p-2 text-center" :class="previewData.comparison.changes.total_requests_received.diff >= 0 ? 'text-green-600' : 'text-red-600'">
                                <span x-text="previewData.comparison.changes.total_requests_received.diff > 0 ? '+' : ''"></span>
                                <span x-text="previewData.comparison.changes.total_requests_received.diff"></span>
                                (<span x-text="previewData.comparison.changes.total_requests_received.diff_percent"></span>%)
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2">Permintaan Selesai</td>
                            <td class="border border-black p-2 text-center font-bold" x-text="previewData.statistics.total_requests_completed"></td>
                            <td class="border border-black p-2 text-center text-gray-600" x-text="previewData.comparison.changes.total_requests_completed.previous"></td>
                            <td class="border border-black p-2 text-center" :class="previewData.comparison.changes.total_requests_completed.diff >= 0 ? 'text-green-600' : 'text-red-600'">
                                <span x-text="previewData.comparison.changes.total_requests_completed.diff > 0 ? '+' : ''"></span>
                                <span x-text="previewData.comparison.changes.total_requests_completed.diff"></span>
                                (<span x-text="previewData.comparison.changes.total_requests_completed.diff_percent"></span>%)
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2">Sampel Diterima</td>
                            <td class="border border-black p-2 text-center font-bold" x-text="previewData.statistics.total_samples_received"></td>
                            <td class="border border-black p-2 text-center text-gray-600" x-text="previewData.comparison.changes.total_samples_received.previous"></td>
                            <td class="border border-black p-2 text-center" :class="previewData.comparison.changes.total_samples_received.diff >= 0 ? 'text-green-600' : 'text-red-600'">
                                <span x-text="previewData.comparison.changes.total_samples_received.diff > 0 ? '+' : ''"></span>
                                <span x-text="previewData.comparison.changes.total_samples_received.diff"></span>
                                (<span x-text="previewData.comparison.changes.total_samples_received.diff_percent"></span>%)
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2">Sampel Diuji</td>
                            <td class="border border-black p-2 text-center font-bold" x-text="previewData.statistics.total_samples_tested"></td>
                            <td class="border border-black p-2 text-center text-gray-600" x-text="previewData.comparison.changes.total_samples_tested.previous"></td>
                            <td class="border border-black p-2 text-center" :class="previewData.comparison.changes.total_samples_tested.diff >= 0 ? 'text-green-600' : 'text-red-600'">
                                <span x-text="previewData.comparison.changes.total_samples_tested.diff > 0 ? '+' : ''"></span>
                                <span x-text="previewData.comparison.changes.total_samples_tested.diff"></span>
                                (<span x-text="previewData.comparison.changes.total_samples_tested.diff_percent"></span>%)
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2">LHU Terbit</td>
                            <td class="border border-black p-2 text-center font-bold" x-text="previewData.statistics.total_lhu_issued"></td>
                            <td class="border border-black p-2 text-center text-gray-600" x-text="previewData.comparison.changes.total_lhu_issued.previous"></td>
                            <td class="border border-black p-2 text-center" :class="previewData.comparison.changes.total_lhu_issued.diff >= 0 ? 'text-green-600' : 'text-red-600'">
                                <span x-text="previewData.comparison.changes.total_lhu_issued.diff > 0 ? '+' : ''"></span>
                                <span x-text="previewData.comparison.changes.total_lhu_issued.diff"></span>
                                (<span x-text="previewData.comparison.changes.total_lhu_issued.diff_percent"></span>%)
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- II. REKAP ZAT AKTIF -->
            <div class="mb-4">
                <h3 class="font-bold mb-2 text-sm">II. REKAP ZAT AKTIF</h3>
                <div class="flex gap-4">
                    <!-- Chart Placeholder (Future Phase) -->
                    <!-- <div class="w-1/3 border border-gray-300 flex items-center justify-center bg-gray-50 text-xs text-gray-400">Chart Visualization</div> -->
                    
                    <table class="w-full border-collapse border border-black text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-black p-2 w-10 text-center">No</th>
                                <th class="border border-black p-2 text-left">Nama Zat Aktif</th>
                                <th class="border border-black p-2 text-center w-24">Jumlah</th>
                                <th class="border border-black p-2 text-center w-24">Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in previewData.active_substances.items" :key="index">
                                <tr>
                                    <td class="border border-black p-2 text-center" x-text="index + 1"></td>
                                    <td class="border border-black p-2 font-medium" x-text="item.name"></td>
                                    <td class="border border-black p-2 text-center" x-text="item.count"></td>
                                    <td class="border border-black p-2 text-center" x-text="item.percentage + '%'"></td>
                                </tr>
                            </template>
                            <template x-if="previewData.active_substances.items.length === 0">
                                <tr>
                                    <td colspan="4" class="border border-black p-4 text-center text-gray-500 italic">Tidak ada zat aktif terdeteksi pada periode ini.</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot x-show="previewData.active_substances.items.length > 0">
                            <tr class="font-bold bg-gray-50">
                                <td colspan="2" class="border border-black p-2 text-right">TOTAL</td>
                                <td class="border border-black p-2 text-center" x-text="previewData.active_substances.total"></td>
                                <td class="border border-black p-2 text-center">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- III. KECEPATAN PENGERJAAN & IV. KEPUASAN PELANGGAN -->
            <div class="mb-4 grid grid-cols-2 gap-4">
                <!-- III. KECEPATAN PENGERJAAN -->
                <div>
                    <h3 class="font-bold mb-2 text-sm">III. KECEPATAN PENGERJAAN</h3>
                    <div class="border border-black p-3 text-xs bg-white h-full">
                        <p class="mb-2"><strong>Rata-rata Waktu Pengerjaan:</strong> <span x-text="previewData.processing_time.avg_days"></span> hari</p>
                        <p class="mb-3"><strong>Total Permintaan Selesai:</strong> <span x-text="previewData.processing_time.total"></span></p>
                        <p class="mb-2 font-bold">Breakdown:</p>
                        <ul class="list-disc pl-5">
                            <template x-for="item in previewData.processing_time.categories">
                                <li>
                                    <span x-text="item.label"></span>: 
                                    <span x-text="item.count"></span> 
                                    (<span x-text="item.percentage + '%'"></span>)
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <!-- IV. KEPUASAN PELANGGAN -->
                <div>
                    <h3 class="font-bold mb-2 text-sm">IV. KEPUASAN PELANGGAN</h3>
                    <div class="border border-black p-3 text-xs bg-white h-full">
                        <p class="mb-2"><strong>Skor Rata-rata:</strong> <span x-text="previewData.satisfaction.avg_score"></span> / 4.00</p>
                        <p class="mb-3"><strong>Total Responden:</strong> <span x-text="previewData.satisfaction.total_respondents"></span></p>
                        <p class="mb-2 font-bold">Distribusi Rating:</p>
                        <ul class="list-disc pl-5">
                            <template x-for="item in previewData.satisfaction.ratings">
                                <li>
                                    <span x-text="item.label"></span>: 
                                    <span x-text="item.count"></span> 
                                    (<span x-text="item.percentage + '%'"></span>)
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- V. DEMOGRAFI TERSANGKA & VI. RENTANG UMUR -->
            <div class="mb-4 grid grid-cols-2 gap-6">
                <!-- Gender -->
                <div>
                    <h3 class="font-bold mb-2 text-sm">V. GENDER TERSANGKA</h3>
                    <div class="border border-black p-4 text-sm bg-white h-full">
                        <p class="mb-3"><strong>Total Tersangka:</strong> <span x-text="previewData.gender.total"></span></p>
                        <ul class="list-disc pl-5">
                            <template x-for="item in previewData.gender.items">
                                <li>
                                    <span x-text="item.label || 'Tidak Diketahui'"></span>: 
                                    <span x-text="item.count"></span> 
                                    (<span x-text="item.percentage + '%'"></span>)
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <!-- Umur -->
                <div>
                    <h3 class="font-bold mb-2 text-sm">VI. RENTANG UMUR</h3>
                    <div class="border border-black p-4 text-sm bg-white h-full">
                        <p class="mb-3"><strong>Total Tersangka:</strong> <span x-text="previewData.age_range.total"></span></p>
                        <ul class="list-disc pl-5">
                            <template x-for="item in previewData.age_range.items">
                                <li>
                                    <span x-text="item.label"></span>: 
                                    <span x-text="item.count"></span> 
                                    (<span x-text="item.percentage + '%'"></span>)
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- VII. ASAL USER (TOP 10) -->
            <div class="mb-4">
                <h3 class="font-bold mb-2 text-sm">VII. ASAL USER (TOP 10 JURISDICTION)</h3>
                <table class="w-full border-collapse border border-black text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-black p-2 text-center w-10">No</th>
                            <th class="border border-black p-2 text-left">Jurisdiction / Satuan</th>
                            <th class="border border-black p-2 text-center w-24">Jumlah</th>
                            <th class="border border-black p-2 text-center w-24">Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in previewData.jurisdiction.items" :key="index">
                            <tr>
                                <td class="border border-black p-2 text-center" x-text="index + 1"></td>
                                <td class="border border-black p-2" x-text="item.label"></td>
                                <td class="border border-black p-2 text-center" x-text="item.count"></td>
                                <td class="border border-black p-2 text-center" x-text="item.percentage + '%'"></td>
                            </tr>
                        </template>
                        <template x-if="previewData.jurisdiction.items.length === 0">
                            <tr><td colspan="4" class="border border-black p-4 text-center italic">Tidak ada data.</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- VIII. IKU (Quarterly Only) -->
            <template x-if="previewData.iku">
                <div class="mb-4">
                    <h3 class="font-bold mb-2 text-sm">VIII. INDEKS KINERJA UTAMA (IKU)</h3>
                    <div class="border border-black p-4 bg-gray-50 mb-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-sm text-gray-600">Nilai IKU:</span>
                                <span class="text-2xl font-bold ml-2" x-text="previewData.iku.iku_value"></span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Kategori:</span>
                                <span class="text-xl font-bold ml-2 px-3 py-1 bg-white border border-gray-300 rounded" x-text="previewData.iku.iku_category"></span>
                            </div>
                        </div>
                    </div>
                    
                    <table class="w-full border-collapse border border-black text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-black p-2 text-left">Komponen</th>
                                <th class="border border-black p-2 text-center">Bobot</th>
                                <th class="border border-black p-2 text-center">Nilai Indeks</th>
                                <th class="border border-black p-2 text-center">Data Mentah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-black p-2">Registrasi Permohonan (R)</td>
                                <td class="border border-black p-2 text-center" x-text="previewData.iku.weights.registration + '%'"></td>
                                <td class="border border-black p-2 text-center" x-text="previewData.iku.indexes.registration"></td>
                                <td class="border border-black p-2 text-center text-xs">
                                    <span x-text="previewData.iku.raw_counts.A"></span>/<span x-text="previewData.iku.raw_counts.B"></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-black p-2">Pemeriksaan Laboratorium (P)</td>
                                <td class="border border-black p-2 text-center" x-text="previewData.iku.weights.lab_exam + '%'"></td>
                                <td class="border border-black p-2 text-center" x-text="previewData.iku.indexes.lab_exam"></td>
                                <td class="border border-black p-2 text-center text-xs">
                                    <span x-text="previewData.iku.raw_counts.C"></span>/<span x-text="previewData.iku.raw_counts.D"></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-black p-2">Laporan Hasil (L)</td>
                                <td class="border border-black p-2 text-center" x-text="previewData.iku.weights.report + '%'"></td>
                                <td class="border border-black p-2 text-center" x-text="previewData.iku.indexes.report"></td>
                                <td class="border border-black p-2 text-center text-xs">
                                    <span x-text="previewData.iku.raw_counts.E"></span>/<span x-text="previewData.iku.raw_counts.A"></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="border border-black p-2">Survei Kepuasan (S)</td>
                                <td class="border border-black p-2 text-center" x-text="previewData.iku.weights.survey + '%'"></td>
                                <td class="border border-black p-2 text-center" x-text="previewData.iku.indexes.survey"></td>
                                <td class="border border-black p-2 text-center text-xs">
                                    <span x-text="previewData.iku.raw_counts.F"></span>/<span x-text="previewData.iku.raw_counts.A"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- LAMPIRAN STATISTIK DASHBOARD -->
            <template x-if="previewData.dashboard_appendix">
            <div class="mb-4 rounded-lg border border-slate-300 bg-slate-50 p-4">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-bold mb-1 text-sm uppercase tracking-wide text-slate-900">Lampiran Statistik Dashboard</h3>
                        <p class="text-xs text-slate-600">Visual ringkas dari chart dan angka dashboard statistik sesuai periode laporan.</p>
                    </div>
                    <span class="rounded-full border border-slate-300 bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">Appendix</span>
                </div>

                <div class="grid grid-cols-4 gap-2 mb-4">
                    <template x-for="card in (previewData.dashboard_appendix.summary_cards || [])" :key="card.label">
                        <div class="rounded-md border border-slate-300 bg-white p-3 text-xs shadow-sm">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500" x-text="card.label"></p>
                            <p class="mt-1 text-xl font-bold text-slate-950" x-text="card.value"></p>
                            <p class="mt-1 text-[10px] leading-tight text-slate-500" x-text="card.note"></p>
                        </div>
                    </template>
                </div>

                <div class="mb-4 grid grid-cols-2 gap-2">
                    <template x-for="row in (previewData.dashboard_appendix.summary_table || [])" :key="row.category">
                        <div class="rounded-md border border-slate-300 bg-white p-3 text-[10px] shadow-sm">
                            <p class="font-bold uppercase tracking-wide text-slate-700" x-text="row.category"></p>
                            <p class="mt-1 text-slate-600">
                                <span class="font-bold text-slate-950" x-text="row.period_value"></span>
                                <span> periode ini, </span>
                                <span class="font-bold text-slate-950" x-text="row.year_value"></span>
                                <span> tahun berjalan.</span>
                            </p>
                            <p class="mt-1 text-slate-500">
                                <span>Target </span><span class="font-semibold" x-text="row.target"></span>
                                <span> · Status </span><span class="font-semibold" x-text="row.status"></span>
                            </p>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <template x-for="chart in (previewData.dashboard_appendix.charts || [])" :key="chart.title">
                    <div class="page-break-inside-avoid rounded-md border border-slate-300 bg-white p-3 shadow-sm" :class="['Permintaan per Bulan', 'Sampel vs Target IKU'].includes(chart.title) ? 'col-span-2' : ''">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h4 class="text-xs font-bold uppercase tracking-wide text-slate-800" x-text="chart.title"></h4>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600" x-text="chart.total + ' total'"></span>
                        </div>

                        <template x-if="(chart.rows || []).length === 0">
                            <div class="rounded border border-dashed border-slate-300 p-4 text-center text-xs italic text-slate-500">Tidak ada data pada periode ini.</div>
                        </template>

                        <template x-if="(chart.rows || []).length > 0 && ['pie', 'doughnut'].includes(chart.type)">
                            <div class="flex items-center gap-4">
                                <div class="relative h-24 w-24 shrink-0 rounded-full border border-slate-200 shadow-inner" :style="`background: ${pieGradient(chart.rows)}`">
                                    <div x-show="chart.type === 'doughnut'" class="absolute inset-5 rounded-full bg-white border border-slate-200"></div>
                                </div>
                                <div class="min-w-0 flex-1 space-y-1 text-[10px]">
                                    <template x-for="(row, index) in topRowsWithOther(chart.rows, 5)" :key="row.label">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 shrink-0 rounded-full" :style="`background-color: ${chartColor(index)}`"></span>
                                            <span class="min-w-0 flex-1 truncate" x-text="row.label"></span>
                                            <span class="font-bold" x-text="row.count + ' / ' + row.percentage + '%'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="(chart.rows || []).length > 0 && chart.type === 'bar'">
                            <div class="space-y-1.5 text-[10px]">
                                <template x-for="(row, index) in topRows(chart.rows, 6)" :key="row.label">
                                    <div>
                                        <div class="mb-0.5 flex justify-between gap-2">
                                            <span class="truncate" x-text="row.label"></span>
                                            <span class="font-bold" x-text="row.count + ' (' + row.percentage + '%)'"></span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-100">
                                            <div class="h-2 rounded-full" :style="`width: ${percentOf(row.count, maxValue(chart.rows, 'count'))}%; background-color: ${chartColor(index)}`"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="chart.title === 'Permintaan per Bulan'">
                            <div class="text-[10px]">
                                <div class="relative w-full rounded border border-slate-200 bg-slate-50 p-2" style="height: 180px;">
                                    <svg viewBox="0 0 356 188" class="absolute inset-0 h-full w-full">
                                        <line x1="28" y1="156" x2="328" y2="156" stroke="#cbd5e1" stroke-width="1" />
                                        <line x1="28" y1="96" x2="328" y2="96" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3 3" />
                                        <polyline :points="linePoints(chart.rows, 'requests', 300, 118, 28, 28, ['requests', 'completed'])" fill="none" stroke="#1d4ed8" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                                        <polyline :points="linePoints(chart.rows, 'completed', 300, 118, 28, 28, ['requests', 'completed'])" fill="none" stroke="#059669" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="5 4" />
                                        <g x-show="hasPositiveValue(chart.rows, 'requests') || hasPositiveValue(chart.rows, 'completed')" x-html="lineSvgLabels(chart.rows, 'requests', '#1d4ed8', -18, 300, 118, 28, 28, ['requests', 'completed'])"></g>
                                        <g x-show="hasPositiveValue(chart.rows, 'requests') || hasPositiveValue(chart.rows, 'completed')" x-html="lineSvgLabels(chart.rows, 'completed', '#059669', 18, 300, 118, 28, 28, ['requests', 'completed'])"></g>
                                    </svg>
                                    <template x-if="!hasPositiveValue(chart.rows, 'requests') && !hasPositiveValue(chart.rows, 'completed')">
                                        <div class="absolute inset-x-6 top-1/2 -translate-y-1/2 rounded border border-dashed border-slate-300 bg-white/80 py-2 text-center text-[10px] font-semibold text-slate-500">Tidak ada tren permintaan pada periode 12 bulan ini.</div>
                                    </template>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-slate-600">
                                    <span><span class="inline-block h-2 w-4 rounded-sm bg-blue-700"></span> Masuk</span>
                                    <span><span class="inline-block h-2 w-4 rounded-sm bg-emerald-600"></span> Selesai</span>
                                    <span class="font-bold" x-text="chart.total + ' permintaan'"></span>
                                </div>
                            </div>
                        </template>

                        <template x-if="chart.title === 'Sampel vs Target IKU'">
                            <div class="text-[10px]">
                                <div class="relative w-full rounded border border-slate-200 bg-slate-50 p-2" style="height: 180px;">
                                    <svg viewBox="0 0 356 188" class="absolute inset-0 h-full w-full">
                                        <line x1="28" y1="156" x2="328" y2="156" stroke="#cbd5e1" stroke-width="1" />
                                        <line x1="28" y1="96" x2="328" y2="96" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3 3" />
                                        <polyline :points="linePoints(chart.rows, 'samples', 300, 118, 28, 28, ['samples', 'target'])" fill="none" stroke="#059669" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                                        <polyline :points="linePoints(chart.rows, 'target', 300, 118, 28, 28, ['samples', 'target'])" fill="none" stroke="#dc2626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="6 4" />
                                        <g x-html="lineSvgLabels(chart.rows, 'samples', '#059669', 18, 300, 118, 28, 28, ['samples', 'target'])"></g>
                                        <g x-html="lineSvgEndpointLabel(chart.rows, 'target', '#dc2626', -18, 300, 118, 28, 28, ['samples', 'target'], 'right')"></g>
                                    </svg>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-slate-600">
                                    <span><span class="inline-block h-2 w-4 rounded-sm bg-emerald-600"></span> Aktual</span>
                                    <span><span class="inline-block h-2 w-4 rounded-sm bg-red-600"></span> Target</span>
                                    <span class="font-bold" x-text="chart.total + '/' + (chart.target?.yearly ?? 200) + ' sampel'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                    </template>
                </div>
            </div>
            </template>

            <!-- NARASI PENUTUP -->
            <div class="mb-8">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1 bg-gray-50 p-1 border border-dashed border-gray-300">Editable Area</label>
                <textarea x-model="previewData.narratives.closing" rows="3" class="w-full border-gray-300 rounded shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-justify"></textarea>
            </div>

            <!-- TANDA TANGAN -->
            <div class="flex justify-between items-start mt-12 gap-4">
                <template x-for="signer in previewData.signers">
                    <div class="text-center flex-1">
                        <p class="mb-20 font-medium" x-text="signer.role"></p>
                        <p class="font-bold underline uppercase" x-text="signer.name"></p>
                        <p class="text-sm" x-text="signer.position"></p>
                        <p class="text-xs" x-show="signer.nip" x-text="'NIP/NRP: ' + signer.nip"></p>
                    </div>
                </template>
            </div>

            <!-- FOOTER -->
            <div class="mt-8 pt-4 border-t border-gray-200 text-xs text-gray-500 text-right italic">
                Dokumen ini digenerate secara otomatis pada <span x-text="new Date().toLocaleString('id-ID')"></span>
            </div>
        </div>
    </div>
</div>
