<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" <?php echo $__env->yieldPushContent('html-attrs'); ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo e(config('app.name', 'Pusdokkes Sub-Satker')); ?></title>

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
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body class="font-sans antialiased bg-medical dark:bg-accent-900 dark:text-accent-100">
    <!-- Skip to main content for keyboard users -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 bg-white text-primary-800 border border-primary-300 rounded px-3 py-2 shadow">
        Lewati ke konten utama
    </a>
    <div class="min-h-screen flex flex-col">
        <?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="absolute top-2 right-2 z-40">
            <form method="POST" action="<?php echo e(route('locale.switch', ['locale' => app()->getLocale()==='id' ? 'en':'id'])); ?>" class="inline">
                <?php echo csrf_field(); ?>
                <button type="submit" class="text-xs px-2 py-1 rounded border-sem-subtle bg-white/70 dark:bg-accent-700/60 backdrop-blur hover:bg-white dark:hover:bg-accent-600 transition" title="Switch Language">
                    <?php echo e(app()->getLocale()==='id' ? 'EN' : 'ID'); ?>

                </button>
            </form>
        </div>

        <!-- Page Heading -->
        <?php if(isset($header)): ?>
            <header class="bg-white/70 backdrop-blur supports-[backdrop-filter]:bg-white/60 border-b border-primary-100">
                <div class="container mx-auto max-w-7xl py-4 px-4 sm:px-6 lg:px-8">
                    <?php echo e($header); ?>

                </div>
            </header>
        <?php endif; ?>

        <!-- Page Content -->
        <main id="main-content" class="flex-1 <?php if(!isset($header)): ?> pt-6 <?php endif; ?>">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                <?php if(isset($slot)): ?>
                    <?php echo e($slot); ?>

                <?php else: ?>
                    <?php echo $__env->yieldContent('content'); ?>
                <?php endif; ?>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-primary-100 bg-white/70 backdrop-blur supports-[backdrop-filter]:bg-white/60">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 text-sm text-primary-600 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="opacity-90">&copy; <?php echo e(date('Y')); ?> Pusdokkes Polri · Sub-Satker Farmapol</div>
                <div class="flex items-center gap-3">
                    <span class="hidden sm:inline">•</span>
                    <a href="<?php echo e(url('/track')); ?>" class="hover:text-primary-800">Lacak Permintaan</a>
                    <span>•</span>
                    <a href="<?php echo e(url('/statistics')); ?>" class="hover:text-primary-800">Statistik</a>
                </div>
            </div>
        </footer>
    </div>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /home/lpmf-dev/website-/resources/views/layouts/app.blade.php ENDPATH**/ ?>