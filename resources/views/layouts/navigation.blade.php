<nav class="bg-gradient-to-r from-primary-50 to-white dark:from-accent-900 dark:to-accent-800 shadow-lg border-b border-primary-200 dark:border-accent-700 relative z-[200]" aria-label="Site navigation">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Logo Section -->
            <div class="flex items-center space-x-4">
                <div class="shrink-0 flex items-center space-x-3 mr-2 xl:mr-4">
                    <img src="/images/logo-pusdokkes-polri.png" alt="Logo Pusdokkes Polri" class="h-12 w-auto">
                    <div class="hidden lg:block">
                        <h1 class="text-lg font-bold text-primary-800 leading-tight xl:max-w-[260px] 2xl:max-w-none xl:truncate">
                            Farmapol Pusdokkes Polri
                        </h1>
                        <p class="text-sm text-primary-600 font-medium hidden lg:block xl:hidden 2xl:block">
                            Kedokteran dan Kesehatan
                        </p>
                    </div>
                </div>
            </div>

            <!-- Center Navigation -->
            <div class="hidden xl:flex xl:items-center flex-1 min-w-0 justify-center gap-3 xl:gap-4 2xl:gap-6 whitespace-nowrap">
                @auth
                    @php
                        $user = Auth::user();
                        $labRoles = ['admin', 'supervisor', 'analyst', 'lab_analyst', 'petugas_lab'];
                        $supervisorRoles = ['admin', 'supervisor'];
                    @endphp

                    <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>

                    <x-nav-link href="{{ route('requests.index') }}" :active="request()->routeIs('requests.*')">
                        Permintaan
                    </x-nav-link>

                    @if(in_array($user->role, $labRoles, true))
                        <x-nav-link href="{{ route('samples.test.create') }}" :active="request()->routeIs('samples.*')">
                            Pengujian
                        </x-nav-link>

                        <x-nav-link href="{{ route('sample-processes.index') }}" :active="request()->routeIs('sample-processes.*')">
                            Proses
                        </x-nav-link>

                        <x-nav-link href="{{ route('delivery.index') }}" :active="request()->routeIs('delivery.*')">
                            Penyerahan
                        </x-nav-link>
                    @endif

                    @php
                        $referensiActive = request()->routeIs('tracking.*')
                            || request()->routeIs('database.*')
                            || request()->routeIs('statistics.*')
                            || request()->routeIs('analysts.*')
                            || request()->routeIs('settings.*');
                    @endphp
                    <!-- Mega menu: Referensi -->
                    <div class="relative group pl-5 ml-2 border-l border-primary-200">
                        <button type="button"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-150
                            {{ $referensiActive ? 'text-primary-900 bg-primary-50 ring-1 ring-primary-200' : 'text-primary-800 hover:text-primary-900 hover:bg-primary-50' }}">
                            <svg class="w-4 h-4 mr-2 text-primary-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3a1 1 0 00.293.707l2 2a1 1 0 101.414-1.414L11 9.586V7z" clip-rule="evenodd" />
                            </svg>
                            <span>Referensi</span>
                            <svg class="w-4 h-4 ml-2 text-primary-500 transition-transform duration-150 group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <!-- Dropdown Panel -->
                        <div class="pointer-events-none opacity-0 group-hover:opacity-100 group-hover:pointer-events-auto transition-opacity duration-150 ease-out absolute right-0 top-full mt-2 w-[90vw] max-w-5xl z-[80]">
                            <div class="bg-white shadow-2xl ring-1 ring-black/5 rounded-2xl p-6">
                                <div class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-primary-600/80">Referensi Data</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                    <a href="{{ route('tracking.index') }}" class="group flex items-start gap-3 p-3 rounded-xl border border-primary-100 hover:border-primary-200 hover:bg-primary-50 transition">
                                        <div class="shrink-0 inline-flex w-10 h-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                                            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3h14v2H3V3zm0 6h10v2H3V9zm0 6h14v2H3v-2z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-primary-900">Tracking</div>
                                            <div class="text-sm text-primary-600/80">Lacak progres permintaan</div>
                                        </div>
                                    </a>

                                    <a href="{{ route('database.index') }}" class="group flex items-start gap-3 p-3 rounded-xl border border-primary-100 hover:border-primary-200 hover:bg-primary-50 transition">
                                        <div class="shrink-0 inline-flex w-10 h-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7.58 2 4 3.79 4 6v12c0 2.21 3.58 4 8 4s8-1.79 8-4V6c0-2.21-3.58-4-8-4zm6 14c0 .99-2.69 2-6 2s-6-1.01-6-2V9.97C7.61 10.61 9.68 11 12 11s4.39-.39 6-1.03V16zm0-8c0 .99-2.69 2-6 2s-6-1.01-6-2 2.69-2 6-2 6 1.01 6 2z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-primary-900">Database</div>
                                            <div class="text-sm text-primary-600/80">Data ringkas &amp; pencarian</div>
                                        </div>
                                    </a>

                                    <a href="{{ route('statistics.index') }}" class="group flex items-start gap-3 p-3 rounded-xl border border-primary-100 hover:border-primary-200 hover:bg-primary-50 transition">
                                        <div class="shrink-0 inline-flex w-10 h-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                                            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 17h14v-2H3v2zm2-4h3V7H5v6zm5 0h3V3h-3v10zm5 0h3V9h-3v4z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-primary-900">Statistik</div>
                                            <div class="text-sm text-primary-600/80">Insight &amp; ringkasan</div>
                                        </div>
                                    </a>
                                </div>

                                @if(in_array($user->role, $supervisorRoles, true) || in_array($user->role, ['admin'], true))
                                    <div class="my-4 border-t border-primary-100"></div>
                                    <div class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-primary-600/80">Admin</div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                        @if(in_array($user->role, $supervisorRoles, true))
                                        <a href="{{ route('analysts.index') }}" class="group flex items-start gap-3 p-3 rounded-xl border border-primary-100 hover:border-primary-200 hover:bg-primary-50 transition">
                                            <div class="shrink-0 inline-flex w-10 h-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 5L20.49 19l-5-5zM9.5 14C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-primary-900">Analis</div>
                                                <div class="text-sm text-primary-600/80">Manajemen analis</div>
                                            </div>
                                        </a>
                                        @endif

                                        @if(in_array($user->role, ['admin', 'supervisor'], true))
                                        <a href="{{ route('settings.index') }}" class="group flex items-start gap-3 p-3 rounded-xl border border-emerald-100 hover:border-emerald-200 hover:bg-emerald-50 transition {{ request()->routeIs('settings.*') ? 'border-emerald-300 bg-emerald-50' : '' }}">
                                            <div class="shrink-0 inline-flex w-10 h-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M11.983 1.58a1.5 1.5 0 00-3.966 0l-.2.69a7.01 7.01 0 00-1.71.988l-.7-.2a1.5 1.5 0 00-1.84 1.06l-.36 1.33a1.5 1.5 0 001.06 1.84l.7.2c-.06.37-.1.74-.1 1.12s.04.75.1 1.12l-.7.2a1.5 1.5 0 001.06 1.84l.36 1.33a1.5 1.5 0 001.84 1.06l.7-.2c.52.4 1.1.74 1.71.99l.2.69a1.5 1.5 0 003.966 0l.2-.69c.61-.25 1.19-.59 1.71-.99l.7.2a1.5 1.5 0 001.84-1.06l-.36-1.33a1.5 1.5 0 00-1.06-1.84l-.7-.2c.06-.37.1-.74.1-1.12s-.04-.75-.1-1.12l.7-.2a1.5 1.5 0 001.06-1.84l-.36-1.33a1.5 1.5 0 00-1.84-1.06l-.7.2a7.01 7.01 0 00-1.71-.99l-.2-.69zM10 13a3 3 0 110-6 3 3 0 010 6z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-emerald-900">Pengaturan Sistem</div>
                                                <div class="text-sm text-emerald-700/70">Penomoran, template, otomatisasi</div>
                                            </div>
                                        </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                 @endauth

                @guest
                    <x-nav-link href="{{ route('login') }}" :active="request()->routeIs('login')">
                        Login
                    </x-nav-link>

                    <x-nav-link href="{{ route('register') }}" :active="request()->routeIs('register')">
                        Register
                    </x-nav-link>
                @endguest
            </div>

            <!-- User Menu - Right Side -->
            <div class="flex items-center">
                <button type="button" onclick="window.__toggleTheme()" class="mr-4 inline-flex items-center justify-center w-9 h-9 rounded-md border border-primary-200 dark:border-accent-600 text-primary-600 dark:text-accent-200 hover:bg-primary-50 dark:hover:bg-accent-700 focus:outline-none focus:ring-2 focus:ring-primary-500" aria-label="Toggle theme">
                    <svg class="h-5 w-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.07 6.07-1.42-1.42M8.35 8.35 6.93 6.93m10.12 0-1.42 1.42M8.35 15.65l-1.42 1.42"/></svg>
                    <svg class="h-5 w-5 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                </button>

                @auth
                    <!-- User Dropdown -->
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <div class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-primary-800 hover:text-primary-900 hover:bg-primary-50 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 cursor-pointer">
                                <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-secondary-400 rounded-full flex items-center justify-center text-white font-bold text-xs">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                </div>
                                <div class="text-left">
                                    <div class="text-sm font-semibold">{{ Auth::user()->name }}</div>
                                    <div class="text-xs text-primary-600">{{ ucfirst(Auth::user()->role) }}</div>
                                </div>
                            </div>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                Profile
                            </x-dropdown-link>

                            <div class="border-t border-primary-100"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                    Log Out
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @endauth

                @guest
                    <div class="flex space-x-3">
                        <a href="{{ route('login') }}" class="btn btn-ghost text-sm">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="btn btn-primary text-sm">
                            Register
                        </a>
                    </div>
                @endguest
            </div>

            <!-- Mobile menu button -->
            <div class="xl:hidden flex items-center ml-2">
                <button
                    type="button"
                    class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-primary-600 hover:text-primary-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    aria-controls="primary-navigation"
                    aria-expanded="false"
                    aria-label="Toggle navigation menu"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path class="menu-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path class="menu-close hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <div id="primary-navigation" class="xl:hidden mobile-menu hidden bg-white border-t border-primary-200">
        <div class="px-4 pt-2 pb-3 space-y-1">
            @auth
                @php
                    $user = Auth::user();
                    $labRoles = ['admin', 'supervisor', 'analyst', 'lab_analyst', 'petugas_lab'];
                    $supervisorRoles = ['admin', 'supervisor'];
                @endphp
                <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                    Dashboard
                </x-responsive-nav-link>

                <x-responsive-nav-link href="{{ route('requests.index') }}" :active="request()->routeIs('requests.*')">
                    Permintaan
                </x-responsive-nav-link>

                @if(in_array($user->role, $labRoles, true))
                    <x-responsive-nav-link href="{{ route('samples.test.create') }}" :active="request()->routeIs('samples.*')">
                        Pengujian
                    </x-responsive-nav-link>

                    <x-responsive-nav-link href="{{ route('sample-processes.index') }}" :active="request()->routeIs('sample-processes.*')">
                        Proses
                    </x-responsive-nav-link>

                    <x-responsive-nav-link href="{{ route('delivery.index') }}" :active="request()->routeIs('delivery.*')">
                        Penyerahan
                    </x-responsive-nav-link>
                @endif

                <!-- Mobile: Referensi collapsible group -->
                <div class="mt-2 pt-2 border-t border-primary-200">
                    <button type="button" data-ref-menu-toggle class="w-full flex items-center justify-between px-2 py-2 rounded-md text-sm font-medium text-primary-700 hover:bg-primary-50">
                        <span class="inline-flex items-center">
                            <svg class="w-4 h-4 mr-2 text-primary-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3a1 1 0 00.293.707l2 2a1 1 0 101.414-1.414L11 9.586V7z" clip-rule="evenodd"/></svg>
                            Referensi
                        </span>
                        <svg class="w-4 h-4 text-primary-500 rotate-0 transition-transform duration-150" data-ref-menu-caret viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <div id="ref-mobile-menu" class="mt-1 ml-2 space-y-1 hidden">
                        <x-responsive-nav-link href="{{ route('tracking.index') }}" :active="request()->routeIs('tracking.*')">
                            Tracking
                        </x-responsive-nav-link>
                        <x-responsive-nav-link href="{{ route('database.index') }}" :active="request()->routeIs('database.*')">
                            Database
                        </x-responsive-nav-link>
                        <x-responsive-nav-link href="{{ route('statistics.index') }}" :active="request()->routeIs('statistics.*')">
                            Statistik
                        </x-responsive-nav-link>
                        @if(in_array($user->role, $supervisorRoles, true))
                            <x-responsive-nav-link href="{{ route('analysts.index') }}" :active="request()->routeIs('analysts.*')">
                                Analis
                            </x-responsive-nav-link>
                        @endif
                        @if(in_array($user->role, ['admin', 'supervisor'], true))
                            <x-responsive-nav-link href="{{ route('settings.index') }}" :active="request()->routeIs('settings.*')">
                                Pengaturan Sistem
                            </x-responsive-nav-link>
                        @endif
                    </div>
                </div>
            @endauth

            @guest
                <x-responsive-nav-link href="{{ route('login') }}" :active="request()->routeIs('login')">
                    Login
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('register') }}" :active="request()->routeIs('register')">
                    Register
                </x-responsive-nav-link>
            @endguest
        </div>

        @auth
            <div class="pt-4 pb-3 border-t border-primary-200">
                <div class="flex items-center px-4">
                    <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-secondary-400 rounded-full flex items-center justify-center text-white font-bold">
                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                    </div>
                    <div class="ml-3">
                        <div class="text-base font-medium text-primary-800">{{ Auth::user()->name }}</div>
                        <div class="text-sm font-medium text-primary-600">{{ Auth::user()->email }}</div>
                    </div>
                </div>
                <div class="mt-3 space-y-1">
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-base font-medium text-primary-600 hover:text-primary-800 hover:bg-primary-50">
                        Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left block px-4 py-2 text-base font-medium text-primary-600 hover:text-primary-800 hover:bg-primary-50">
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</nav>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const button = document.querySelector('.mobile-menu-button');
    const menu = document.querySelector('.mobile-menu');

    if (button && menu) {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('hidden');
            const isOpen = !menu.classList.contains('hidden');
            button.setAttribute('aria-expanded', String(isOpen));

            const openIcon = button.querySelector('.menu-open');
            const closeIcon = button.querySelector('.menu-close');

            if (openIcon && closeIcon) {
                openIcon.classList.toggle('hidden');
                closeIcon.classList.toggle('hidden');
            }
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!button.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
                button.setAttribute('aria-expanded', 'false');
                const openIcon = button.querySelector('.menu-open');
                const closeIcon = button.querySelector('.menu-close');
                if (openIcon && closeIcon) {
                    openIcon.classList.remove('hidden');
                    closeIcon.classList.add('hidden');
                }
            }
        });
    }

    // Mobile: Referensi accordion toggle
    const refToggle = document.querySelector('[data-ref-menu-toggle]');
    const refMenu = document.getElementById('ref-mobile-menu');
    const refCaret = document.querySelector('[data-ref-menu-caret]');
    if (refToggle && refMenu) {
        refToggle.addEventListener('click', function() {
            refMenu.classList.toggle('hidden');
            if (refCaret) {
                refCaret.classList.toggle('rotate-180');
            }
        });
    }
});
</script>
@endpush
