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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => '📦 ' . __('tracking.page_title') . ' - ' . $trackingData['request_number'],'breadcrumbs' => [[ 'label' => __('tracking.breadcrumbs.tracking'), 'href' => route('tracking.index') ], [ 'label' => __('tracking.breadcrumbs.result') ]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('📦 ' . __('tracking.page_title') . ' - ' . $trackingData['request_number']),'breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([[ 'label' => __('tracking.breadcrumbs.tracking'), 'href' => route('tracking.index') ], [ 'label' => __('tracking.breadcrumbs.result') ]])]); ?>
             <?php $__env->slot('actions', null, []); ?> 
                <a href="<?php echo e(route('tracking.index')); ?>" class="btn-sem inline-flex items-center"><?php echo e(__('tracking.actions.track_again')); ?></a>
             <?php $__env->endSlot(); ?>
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

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6"
         x-data="trackingProgress({ initial: <?php echo \Illuminate\Support\Js::from($condensed)->toHtml() ?> })">

        <!-- Condensed Progress Card -->
        <div class="card-sem rounded-lg shadow-sm border-sem p-6 surface-sem">
            <div class="flex flex-col gap-6">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-semibold text-sem-high"><?php echo e(__('tracking.labels.progress_testing')); ?></h3>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="manualRefresh()" class="text-xs px-2 py-1 rounded-md border-sem-subtle hover:bg-sem-muted transition"><?php echo e(__('tracking.actions.manual_refresh')); ?></button>
                            <span class="text-xs text-sem-dim" x-text="lastUpdatedDisplay()"></span>
                        </div>
                    </div>
                    <!-- Linear progress bar -->
                    <div class="w-full h-3 bg-sem-line rounded-full overflow-hidden">
                        <div class="h-full bg-primary-600 dark:bg-primary-500 transition-all duration-500" :style="`width: ${data.progress_percent}%`"></div>
                    </div>
                    <div class="mt-1 text-right text-xs text-sem-dim" x-text="data.progress_percent + '%'" aria-live="polite"></div>
                </div>

                <!-- 4 Stage Stepper - Improved Connected Design -->
                <div class="relative">
                    <!-- Background connecting line -->
                    <div class="absolute top-[52px] left-0 right-0 h-1 hidden sm:block">
                        <div class="mx-auto h-full relative" style="width: calc(100% - 80px); margin-left: 40px; margin-right: 40px;">
                            <div class="absolute inset-0 bg-sem-line rounded-full"></div>
                            <div class="absolute inset-0 bg-gradient-to-r from-success-500 to-primary-500 rounded-full transition-all duration-500"
                                 :style="`width: ${Math.max(0, Math.min(100, (data.progress_percent / 100) * 100))}%`"></div>
                        </div>
                    </div>
                    
                    <!-- Stages -->
                    <ol class="relative grid grid-cols-4 gap-4">
                        <template x-for="stage in data.stages" :key="stage.key">
                            <li class="flex flex-col items-center text-center relative z-10">
                                <!-- Icon Circle with Ring Effect -->
                                <div class="relative mb-3">
                                    <!-- Outer ring for current step -->
                                    <div class="absolute inset-0 -m-1.5 rounded-full transition-all duration-300"
                                         :class="{
                                            'ring-4 ring-primary-200 dark:ring-primary-800 animate-pulse': stage.status==='current'
                                         }"></div>
                                    
                                    <!-- Main circle -->
                                    <div class="relative h-14 w-14 rounded-full flex items-center justify-center text-xl font-bold transition-all duration-300 transform"
                                         :class="{
                                            'bg-gradient-to-br from-success-500 to-success-600 text-white shadow-lg shadow-success-500/50 scale-100': stage.status==='completed',
                                            'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/50 scale-110': stage.status==='current',
                                            'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500': stage.status==='pending'
                                         }"
                                         :aria-current="stage.status==='current' ? 'step' : null"
                                         :title="stage.label">
                                        <!-- Checkmark for completed -->
                                        <template x-if="stage.status==='completed'">
                                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </template>
                                        <!-- Icon for current/pending -->
                                        <template x-if="stage.status!=='completed'">
                                            <span x-text="stage.icon"></span>
                                        </template>
                                    </div>
                                </div>
                                
                                <!-- Stage Label -->
                                <div class="text-sm font-semibold transition-colors"
                                     :class="{
                                        'text-success-700 dark:text-success-400': stage.status==='completed',
                                        'text-primary-700 dark:text-primary-400': stage.status==='current',
                                        'text-gray-500 dark:text-gray-500': stage.status==='pending'
                                     }"
                                     x-text="stage.label"></div>
                                
                                <!-- Timestamp -->
                                <div class="text-xs mt-1 transition-colors"
                                     :class="{
                                        'text-success-600 dark:text-success-500 font-medium': stage.status==='completed',
                                        'text-primary-600 dark:text-primary-500 font-medium': stage.status==='current',
                                        'text-gray-400 dark:text-gray-600': stage.status==='pending'
                                     }"
                                     x-text="formatStageTime(stage.timestamp)"></div>
                            </li>
                        </template>
                    </ol>
                </div>
            </div>
        </div>

        <!-- FULL LEGACY TIMELINE -->
        <div class="card-sem rounded-lg shadow-sm border-sem p-6 surface-sem" aria-labelledby="full-timeline-heading">
            <h3 id="full-timeline-heading" class="text-lg font-semibold mb-4 text-sem-high">Timeline Detail</h3>
            <ol class="relative border-l-2 border-sem-line ml-3 space-y-6">
                <?php $__currentLoopData = $trackingData['tracking_stages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="ml-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-1 text-xl"><?php echo e($stage['icon']); ?></div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-sem-high"><?php echo e($stage['title']); ?></h4>
                                <p class="text-sm text-sem-dim"><?php echo e($stage['description']); ?></p>
                                <?php if($stage['timestamp']): ?>
                                    <p class="text-xs mt-1 text-sem-dim"><?php echo e($stage['timestamp']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (isset($component)) { $__componentOriginal9334e2d0bc1de3367152dad0cd4ec3f4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9334e2d0bc1de3367152dad0cd4ec3f4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.timeline-status-badge','data' => ['status' => $stage['status']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('timeline-status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stage['status'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9334e2d0bc1de3367152dad0cd4ec3f4)): ?>
<?php $attributes = $__attributesOriginal9334e2d0bc1de3367152dad0cd4ec3f4; ?>
<?php unset($__attributesOriginal9334e2d0bc1de3367152dad0cd4ec3f4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9334e2d0bc1de3367152dad0cd4ec3f4)): ?>
<?php $component = $__componentOriginal9334e2d0bc1de3367152dad0cd4ec3f4; ?>
<?php unset($__componentOriginal9334e2d0bc1de3367152dad0cd4ec3f4); ?>
<?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ol>
        </div>

        <!-- Request Info Cards -->
        <div class="surface-sem shadow-sm sm:rounded-lg p-6 border-sem">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-center">
                <div class="surface-sem-alt p-4 rounded-lg">
                    <div class="text-2xl mb-2">📝</div>
                    <div class="text-sm text-sem-dim"><?php echo e(__('tracking.labels.request_number')); ?></div>
                    <div class="font-semibold text-sem-high"><?php echo e($trackingData['request_number']); ?></div>
                </div>
                <div class="surface-sem-alt p-4 rounded-lg">
                    <div class="text-2xl mb-2">👤</div>
                    <div class="text-sm text-sem-dim"><?php echo e(__('tracking.labels.investigator')); ?></div>
                    <div class="font-semibold text-sem-high"><?php echo e($trackingData['investigator']['rank']); ?> <?php echo e($trackingData['investigator']['name']); ?></div>
                </div>
                <div class="surface-sem-alt p-4 rounded-lg">
                    <div class="text-2xl mb-2">🧪</div>
                    <div class="text-sm text-sem-dim"><?php echo e(__('tracking.labels.samples_count')); ?></div>
                    <div class="font-semibold text-sem-high"><?php echo e($trackingData['samples_count']); ?> sampel</div>
                </div>
                <div class="surface-sem-alt p-4 rounded-lg">
                    <div class="text-2xl mb-2">⏰</div>
                    <div class="text-sm text-sem-dim"><?php echo e(__('tracking.labels.estimated_completion')); ?></div>
                    <div class="font-semibold text-sem-high"><?php echo e(date('d M Y', strtotime($trackingData['estimated_completion']))); ?></div>
                </div>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="surface-sem shadow-sm sm:rounded-lg p-6 border-sem">
            <h3 class="text-lg font-semibold text-sem-high mb-4">📞 <?php echo e(__('tracking.labels.contact_info')); ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="surface-sem-alt p-4 rounded-lg">
                    <h4 class="font-semibold text-primary-700 dark:text-primary-300 mb-2"><?php echo e(__('tracking.labels.investigator_submitter')); ?></h4>
                    <div class="text-sm space-y-1 text-sem-high">
                        <p><strong><?php echo e(__('tracking.misc.investigator_name')); ?>:</strong> <?php echo e($trackingData['investigator']['rank']); ?> <?php echo e($trackingData['investigator']['name']); ?></p>
                        <p><strong><?php echo e(__('tracking.misc.investigator_unit')); ?>:</strong> <?php echo e($trackingData['investigator']['jurisdiction']); ?></p>
                        <p><strong><?php echo e(__('tracking.misc.investigator_phone')); ?>:</strong> <?php echo e($trackingData['investigator']['phone']); ?></p>
                    </div>
                </div>
                <div class="surface-sem-alt p-4 rounded-lg">
                    <h4 class="font-semibold text-success-700 dark:text-success-300 mb-2"><?php echo e(__('tracking.labels.lab_unit')); ?></h4>
                    <div class="text-sm space-y-1 text-sem-high">
                        <p><strong><?php echo e(__('tracking.misc.lab_address')); ?>:</strong> Jl. Cipinang Baru Raya No.3B 11, RT.11/RW.6, Cipinang, Kec. Pulo Gadung, Kota Jakarta Timur, Daerah Khusus Ibukota Jakarta 13240</p>
                        <p><strong><?php echo e(__('tracking.misc.lab_phone')); ?>:</strong> (021) 720-0461</p>
                        <p><strong><?php echo e(__('tracking.misc.lab_email')); ?>:</strong> Labmutufarmapol@gmail.com</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        function trackingProgress({ initial }) {
            return {
                data: initial || { stages: [], progress_percent: 0, last_updated: null, request_number: <?php echo \Illuminate\Support\Js::from($trackingData['request_number'])->toHtml() ?> },
                pollInterval: null,
                pollingMs: 45000,
                init() { this.startPolling(); },
                startPolling() { if (!this.shouldPoll()) return; this.pollInterval = setInterval(() => { this.fetchUpdate(); }, this.pollingMs); },
                shouldPoll() { return this.data.current_stage_index !== 3; },
                async fetchUpdate(force = false) {
                    try {
                        const url = `/track/${this.data.request_number}.json` + (force ? '?nocache=1' : '');
                        const resp = await fetch(url, { headers: { 'Accept': 'application/json' }});
                        if (!resp.ok) return;
                        const json = await resp.json();
                        this.data = json;
                        if (!this.shouldPoll() && this.pollInterval) { clearInterval(this.pollInterval); }
                    } catch (e) { console.warn('Tracking poll failed', e); }
                },
                manualRefresh() { this.fetchUpdate(true); },
                lastUpdatedDisplay() { if (!this.data.last_updated) return ''; const d = new Date(this.data.last_updated.replace(' ', 'T')); return '<?php echo e(__('tracking.labels.last_updated')); ?>: ' + d.toLocaleString('id-ID'); },
                formatStageTime(ts) { if (!ts) return ''; const d = new Date(ts.replace(' ', 'T')); return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }); }
            }
        }
    </script>
    <?php $__env->stopPush(); ?>

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


<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/tracking/result.blade.php ENDPATH**/ ?>