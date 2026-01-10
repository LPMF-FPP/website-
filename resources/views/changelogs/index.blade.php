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
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Latest
                        </span>
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
