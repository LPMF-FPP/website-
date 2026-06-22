<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'LPMF LIMS') }}</title>
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900">
        <!-- Skip to main content for keyboard users -->
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 bg-white text-blue-800 border border-blue-300 rounded px-3 py-2 shadow">
            Lewati ke konten utama
        </a>
        <!-- Navigation -->
        <nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 flex items-center gap-3">
                            <img class="h-10 w-auto" src="{{ asset('images/logo-pusdokkes-polri.png') }}" alt="Pusdokkes Polri">
                            <div>
                                <h1 class="text-lg font-bold text-slate-900 leading-none">LPMF LIMS</h1>
                                <p class="text-xs text-slate-500 font-medium">Farmapol Pusdokkes Polri</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-sm font-semibold text-slate-700 hover:text-blue-700">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-700 hover:text-blue-700">Log in</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main id="main-content">
        <!-- Hero Section -->
        <div class="relative bg-slate-900 overflow-hidden">
            <!-- Background Pattern/Image -->
            <div class="absolute inset-0 opacity-20 bg-[url('https://images.unsplash.com/photo-1579165466741-7f35a4755657?q=80&w=2000&auto=format&fit=crop')] bg-cover bg-center"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-900 via-slate-900/90 to-slate-900/50"></div>
            
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
                <div class="lg:w-2/3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-900/50 border border-blue-700 text-blue-200 text-xs font-semibold uppercase tracking-wider mb-6">
                        <span class="w-2 h-2 rounded-full bg-blue-400 motion-safe:animate-pulse"></span>
                        System Operational
                    </div>
                    <h1 class="text-4xl lg:text-6xl font-bold text-white tracking-tight mb-6">
                        The Pulse of <span class="text-blue-400">Evidence</span>.
                        <br>Absolute Accountability.
                    </h1>
                    <p class="text-xl text-slate-300 mb-8 max-w-2xl">
                        Sistem Informasi Manajemen Laboratorium Forensik dengan Chain of Custody yang aman, audit trail yang tidak dapat diubah, dan telemetri real-time untuk Pusdokkes Polri.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-900/50">
                            Masuk Sistem
                        </a>
                        <a href="#features" class="inline-flex items-center justify-center px-6 py-3 border border-slate-600 text-base font-medium rounded-md text-slate-200 hover:bg-slate-800 hover:text-white transition">
                            Pelajari Lebih Lanjut
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Stats / Telemetry Banner -->
        <div class="bg-slate-800 border-y border-slate-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-slate-700">
                    <div class="py-4 px-4 flex items-center gap-3">
                        <div class="text-emerald-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 uppercase tracking-wider">Status Sistem</div>
                            <div class="text-sm font-bold text-white">Online & Secure</div>
                        </div>
                    </div>
                    <div class="py-4 px-4 flex items-center gap-3">
                        <div class="text-blue-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 uppercase tracking-wider">Sampel Aktif</div>
                            <div class="text-sm font-bold text-white">10 Permintaan</div>
                        </div>
                    </div>
                    <div class="py-4 px-4 flex items-center gap-3">
                        <div class="text-amber-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 uppercase tracking-wider">Avg. Turnaround</div>
                            <div class="text-sm font-bold text-white">2.4 Hari</div>
                        </div>
                    </div>
                    <div class="py-4 px-4 flex items-center gap-3">
                        <div class="text-purple-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 uppercase tracking-wider">Reports Gen.</div>
                            <div class="text-sm font-bold text-white">142 Bulan Ini</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div id="features" class="py-24 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-base text-blue-600 font-semibold tracking-wide uppercase">Core Capabilities</h2>
                    <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                        Standar Emas Forensik Digital
                    </p>
                    <p class="mt-4 max-w-2xl text-xl text-slate-500 mx-auto">
                        Dibangun untuk integritas, kecepatan, dan akurasi yang dibutuhkan oleh penegak hukum modern.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="relative p-8 bg-slate-50 rounded-2xl border border-slate-100 hover:shadow-xl transition-shadow duration-300">
                        <div class="absolute top-0 -translate-y-1/2 bg-blue-600 rounded-xl p-3 shadow-lg text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <h3 class="mt-4 text-xl font-bold text-slate-900">Chain of Custody Digital</h3>
                        <p class="mt-3 text-slate-600">Pelacakan bukti end-to-end yang tidak dapat disangkal. Setiap transfer, analisis, dan pengambilan sampel dicatat dengan timestamp presisi.</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="relative p-8 bg-slate-50 rounded-2xl border border-slate-100 hover:shadow-xl transition-shadow duration-300">
                        <div class="absolute top-0 -translate-y-1/2 bg-blue-600 rounded-xl p-3 shadow-lg text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <h3 class="mt-4 text-xl font-bold text-slate-900">Audit Trail Immutable</h3>
                        <p class="mt-3 text-slate-600">Log aktivitas yang transparan dan aman. Memastikan akuntabilitas setiap personel yang berinteraksi dengan barang bukti.</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="relative p-8 bg-slate-50 rounded-2xl border border-slate-100 hover:shadow-xl transition-shadow duration-300">
                        <div class="absolute top-0 -translate-y-1/2 bg-blue-600 rounded-xl p-3 shadow-lg text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="mt-4 text-xl font-bold text-slate-900">Efisiensi Workflow</h3>
                        <p class="mt-3 text-slate-600">Otomatisasi alur kerja dari penerimaan hingga pelaporan. Mengurangi kesalahan manusia dan mempercepat waktu penyelesaian kasus.</p>
                    </div>
                </div>
            </div>
        </div>
        </main>

        <!-- Footer -->
        <footer class="bg-slate-900 text-white py-12 border-t border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="flex items-center gap-3 mb-6 md:mb-0">
                        <img class="h-12 w-auto" src="{{ asset('images/logo-pusdokkes-polri.png') }}" alt="Pusdokkes Polri">
                        <div>
                            <span class="block text-lg font-bold">LPMF LIMS</span>
                            <span class="block text-sm text-slate-400">Pusdokkes Polri</span>
                        </div>
                    </div>
                    <div class="text-slate-400 text-sm">
                        &copy; {{ date('Y') }} Laboratorium Forensik Pusdokkes Polri. Akses Terbatas.
                    </div>
                </div>
            </div>
        </footer>
    </body>
</html>
