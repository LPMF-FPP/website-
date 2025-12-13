<?php
    use Illuminate\Support\Facades\Storage;
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Detail Proses Pengujian','breadcrumbs' => [[ 'label' => 'Proses', 'href' => route('sample-processes.index') ], [ 'label' => 'Detail' ]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Detail Proses Pengujian','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([[ 'label' => 'Proses', 'href' => route('sample-processes.index') ], [ 'label' => 'Detail' ]])]); ?>
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

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <?php if(session('success')): ?>
            <div class="rounded-lg border-2 border-green-300 bg-green-50 p-5 text-sm shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-green-900">Berhasil!</h3>
                        <p class="mt-1 text-green-800"><?php echo e(session('success')); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if(session('error') || $errors->any()): ?>
            <div class="rounded-lg border-2 border-red-300 bg-red-50 p-5 text-sm shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-red-900">Error!</h3>
                        <?php if(session('error')): ?>
                            <p class="mt-1 text-red-800"><?php echo e(session('error')); ?></p>
                        <?php endif; ?>
                        <?php if($errors->any()): ?>
                            <ul class="mt-2 list-disc list-inside space-y-1">
                                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li class="text-red-800"><?php echo e($error); ?></li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap items-center justify-between gap-3">

            <a href="<?php echo e(route('sample-processes.index')); ?>"

                class="inline-flex items-center text-sm font-semibold text-primary-700 hover:text-primary-800">&larr; Kembali ke daftar</a>

            <div class="flex flex-wrap items-center gap-2">

                <?php if(optional($sampleProcess->sample)->status === 'ready_for_delivery'): ?>

                          <a href="<?php echo e(route('delivery.show', $sampleProcess->sample->testRequest)); ?>"

                              class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-500 hover:text-primary-700">Penyerahan</a>

                <?php endif; ?>

                <?php
                    $stageVal = ($sampleProcess->stage instanceof \App\Enums\TestProcessStage ? $sampleProcess->stage->value : $sampleProcess->stage);
                ?>
                <?php if($stageVal === 'preparation'): ?>
                    <div class="flex gap-2">
                        <a href="<?php echo e(route('sample-processes.generate-form', ['sample_process' => $sampleProcess->id, 'stage' => 'preparation'])); ?>"
                           target="_blank"
                           class="inline-flex items-center gap-2 rounded-md bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 hover:bg-primary-100">
                            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'document','class' => 'h-4 w-4','ariaHidden' => 'true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'document','class' => 'h-4 w-4','aria-hidden' => 'true']); ?>
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
                            Lihat Formulir Preparasi
                        </a>
                        <a href="<?php echo e(route('sample-processes.generate-form', ['sample_process' => $sampleProcess->id, 'stage' => 'preparation'])); ?>?download=1"
                           class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download
                        </a>
                    </div>
                <?php elseif($stageVal === 'instrumentation'): ?>
                    <a href="<?php echo e(route('sample-processes.generate-form', ['sample_process' => $sampleProcess->id, 'stage' => 'instrumentation'])); ?>"
                       class="inline-flex items-center gap-2 rounded-md bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 hover:bg-primary-100">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'document','class' => 'h-4 w-4','ariaHidden' => 'true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'document','class' => 'h-4 w-4','aria-hidden' => 'true']); ?>
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
                        Generate Formulir Pengujian Instrumen
                    </a>
                <?php elseif($stageVal === 'interpretation'): ?>
                    <a href="<?php echo e(route('sample-processes.lab-report', $sampleProcess)); ?>" target="_blank"
                       class="inline-flex items-center gap-2 rounded-md bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 hover:bg-primary-100">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'document','class' => 'h-4 w-4','ariaHidden' => 'true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'document','class' => 'h-4 w-4','aria-hidden' => 'true']); ?>
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
                        Lihat Laporan Hasil Uji
                    </a>
                    <a href="<?php echo e(route('sample-processes.lab-report', ['sample_process' => $sampleProcess, 'download' => 1])); ?>"
                       class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'download','class' => 'h-4 w-4','ariaHidden' => 'true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'download','class' => 'h-4 w-4','aria-hidden' => 'true']); ?>
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
                        Download Laporan
                    </a>
                <?php endif; ?>

                <a href="<?php echo e(route('sample-processes.edit', ['sample_process' => $sampleProcess->id])); ?>"
                   class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Ubah Proses</a>

            </div>

        </div>



        <div class="rounded-lg bg-white p-6 shadow-sm space-y-6">
            <?php if (isset($component)) { $__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-section','data' => ['title' => 'Sampel']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Sampel']); ?>
                <p class="mt-1 text-lg font-semibold text-gray-900"><?php echo e($sampleProcess->sample->sample_name); ?></p>
                <p class="text-sm text-gray-500">Permintaan: <?php echo e($sampleProcess->sample->testRequest?->request_number ?? '-'); ?></p>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64)): ?>
<?php $attributes = $__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64; ?>
<?php unset($__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64)): ?>
<?php $component = $__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64; ?>
<?php unset($__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-section','data' => ['title' => 'Informasi Proses']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Informasi Proses']); ?>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tahapan</div>
                        <p class="mt-1 text-base text-gray-800"><?php echo e($sampleProcess->stage_label); ?></p>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pelaksana</div>
                        <p class="mt-1 text-base text-gray-800">
                            <?php echo e($sampleProcess->analyst?->display_name_with_title ?? 'Belum ditentukan'); ?>

                        </p>
                        <?php if($sampleProcess->analyst): ?>
                            <p class="text-sm text-gray-500"><?php echo e($sampleProcess->analyst->rank); ?> <?php echo e($sampleProcess->analyst->identification_number ? '(' . $sampleProcess->analyst->identification_number . ')' : ''); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64)): ?>
<?php $attributes = $__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64; ?>
<?php unset($__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64)): ?>
<?php $component = $__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64; ?>
<?php unset($__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-section','data' => ['title' => 'Waktu']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Waktu']); ?>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Mulai</div>
                        <p class="mt-1 text-base text-gray-800"><?php echo e(optional($sampleProcess->started_at)->format('d/m/Y H:i') ?? '-'); ?></p>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Selesai</div>
                        <p class="mt-1 text-base text-gray-800"><?php echo e(optional($sampleProcess->completed_at)->format('d/m/Y H:i') ?? '-'); ?></p>
                    </div>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64)): ?>
<?php $attributes = $__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64; ?>
<?php unset($__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64)): ?>
<?php $component = $__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64; ?>
<?php unset($__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64); ?>
<?php endif; ?>

            <?php if($interpretationDetails): ?>
                <?php if (isset($component)) { $__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-section','data' => ['title' => 'Interpretasi Hasil']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Interpretasi Hasil']); ?>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Nomor Laporan Hasil Uji</div>
                    <div class="mt-1 mb-3 font-semibold text-gray-900"><?php echo e($interpretationDetails['report_number']); ?></div>
                    <?php
                        // Build unified rows: primary + multi
                        $rows = [];
                        $rows[] = [
                            'instrument' => $interpretationDetails['instrument'] ?? '-',
                            'result_raw' => $interpretationDetails['test_result_raw'] ?? null,
                            'result'     => $interpretationDetails['test_result'] ?? 'Belum ditentukan',
                            'detected'   => $interpretationDetails['detected_substance'] ?? '-',
                            'attachment_url' => $interpretationDetails['attachment_url'] ?? null,
                            'attachment_original' => $interpretationDetails['attachment_original'] ?? null,
                        ];
                        if (!empty($interpretationDetails['multi'])) {
                            foreach ($interpretationDetails['multi'] as $mi) {
                                $rows[] = [
                                    'instrument' => $mi['instrument'] ?? '-',
                                    'result_raw' => $mi['test_result_raw'] ?? null,
                                    'result'     => $mi['test_result'] ?? 'Belum ditentukan',
                                    'detected'   => $mi['detected_substance'] ?? '-',
                                    'attachment_url' => $mi['attachment_url'] ?? null,
                                    'attachment_original' => $mi['attachment_original'] ?? null,
                                ];
                            }
                        }
                    ?>
                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Instrumen Pengujian</th>
                                    <th class="px-4 py-3 text-left">Hasil Uji</th>
                                    <th class="px-4 py-3 text-left">Zat Aktif Terdeteksi</th>
                                    <th class="px-4 py-3 text-left">Lampiran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-gray-700">
                                <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $badge = match ($r['result_raw']) {
                                            'positive' => 'inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700',
                                            'negative' => 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700',
                                            default => 'inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700',
                                        };
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-gray-900"><?php echo e($r['instrument']); ?></td>
                                        <td class="px-4 py-3"><span class="<?php echo e($badge); ?>"><?php echo e($r['result']); ?></span></td>
                                        <td class="px-4 py-3 font-semibold text-gray-900"><?php echo e($r['detected']); ?></td>
                                        <td class="px-4 py-3">
                                            <?php if(!empty($r['attachment_url'])): ?>
                                                <a href="<?php echo e($r['attachment_url']); ?>" target="_blank" class="text-primary-700 hover:text-primary-800 underline"><?php echo e($r['attachment_original'] ?? 'Lihat dokumen'); ?></a>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>

                    
                    <?php if($interpretationDetails['report_exists'] && $interpretationDetails['report_document']): ?>
                        <?php
                            $doc = $interpretationDetails['report_document'];
                        ?>
                        <div class="mt-4 rounded-md border border-primary-200 bg-primary-50 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'document','class' => 'h-5 w-5 text-primary-600']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'document','class' => 'h-5 w-5 text-primary-600']); ?>
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
                                <div class="flex-1">
                                    <span class="font-semibold text-primary-900">Laporan Hasil Uji</span>
                                    <p class="text-xs text-primary-700 mt-1">
                                        Nomor: <span class="font-mono"><?php echo e($interpretationDetails['report_number']); ?></span>
                                        <?php if($doc->created_at): ?>
                                            &middot; Generated: <?php echo e($doc->created_at->format('d/m/Y H:i')); ?>

                                        <?php endif; ?>
                                    </p>
                                </div>
                                <a href="<?php echo e(asset('storage/' . ltrim($doc->path, '/'))); ?>"
                                   target="_blank"
                                   class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition">
                                    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'eye','class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'eye','class' => 'h-4 w-4']); ?>
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
                                    Lihat Laporan
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64)): ?>
<?php $attributes = $__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64; ?>
<?php unset($__attributesOriginale0eb35ec0eaec4a924d66a4f375c2f64); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64)): ?>
<?php $component = $__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64; ?>
<?php unset($__componentOriginale0eb35ec0eaec4a924d66a4f375c2f64); ?>
<?php endif; ?>
            <?php endif; ?>

            <div class="text-xs text-gray-500">
                Dibuat: <?php echo e($sampleProcess->created_at->format('d/m/Y H:i')); ?> &middot; Diperbarui: <?php echo e($sampleProcess->updated_at->format('d/m/Y H:i')); ?>

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
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/sample-processes/show.blade.php ENDPATH**/ ?>