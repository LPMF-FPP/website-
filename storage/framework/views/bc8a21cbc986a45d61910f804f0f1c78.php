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
        <div class="flex items-center justify-between">
            <div>
                <?php if (isset($component)) { $__componentOriginal360d002b1b676b6f84d43220f22129e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal360d002b1b676b6f84d43220f22129e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.breadcrumbs','data' => ['items' => []]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $attributes = $__attributesOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__attributesOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $component = $__componentOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__componentOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
                <h2 class="font-semibold text-xl text-primary-900 leading-tight">
                    <?php echo e(__('Dashboard')); ?>

                </h2>
            </div>
            <a href="<?php echo e(url()->current()); ?>" class="inline-flex items-center rounded border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-700">
                Refresh
            </a>
        </div>
     <?php $__env->endSlot(); ?>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Stats Cards (SSR) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php $cards = [
                    ['label' => 'Total Permintaan', 'key' => 'total_requests'],
                    ['label' => 'Sampel Pending', 'key' => 'pending_samples'],
                    ['label' => 'Pengujian Selesai', 'key' => 'completed_tests'],
                    ['label' => 'SLA Performance', 'key' => 'sla_performance', 'suffix' => '%'],
                ]; ?>
                <?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="card">
                    <div class="space-y-1">
                        <div class="text-3xl font-semibold text-primary-900">
                            <?php echo e($stats[$c['key']] ?? 0); ?><?php echo e($c['suffix'] ?? ''); ?>

                        </div>
                        <div class="text-sm font-medium text-accent-600"><?php echo e($c['label']); ?></div>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <!-- Tiny Status Breakdown Bar -->
            <?php
                $breakdown = $status_breakdown ?? [];
                $total = array_sum($breakdown);
                $colors = [
                    'submitted' => '#93c5fd', // blue-300
                    'in_testing' => '#fcd34d', // yellow-300
                    'analysis' => '#fdba74', // orange-300
                    'ready_for_delivery' => '#2dd4bf', // teal-400
                    'completed' => '#86efac', // green-300
                ];
            ?>
            <div class="card">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-primary-900">Status Permintaan</h3>
                        <div class="text-xs text-accent-600">Total: <?php echo e($total); ?></div>
                    </div>
                    <div class="h-3 w-full rounded bg-gray-100 overflow-hidden flex">
                        <?php $__currentLoopData = $breakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $pct = $total > 0 ? round(($val / $total) * 100, 2) : 0; ?>
                            <div title="<?php echo e($key); ?>: <?php echo e($val); ?> (<?php echo e($pct); ?>%)"
                                 style="width: <?php echo e($pct); ?>%; background: <?php echo e($colors[$key] ?? '#e5e7eb'); ?>"></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs text-accent-700">
                        <?php $__currentLoopData = $breakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="inline-flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded" style="background: <?php echo e($colors[$key] ?? '#e5e7eb'); ?>"></span>
                                <span class="capitalize"><?php echo e(str_replace('_',' ', $key)); ?></span>
                                <span class="text-accent-500">— <?php echo e($val); ?></span>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php if(empty($breakdown)): ?>
                            <div class="text-accent-500">Belum ada data status.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activities (SSR) -->
            <div class="card">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-primary-900">Aktivitas Terbaru</h3>
                    </div>
                    <?php if($recent_activities->count() > 0): ?>
                        <div class="flow-root">
                            <ul class="-mb-8">
                                <?php $__currentLoopData = $recent_activities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if($index < $recent_activities->count() - 1): ?>
                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-primary-100"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-<?php echo e($activity->color); ?>-500 flex items-center justify-center ring-2 ring-white">
                                                    <span class="text-white text-sm"><?php echo e($activity->icon); ?></span>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                <div>
                                                    <p class="text-sm font-medium text-primary-900"><?php echo e($activity->title); ?></p>
                                                    <p class="text-sm text-accent-600"><?php echo e($activity->description); ?></p>
                                                </div>
                                                <div class="text-right text-sm whitespace-nowrap text-accent-600">
                                                    <?php echo e($activity->time->diffForHumans()); ?>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <p class="text-accent-600">Belum ada aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions (unchanged other than labels earlier) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="<?php echo e(route('requests.create')); ?>" class="card block text-center hover:shadow-md transition-shadow duration-200">
                    <div class="space-y-2">
                        <div class="text-4xl mb-4">➕</div>
                        <h3 class="text-lg font-semibold text-primary-900">Buat Permintaan</h3>
                        <p class="text-sm text-accent-600">Submit permintaan pengujian baru</p>
                    </div>
                </a>

                <a href="<?php echo e(route('requests.index')); ?>" class="card block text-center hover:shadow-md transition-shadow duration-200">
                    <div class="space-y-2">
                        <div class="text-4xl mb-4">📄</div>
                        <h3 class="text-lg font-semibold text-primary-900">Lihat Permintaan</h3>
                        <p class="text-sm text-accent-600">Monitor status pengujian</p>
                    </div>
                </a>

                <a href="<?php echo e(route('tracking.index')); ?>" class="card block text-center hover:shadow-md transition-shadow duration-200">
                    <div class="space-y-2">
                        <div class="text-4xl mb-4">🔍</div>
                        <h3 class="text-lg font-semibold text-primary-900">Tracking Permintaan</h3>
                        <p class="text-sm text-accent-600">Lacak status permintaan</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

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
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/dashboard.blade.php ENDPATH**/ ?>