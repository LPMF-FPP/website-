<nav x-data="{ mobileOpen: false }" class="bg-white dark:bg-accent-900 border-b border-primary-100 dark:border-accent-800 relative z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo Section -->
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                    <img src="/images/logo-pusdokkes-polri.png" alt="Logo Pusdokkes Polri" class="h-10 w-auto group-hover:scale-105 transition-transform duration-200">
                    <div class="hidden lg:block h-8 w-px bg-gray-200 dark:bg-white/10"></div>
                    <div class="hidden lg:block">
                        <h1 class="text-lg font-bold text-primary-900 dark:text-white leading-tight">
                            Farmapol
                        </h1>
                        <p class="text-xs text-primary-500 font-medium tracking-wide uppercase">
                            Pusdokkes Polri
                        </p>
                    </div>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden xl:flex items-center space-x-1">
                @auth
                    @php
                        $user = Auth::user();
                    @endphp

                    @can('dashboard.view')
                    <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>
                    @endcan

                    @can('permintaan.view')
                    <x-nav-link href="{{ route('requests.index') }}" :active="request()->routeIs('requests.*')">
                        Permintaan
                    </x-nav-link>
                    @endcan

                    @can('kaji-ulang.view')
                    <x-nav-link href="{{ route('review.create') }}" :active="request()->routeIs('review.*')">
                        Kaji Ulang Permintaan
                    </x-nav-link>
                    @endcan

                    @can('pengujian.view')
                    <x-nav-link href="{{ route('testing.index') }}" :active="request()->routeIs('testing.*')">
                        Pengujian
                    </x-nav-link>
                    @endcan

                    @can('penyerahan.view')
                    <x-nav-link href="{{ route('delivery.index') }}" :active="request()->routeIs('delivery.*')">
                        Penyerahan
                    </x-nav-link>
                    @endcan

                    <!-- Referensi Mega Menu -->
                    <div class="relative ml-1" x-data="{ open: false }" @click.away="open = false">
                        <button @click="open = !open" 
                                type="button"
                                :aria-expanded="open"
                                aria-haspopup="menu"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-150
                                {{ (request()->routeIs('tracking.*') || request()->routeIs('search.*') || request()->routeIs('statistics.*') || request()->routeIs('inventory.*') || request()->routeIs('analysts.*') || request()->routeIs('settings.*')) 
                                   ? 'bg-primary-50 text-primary-700 dark:bg-accent-800 dark:text-primary-400' 
                                   : 'text-primary-600 hover:bg-primary-50 hover:text-primary-900 dark:text-accent-400 dark:hover:bg-accent-800 dark:hover:text-accent-100' }}">
                            <span>Referensi</span>
                            <svg class="w-4 h-4 ml-1.5 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             class="absolute left-1/2 transform -translate-x-1/2 mt-3 w-screen max-w-3xl px-4 sm:px-0 z-50"
                             style="display: none;">
                            <div class="overflow-hidden rounded-2xl shadow-xl ring-1 ring-black/5 bg-white dark:bg-accent-900 dark:ring-white/10 p-6">
                                <div class="grid grid-cols-2 gap-8">
                                    <!-- Column 1: Umum -->
                                    <div>
                                        <h2 class="text-xs font-semibold text-primary-500 uppercase tracking-wider mb-4">Referensi Data</h2>
                                        <div class="space-y-3">
                                            @can('tracking.view')
                                            <a href="{{ route('tracking.index') }}" class="group flex items-start p-3 -m-3 rounded-lg hover:bg-primary-50 dark:hover:bg-accent-800 transition duration-150">
                                                <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                                </div>
                                                <div class="ml-4">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-700 dark:group-hover:text-primary-400">Tracking</p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Lacak progres permintaan</p>
                                                </div>
                                            </a>
                                            @endcan
                                            @can('pencarian.view')
                                            <a href="{{ route('search.index') }}" class="group flex items-start p-3 -m-3 rounded-lg hover:bg-primary-50 dark:hover:bg-accent-800 transition duration-150">
                                                <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                                </div>
                                                <div class="ml-4">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-700 dark:group-hover:text-primary-400">Pencarian</p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Cari data & dokumen</p>
                                                </div>
                                            </a>
                                            @endcan
                                            @can('statistik.view')
                                            <a href="{{ route('statistics.index') }}" class="group flex items-start p-3 -m-3 rounded-lg hover:bg-primary-50 dark:hover:bg-accent-800 transition duration-150">
                                                <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                                </div>
                                                <div class="ml-4">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-700 dark:group-hover:text-primary-400">Statistik</p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Analisa data laboratorium</p>
                                                </div>
                                            </a>
                                            @endcan
                                        </div>
                                    </div>

                                    <!-- Column 2: Admin / More -->
                                    <div>
                                        <h2 class="text-xs font-semibold text-primary-500 uppercase tracking-wider mb-4">Sistem & Inventori</h2>
                                        <div class="space-y-3">
                                            @can('monitoring.view')
                                            <a href="{{ route('monitoring.sensors.index') }}" class="group flex items-start p-3 -m-3 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition duration-150">
                                                <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                </div>
                                                <div class="ml-4">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-400">Monitoring Suhu</p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Sensor & Peringatan</p>
                                                </div>
                                            </a>
                                            @endcan

                                            @can('inventori.view')
                                            <a href="{{ route('inventory.dashboard') }}" class="group flex items-start p-3 -m-3 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/30 transition duration-150">
                                                <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                                </div>
                                                <div class="ml-4">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-amber-700 dark:group-hover:text-amber-400">Inventori</p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Manajemen stok reagen</p>
                                                </div>
                                            </a>
                                            @endcan
                                            
                                            @can('changelogs.view')
                                            <a href="{{ route('changelogs.index') }}" class="group flex items-start p-3 -m-3 rounded-lg hover:bg-primary-50 dark:hover:bg-accent-800 transition duration-150">
                                                <div class="shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </div>
                                                <div class="ml-4">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-700 dark:group-hover:text-primary-400">Changelogs</p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Riwayat perubahan</p>
                                                </div>
                                            </a>
                                            @endcan

                                            @canany(['analysts.view', 'settings.view'])
                                                <div class="border-t border-gray-100 dark:border-white/10 my-2"></div>

                                                @can('analysts.view')
                                                <a href="{{ route('analysts.index') }}" class="group flex items-center p-2 rounded-md hover:bg-primary-50 dark:hover:bg-accent-800 transition duration-150">
                                                    <svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Manajemen Staff</span>
                                                </a>
                                                @endcan

                                                @can('settings.view')
                                                <a href="{{ route('settings.index') }}" class="group flex items-center p-2 rounded-md hover:bg-primary-50 dark:hover:bg-accent-800 transition duration-150">
                                                    <svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Pengaturan Sistem</span>
                                                </a>
                                                @endcan
                                            @endcanany
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endauth
            </div>

            <!-- Right Side Actions -->
            <div class="flex items-center gap-2">
                 <!-- Theme Toggle -->
                 <button type="button" onclick="window.__toggleTheme()" aria-label="Toggle theme" class="p-2 rounded-full text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-accent-800 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors">
                    <span class="sr-only">Toggle theme</span>
                    <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    <svg class="h-5 w-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                </button>

                @auth
                    <!-- User Dropdown -->
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <div class="inline-flex items-center gap-3 px-3 py-2 rounded-full hover:bg-gray-50 dark:hover:bg-accent-800 transition-colors duration-150 cursor-pointer">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center text-white text-xs font-bold shadow-sm ring-2 ring-white dark:ring-accent-800">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                </div>
                                <div class="hidden sm:flex flex-col items-start justify-center text-left">
                                    <span class="text-sm font-bold text-gray-700 dark:text-gray-200 leading-tight">{{ Auth::user()->name }}</span>
                                    <span class="text-xs text-primary-600 dark:text-primary-400 font-medium leading-none mt-0.5">{{ ucfirst(Auth::user()->role) }}</span>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </x-slot>

                        <x-slot name="content">
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                {{ __('Manage Account') }}
                            </div>
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>
                            <div class="border-t border-gray-100 dark:border-white/10"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @endauth

                @guest
                    <div class="flex items-center gap-2">
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white px-3 py-2">Login</a>
                        <a href="{{ route('register') }}" class="text-sm font-medium bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 shadow-sm transition-all duration-150">Register</a>
                    </div>
                @endguest

                <!-- Mobile Menu Button -->
                <button @click="mobileOpen = true" type="button" class="xl:hidden p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-accent-800 focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <span class="sr-only">Open menu</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Drawer -->
    <div x-show="mobileOpen" class="relative z-50 xl:hidden" style="display: none;">
        <!-- Backdrop -->
        <div x-show="mobileOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm"
             @click="mobileOpen = false"></div>

        <!-- Drawer Panel -->
        <div x-show="mobileOpen"
             x-trap.noscroll.inert="mobileOpen"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="fixed inset-y-0 right-0 z-50 w-full max-w-xs bg-white dark:bg-accent-900 shadow-2xl overflow-y-auto">
            
            <div class="px-6 py-4 flex items-center justify-between border-b border-gray-100 dark:border-white/10">
                <span class="text-lg font-bold text-gray-900 dark:text-white">Menu</span>
                <button @click="mobileOpen = false" class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md" aria-label="Close menu">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-2 py-4 space-y-1">
                @auth
                    @can('dashboard.view')
                    <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-responsive-nav-link>
                    @endcan

                    @can('permintaan.view')
                    <x-responsive-nav-link href="{{ route('requests.index') }}" :active="request()->routeIs('requests.*')">
                        Permintaan
                    </x-responsive-nav-link>
                    @endcan

                    @can('kaji-ulang.view')
                    <x-responsive-nav-link href="{{ route('review.create') }}" :active="request()->routeIs('review.*')">
                        Kaji Ulang Permintaan
                    </x-responsive-nav-link>
                    @endcan

                    @can('pengujian.view')
                    <x-responsive-nav-link href="{{ route('testing.index') }}" :active="request()->routeIs('testing.*')">
                        Pengujian
                    </x-responsive-nav-link>
                    @endcan

                    @can('penyerahan.view')
                    <x-responsive-nav-link href="{{ route('delivery.index') }}" :active="request()->routeIs('delivery.*')">
                        Penyerahan
                    </x-responsive-nav-link>
                    @endcan

                    <!-- Mobile Reference Section -->
                    <div x-data="{ refExpanded: false }" class="space-y-1">
                        <button @click="refExpanded = !refExpanded" class="w-full flex items-center justify-between px-3 py-2 text-base font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-accent-800 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500" :aria-expanded="refExpanded" aria-controls="mobile-ref-section">
                            <span>Referensi</span>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': refExpanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="refExpanded" id="mobile-ref-section" class="pl-4 space-y-1" style="display: none;">
                            @can('tracking.view')
                            <x-responsive-nav-link href="{{ route('tracking.index') }}" :active="request()->routeIs('tracking.*')">Tracking</x-responsive-nav-link>
                            @endcan
                            @can('pencarian.view')
                            <x-responsive-nav-link href="{{ route('search.index') }}" :active="request()->routeIs('search.*')">Pencarian</x-responsive-nav-link>
                            @endcan
                            @can('statistik.view')
                            <x-responsive-nav-link href="{{ route('statistics.index') }}" :active="request()->routeIs('statistics.*')">Statistik</x-responsive-nav-link>
                            @endcan
                            @can('inventori.view')
                            <x-responsive-nav-link href="{{ route('inventory.dashboard') }}" :active="request()->routeIs('inventory.*')">Inventori</x-responsive-nav-link>
                            @endcan
                            @can('changelogs.view')
                            <x-responsive-nav-link href="{{ route('changelogs.index') }}" :active="request()->routeIs('changelogs.*')">Changelogs</x-responsive-nav-link>
                            @endcan
                            
                            @can('analysts.view')
                            <x-responsive-nav-link href="{{ route('analysts.index') }}" :active="request()->routeIs('analysts.*')">Manajemen Staff</x-responsive-nav-link>
                            @endcan
                            @can('settings.view')
                            <x-responsive-nav-link href="{{ route('settings.index') }}" :active="request()->routeIs('settings.*')">Pengaturan</x-responsive-nav-link>
                            @endcan
                        </div>
                    </div>
                @endauth
            </div>

            @auth
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-accent-800/50">
                <div class="flex items-center">
                    <div class="shrink-0">
                        <div class="h-10 w-10 rounded-full bg-primary-600 flex items-center justify-center text-white font-bold">
                            {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                        </div>
                    </div>
                    <div class="ml-3">
                        <div class="text-base font-medium text-gray-800 dark:text-white">{{ Auth::user()->name }}</div>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button type="submit" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md" aria-label="Logout">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
            @endauth
        </div>
    </div>
</nav>
