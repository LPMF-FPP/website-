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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Proses Pengujian Sampel','breadcrumbs' => [[ 'label' => 'Pengujian', 'href' => route('samples.test.create') ], [ 'label' => 'Proses' ]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Proses Pengujian Sampel','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([[ 'label' => 'Pengujian', 'href' => route('samples.test.create') ], [ 'label' => 'Proses' ]])]); ?>
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

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6" x-data="listFetcher()" x-init="init()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="<?php echo e(route('sample-processes.index')); ?>" class="flex flex-wrap items-end gap-3" @submit.prevent="handleFilterSubmit($event)">
                <div>
                    <label for="filter_stage" class="block text-xs font-medium text-gray-600 uppercase tracking-wide">Tahapan</label>
                    <select id="filter_stage" name="stage"
                        class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Tahapan</option>
                        <?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($stage->value); ?>" <?php if(($filters['stage'] ?? '') === $stage->value): echo 'selected'; endif; ?>><?php echo e($stage->label()); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div>
                    <label for="filter_sample_name" class="block text-xs font-medium text-gray-600 uppercase tracking-wide">
                        Nama Sampel
                        <span class="text-gray-400 normal-case">(pilih dari yang tersedia)</span>
                    </label>
                    <select id="filter_sample_name" name="sample_name"
                        class="mt-1 block w-56 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Nama</option>
                        <?php $__empty_1 = true; $__currentLoopData = $sampleNames; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <option value="<?php echo e($name); ?>" <?php if(($filters['sample_name'] ?? '') === $name): echo 'selected'; endif; ?>><?php echo e($name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <option disabled>Tidak ada nama sampel</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label for="filter_request_number" class="block text-xs font-medium text-gray-600 uppercase tracking-wide">
                        Nomor Permintaan
                    </label>
                    <select id="filter_request_number" name="request_number"
                        class="mt-1 block w-56 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Nomor</option>
                        <?php $__empty_1 = true; $__currentLoopData = $requestNumbers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $no): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <option value="<?php echo e($no); ?>" <?php if(($filters['request_number'] ?? '') === $no): echo 'selected'; endif; ?>><?php echo e($no); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <option disabled>Tidak ada nomor permintaan</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Uses centralized listFetcher from app.js -->

                <button type="submit"
                    class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">
                    Terapkan
                </button>
            </form>

            <div class="flex flex-col items-start sm:items-end gap-2">
                <a href="<?php echo e(route('sample-processes.create')); ?>"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                    Tambah Proses
                </a>

                <?php
                    $readyOptions = [];
                    foreach ($processes as $p) {
                        if (in_array($p->sample_id, $samplesReadyForDelivery ?? [])) {
                            $label = ($p->sample->sample_name ?? 'Sampel') . ' (' . ($p->sample->testRequest?->request_number ?? '-') . ')';
                            $readyOptions[$p->sample_id] = $label;
                        }
                    }
                ?>
                <?php if(!empty($readyOptions)): ?>
                    <form id="readyForm" method="POST" action="" onsubmit="return confirm('Kirim sampel ini ke Penyerahan?')" class="flex items-center gap-2">
                        <?php echo csrf_field(); ?>
                        <select id="readySampleSelect" class="block w-64 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih sampel siap diserahkan…</option>
                            <?php $__currentLoopData = $readyOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($id); ?>"><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <button type="submit" id="readySubmit" disabled class="inline-flex items-center rounded-md bg-secondary-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-secondary-600 disabled:opacity-50">
                            Siapkan Penyerahan
                        </button>
                    </form>
                    <script>
                        (function(){
                            const sel = document.getElementById('readySampleSelect');
                            const btn = document.getElementById('readySubmit');
                            const frm = document.getElementById('readyForm');
                            const base = '<?php echo e(url('samples')); ?>';
                            sel?.addEventListener('change', () => {
                                if (sel.value) {
                                    frm.action = base + '/' + sel.value + '/ready-for-delivery';
                                    btn.disabled = false;
                                } else {
                                    frm.action = '';
                                    btn.disabled = true;
                                }
                            });
                        })();
                    </script>
                <?php endif; ?>
            </div>
        </div>

        <!-- Skeleton while loading -->
        <div x-show="loading" class="mt-2">
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
        </div>

        <!-- List container (table + pagination) -->
        <div x-show="!loading" x-ref="listContainer">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Sampel</th>
                            <th class="px-4 py-3 text-left">Tahapan</th>
                            <th class="px-4 py-3 text-left">Pelaksana</th>
                            <th class="px-4 py-3 text-left">Jadwal</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        <?php $__empty_1 = true; $__currentLoopData = $processes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $process): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $isReadyForDelivery = in_array($process->sample_id, $samplesReadyForDelivery);
                            ?>
                            <tr class="hover:bg-gray-50/60 <?php echo e($isReadyForDelivery ? 'bg-blue-50/30' : ''); ?>">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">
                                        <?php echo e($process->sample->sample_name); ?>

                                        <?php if($isReadyForDelivery): ?>
                                            <span class="ml-2 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Siap Diserahkan</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Permintaan: <?php echo e($process->sample->testRequest?->request_number ?? '-'); ?></div>
                                </td>
                                <td class="px-4 py-3"><?php echo e($process->stage_label); ?></td>
                                <td class="px-4 py-3">
                                    <?php echo e($process->analyst?->display_name_with_title ?? 'Belum ditentukan'); ?>

                                    <div class="text-xs text-gray-500">
                                        <?php echo e($process->analyst?->rank); ?> <?php echo e($process->analyst?->identification_number ? '(' . $process->analyst->identification_number . ')' : ''); ?>

                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>Mulai: <?php echo e(optional($process->started_at)->format('d/m/Y H:i') ?? '-'); ?></div>
                                    <div>Selesai: <?php echo e(optional($process->completed_at)->format('d/m/Y H:i') ?? '-'); ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if($process->completed_at): ?>
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Selesai</span>
                                    <?php elseif($process->started_at): ?>
                                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-700">Berjalan</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Belum dimulai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?php echo e(route('sample-processes.show', ['sample_process' => $process->id])); ?>" class="text-sm font-semibold text-primary-700 hover:text-primary-800">Detail</a>
                                        <a href="<?php echo e(route('sample-processes.edit', ['sample_process' => $process->id])); ?>" class="text-sm font-semibold text-gray-600 hover:text-gray-800">Ubah</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    <div class="space-y-2">
                                        <p>Belum ada data proses pengujian.</p>
                                        <p class="text-sm">
                                            <a href="<?php echo e(route('samples.test.create')); ?>" class="text-primary-700 hover:text-primary-800 underline">
                                                Input data pengujian sampel terlebih dahulu →
                                            </a>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <?php echo e($processes->links()); ?>

            </div>
        </div>

        <!-- Uses centralized listFetcher from app.js -->
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
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/sample-processes/index.blade.php ENDPATH**/ ?>