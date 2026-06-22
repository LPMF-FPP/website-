<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LPMF LIMS - Sistem Informasi Manajemen Laboratorium</title>
    <meta name="description" content="LPMF LIMS mendukung pengelolaan proses laboratorium, pemantauan data operasional, dan pelacakan status layanan dalam satu sistem yang terintegrasi.">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-pusdokkes-polri.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400;1,500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        :root {
            color-scheme: light;
            --lp-bg: #f6f4f1;
            --lp-text: #15233d;
            --lp-text-soft: #536482;
            --lp-border: rgba(21, 35, 61, 0.10);
            --lp-border-strong: rgba(21, 35, 61, 0.18);
            --lp-green: #0d6a4a;
            --lp-gold: #b28917;
            --lp-navy: #192845;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--lp-bg);
            color: var(--lp-text);
            font-family: 'Instrument Sans', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .lp-shell {
            position: relative;
            isolation: isolate;
            overflow: clip;
        }

        .lp-shell::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at top left, rgba(178, 137, 23, 0.07), transparent 32%),
                radial-gradient(circle at 85% 24%, rgba(13, 106, 74, 0.07), transparent 26%);
            z-index: -1;
        }

        .lp-container {
            width: min(100%, 88rem);
            margin-inline: auto;
            padding-inline: 1.5rem;
        }

        .lp-thin-border {
            border-color: var(--lp-border);
        }

        .lp-topbar {
            backdrop-filter: blur(14px);
            background: rgba(246, 244, 241, 0.84);
        }

        .lp-logo-mark {
            width: 2.7rem;
            height: 2.7rem;
            object-fit: contain;
        }

        .lp-label {
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(178, 137, 23, 0.42);
            color: var(--lp-gold);
            padding: 0.42rem 0.72rem;
            font-size: 0.63rem;
            font-weight: 700;
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }

        .lp-display {
            font-size: clamp(3.3rem, 8vw, 6.9rem);
            line-height: 0.94;
            letter-spacing: -0.06em;
            font-weight: 500;
        }

        .lp-serif {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-style: italic;
            font-weight: 500;
            letter-spacing: -0.01em;
        }

        .lp-hero-copy {
            max-width: 44rem;
            font-size: clamp(1.2rem, 2.5vw, 1.9rem);
            line-height: 1.55;
            color: var(--lp-text-soft);
        }

        .lp-stat-label,
        .lp-kicker {
            font-size: 0.68rem;
            letter-spacing: 0.34em;
            text-transform: uppercase;
            color: #8694ac;
        }

        .lp-metric-value {
            letter-spacing: -0.05em;
            line-height: 1.02;
            overflow-wrap: anywhere;
            word-break: break-word;
            text-wrap: balance;
        }

        .lp-dark-section {
            background: var(--lp-navy);
            color: #f5f7fb;
        }

        .lp-dark-panel {
            border: 1px solid rgba(255, 255, 255, 0.10);
            background: rgba(255, 255, 255, 0.04);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
        }

        .lp-input {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.02);
            color: #f8fafc;
            padding: 1rem 1.1rem;
            font-size: 1.05rem;
            line-height: 1.2;
            font-family: 'Instrument Sans', system-ui, sans-serif;
        }

        .lp-input::placeholder {
            color: rgba(241, 245, 249, 0.28);
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .lp-input:focus-visible {
            outline: 2px solid rgba(13, 106, 74, 0.95);
            outline-offset: 2px;
            border-color: rgba(13, 106, 74, 0.85);
        }

        .lp-button,
        .lp-button-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            text-decoration: none;
            transition: 180ms ease;
        }

        .lp-button {
            background: var(--lp-green);
            color: #f8fafc;
            padding: 1rem 1.8rem;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            border: 0;
            cursor: pointer;
        }

        .lp-button:hover {
            background: #0a5c40;
        }

        .lp-button-ghost {
            border: 1px solid var(--lp-border-strong);
            color: var(--lp-text);
            padding: 0.9rem 1.4rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .lp-button-ghost:hover {
            background: var(--lp-text);
            color: #f8fafc;
        }

        .lp-focus:focus-visible {
            outline: 2px solid var(--lp-green);
            outline-offset: 3px;
        }

        @media (max-width: 1024px) {
            .lp-display {
                font-size: clamp(3rem, 12vw, 5rem);
            }
        }
    </style>
</head>
<body>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-sm focus:bg-white focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:text-slate-900">
        Lewati ke konten utama
    </a>

    <div class="lp-shell min-h-screen">
        <nav class="lp-topbar sticky top-0 z-50 border-b lp-thin-border">
            <div class="lp-container flex items-center justify-between gap-6 py-4">
                <a href="{{ url('/') }}" class="lp-focus inline-flex items-center gap-2.5 no-underline" aria-label="LPMF LIMS beranda">
                    <img src="{{ asset('images/logo-pusdokkes-polri.png') }}" alt="Logo Pusdokkes Polri" class="lp-logo-mark">
                    <span class="text-lg font-semibold tracking-tight text-slate-950">LPMF LIMS</span>
                </a>

                <div class="hidden items-center gap-8 md:flex">
                    <a href="#statistik" class="lp-focus text-[0.72rem] font-medium uppercase tracking-[0.28em] text-slate-900 no-underline transition hover:text-[var(--lp-green)]">Statistik</a>
                    <a href="#lacak-layanan" class="lp-focus text-[0.72rem] font-medium uppercase tracking-[0.28em] text-slate-900 no-underline transition hover:text-[var(--lp-green)]">Lacak Layanan</a>
                    <a href="#tentang-kami" class="lp-focus text-[0.72rem] font-medium uppercase tracking-[0.28em] text-slate-900 no-underline transition hover:text-[var(--lp-green)]">Tentang Kami</a>
                </div>

                <a href="{{ route('login') }}" class="lp-button-ghost lp-focus">
                    Login Sistem
                </a>
            </div>
        </nav>

        <main id="main-content">
            <section class="border-b lp-thin-border pt-16 pb-24 sm:pt-20 sm:pb-28 lg:pt-24 lg:pb-32">
                <div class="lp-container">
                    <div class="grid grid-cols-1 items-end gap-14 lg:grid-cols-12 lg:gap-12">
                        <div class="lg:col-span-8">
                            <span class="lp-label mb-8">Civic Tech Infrastructure</span>
                            <h1 class="lp-display max-w-5xl text-slate-950">
                                Operasional<br>
                                laboratorium yang<br>
                                <span class="lp-serif">tertata</span>, terlacak, dan<br>
                                <span style="color: var(--lp-green);">siap audit.</span>
                            </h1>
                            <p class="lp-hero-copy mt-10">
                                LPMF LIMS mendukung pengelolaan proses laboratorium, pemantauan data operasional, dan pelacakan status layanan dalam satu sistem yang terintegrasi.
                            </p>
                        </div>

                        <aside class="flex flex-col gap-6 lg:col-span-4 lg:pb-2">
                            <div class="border-t lp-thin-border pt-4">
                                <p class="lp-stat-label">Status Operasional</p>
                                <div class="mt-3 flex items-center gap-2.5 text-sm font-medium text-slate-800">
                                    <span @class([
                                        'h-2 w-2 rounded-full',
                                        'bg-emerald-500 motion-safe:animate-pulse' => ($heroStatus['indicator'] ?? 'offline') === 'online',
                                        'bg-amber-500' => ($heroStatus['indicator'] ?? 'offline') !== 'online',
                                    ])></span>
                                    <span>{{ $heroStatus['label'] ?? 'Data Operasional Belum Tersedia' }}</span>
                                </div>
                                <p class="mt-2 text-sm leading-7 text-slate-500">{{ $heroStatus['detail'] ?? 'Menunggu sinkronisasi data laboratorium' }}</p>
                            </div>
                            <div class="border-t lp-thin-border pt-4">
                                <p class="max-w-sm text-sm italic leading-7 text-slate-500">
                                    Meningkatkan akuntabilitas data penelitian dan layanan publik melalui digitalisasi proses hulu ke hilir.
                                </p>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

            <section id="statistik" class="py-20 sm:py-24">
                <div class="lp-container">
                    <div class="mb-12 flex items-center gap-5 sm:gap-8">
                        <h2 class="lp-kicker shrink-0">{{ $stats['period_label'] ?? 'Ringkasan Operasional' }}</h2>
                        <div class="h-px flex-1 bg-[var(--lp-border)]"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-x-2 gap-y-10 sm:grid-cols-3 sm:gap-x-4 lg:grid-cols-6 lg:gap-x-3 xl:gap-x-5">
                        @foreach (($stats['items'] ?? []) as $item)
                            <div class="min-w-0 border-l lp-thin-border px-3 sm:px-4">
                                <div class="lp-metric-value text-[1.2rem] font-medium text-slate-950 sm:text-[1.38rem] lg:text-[1.18rem] xl:text-[1.42rem] 2xl:text-[1.55rem]">{{ $item['value'] }}</div>
                                <div class="mt-3 text-[0.68rem] uppercase tracking-[0.18em] text-slate-500">{{ $item['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="lacak-layanan" class="lp-dark-section py-24 sm:py-28">
                <div class="lp-container grid grid-cols-1 gap-14 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <h2 class="text-3xl font-light tracking-tight text-white sm:text-[2.1rem]">Transparansi Layanan</h2>
                        <p class="mt-6 max-w-xl text-lg leading-8 text-slate-300">
                            Gunakan nomor permohonan Anda untuk memantau progres pengujian secara real-time. Kami menjamin setiap tahapan tercatat dalam audit trail yang sah.
                        </p>

                        <div class="mt-10 space-y-6">
                            <div class="flex items-start gap-4">
                                <span class="pt-0.5 font-mono text-sm text-[var(--lp-gold)]">01/</span>
                                <p class="text-sm leading-7 text-slate-100">Masukan nomor resi yang tertera pada form tanda terima.</p>
                            </div>
                            <div class="flex items-start gap-4">
                                <span class="pt-0.5 font-mono text-sm text-[var(--lp-gold)]">02/</span>
                                <p class="text-sm leading-7 text-slate-100">Klik periksa untuk melihat detail posisi sampel dan estimasi selesai.</p>
                            </div>
                        </div>
                    </div>

                    <div class="lp-dark-panel p-6 sm:p-8 lg:self-center">
                        <form action="{{ route('public.track') }}" method="POST" class="space-y-4">
                            @csrf
                            <label for="tracking_number" class="block text-[0.68rem] uppercase tracking-[0.28em] text-slate-400">
                                ID Lacak Layanan
                            </label>
                            <div class="flex flex-col gap-4 md:flex-row">
                                <input
                                    id="tracking_number"
                                    name="tracking_number"
                                    type="text"
                                    required
                                    value="{{ old('tracking_number') }}"
                                    placeholder="Contoh: REQ-2023-001"
                                    class="lp-input lp-focus flex-1"
                                >
                                <button type="submit" class="lp-button lp-focus md:px-7">
                                    Periksa Status
                                </button>
                            </div>
                            @if ($errors->has('tracking_number'))
                                <p class="text-sm text-rose-300">{{ $errors->first('tracking_number') }}</p>
                            @endif
                            <p class="pt-1 text-[0.68rem] italic leading-6 text-slate-500">
                                Data diperbarui secara otomatis setiap kali ada perubahan status di meja teknis.
                            </p>
                        </form>
                    </div>
                </div>
            </section>

            <section id="tentang-kami" class="py-24 sm:py-28">
                <div class="lp-container grid grid-cols-1 gap-14 lg:grid-cols-12 lg:gap-12">
                    <div class="lg:col-span-5">
                        <p class="mb-8 text-[0.7rem] font-bold uppercase tracking-[0.3em] text-[var(--lp-gold)]">Selayang Pandang</p>
                        <div class="max-w-xl space-y-6 text-slate-700">
                            <p class="text-4xl leading-[1.3] text-slate-800 md:text-[2.65rem]">
                                <span class="lp-serif">LPMF LIMS mendukung layanan laboratorium yang tertib, transparan, dan dapat dipertanggungjawabkan.</span>
                            </p>
                            <p class="text-lg leading-8 text-slate-600">
                                LPMF adalah Laboratorium Pengujian Mutu Farmapol Pusdokkes Polri. Melalui LPMF LIMS, proses penerimaan, pengujian, pemantauan status layanan, dan dokumentasi operasional dikelola dalam satu alur kerja yang terintegrasi.
                            </p>
                        </div>
                    </div>

                    <div class="border-l lp-thin-border pl-0 lg:col-span-7 lg:pl-14">
                        <ul class="space-y-10">
                            <li>
                                <h3 class="mb-3 flex items-center gap-4 text-sm font-bold uppercase tracking-[0.28em] text-slate-950">
                                    <span class="h-2 w-2 bg-[var(--lp-green)]"></span>
                                    Integritas ISO 17025
                                </h3>
                                <p class="max-w-2xl text-lg leading-8 text-slate-600">
                                    Seluruh alur kerja dirancang untuk memenuhi standar kompetensi laboratorium pengujian dan kalibrasi, memastikan hasil yang valid dan dapat dipertanggungjawabkan.
                                </p>
                            </li>
                            <li>
                                <h3 class="mb-3 flex items-center gap-4 text-sm font-bold uppercase tracking-[0.28em] text-slate-950">
                                    <span class="h-2 w-2 bg-[var(--lp-green)]"></span>
                                    Manajemen Sampel Digital
                                </h3>
                                <p class="max-w-2xl text-lg leading-8 text-slate-600">
                                    Pelacakan barcode terintegrasi meminimalkan risiko kesalahan manusia dalam penanganan dan pencatatan data sampel di area teknis.
                                </p>
                            </li>
                            <li>
                                <h3 class="mb-3 flex items-center gap-4 text-sm font-bold uppercase tracking-[0.28em] text-slate-950">
                                    <span class="h-2 w-2 bg-[var(--lp-green)]"></span>
                                    Keamanan Data Institusi
                                </h3>
                                <p class="max-w-2xl text-lg leading-8 text-slate-600">
                                    Protokol enkripsi modern dan pembatasan akses berbasis peran (RBAC) untuk melindungi kerahasiaan data pihak ketiga.
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t lp-thin-border py-12 sm:py-14">
            <div class="lp-container flex flex-col gap-10 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="mb-4 flex items-center gap-2.5">
                        <span class="h-6 w-6 rounded-sm bg-slate-300"></span>
                        <span class="font-bold tracking-tight text-slate-950">LPMF LIMS</span>
                    </div>
                    <p class="text-[0.68rem] uppercase tracking-[0.22em] text-slate-400">
                        Laboratorium Pengujian Mutu Farmapol<br>
                        Pusdokkes Polri
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-10 text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-900 sm:gap-16">
                    <div class="flex flex-col gap-4">
                        <a href="#" class="lp-focus no-underline transition hover:text-[var(--lp-green)]">Kebijakan Privasi</a>
                        <a href="#" class="lp-focus no-underline transition hover:text-[var(--lp-green)]">Ketentuan Layanan</a>
                    </div>
                    <div class="flex flex-col gap-4 md:items-end md:text-right">
                        <span class="text-slate-400">© 2023 LPMF LIMS</span>
                        <span class="text-slate-400">Ver. {{ $footerVersion ?? 'v2.4.x' }}</span>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
