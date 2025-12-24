<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @stack('html-attrs')>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Pusdokkes Sub-Satker') }}</title>

    <!-- Preload theme (no-flash) -->
    <script>
        (function(){
            try {
                var ls=localStorage.getItem('ui.theme');
                var m=window.matchMedia('(prefers-color-scheme: dark)').matches;
                if(ls==='dark'||(!ls&&m)) { document.documentElement.classList.add('dark'); document.documentElement.setAttribute('data-theme','dark'); }
            } catch(e) {}
        })();
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="font-sans antialiased bg-medical dark:bg-accent-900 dark:text-accent-100">
    <!-- Skip to main content for keyboard users -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 bg-white text-primary-800 border border-primary-300 rounded px-3 py-2 shadow">
        Lewati ke konten utama
    </a>
    <div class="min-h-screen flex flex-col">
        @include('layouts.navigation')
        <div class="absolute top-2 right-2 z-40">
            <form method="POST" action="{{ route('locale.switch', ['locale' => app()->getLocale()==='id' ? 'en':'id']) }}" class="inline">
                @csrf
                <button type="submit" class="text-xs px-2 py-1 rounded border-sem-subtle bg-white/70 dark:bg-accent-700/60 backdrop-blur hover:bg-white dark:hover:bg-accent-600 transition" title="Switch Language">
                    {{ app()->getLocale()==='id' ? 'EN' : 'ID' }}
                </button>
            </form>
        </div>

        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white/70 backdrop-blur supports-[backdrop-filter]:bg-white/60 border-b border-primary-100">
                <div class="container mx-auto max-w-7xl py-4 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main id="main-content" class="flex-1 @if(!isset($header)) pt-6 @endif">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-primary-100 bg-white/70 backdrop-blur supports-[backdrop-filter]:bg-white/60">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 text-sm text-primary-600 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="opacity-90">&copy; {{ date('Y') }} Pusdokkes Polri · Sub-Satker Farmapol</div>
                <div class="flex items-center gap-3">
                    <span class="hidden sm:inline">•</span>
                    <a href="{{ url('/track') }}" class="hover:text-primary-800">Lacak Permintaan</a>
                    <span>•</span>
                    <a href="{{ url('/statistics') }}" class="hover:text-primary-800">Statistik</a>
                </div>
            </div>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
