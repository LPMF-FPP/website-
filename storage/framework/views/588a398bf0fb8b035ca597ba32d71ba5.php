<?php $__env->startPush('html-attrs'); ?> data-ui="minimal" data-theme="light" <?php $__env->stopPush(); ?>
<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/ui-scope.css']); ?>
<?php $__env->stopPush(); ?>
<?php $__env->startPush('scripts'); ?>
    <script type="module" src="/scripts/ui.theme-toggle.js"></script>
<?php $__env->stopPush(); ?>

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
        <div>
            <?php if (isset($component)) { $__componentOriginal360d002b1b676b6f84d43220f22129e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal360d002b1b676b6f84d43220f22129e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.breadcrumbs','data' => ['items' => [[ 'label' => 'Permintaan' ]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([[ 'label' => 'Permintaan' ]])]); ?>
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
                📋 Daftar Permintaan Pengujian
            </h2>
        </div>
     <?php $__env->endSlot(); ?>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <?php if(session('success')): ?>
            <div class="mb-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-success-700">
                <?php echo e(session('success')); ?>

            </div>
        <?php endif; ?>

        <div class="card overflow-hidden" x-data="{ loading: false }">
            <div class="space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold text-primary-900">Daftar Permintaan</h3>
                    <a href="<?php echo e(route('requests.create')); ?>"
                       class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white transition hover:bg-primary-700">
                        <span>➕</span>
                        <span>Buat Permintaan Baru</span>
                    </a>
                </div>

                <template x-if="loading">
                    <?php if (isset($component)) { $__componentOriginalb3a7e296665e2f58ea67cad97892cbe7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb3a7e296665e2f58ea67cad97892cbe7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.skeleton-table','data' => ['columns' => 6,'rows' => 8]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('skeleton-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['columns' => 6,'rows' => 8]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb3a7e296665e2f58ea67cad97892cbe7)): ?>
<?php $attributes = $__attributesOriginalb3a7e296665e2f58ea67cad97892cbe7; ?>
<?php unset($__attributesOriginalb3a7e296665e2f58ea67cad97892cbe7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb3a7e296665e2f58ea67cad97892cbe7)): ?>
<?php $component = $__componentOriginalb3a7e296665e2f58ea67cad97892cbe7; ?>
<?php unset($__componentOriginalb3a7e296665e2f58ea67cad97892cbe7); ?>
<?php endif; ?>
                </template>

                <?php if($requests->count() > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-primary-100">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">No. Permintaan</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Penyidik</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Tersangka</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Tanggal</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-primary-100 bg-white">
                                <?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="transition hover:bg-primary-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-primary-900">
                                            <?php echo e($request->request_number); ?>

                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-primary-800">
                                            <?php echo e($request->investigator->name); ?> (<?php echo e($request->investigator->rank); ?>)
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-primary-800">
                                            <?php echo e($request->suspect_name); ?>

                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (isset($component)) { $__componentOriginal8860cf004fec956b6e41d036eb967550 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8860cf004fec956b6e41d036eb967550 = $attributes; } ?>
<?php $component = App\View\Components\StatusBadge::resolve(['status' => $request->status] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\StatusBadge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8860cf004fec956b6e41d036eb967550)): ?>
<?php $attributes = $__attributesOriginal8860cf004fec956b6e41d036eb967550; ?>
<?php unset($__attributesOriginal8860cf004fec956b6e41d036eb967550); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8860cf004fec956b6e41d036eb967550)): ?>
<?php $component = $__componentOriginal8860cf004fec956b6e41d036eb967550; ?>
<?php unset($__componentOriginal8860cf004fec956b6e41d036eb967550); ?>
<?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-accent-600">
                                            <?php echo e($request->created_at->format('d M Y')); ?>

                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php ($firstSampleId = optional($request->samples->first())->id); ?>
                                            <div class="flex flex-wrap gap-2">
                                                <a href="<?php echo e(route('samples.test.create', ['request_id' => $request->id])); ?>"
                                                   class="inline-flex items-center rounded-lg border border-primary-600 px-3 py-1 text-sm font-semibold text-primary-600 transition hover:bg-primary-50">
                                                    Pengujian
                                                </a>
                                                <?php if($firstSampleId): ?>
                                                    <a href="<?php echo e(route('sample-processes.index', ['sample_id' => $firstSampleId])); ?>"
                                                       class="inline-flex items-center rounded-lg border border-primary-200 px-3 py-1 text-sm font-semibold text-primary-700 transition hover:border-primary-500 hover:text-primary-600">
                                                        Proses
                                                    </a>
                                                <?php endif; ?>
                                                <?php if($request->status === 'ready_for_delivery'): ?>
                                                    <a href="<?php echo e(route('delivery.show', $request)); ?>"
                                                       class="inline-flex items-center rounded-lg bg-secondary-400 px-3 py-1 text-sm font-semibold text-accent-900 transition hover:bg-secondary-500">
                                                        Penyerahan
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                                                <a href="<?php echo e(route('requests.show', $request)); ?>"
                                                   class="text-primary-600 transition hover:text-primary-700">Detail</a>
                                                <a href="<?php echo e(route('requests.edit', $request)); ?>"
                                                   class="text-warning-600 transition hover:text-warning-700">Edit</a>
                                                <form method="POST" action="<?php echo e(route('requests.destroy', $request)); ?>"
                                                      class="inline" onsubmit="return confirm('Yakin ingin menghapus?')">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('DELETE'); ?>
                                                    <button type="submit" class="text-danger-600 transition hover:text-danger-700">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div>
                        <?php echo e($requests->links()); ?>

                    </div>
                <?php else: ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Belum ada permintaan pengujian','description' => 'Mulai dengan membuat permintaan pertama untuk pengujian.','actionHref' => route('requests.create'),'actionText' => 'Buat Permintaan Pertama','icon' => 'document']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Belum ada permintaan pengujian','description' => 'Mulai dengan membuat permintaan pertama untuk pengujian.','actionHref' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('requests.create')),'actionText' => 'Buat Permintaan Pertama','icon' => 'document']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
                <?php endif; ?>
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
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/requests/index.blade.php ENDPATH**/ ?>