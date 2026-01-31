<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <x-breadcrumbs :items="[['label' => 'Beranda', 'href' => route('dashboard')], ['label' => 'Changelogs']]" />
            <div>
                <h1 class="text-2xl font-semibold text-primary-900">Changelogs</h1>
                <p class="text-sm text-accent-600">Riwayat perubahan dan pembaruan sistem LPMF LIMS</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- Version 1.8.2 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.8.2</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Latest</span>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 31 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                    Flexible Reminders & Dashboard Precision
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Flexible Reminder Schedule:</strong> Admin sekarang bisa memilih hari spesifik (Senin-Minggu) untuk setiap reminder WhatsApp.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Dashboard Precision:</strong> Perbaikan logika tanggal HASIL (menggunakan waktu kirim Delivery) dan status Aging (menggunakan Hari Kerja).</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Improved Logic:</strong> Status Merah/Kuning sekarang mengecualikan hari Sabtu-Minggu agar lebih adil dalam pengukuran kinerja.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.7.9 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5h6m-6 4h6m-7 4h8m-6 4h4M7 5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H7z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.7.9</h2>
                        {{-- <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Latest</span> --}}
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 29 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2M20 12a8 8 0 11-16 0 8 8 0 0116 0z" />
                        </svg>
                    </span>
                    Manajemen Penyidik & Presisi Label 121
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Manajemen Penyidik (Admin):</strong> Halaman baru untuk lihat, edit, dan hapus biodata penyidik dengan kontrol permission.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Label 121 Presisi:</strong> Ukuran 75×38mm untuk sheet dan single, grid 2×5 A4 dengan offset tetap.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Navigasi:</strong> Menu “Manajemen Penyidik” tersedia di Referensi.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.7.8 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.7.8</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 24 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </span>
                    IKU Quarterly Support & Dashboard Icons
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>IKU Triwulan:</strong> Sistem kini mendukung perhitungan Indeks Kinerja Utama per Triwulan dengan target sampel otomatis dibagi 4.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Dashboard Icons:</strong> Penambahan ikon visual pada card Kecepatan Pengerjaan (Jam) dan Kepuasan Pelanggan (Senyum) untuk estetika yang lebih baik.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Settings Updated:</strong> Opsi periode "Triwulan" telah ditambahkan dan divalidasi di menu Settings > Perhitungan IKU.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.7.7 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-teal-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.7.7</h2>
                        {{-- <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Latest</span> --}}
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 22 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-teal-100 text-teal-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                        </svg>
                    </span>
                    Request Detail Page UX Redesign
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Header Summary Card:</strong> Info ringkas (No. Resi, Status, Penyidik, Jumlah Sampel) di bagian atas halaman.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Reminder Cetak BA:</strong> Petunjuk dismissable untuk cetak BA 2 rangkap dan serahkan ke Administrasi.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Collapsible Sections:</strong> Section expand/collapse untuk Sampel, Dokumen, dan Data Penyidik.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Document Grid:</strong> Tampilan kartu dokumen dengan thumbnail dan aksi hover.</span>
                    </li>
                </ul>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-amber-100 text-amber-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                    Bug Fixes
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Task Templates:</strong> Template notifikasi WhatsApp untuk task sekarang muncul di Settings.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>BA Update:</strong> Dokumen BA sekarang dihapus sepenuhnya saat edit permintaan.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Inactive Staff:</strong> Staff nonaktif tidak lagi muncul di dropdown analis.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.7.6 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.7.6</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 19 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </span>
                    PostgreSQL Compatibility Fix
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Database Agnostic:</strong> Mengatasi error 500 pada fitur Numbering Repair di PostgreSQL dengan mengganti raw MySQL JSON functions ke Eloquent JSON syntax.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Affected Scopes:</strong> Perbaikan diterapkan pada scope Laporan Hasil Uji (lhu) dan Berita Acara Penyerahan.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.7.4 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-gray-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.7.4</h2>
                        {{-- <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Latest</span> --}}
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 18 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-gray-100 text-gray-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    Settings Page Cleanup
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-gray-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span><strong>Notifications & Security:</strong> Menghapus pengaturan Email, SMTP, dan Role management. Menyisakan konfigurasi WhatsApp.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-gray-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span><strong>Localization & Retention:</strong> Menghapus pengaturan Format Tanggal dan Angka.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.7.3 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.7.3</h2>
                        {{-- <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Latest</span> --}}
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 18 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    RAMS Accessibility & UI Polish
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Landing Page Overhaul:</strong> Pemulihan kursor sistem, perbaikan kontras, dan indikator fokus yang lebih baik.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Accessibility Fixes:</strong> Modals kini memiliki focus trap dan escape handler; form input memiliki label yang tepat.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Reduced Motion:</strong> Animasi berat dinonaktifkan jika preferensi pengguna diatur ke "Reduced Motion".</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Heading Hierarchy:</strong> Memperbaiki struktur heading di dashboard dan navigation untuk memenuhi standar WCAG 2.1.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Test Stability:</strong> Peningkatan stabilitas Dusk tests dengan DatabaseTruncation dan form-based login.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.7.2 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.7.2</h2>
                        {{-- <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">Latest</span> --}}
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 18 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    WhatsApp Templates & Admin Access
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Format resmi WhatsApp:</strong> Template baru untuk permintaan diterima, ditolak, siap diambil, dan serah-terima selesai.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Milestone disederhanakan:</strong> Notifikasi hanya memakai 4 milestone utama.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Admin akses penuh:</strong> Seeder admin otomatis meng-assign semua permission.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.6.5 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.6.5</h2>
                        {{-- Older version - no badge --}}
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 15 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    Numbering Repair & Sync System
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Reset Manual Counter:</strong> Admin dapat mengatur counter ke nilai tertentu dengan alasan wajib.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Auto-Sync Counter:</strong> Sinkronisasi otomatis dari nomor tertinggi atau jumlah dokumen.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Deteksi Masalah:</strong> Scan otomatis untuk menemukan duplikat dan gap dalam penomoran.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Audit Logging:</strong> Semua perubahan tercatat dengan alasan wajib untuk transparansi.</span>
                    </li>
                </ul>
            </div>

            <div class="bg-indigo-50 border-l-4 border-indigo-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-indigo-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-indigo-700">
                            <strong>Lokasi:</strong> Settings > Penomoran Otomatis > Perbaikan & Sinkronisasi Nomor
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Version 1.6.2 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-emerald-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.6.2</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 15 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-emerald-100 text-emerald-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    WhatsApp Template Refresh
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Greeting Made Clear:</strong> Sapaan dibikin langsung "Halo …" agar mudah dipahami user.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Friendly Emojis:</strong> Menambahkan emoji agar pesan terasa lebih ramah.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Defaults Updated:</strong> Template default dan template tersimpan di settings ikut diperbarui.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.5.7 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 9h2a1 1 0 001-1v-2a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 18h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.5.7</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Latest
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 13 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    Search Results UI/UX Fixes
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Encrypted Data Fix:</strong> Search results now correctly decrypt suspect names instead of showing raw ciphertext.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Layout Stability:</strong> Added text truncation to prevent long names from breaking the card layout.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Loading State:</strong> Fixed skeleton animation visibility to properly disappear when results are loaded.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.5.6 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.5.6</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 13 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </span>
                    Settings Page State Reactivity Fix
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Fixed Reactivity:</strong> Solved issue where UI didn't update after saving settings due to Alpine.js state merging strategy.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Notification Validation:</strong> Fixed email notification validation errors by aligning frontend field names with backend expectations.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Enhanced UI:</strong> Added subject and body fields to email notification settings.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.4.8 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.4.8</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Latest
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 12 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </span>
                    UI Revamp: Clinical Precision
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Concept:</strong> "Clinical Precision" theme for sterile, professional medical/police look.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Visuals:</strong> Tighter radius (4px), flat aesthetics (no shadows), solid white headers.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Navigation:</strong> Vertical divider for logo and improved spacing (gap).</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.4.6 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.4.6</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 12 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    Template Preview Data Fix
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Fixed Preview Error:</strong> Resolved "Undefined array key deskripsi_singkat" error when previewing Evidence Labels.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Data Consistency:</strong> Added missing keys to both real and dummy data generators for template compatibility.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><strong>Regression Testing:</strong> Added new test case to prevent future regressions.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.4.5 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.4.5</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 12 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    Form Stepper Scroll Tracking Fix
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Fixed Step Activation:</strong> Resolved issue where Steps 4 and 5 were not marking as active/completed even when scrolled to the bottom.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Improved Tracking Logic:</strong> Replaced IntersectionObserver with robust ScrollSpy logic using scroll event listener.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Bottom-of-Page Detection:</strong> Added explicit check for end-of-page scroll to force the last step to activate.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.4.4 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.4.4</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 12 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-purple-100 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </span>
                    Form Stepper Connector Fix
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Fixed Broken Connectors:</strong> Replaced absolute positioning with Flex-Grow implementation to ensure connector lines consistently bridge steps regardless of label width.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Improved Responsiveness:</strong> Adjusted line rendering for mobile devices (hidden labels, simplified flow).</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Layout Stability:</strong> Fixed issue where lines would disconnect or misalign when steps wrapped.</span>
                    </li>
                </ul>
            </div>
        </div>
        
        {{-- Version 1.4.3 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.4.3</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 12 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-purple-100 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </span>
                    Form Stepper Redesign
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Modern UI:</strong> Glassmorphism sticky header, refined shadows, and smoother transitions.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Active State:</strong> Indikator langkah aktif lebih menonjol dengan ring effect dan scaling.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Mobile Optimized:</strong> Layout yang lebih bersih dan responsif untuk layar kecil.</span>
                    </li>
                </ul>
            </div>
        </div>
        
        {{-- Version 1.4.2 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.4.2</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 12 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </span>
                    Stylelint & CSS Audit Fixes
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Stylelint Configuration:</strong> Updated config to support Tailwind directives and moved report options to root.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>CSS Standardization:</strong> Modern color syntax (rgb/a), lowercase keywords, and hex color optimization.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Formatting Fixes:</strong> Fixed syntax errors in fonts.css and enforced consistent spacing/empty lines.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Accessibility:</strong> Fixed universal selector issues in a11y.css and ensured WCAG color compliance.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.4.1 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.4.1</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 12 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </span>
                    Pembaruan Alur Permintaan & Pengujian
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Renaming Tahapan:</strong> Pengujian → Kaji Ulang Permintaan, Proses → Pengujian</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Review Flow Lock:</strong> Metode uji yang diminta terkunci, reviewer dapat menambah metode tambahan</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Rejection Flow:</strong> Wajib alasan penolakan + notifikasi WhatsApp REQUEST_REJECTED</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Legacy Redirect:</strong> Rute lama dialihkan ke rute baru agar link lama tetap berjalan</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Test Update:</strong> Test rute dan rejection flow disesuaikan dengan perubahan terbaru</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><strong>Bug Fix:</strong> Perbaikan 500 error pada Form Kaji Ulang Permintaan (masalah kompiler Blade).</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.4.0 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.4.0</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 11 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </span>
                    WhatsApp Webhook Finalization
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Status Operasional:</strong> Webhook WhatsApp aktif dan stabil untuk menerima event masuk</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Payload Handling Fix:</strong> Format payload GOWA dinormalisasi agar parsing perintah berjalan konsisten</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Docker Networking Fix:</strong> Webhook URL diperbarui ke <code>172.17.0.1</code> untuk akses host dari container</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.3.3 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.3.3</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 11 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </span>
                    WhatsApp Dynamic Greeting System
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Dynamic Time-Based Greeting:</strong> Sapaan otomatis berubah sesuai waktu - Selamat Pagi (05:00-10:59), Siang (11:00-14:59), Sore (15:00-18:59), Malam (19:00-04:59)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Role-Based Salutation:</strong> Sapaan disesuaikan jabatan - POLRI menggunakan pangkat (contoh: "Selamat Pagi IPDA Ahmad Yani"), non-POLRI menggunakan Bapak/Ibu</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Individual Test Buttons:</strong> Tombol test 🧪 pada setiap milestone template di halaman Settings dengan feedback real-time dan auto-clear setelah 5 detik</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Template Placeholder Update:</strong> {nama_penyidik} diganti dengan {greeting} untuk dynamic greeting generation</span>
                    </li>
                </ul>
            </div>

            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Contoh Dynamic Greeting:</strong> Pada jam 14:00 WIB, investigator non-POLRI akan menerima sapaan "Selamat Siang Bapak/Ibu [Nama]", sedangkan POLRI akan menerima "Selamat Siang [Pangkat] [Nama]"
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="flex items-start gap-2 text-gray-600">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <span><strong>Test Coverage:</strong> 7/7 milestones tested, 17 messages sent successfully</span>
                    </div>
                    <div class="flex items-start gap-2 text-gray-600">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><strong>Timezone:</strong> Asia/Jakarta (WIB) untuk dynamic greetings</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Version 1.2.5 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.2.5</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-purple-100 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </span>
                    Advanced Monitoring Tools
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Laravel Telescope:</strong> Dashboard debugging untuk dev/staging di /telescope (admin/supervisor only)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Laravel Pulse:</strong> Real-time metrics production di /pulse dengan monitoring slow queries, jobs, exceptions</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Sentry:</strong> Error tracking otomatis dengan performance monitoring (10% sample rate)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Slack Alerts:</strong> Notifikasi real-time untuk critical errors via webhook</span>
                    </li>
                </ul>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Setup guide: <strong>PRODUCTION_READINESS.md</strong> - Monitoring Tools Section</span>
                </div>
            </div>
        </div>

        {{-- Version 1.2.4 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.2.4</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    Production-Ready Observability & Monitoring
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Health Checks:</strong> Endpoints /health (full check), /health/liveness, /health/readiness untuk monitoring</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Query Monitoring:</strong> Auto-log slow queries (>1000ms warning, >3000ms error) untuk optimasi performa</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Error Tracking:</strong> Sentry/Flare integration dengan rate limiting (5 errors/menit per tipe)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Security:</strong> PII encryption (suspect_name, suspect_address) + API rate limiting (60 req/menit)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Resilience:</strong> Service timeouts (WhatsApp, S3), exponential backoff retry untuk jobs</span>
                    </li>
                </ul>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Dokumentasi lengkap: <strong>PRODUCTION_READINESS.md</strong></span>
                </div>
            </div>
        </div>

        {{-- Version 1.2.3 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-gray-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.2.3</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    Precognition & Optimistic UI Guide
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Panduan Precognition:</strong> Setup route, FormRequest, dan Alpine.js integration untuk validasi realtime</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Optimistic UI Patterns:</strong> Toggle, inline edit, delete + undo dengan rollback + toast a11y</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Template & Testing:</strong> Checklist implementasi, template siap copy-paste, strategi testing</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.2.2 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.2.2</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    Alpine.js Frontend Patterns Guide
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Panduan Lengkap Alpine:</strong> state management, modal/dialog, aksesibilitas, performa, dan toast</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Motion & A11y:</strong> token timing 150/200/300ms, reduced-motion, live region, focus trap</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Loading Matrix & Troubleshooting:</strong> skeleton, spinner, toast, x-cloak, reactivity tips</span>
                    </li>
                </ul>
            </div>

            <div class="p-4 bg-indigo-50 rounded-lg text-xs text-gray-600">
                <strong>Referensi:</strong> Lihat dokumentasi lengkap di <code>WALKTHROUGH.md</code> (Panduan Pengembangan).
            </div>
        </div>

        {{-- Version 1.1.6 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.1.6</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    Semantic Versioning Constraint
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Aturan Versioning Baru:</strong> Setiap segmen versi (MAJOR.MINOR.PATCH) maksimal 9. Contoh: <code>1.0.9</code> → <code>1.1.0</code> (bukan <code>1.0.10</code>)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Retroactive Fix:</strong> 6 versi sebelumnya di-renumber untuk comply dengan constraint baru</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Dokumentasi Standar:</strong> AGENTS.md dan WALKTHROUGH.md diupdate dengan aturan versioning</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.1.5 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.1.5</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-purple-100 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </span>
                    UI/UX Phase 4 - Form Stepper Integration
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Form Stepper Terintegrasi:</strong> Halaman pembuatan permintaan (1166 baris) kini dilengkapi progress indicator 5 langkah</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Auto-Tracking:</strong> Progress bar sticky di atas, otomatis highlight langkah aktif saat scroll</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Click-to-Jump:</strong> Klik nomor langkah untuk loncat ke seksi tersebut dengan smooth scroll</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Mobile Responsive:</strong> Label langkah disembunyikan di layar kecil (&lt;768px)</span>
                    </li>
                </ul>
            </div>

            <div class="p-4 bg-purple-50 rounded-lg text-xs text-gray-600">
                <strong>5 Langkah:</strong> Data Penyidik → Info Surat → Tersangka → Dokumen → Sampel
            </div>
        </div>

        {{-- Version 1.1.4 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.1.4</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    UI/UX Phase 3 - Confirm Dialog Deployment
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>100% Coverage:</strong> Semua 14 native <code>confirm()</code> diganti dengan custom dialog</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Modul Tercover:</strong> User management (5), Sample processing (2), Requests (1), Delivery & Labels (3), Settings (4), Inventory (1)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Form Field Component:</strong> Komponen reusable <code>&lt;x-form-field&gt;</code> dengan auto-validation Laravel</span>
                    </li>
                </ul>
            </div>

            <div class="p-4 bg-green-50 rounded-lg text-xs text-gray-600">
                <strong>Benefit:</strong> UX konsisten, async support, loading states, keyboard navigation (Escape, Tab), ARIA attributes
            </div>
        </div>

        {{-- Version 1.1.3 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.1.3</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </span>
                    UI/UX Phase 2 - Component Creation
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Form Stepper:</strong> Visual progress indicator untuk multi-step form dengan Intersection Observer API</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Confirm Dialog:</strong> Custom modal 3 tipe (danger/warning/info) dengan async/Promise support</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Dropdown ARIA:</strong> Enhanced <code>&lt;x-dropdown&gt;</code> dengan <code>aria-haspopup</code>, <code>aria-expanded</code>, keyboard navigation</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.1.2 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.1.2</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                    UI/UX Phase 1 - Critical Fixes
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span><strong>CRITICAL: Breadcrumb Links Broken</strong> - Komponen expect <code>'href'</code> tapi view pakai <code>'url'</code> (2 halaman diperbaiki)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span><strong>CRITICAL: Tables Not Responsive</strong> - <code>overflow-hidden</code> → <code>overflow-x-auto</code> (2 halaman diperbaiki)</span>
                    </li>
                </ul>
            </div>

            <div class="p-4 bg-red-50 rounded-lg text-xs text-gray-600">
                <strong>Impact:</strong> Navigasi berfungsi kembali, tabel mobile dapat di-scroll horizontal
            </div>
        </div>

        {{-- Version 1.1.1 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-amber-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.1.1</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-amber-100 text-amber-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                    Party Mode - Multi-Agent Collaboration Workflow
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Parallel Orchestration:</strong> Launch 2-4 specialized agents simultaneously untuk comprehensive analysis</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Agent Roles:</strong> explore (codebase), librarian (docs/API), oracle (problem-solving), frontend-ui-ux-engineer, document-writer</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Performance Gain:</strong> 60-70% time savings melalui parallel execution vs sequential work</span>
                    </li>
                </ul>
            </div>

            <div class="p-4 bg-amber-50 rounded-lg text-xs text-gray-600">
                <strong>Workflow:</strong> ANALYZE → SYNTHESIZE → CONSULT → IMPLEMENT → DOCUMENT → REVIEW
            </div>
        </div>

        {{-- Version 1.1.0 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-emerald-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.1.0</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 10 Januari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-emerald-100 text-emerald-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </span>
                    WhatsApp Bugfix & Editable Templates
                </h3>
                <div class="mb-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Perbaikan Bug:</h4>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <span>Fix GOWA API parameter mismatch (<code>jid</code> → <code>phone</code>)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <span>Fix database constraint violation (hapus status <code>'sending'</code>)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <span>Fix DNS resolution (<code>gowa.lpmf.local</code> → <code>localhost:3000</code>)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <span>Fix message ID extraction dari GOWA response</span>
                        </li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Fitur Baru:</h4>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span><strong>Editable Templates:</strong> Edit template WhatsApp per milestone dari Settings, support placeholder <code>{resi}</code></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span><strong>Override System:</strong> Template override dengan default fallback otomatis</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="p-4 bg-emerald-50 rounded-lg text-xs text-gray-600">
                <strong>Testing:</strong> Successfully sent 5 messages to +6285956592404 with provider message IDs
            </div>
        </div>

        {{-- Older Versions (Collapsed) --}}
        <div class="card">
            <details class="group">
                <summary class="cursor-pointer list-none">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-700">Versi Sebelumnya (v1.0.1 - v1.0.9)</h3>
                        <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </summary>
                <div class="mt-4 pt-4 border-t border-gray-200 space-y-4 text-sm text-gray-600">
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.9 (9 Januari 2026)</p>
                        <p>WhatsApp Notification System - Automated notifications, queue system, outbox tracking</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.8 (8 Januari 2026)</p>
                        <p>Document Template System - Editable Blade templates, version control, validation</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.7 (8 Januari 2026)</p>
                        <p>Inventory Management - Item catalog, stock tracking, low stock alerts</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.6 (7 Januari 2026)</p>
                        <p>Delivery Tracking - Scheduling, recipient confirmation, label generation</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.5 (5 Januari 2026)</p>
                        <p>Sample Processing - Test recording, multi-step tracking, result validation</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.4 (3 Januari 2026)</p>
                        <p>Document Generation - PDF reports with DomPDF, QR code support</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.3 (3 Januari 2026)</p>
                        <p>Request Management - Multi-step form, file uploads, status workflow</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.2 (2 Januari 2026)</p>
                        <p>User Management - RBAC, user CRUD, password reset, activity logging</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">v1.0.1 (31 Desember 2025)</p>
                        <p>Initial Setup - Laravel 12, PostgreSQL, Tailwind CSS, Alpine.js, Vite</p>
                    </div>
                </div>
            </details>
        </div>

    </div>
</x-app-layout>
