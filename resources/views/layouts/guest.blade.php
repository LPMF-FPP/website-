<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LPMF LIMS') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo-pusdokkes-polri.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-primary-900 antialiased bg-medical">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-pd-overlay bg-white text-primary-800 border border-primary-300 rounded px-3 py-2 shadow">
            Lewati ke konten utama
        </a>
        <div class="min-h-dvh flex flex-col sm:justify-center items-center pt-6 sm:pt-0" role="main">
            <div>
                <a href="/">
                    <img src="/images/logo-pusdokkes-polri.png" alt="Logo Pusdokkes Polri" class="w-20 h-20">
                </a>
            </div>

            <main id="main-content" class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-lg border border-primary-100 overflow-hidden sm:rounded-xl">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
