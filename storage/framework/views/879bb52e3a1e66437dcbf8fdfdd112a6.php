<!DOCTYPE html>
<html lang="id" data-ui="marketing" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LIMS Farmapol Pusdokkes Polri</title>
    <meta name="description" content="Laboratory Information Management System (LIMS) Farmapol Pusdokkes Polri – Transparansi, kecepatan, dan akurasi pengelolaan pengujian forensik farmasi kepolisian.">
    <script>(function(){try{var ls=localStorage.getItem('ui.theme');var m=window.matchMedia('(prefers-color-scheme: dark)').matches;if(ls==='dark'||(!ls&&m)){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();</script>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="antialiased font-body bg-white text-accent-900 dark:bg-accent-900 dark:text-accent-100">
    <!-- Hero -->
    <header class="relative overflow-hidden bg-gradient-to-b from-primary-50 to-white dark:from-accent-900 dark:to-accent-800">
        <div class="absolute inset-0 pointer-events-none" aria-hidden="true"></div>
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <div class="flex items-center gap-3">
                <img src="/images/logo-pusdokkes-polri.png" alt="Logo Pusdokkes" class="h-10 w-10" loading="lazy">
                <span class="font-display text-lg font-semibold tracking-tight text-primary-800 dark:text-primary-300">Farmapol LIMS</span>
            </div>
            <div class="flex items-center gap-4">
                <button type="button" onclick="window.__toggleTheme()" class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-primary-200 dark:border-accent-600 text-primary-600 dark:text-accent-200 hover:bg-primary-50 dark:hover:bg-accent-700 focus:outline-none focus:ring-2 focus:ring-primary-500" aria-label="Toggle theme">
                    <svg class="h-5 w-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.07 6.07-1.42-1.42M8.35 8.35 6.93 6.93m10.12 0-1.42 1.42M8.35 15.65l-1.42 1.42"/></svg>
                    <svg class="h-5 w-5 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                </button>
                <a href="<?php echo e(route('public.tracking')); ?>" class="text-sm font-medium text-accent-600 hover:text-primary-700 dark:text-accent-300 dark:hover:text-primary-300">Pelacakan</a>
                <?php if(Route::has('login')): ?>
                    <?php if(auth()->guard()->check()): ?>
                        <a href="<?php echo e(route('dashboard')); ?>" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">Dashboard</a>
                    <?php else: ?>
                        <a href="<?php echo e(route('login')); ?>" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">Masuk</a>
                        <?php if(Route::has('register')): ?>
                            <a href="<?php echo e(route('register')); ?>" class="text-sm font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-200">Daftar</a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </nav>
        <div class="mx-auto max-w-7xl px-6 py-16 sm:py-24 lg:flex lg:items-center lg:gap-16 lg:px-8">
            <div class="max-w-2xl">
                <h1 class="text-3xl font-bold tracking-tight text-accent-900 sm:text-4xl md:text-5xl">
                    Modernisasi Manajemen Pengujian <span class="text-primary-600">Farmasi Forensik</span>
                </h1>
                <p class="mt-6 text-base leading-7 text-accent-600 max-w-xl">
                    Sistem terpadu untuk permintaan, proses laboratorium, pelacakan status, dokumentasi, dan pelaporan hasil uji – meningkatkan akurasi, kecepatan, dan transparansi pelayanan Pusdokkes Polri.
                </p>
                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="<?php echo e(route('login')); ?>" class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <span>Mulai Gunakan</span>
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'arrow-right','size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'arrow-right','size' => 'sm']); ?>
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
                    </a>
                    <a href="#fitur" class="inline-flex items-center gap-2 rounded-md border border-primary-200 px-6 py-3 text-sm font-semibold text-primary-700 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500">Lihat Fitur</a>
                </div>
            </div>
            <div class="mt-12 lg:mt-0 flex-1">
                <div class="relative rounded-xl border bg-white p-4 shadow-md ring-1 ring-primary-100">
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-lg bg-primary-50 p-3">
                            <p class="font-semibold text-primary-700">Permintaan</p>
                            <p class="mt-1 text-accent-600">Pengajuan & validasi terstruktur</p>
                        </div>
                        <div class="rounded-lg bg-secondary-50 p-3">
                            <p class="font-semibold text-secondary-700">Pengujian</p>
                            <p class="mt-1 text-accent-600">Proses laboratorium terdokumentasi</p>
                        </div>
                        <div class="rounded-lg bg-success-50 p-3">
                            <p class="font-semibold text-success-700">Pelacakan</p>
                            <p class="mt-1 text-accent-600">Status transparan realtime</p>
                        </div>
                        <div class="rounded-lg bg-warning-50 p-3">
                            <p class="font-semibold text-warning-700">Pelaporan</p>
                            <p class="mt-1 text-accent-600">Dokumen BA & LHU otomatis</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Value Props -->
    <section id="fitur" class="mx-auto max-w-7xl px-6 py-20 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-2xl font-bold tracking-tight text-accent-900 sm:text-3xl">Fitur Inti</h2>
            <p class="mt-3 text-accent-600">Dirancang untuk alur kerja laboratorium kepolisian yang akurat, terukur, dan cepat.</p>
        </div>
        <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php $features = [
                ['icon' => 'document', 'title' => 'Manajemen Permintaan', 'desc' => 'Pengajuan terstruktur dengan nomor otomatis dan validasi.'],
                ['icon' => 'check', 'title' => 'Proses Tahap Uji', 'desc' => 'Tracking tiap tahap: preparasi, instrumen, verifikasi.'],
                ['icon' => 'search', 'title' => 'Pelacakan Publik', 'desc' => 'Masyarakat / penyidik dapat memantau status secara aman.'],
                ['icon' => 'shield', 'title' => 'Kontrol & Audit', 'desc' => 'Jejak audit & kontrol akses berbasis peran.'],
                ['icon' => 'download', 'title' => 'Dokumen Otomatis', 'desc' => 'BA Penyerahan & Laporan Hasil Uji (HTML/PDF).'],
                ['icon' => 'settings', 'title' => 'Konfigurasi Fleksibel', 'desc' => 'Template, penomoran, branding, dan lokalitas.'],
            ]; ?>
            <?php $__currentLoopData = $features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="relative flex flex-col rounded-xl border border-accent-200 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $f['icon'],'size' => 'md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($f['icon']),'size' => 'md']); ?>
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
                    <h3 class="text-sm font-semibold text-accent-900"><?php echo e($f['title']); ?></h3>
                    <p class="mt-2 text-sm leading-6 text-accent-600"><?php echo e($f['desc']); ?></p>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </section>

    <!-- Workflow Illustration -->
    <section class="bg-accent-50 py-20">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-accent-900 sm:text-3xl">Alur Terintegrasi</h2>
                    <p class="mt-4 text-sm leading-6 text-accent-600">Mulai dari permintaan masuk hingga dokumen akhir diserahkan – semua terdokumentasi, terstandardisasi, dan mudah diaudit.</p>
                    <ul class="mt-6 space-y-3 text-sm text-accent-700">
                        <li class="flex gap-2"><span class="text-primary-600">1.</span> Pengajuan Permintaan & Validasi</li>
                        <li class="flex gap-2"><span class="text-primary-600">2.</span> Preparasi & Uji Instrumen</li>
                        <li class="flex gap-2"><span class="text-primary-600">3.</span> Analisis & Verifikasi</li>
                        <li class="flex gap-2"><span class="text-primary-600">4.</span> Pelacakan & Monitoring Publik</li>
                        <li class="flex gap-2"><span class="text-primary-600">5.</span> Dokumen BA & Laporan Hasil Uji</li>
                        <li class="flex gap-2"><span class="text-primary-600">6.</span> Penyerahan & Survey Kepuasan</li>
                    </ul>
                </div>
                <div class="relative rounded-2xl border border-accent-200 bg-white p-6 shadow-md">
                    <div class="grid gap-4">
                        <div class="flex items-center gap-3"><span class="h-6 w-6 rounded-full bg-primary-600 text-[10px] font-bold text-white flex items-center justify-center">1</span><p class="text-xs font-medium">Permintaan masuk (validasi otom.)</p></div>
                        <div class="flex items-center gap-3"><span class="h-6 w-6 rounded-full bg-primary-600 text-[10px] font-bold text-white flex items-center justify-center">2</span><p class="text-xs font-medium">Preparasi sampel & chain-of-custody</p></div>
                        <div class="flex items-center gap-3"><span class="h-6 w-6 rounded-full bg-primary-600 text-[10px] font-bold text-white flex items-center justify-center">3</span><p class="text-xs font-medium">Pengujian instrumen & logging</p></div>
                        <div class="flex items-center gap-3"><span class="h-6 w-6 rounded-full bg-primary-600 text-[10px] font-bold text-white flex items-center justify-center">4</span><p class="text-xs font-medium">Analisis, verifikasi & QA</p></div>
                        <div class="flex items-center gap-3"><span class="h-6 w-6 rounded-full bg-primary-600 text-[10px] font-bold text-white flex items-center justify-center">5</span><p class="text-xs font-medium">Dokumen otomatis (BA, LHU)</p></div>
                        <div class="flex items-center gap-3"><span class="h-6 w-6 rounded-full bg-primary-600 text-[10px] font-bold text-white flex items-center justify-center">6</span><p class="text-xs font-medium">Penyerahan & survey kepuasan</p></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- KPI / Stats -->
    <section class="py-20">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-2xl font-bold tracking-tight text-accent-900 sm:text-3xl">Dampak Operasional</h2>
                <p class="mt-3 text-accent-600">Meningkatkan efisiensi dan visibilitas kinerja laboratorium.</p>
            </div>
            <dl class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <?php $stats = [
                    ['value' => '98%', 'label' => 'Ketepatan Waktu Dokumen'],
                    ['value' => '40%', 'label' => 'Percepatan Siklus Uji'],
                    ['value' => '100%', 'label' => 'Jejak Audit Tahapan'],
                    ['value' => '24/7', 'label' => 'Akses Pelacakan Publik'],
                ]; ?>
                <?php $__currentLoopData = $stats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex flex-col items-center rounded-xl border border-accent-200 bg-white p-6 shadow-sm">
                        <dt class="text-sm font-medium text-accent-600"><?php echo e($s['label']); ?></dt>
                        <dd class="mt-2 text-2xl font-semibold text-primary-700"><?php echo e($s['value']); ?></dd>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </dl>
        </div>
    </section>

    <!-- Testimonials Placeholder -->
    <section class="bg-white py-20 border-t border-accent-100">
        <div class="mx-auto max-w-5xl px-6 lg:px-8 text-center">
            <h2 class="text-2xl font-bold tracking-tight text-accent-900 sm:text-3xl">Kepercayaan & Kualitas</h2>
            <p class="mt-3 text-accent-600 max-w-2xl mx-auto">Fokus pada integritas data, keamanan, dan keandalan proses untuk mendukung penegakan hukum berbasis evidensi.</p>
            <div class="mt-10 grid gap-8 md:grid-cols-3">
                <?php $__currentLoopData = [1,2,3]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="rounded-xl border border-accent-200 bg-white p-6 shadow-sm">
                        <p class="text-sm leading-6 text-accent-700">“Platform ini membantu mempercepat koordinasi dan memastikan setiap tahap tercatat jelas dan akuntabel.”</p>
                        <div class="mt-4 flex items-center justify-center gap-3">
                            <span class="h-10 w-10 rounded-full bg-accent-200"></span>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-accent-900">Pengguna Internal <?php echo e($i); ?></p>
                                <p class="text-xs text-accent-600">Laboratorium Forensik</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="relative isolate overflow-hidden bg-primary-700 py-20">
        <div class="mx-auto max-w-3xl px-6 text-center">
            <h2 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Siap meningkatkan kualitas operasional?</h2>
            <p class="mt-4 text-sm leading-6 text-primary-100">Masuk sekarang dan kelola alur pengujian dengan lebih efektif.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="<?php echo e(route('login')); ?>" class="inline-flex items-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-primary-700 shadow-sm hover:bg-primary-50">Masuk Sistem</a>
                <a href="<?php echo e(route('public.tracking')); ?>" class="inline-flex items-center rounded-md border border-primary-100 bg-primary-600/20 px-6 py-3 text-sm font-semibold text-white hover:bg-primary-600/30">Pelacakan Publik</a>
            </div>
        </div>
    </section>

    <footer class="border-t border-accent-100 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4 text-sm text-accent-500">
                <p>&copy; <?php echo e(date('Y')); ?> Pusdokkes Polri. Semua hak dilindungi.</p>
                <nav class="flex gap-4">
                    <a href="#fitur" class="hover:text-accent-700">Fitur</a>
                    <a href="#" class="hover:text-accent-700">Kebijakan</a>
                    <a href="#" class="hover:text-accent-700">Kontak</a>
                </nav>
            </div>
        </div>
    </footer>
</body>
</html>
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/landing.blade.php ENDPATH**/ ?>