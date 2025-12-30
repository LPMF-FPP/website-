<!DOCTYPE html>
<html lang="id" data-ui="marketing" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LIMS Farmapol — Pusdokkes Polri</title>
    <meta name="description" content="Sistem Informasi Laboratorium Farmasi Forensik Pusdokkes Polri.">
    <script>(function(){try{var ls=localStorage.getItem('ui.theme');var m=window.matchMedia('(prefers-color-scheme: dark)').matches;if(ls==='dark'||(!ls&&m)){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();</script>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
    <style>
        .font-feature-settings { font-feature-settings: "cv11", "ss01"; }
    </style>
</head>
<body class="antialiased font-body bg-white text-accent-900 dark:bg-accent-900 dark:text-accent-100 selection:bg-primary-500 selection:text-white">

    <!-- Navbar (Sticky, solid white/glass) -->
    <nav class="sticky top-0 z-50 bg-white/98 dark:bg-accent-900/98 backdrop-blur-md border-b border-accent-100 dark:border-accent-800 shadow-sm">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="flex h-20 items-center justify-between">
                <div class="flex items-center gap-4">
                    <img src="/images/logo-pusdokkes-polri.png" alt="Logo Pusdokkes" class="h-10 w-10" loading="lazy">
                    <div class="hidden sm:block">
                        <p class="font-display text-lg font-bold text-accent-900 dark:text-white leading-tight">LIMS Farmapol</p>
                        <p class="text-xs font-semibold text-primary-600 dark:text-primary-400 tracking-wider">PUSDOKKES POLRI</p>
                    </div>
                </div>
                <div class="flex items-center gap-8">
                    <div class="hidden md:flex gap-8">
                        <a href="#tentang" class="text-sm font-semibold text-accent-600 hover:text-primary-700 dark:text-accent-300 transition-colors">Tentang</a>
                        <a href="#layanan" class="text-sm font-semibold text-accent-600 hover:text-primary-700 dark:text-accent-300 transition-colors">Layanan</a>
                        <a href="#faq" class="text-sm font-semibold text-accent-600 hover:text-primary-700 dark:text-accent-300 transition-colors">FAQ</a>
                        <a href="<?php echo e(route('public.tracking')); ?>" class="text-sm font-semibold text-accent-600 hover:text-primary-700 dark:text-accent-300 transition-colors">Pelacakan</a>
                    </div>
                    <div class="flex items-center gap-4">
                        <button type="button" onclick="window.__toggleTheme()" class="text-accent-500 hover:text-accent-900 dark:text-accent-400 dark:hover:text-white transition-colors p-2" aria-label="Toggle theme">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'moon','class' => 'h-5 w-5 dark:hidden']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'moon','class' => 'h-5 w-5 dark:hidden']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'sun','class' => 'h-5 w-5 hidden dark:block']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sun','class' => 'h-5 w-5 hidden dark:block']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                        </button>
                        <?php if(auth()->guard()->check()): ?>
                            <a href="<?php echo e(route('dashboard')); ?>" class="inline-flex items-center rounded-full bg-primary-700 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary-800 transition-transform active:scale-95">Dashboard</a>
                        <?php else: ?>
                            <a href="<?php echo e(route('login')); ?>" class="inline-flex items-center rounded-full bg-primary-700 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary-800 transition-transform active:scale-95">Masuk</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section (Solid Primary Background) -->
    <!-- INA Digital style: Solid bold background, left aligned text -->
    <header class="bg-primary-700 dark:bg-accent-800 relative overflow-hidden">
        <!-- Abstract Decoration -->
        <div class="absolute inset-0 opacity-5 pattern-grid-lg text-white"></div>
        <div class="absolute right-0 top-0 -mt-20 -mr-20 h-[500px] w-[500px] rounded-full bg-primary-500 blur-3xl opacity-10"></div>
        
        <div class="relative mx-auto max-w-7xl px-6 py-20 sm:py-28 lg:px-8">
            <div class="max-w-2xl">
                <div class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-white ring-1 ring-inset ring-white/20 mb-6 backdrop-blur-sm">
                    <span class="mr-2 h-2 w-2 rounded-full bg-green-400"></span>
                    Sistem Terintegrasi v1.0
                </div>
                <h1 class="text-4xl font-bold tracking-tight text-white sm:text-6xl font-display leading-[1.1]">
                    Modernisasi Pengujian <br> <span class="text-primary-200">Farmasi Forensik.</span>
                </h1>
                <p class="mt-6 text-lg leading-8 text-primary-50 max-w-xl text-balance">
                    Platform digital Pusdokkes Polri untuk manajemen laboratorium yang transparan, akuntabel, dan presisi. Mendukung penegakan hukum berbasis scientific crime investigation.
                </p>
                <div class="mt-10 flex items-center gap-x-6">
                    <a href="<?php echo e(route('login')); ?>" class="rounded-full bg-white px-8 py-3.5 text-sm font-bold text-primary-700 shadow-sm hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white transition-all">Mulai Sekarang</a>
                    <a href="#tentang" class="text-sm font-semibold leading-6 text-white flex items-center gap-2 hover:gap-3 transition-all">
                        Pelajari Lebih Lanjut <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- About / Value Props (Split Layout) -->
    <!-- INA Digital style: Clean white section, structured grid -->
    <section id="tentang" class="bg-white dark:bg-accent-900 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
                <div>
                    <h2 class="text-base font-bold leading-7 text-primary-600 dark:text-primary-400 uppercase tracking-wide">Tentang Sistem</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-accent-900 dark:text-white sm:text-4xl font-display">
                        Standar Baru dalam <br>Manajemen Barang Bukti.
                    </p>
                    <p class="mt-6 text-lg leading-8 text-accent-600 dark:text-accent-300">
                        LIMS Farmapol memastikan seluruh rantai pengujian (Chain of Custody) terdokumentasi secara digital. Menghilangkan proses manual yang rentan kesalahan dan meningkatkan kecepatan layanan teknis kepolisian.
                    </p>
                    <div class="mt-8 pt-8 border-t border-accent-100 dark:border-accent-800">
                        <div class="flex gap-4">
                            <div class="flex-none">
                                <div class="h-10 w-1 bg-primary-600 rounded-full"></div>
                            </div>
                            <blockquote class="text-base italic text-accent-800 dark:text-accent-200 font-medium">
                                "Integritas data adalah kunci dalam pembuktian forensik. Sistem ini adalah wujud komitmen kami terhadap transparansi."
                            </blockquote>
                        </div>
                    </div>
                </div>
                
                <!-- 2x2 Grid Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="rounded-2xl bg-accent-50 dark:bg-accent-800/50 p-8 hover:bg-accent-100 dark:hover:bg-accent-800 transition-colors">
                        <div class="h-10 w-10 rounded-lg bg-white dark:bg-accent-700 flex items-center justify-center shadow-sm mb-4">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'document-text','class' => 'h-6 w-6 text-primary-600']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'document-text','class' => 'h-6 w-6 text-primary-600']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                        </div>
                        <h3 class="font-bold text-accent-900 dark:text-white text-lg">Terdokumentasi</h3>
                        <p class="mt-2 text-sm text-accent-600 dark:text-accent-400 leading-relaxed">Pencatatan otomatis seluruh aktivitas pengujian dalam satu database terpusat.</p>
                    </div>
                    <div class="rounded-2xl bg-accent-50 dark:bg-accent-800/50 p-8 hover:bg-accent-100 dark:hover:bg-accent-800 transition-colors">
                        <div class="h-10 w-10 rounded-lg bg-white dark:bg-accent-700 flex items-center justify-center shadow-sm mb-4">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'shield-check','class' => 'h-6 w-6 text-primary-600']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shield-check','class' => 'h-6 w-6 text-primary-600']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                        </div>
                        <h3 class="font-bold text-accent-900 dark:text-white text-lg">Audit Trail</h3>
                        <p class="mt-2 text-sm text-accent-600 dark:text-accent-400 leading-relaxed">Jejak digital yang tidak dapat diubah untuk menjamin keaslian data.</p>
                    </div>
                    <div class="rounded-2xl bg-accent-50 dark:bg-accent-800/50 p-8 hover:bg-accent-100 dark:hover:bg-accent-800 transition-colors">
                        <div class="h-10 w-10 rounded-lg bg-white dark:bg-accent-700 flex items-center justify-center shadow-sm mb-4">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'clock','class' => 'h-6 w-6 text-primary-600']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'clock','class' => 'h-6 w-6 text-primary-600']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                        </div>
                        <h3 class="font-bold text-accent-900 dark:text-white text-lg">Real-time</h3>
                        <p class="mt-2 text-sm text-accent-600 dark:text-accent-400 leading-relaxed">Pemantauan progres pengujian secara langsung oleh pihak berwenang.</p>
                    </div>
                    <div class="rounded-2xl bg-accent-50 dark:bg-accent-800/50 p-8 hover:bg-accent-100 dark:hover:bg-accent-800 transition-colors">
                        <div class="h-10 w-10 rounded-lg bg-white dark:bg-accent-700 flex items-center justify-center shadow-sm mb-4">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'check-circle','class' => 'h-6 w-6 text-primary-600']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check-circle','class' => 'h-6 w-6 text-primary-600']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                        </div>
                        <h3 class="font-bold text-accent-900 dark:text-white text-lg">Terstandar</h3>
                        <p class="mt-2 text-sm text-accent-600 dark:text-accent-400 leading-relaxed">Penerapan SOP ISO 17025 dalam setiap langkah kerja sistem.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services / Workflow Grid -->
    <!-- INA Digital style: Light colored background, card grid -->
    <section id="layanan" class="bg-primary-50/50 dark:bg-accent-800 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center mb-16">
                <h2 class="text-3xl font-bold tracking-tight text-accent-900 dark:text-white sm:text-4xl font-display">Lingkup Layanan</h2>
                <p class="mt-4 text-lg text-accent-600 dark:text-accent-300">Modul komprehensif untuk mendukung operasional harian.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                $services = [
                    ['title' => 'Manajemen Permintaan', 'desc' => 'Registrasi barang bukti masuk dengan validasi dokumen dan penomoran perkara otomatis.', 'icon' => 'folder-open'],
                    ['title' => 'Pengujian Laboratorium', 'desc' => 'Pencatatan parameter uji, metode analisis, dan hasil raw data instrumen.', 'icon' => 'beaker'],
                    ['title' => 'Penerbitan Dokumen', 'desc' => 'Generate otomatis Berita Acara (BA) dan Laporan Hasil Uji (LHU) format pro-justitia.', 'icon' => 'document-duplicate'],
                    ['title' => 'Verifikasi Berjenjang', 'desc' => 'Alur persetujuan bertingkat dari Analis, Supervisor, hingga Kepala Laboratorium.', 'icon' => 'user-group'],
                    ['title' => 'Manajemen Stok', 'desc' => 'Monitoring ketersediaan reagen dan bahan habis pakai secara real-time.', 'icon' => 'cube'],
                    ['title' => 'Pelacakan Publik', 'desc' => 'Portal khusus bagi penyidik untuk memantau status penyelesaian sampel.', 'icon' => 'search-circle'],
                ];
                ?>
                
                <?php $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $svc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="bg-white dark:bg-accent-900 rounded-3xl p-8 shadow-sm ring-1 ring-accent-100 dark:ring-accent-700/50 hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 bg-primary-50 dark:bg-primary-900/20 rounded-2xl flex items-center justify-center text-primary-600 dark:text-primary-400 mb-6">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $svc['icon'],'class' => 'w-6 h-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($svc['icon']),'class' => 'w-6 h-6']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                    </div>
                    <h3 class="text-xl font-bold text-accent-900 dark:text-white mb-3"><?php echo e($svc['title']); ?></h3>
                    <p class="text-base text-accent-600 dark:text-accent-400 leading-relaxed"><?php echo e($svc['desc']); ?></p>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="bg-white dark:bg-accent-900 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight text-accent-900 dark:text-white sm:text-4xl font-display">Pertanyaan Umum</h2>
                    <p class="mt-4 text-base text-accent-600 dark:text-accent-300 leading-relaxed">
                        Jawaban atas pertanyaan yang sering diajukan mengenai penggunaan LIMS Farmapol.
                    </p>
                    <div class="mt-8">
                        <a href="#" class="text-sm font-semibold text-primary-600 hover:text-primary-700 flex items-center gap-2">
                            Hubungi Bantuan Teknis <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>
                <div class="lg:col-span-2 space-y-6">
                    <?php
                    $faqs = [
                        ['q' => 'Siapa yang dapat mengakses sistem ini?', 'a' => 'Akses sistem dibatasi hanya untuk personel internal Pusdokkes Polri dan Satwil yang telah terdaftar dan diverifikasi.'],
                        ['q' => 'Bagaimana cara mendapatkan akun?', 'a' => 'Pendaftaran akun dilakukan melalui admin pusat. Silakan hubungi bagian administrasi untuk permohonan akses.'],
                        ['q' => 'Apakah data aman?', 'a' => 'Ya, kami menggunakan enkripsi end-to-end dan server internal Polri untuk menjamin kerahasiaan data investigasi.'],
                        ['q' => 'Bagaimana melacak progres sampel?', 'a' => 'Gunakan fitur "Pelacakan Publik" di menu atas, masukkan nomor registrasi sampel yang tertera pada tanda terima.'],
                    ];
                    ?>
                    
                    <?php $__currentLoopData = $faqs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $faq): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="group border-b border-accent-100 dark:border-accent-800 pb-6 last:border-0 last:pb-0">
                        <details class="group">
                            <summary class="flex items-center justify-between cursor-pointer list-none">
                                <h3 class="text-lg font-semibold text-accent-900 dark:text-white group-hover:text-primary-600 transition-colors">
                                    <?php echo e($faq['q']); ?>

                                </h3>
                                <span class="bg-accent-50 dark:bg-accent-800 p-2 rounded-lg text-accent-400 group-open:text-primary-600 transition-colors">
                                    <svg class="w-5 h-5 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </span>
                            </summary>
                            <div class="mt-4 text-base text-accent-600 dark:text-accent-400 leading-relaxed pl-1">
                                <?php echo e($faq['a']); ?>

                            </div>
                        </details>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <!-- INA Digital style: Dark background, solid structure -->
    <footer class="bg-accent-900 text-white py-16">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 border-b border-accent-800 pb-12 mb-12">
                <div class="col-span-1 md:col-span-1">
                    <img src="/images/logo-pusdokkes-polri.png" alt="Logo" class="h-12 w-auto mb-6 grayscale brightness-200">
                    <p class="text-sm leading-relaxed text-accent-300">
                        Pusat Kedokteran dan Kesehatan Polri.<br>
                        Laboratorium Farmasi Forensik.
                    </p>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-accent-400 mb-4">Sistem</h3>
                    <ul class="space-y-3">
                        <li><a href="#tentang" class="text-sm text-accent-300 hover:text-white transition-colors">Tentang LIMS</a></li>
                        <li><a href="#layanan" class="text-sm text-accent-300 hover:text-white transition-colors">Fitur & Layanan</a></li>
                        <li><a href="<?php echo e(route('login')); ?>" class="text-sm text-accent-300 hover:text-white transition-colors">Login Staff</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-accent-400 mb-4">Bantuan</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-sm text-accent-300 hover:text-white transition-colors">Panduan Pengguna</a></li>
                        <li><a href="#" class="text-sm text-accent-300 hover:text-white transition-colors">FAQ</a></li>
                        <li><a href="#" class="text-sm text-accent-300 hover:text-white transition-colors">Kontak Support</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-accent-400 mb-4">Legal</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-sm text-accent-300 hover:text-white transition-colors">Kebijakan Privasi</a></li>
                        <li><a href="#" class="text-sm text-accent-300 hover:text-white transition-colors">Syarat Penggunaan</a></li>
                    </ul>
                </div>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-center text-sm text-accent-500">
                <p>&copy; <?php echo e(date('Y')); ?> Pusdokkes Polri. All rights reserved.</p>
                <div class="flex items-center gap-2 mt-4 md:mt-0">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                    <span>System Operational</span>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/landing.blade.php ENDPATH**/ ?>