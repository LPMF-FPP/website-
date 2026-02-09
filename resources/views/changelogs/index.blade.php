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

        {{-- Version 2.2.0 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-teal-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v2.2.0</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Latest</span>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 09 Februari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-teal-100 text-teal-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    WhatsApp Hub Settings Redesign + Whitelist Manager
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Horizontal Sub-tabs:</strong> Settings WhatsApp Hub dibagi menjadi 5 sub-tab (Quick Test, Templates, GOWA, Whitelist, Alerts) untuk mengurangi scroll fatigue.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Quick Test Default:</strong> Send Test Message + Connected Devices diprioritaskan sebagai entry point untuk cek harian.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Template Editor Redesign:</strong> Pilih category + template lalu edit satu template per waktu (lebih fokus, minim scroll).</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Whitelist Manager (Web UI):</strong> Admin whitelist kini bisa dikelola lewat UI (add/remove), dengan Super Admin sebagai protected fallback.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 2.1.0 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-pink-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v2.1.0</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: {{ now()->translatedFormat('d F Y') }}</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-pink-100 text-pink-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    AI Magic Compose
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-pink-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>AI Magic Compose:</strong> Generate and refine WhatsApp messages instantly with AI assistance. Includes automatic formatting for Bold, Italic, and Monospace.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 2.0.0 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v2.0.0</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 09 Februari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </span>
                    Inventory Dashboard 2.0
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Overview Widget:</strong> Daftar stok real-time dengan pagination dan pencarian. Baris item dapat di-expand untuk melihat detail lokasi dan lot (termasuk tanggal kadaluarsa).</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Fast Moving Analysis:</strong> Tombol baru di Quick Actions untuk menampilkan modal analisis 10 item paling sering digunakan dalam 30 hari terakhir.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Visual Health Bar & Trend:</strong> Tab "Low Stock" di widget Alerts kini menampilkan progress bar visual untuk sisa stok dan indikator tren pemakaian (High/Moderate/Low).</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Layout Optimization:</strong> Tata letak dashboard dioptimalkan (Grid 2/3 + 1/3) dengan Overview Widget yang lebar penuh di bagian bawah untuk keterbacaan maksimal.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.10.3 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-amber-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.10.3</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 08 Februari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-amber-100 text-amber-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    Inventory Alerts History + Global Search / Barcode Scan
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Inventory Alerts (WhatsApp):</strong> Alert low stock/expiry sekarang dikirim ke semua admin di whitelist + super admin fallback, lengkap dengan outcome sent/failed.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Alert History Log:</strong> Catatan pengiriman disimpan di table <code class="font-mono text-xs">inventory_alert_logs</code> untuk audit dan troubleshooting.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>WhatsApp Hub Tab:</strong> Tab baru <em>Inventory Alerts</em> menampilkan preview low stock, near-expiry, dan history terkini.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Global Search Dashboard:</strong> Search bar untuk lookup/scan barcode dan deep link ke form Issue/Disposal dengan prefill <code class="font-mono text-xs">item_id</code> dan <code class="font-mono text-xs">lot_id</code>.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.10.2 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-primary-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.10.2</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 08 Februari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-primary-100 text-primary-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    WhatsApp /stok Audit Trail + Functional Quick Actions Dashboard
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-primary-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>WhatsApp <code class="font-mono text-xs">/stok</code> Fix:</strong> Transaksi via WhatsApp sekarang tercatat di <code class="font-mono text-xs">inventory_movements</code> (audit trail lengkap) dan <code class="font-mono text-xs">performed_by</code> otomatis resolve dari nomor pengirim.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-primary-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Quick Actions Dashboard:</strong> Form Pengeluaran/Penerimaan/Transfer Cepat di dashboard inventori sekarang bisa dipakai untuk submit transaksi langsung.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-primary-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Lokasi Otomatis:</strong> Untuk stok masuk pakai lokasi pertama, untuk stok keluar pilih lokasi dengan stok terbanyak.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.10.1 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-emerald-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.10.1</h2>
                        {{-- <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Latest</span> --}}
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 07 Februari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-emerald-100 text-emerald-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    Generic Countdown Reminder CRUD + Disposal Access
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Reminder CRUD:</strong> Tab Reminders sekarang punya tombol <em>Tambah Reminder</em> dan <em>Delete</em> dengan endpoint create/delete dedicated.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Generic Countdown:</strong> Countdown tidak lagi spesifik ISO; bisa dipakai untuk berbagai event dengan custom milestones dan placeholder dinamis.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Reminder UX Upgrade:</strong> Modal reminder kini mendukung mode create/edit, curated emoji selector profesional, dan custom milestone builder.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Sample Disposal Access:</strong> Quick Action "Sampel Disposal" ditambahkan ke Dashboard Inventori beserta indikator jumlah sampel eligible.</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.10.0 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.10.0</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 07 Februari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    WhatsApp Magic Insert & Sample Disposal System
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Magic Insert Toolbar:</strong> Tambahan toolbar variabel dan formatting di form Task, Reminder, dan Broadcast pada WhatsApp Hub agar input template lebih cepat dan minim typo.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Sample Disposal Dashboard:</strong> Fitur baru pemusnahan sisa sampel di modul Inventori dengan tab Siap Musnah dan Riwayat.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Batch Execution + Audit Trail:</strong> Eksekusi pemusnahan massal dengan metadata saksi/metode, status disposal pada sampel, dan relasi batch disposal untuk kebutuhan audit.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Berita Acara Pemusnahan PDF:</strong> Dokumen resmi PDF per batch dengan tabel No. LHU, No. LP/Tgl, Tersangka, dan Bukti Sisa (Asal).</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Version 1.9.0 --}}
        <div class="card">
            <div class="flex items-start gap-4 mb-6">
                <div class="flex-shrink-0 h-12 w-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-xl font-bold text-gray-900">LPMF LIMS v1.9.0</h2>
                    </div>
                    <p class="text-sm text-gray-500">Dirilis: 04 Februari 2026</p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-purple-100 text-purple-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    WhatsApp Whitelist & Report Fixes
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Admin Whitelist System:</strong> Fitur keamanan baru untuk membatasi akses command admin WhatsApp (`/restart`, `/status`, dll) hanya untuk nomor terdaftar.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Consolidated Report Fix:</strong> Perbaikan logika perhitungan "Permintaan Selesai" dan "LHU Terbit" agar data laporan lebih akurat sesuai kondisi riil.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>WhatsApp Fixes:</strong> Mengatasi notifikasi duplikat pada status "Siap Diambil" dan perbaikan pengambilan daftar grup WhatsApp.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><strong>Infrastructure Update:</strong> Update konfigurasi Webhook WhatsApp menyesuaikan perpindahan server ke IP 192.168.0.209.</span>
                    </li>
                </ul>
            </div>
        </div>

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
                        {{-- <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Latest</span> --}}
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
