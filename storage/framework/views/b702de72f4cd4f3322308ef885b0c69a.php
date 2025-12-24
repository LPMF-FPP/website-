<?php
    $initialSettings = $settings ?? [];
    $initialRoles = data_get($initialSettings, 'security.roles', []);
    $initialManageRoles = data_get($initialRoles, 'can_manage_settings', []);
    $initialIssueRoles = data_get($initialRoles, 'can_issue_number', []);
    $tz = data_get($initialSettings, 'locale.timezone', 'Asia/Jakarta');
    $fmtTok = data_get($initialSettings, 'locale.date_format', 'DD/MM/YYYY');
    $phpFmtMap = [
        'DD/MM/YYYY' => 'd/m/Y',
        'YYYY-MM-DD' => 'Y-m-d',
        'DD-MM-YYYY' => 'd-m-Y',
    ];
    $phpFmt = $phpFmtMap[$fmtTok] ?? 'd/m/Y';
    $initialNowPreview = \Carbon\Carbon::now($tz)->format($phpFmt . ' H:i:s');
?>

<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Pengaturan LIMS','breadcrumbs' => [['label' => 'Settings']],'description' => 'On-load GET /api/settings + current numbering + preview + save per-section']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Pengaturan LIMS','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label' => 'Settings']]),'description' => 'On-load GET /api/settings + current numbering + preview + save per-section']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
     <?php $__env->endSlot(); ?>

    <div x-data="settingsPageAlpine" x-init="init()" class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        
        <div class="mb-6 bg-white rounded-lg shadow-sm p-4 border border-gray-200">
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-gray-600">UI state:</span>
                
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full border" :class="client.state.pageLoading ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'">
                    <svg x-show="client.state.pageLoading" class="animate-spin h-4 w-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                        <path class="opacity-75" stroke-width="4" d="M4 12a8 8 0 018-8" stroke-linecap="round"></path>
                    </svg>
                    <span class="text-xs" :class="client.state.pageLoading ? 'text-blue-700' : 'text-gray-500'">Loading GET /settings</span>
                </div>
                
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full border" :class="client.state.saved ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'">
                    <svg x-show="client.state.saved" class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="text-xs" :class="client.state.saved ? 'text-green-700' : 'text-gray-500'">Saved per-section</span>
                </div>
                
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full border" :class="client.state.error ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'">
                    <svg x-show="client.state.error" class="h-4 w-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-xs" :class="client.state.error ? 'text-red-700' : 'text-gray-500'">Error inline</span>
                </div>
                
                <span class="text-xs text-gray-500 ml-auto">Buttons disabled when loading</span>
            </div>
        </div>

        
        <div class="flex gap-6" x-show="!client.state.pageLoading" x-cloak>
            
            
            <div class="w-80 flex-shrink-0">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 px-3">Sections</h3>
                    <nav class="space-y-2">
                        <button 
                            @click="activeSection = 'numbering'" 
                            :class="activeSection === 'numbering' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm">
                            Penomoran Otomatis
                        </button>
                        
                        <a
                            href="<?php echo e(route('settings.blade-templates')); ?>"
                            class="block w-full text-left px-4 py-3 rounded-lg transition-colors text-sm bg-white text-gray-700 hover:bg-gray-50"
                        >
                            Template Dokumen
                        </a>
                        
                        <button 
                            @click="activeSection = 'localization'" 
                            :class="activeSection === 'localization' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm">
                            Lokalisasi & Retensi
                        </button>
                        
                        <button 
                            @click="activeSection = 'notifications'" 
                            :class="activeSection === 'notifications' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm">
                            Notifikasi & Security
                        </button>

                        <button 
                            @click="activeSection = 'documents'" 
                            :class="activeSection === 'documents' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm">
                            Manajemen Dokumen
                        </button>
                    </nav>
                </div>
            </div>

            
            <div class="flex-1 min-w-0">
                <div x-show="activeSection === 'numbering'">
                    <?php echo $__env->make('settings.partials.numbering', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
                
                <div x-show="activeSection === 'templates'">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold text-gray-900">Template Dokumen</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Pengelolaan template dokumen telah dipindahkan ke halaman khusus.
                        </p>
                        <div class="mt-4">
                            <a
                                href="<?php echo e(route('settings.blade-templates')); ?>"
                                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                            >
                                Buka Blade Templates
                            </a>
                        </div>
                    </div>
                </div>
                
                <div x-show="activeSection === 'localization'">
                    <?php echo $__env->make('settings.partials.localization-retention', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
                
                <div x-show="activeSection === 'notifications'">
                    <?php echo $__env->make('settings.partials.notifications-security', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>

                <div x-show="activeSection === 'documents'">
                    <?php echo $__env->make('settings.partials.documents', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
            </div>

        </div>
    </div>

    
    <script>
        window.__SETTINGS_INITIAL_DATA__ = {
            initialForm: <?php echo json_encode($initialSettings, 15, 512) ?>,
            initialTemplates: <?php echo json_encode($templates ?? [], 15, 512) ?>,
            optionValues: <?php echo json_encode($options ?? [], 15, 512) ?>,
            initialManageRoles: <?php echo json_encode($initialManageRoles ?? [], 15, 512) ?>,
            initialIssueRoles: <?php echo json_encode($initialIssueRoles ?? [], 15, 512) ?>,
            initialNowPreview: '<?php echo e($initialNowPreview); ?>',
        };
    </script>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.js', 'resources/js/pages/settings/index.js']); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH /home/lpmf-dev/website-/resources/views/settings/index.blade.php ENDPATH**/ ?>