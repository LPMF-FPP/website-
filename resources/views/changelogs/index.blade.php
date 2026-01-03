<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <x-breadcrumbs :items="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Changelogs']]" />
            <div>
                <h1 class="text-2xl font-semibold text-primary-900">Changelogs</h1>
                <p class="text-sm text-accent-600">Riwayat perubahan dan pembaruan sistem LPMF LIMS</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        {{-- Version 1.0.3 --}}
        <div class="card mb-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.0.3</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Latest
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 3 Januari 2026</p>
                </div>
            </div>

            {{-- New Features --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                    </span>
                    Fitur Baru
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Sistem IKU (Indeks Kinerja Utama):</strong> Dashboard card IKU menggantikan SLA Performance dengan preview real-time, konfigurasi bobot, dan target sampel per tahun</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Dummy Data Seeder:</strong> Membuat data testing otomatis termasuk permohonan, sampel, LHU, dan survey kepuasan pelanggan</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Clear Dummy Data:</strong> Command <code class="bg-gray-100 px-1 rounded">php artisan dummy:clear</code> untuk menghapus semua data dummy</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Admin User Persistence:</strong> User admin tidak lagi hilang setelah migration atau seeding</span>
                    </li>
                </ul>
            </div>

            {{-- Bug Fixes --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                    Perbaikan Bug
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix double <code class="bg-gray-100 px-1 rounded">JSON.stringify()</code> di <code class="bg-gray-100 px-1 rounded">saveIkuSettings()</code> yang menyebabkan data tidak tersimpan</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix bug "Tambah Tahun" yang mengubah object menjadi array di konfigurasi IKU</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix IKU samples count selalu 0 - sekarang mengenali status 'ready_for_delivery', 'interpretation_done', 'tested', 'completed'</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix IKU LHU count selalu 0 - sekarang mengenali document type 'laporan_hasil_uji' dan 'lhu'</span>
                    </li>
                </ul>
            </div>

            {{-- UI Improvements --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-purple-100 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </span>
                    Peningkatan UI
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Preview IKU dengan penjelasan komprehensif variabel A sampai F</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Tampilan formula perhitungan R, P, L, S dengan nilai aktual</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Skala kategori IKU (A: >4.00 Sangat Baik sampai E: ≤1.00 Sangat Kurang)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Dashboard IKU card dengan warna dinamis sesuai kategori</span>
                    </li>
                </ul>
            </div>

            {{-- Technical Improvements --}}
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    Peningkatan Teknis
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span><strong>IkuService:</strong> Perbaikan <code class="bg-gray-100 px-1 rounded">getSamplesCompletedCount()</code> dan <code class="bg-gray-100 px-1 rounded">getLhuIssuedCount()</code></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span><strong>DatabaseSeeder:</strong> Integrasi <code class="bg-gray-100 px-1 rounded">AdminUserSeeder</code> untuk memastikan admin user persist</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span><strong>DummyDataSeeder:</strong> Fix enum constraints dan unique constraint violations</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.0.1 --}}
        <div class="card mb-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.0.1</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 31 Desember 2025</p>
                </div>
            </div>

            {{-- New Features --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                    </span>
                    Fitur Baru
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Multi-Suspect Support:</strong> Mendukung pendaftaran lebih dari satu tersangka per permohonan dengan dynamic add/remove</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Non-Polri Investigator:</strong> Dukungan untuk pemohon dari luar kepolisian dengan form terpisah dan NRP sintetik format EXT-XXXXXXXX</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Toggle Penyidik:</strong> Radio button "Apakah Anda penyidik?" untuk memilih antara form Polri atau non-Polri</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Improved Suspect Display:</strong> Tampilan tersangka di halaman index dengan "+N tersangka lainnya" dan card-style di halaman detail</span>
                    </li>
                </ul>
            </div>

            {{-- Bug Fixes --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                    Perbaikan Bug
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix <code class="bg-gray-100 px-1 rounded">deleteDocument()</code> menggunakan undefined <code class="bg-gray-100 px-1 rounded">$request->id</code> (seharusnya <code class="bg-gray-100 px-1 rounded">$testRequest->id</code>)</span>
                    </li>
                </ul>
            </div>

            {{-- UI Improvements --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-purple-100 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </span>
                    Peningkatan UI
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Redesign form Data Tersangka dengan styling oranye dan numbered badges</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Section tersangka sekarang full-width (tidak lagi cramped di dalam grid)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Tombol "Hapus" tersangka dengan icon SVG yang lebih jelas</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Removed: Kolom "Alamat Tersangka" dari form permohonan</span>
                    </li>
                </ul>
            </div>

            {{-- Database Changes --}}
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </span>
                    Perubahan Database
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span><strong>Tabel investigators:</strong> Kolom baru <code class="bg-gray-100 px-1 rounded">is_polri</code>, <code class="bg-gray-100 px-1 rounded">institution</code>, <code class="bg-gray-100 px-1 rounded">occupation</code>, <code class="bg-gray-100 px-1 rounded">alt_phone</code></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span><strong>Tabel suspects:</strong> Tabel baru untuk menyimpan multi tersangka dengan relasi ke <code class="bg-gray-100 px-1 rounded">test_requests</code></span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.0.0 --}}
        <div class="card mb-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.0.0</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 30 Desember 2025</p>
                </div>
            </div>

            {{-- Core Features --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    Fitur Utama
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Manajemen Permohonan Pengujian:</strong> Sistem lengkap untuk mengelola permohonan pengujian dari penyidik, termasuk tracking status dan workflow approval</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Manajemen Sampel (Barang Bukti):</strong> Registrasi, pelabelan, penyimpanan, dan chain of custody untuk sampel narkotika</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Manajemen Penyidik:</strong> Database penyidik lengkap dengan informasi NRP, pangkat, dan satuan kerja</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Manajemen Inventaris:</strong> Pengelolaan alat dan bahan laboratorium dengan fitur stock opname</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Dashboard Analytics:</strong> Statistik dan grafik untuk monitoring kinerja laboratorium</span>
                    </li>
                </ul>
            </div>

            {{-- Document Generation --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-purple-100 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    Sistem Generasi Dokumen
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Laporan Hasil Uji (LHU):</strong> Generate otomatis dengan penomoran per kategori sampel</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Berita Acara:</strong> Template untuk BA Penerimaan, Penyerahan, Pemusnahan, dan lainnya</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Template Editor (Blade):</strong> Inline code editor untuk membuat dan mengedit template dokumen</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Export PDF:</strong> Konversi dokumen ke PDF menggunakan DomPDF</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Penomoran Otomatis:</strong> Sistem penomoran per scope (LHU, BA) dengan konfigurasi fleksibel</span>
                    </li>
                </ul>
            </div>

            {{-- Settings & Configuration --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-gray-100 text-gray-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    Pengaturan & Konfigurasi
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Pengaturan Umum:</strong> Konfigurasi nama laboratorium, alamat, dan informasi institusi</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Pengaturan Dokumen:</strong> Format penomoran, template default, dan konfigurasi ekspor</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Manajemen Pengguna:</strong> Role-based access control (Admin, Analyst, Viewer)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Audit Log:</strong> Pencatatan semua aktivitas sistem untuk compliance</span>
                    </li>
                </ul>
            </div>

            {{-- Bug Fixes --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                    Perbaikan Bug
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix error 500 pada pencarian database</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix error 401 pada fitur pencarian</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix Alpine.js reactivity issues pada forms</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix template editor container dan loading issues</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix template loading dan preview errors</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix data mapping pada Laporan Hasil Uji</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix penomoran per scope untuk dokumen</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>Fix label pada Berita Acara Penyerahan</span>
                    </li>
                </ul>
            </div>

            {{-- Technical Improvements --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-yellow-100 text-yellow-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </span>
                    Peningkatan Teknis
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <span>Refactoring sistem generasi dokumen untuk maintainability</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <span>Implementasi Safe Mode v2 untuk overlay CSS</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <span>Frontend audit system (CSS, JS, accessibility, performance)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <span>Queue system untuk background jobs</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <span>Security fixes dan validasi input</span>
                    </li>
                </ul>
            </div>

            {{-- Infrastructure --}}
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                        </svg>
                    </span>
                    Infrastruktur & Deployment
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>PostgreSQL database support</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>DomPDF untuk PDF generation</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-gray-500">WhatsApp notification <span class="text-xs bg-gray-200 px-1.5 py-0.5 rounded">Planned</span></span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Production deployment checklist</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Tech Stack Reference --}}
        <div class="card bg-gray-50 border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-gray-700 text-white">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </span>
                Tech Stack v1.0.0
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                <div class="bg-white p-2 rounded border">
                    <span class="font-medium text-gray-700">Backend:</span>
                    <span class="text-gray-600">Laravel 12.x (PHP 8.3+)</span>
                </div>
                <div class="bg-white p-2 rounded border">
                    <span class="font-medium text-gray-700">Frontend:</span>
                    <span class="text-gray-600">Alpine.js 3.x + Tailwind 3.x</span>
                </div>
                <div class="bg-white p-2 rounded border">
                    <span class="font-medium text-gray-700">Database:</span>
                    <span class="text-gray-600">PostgreSQL 16+</span>
                </div>
                <div class="bg-white p-2 rounded border">
                    <span class="font-medium text-gray-700">PDF Engine:</span>
                    <span class="text-gray-600">DomPDF ^3.1</span>
                </div>
                <div class="bg-white p-2 rounded border">
                    <span class="font-medium text-gray-700">Build Tool:</span>
                    <span class="text-gray-600">Vite 7.x</span>
                </div>
                <div class="bg-white p-2 rounded border">
                    <span class="font-medium text-gray-700">Queue:</span>
                    <span class="text-gray-600">Database Driver</span>
                </div>
            </div>
        </div>

        {{-- Documentation Note --}}
        <div class="card bg-blue-50 border-blue-200">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-blue-900">Dokumentasi Lengkap</h4>
                    <p class="text-sm text-blue-700 mt-1">
                        Untuk dokumentasi teknis lengkap (PRD, ERD, API), lihat file <code class="bg-blue-100 px-1 rounded">WALKTHROUGH.md</code> di root project.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
