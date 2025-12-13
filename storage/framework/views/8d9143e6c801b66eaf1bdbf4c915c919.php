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
    <?php if(!isset($firstSampleId)): ?>
    <?php ($firstSampleId = optional($selectedRequest?->samples->first())->id); ?>
<?php endif; ?>


     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Form Pengujian Sampel','breadcrumbs' => [[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Pengujian' ]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Form Pengujian Sampel','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Pengujian' ]])]); ?>
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

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <a href="<?php echo e(route('requests.index')); ?>"
               class="inline-flex items-center rounded-md border border-transparent bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:text-primary-700">
                Daftar Permintaan
            </a>
            <a href="<?php echo e(route('samples.test.create')); ?>"
               class="inline-flex items-center rounded-md border border-primary-600 bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                Pengujian Sampel
            </a>
            <a href="<?php echo e($firstSampleId ? route('sample-processes.index', ['sample_id' => $firstSampleId]) : route('sample-processes.index')); ?>"
               class="inline-flex items-center rounded-md border border-transparent bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:text-primary-700">
                Proses Pengujian
            </a>
            <a href="<?php echo e(route('delivery.index')); ?>"
               class="inline-flex items-center rounded-md border border-transparent bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:text-primary-700">
                Penyerahan
            </a>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200 space-y-6">
                <?php if(session('success')): ?>
                    <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                        <?php echo e(session('success')); ?>

                    </div>
                <?php endif; ?>

                <?php if($errors->any()): ?>
                    <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <ul class="list-disc pl-5 space-y-1">
                            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php ($selectedId = old('request_id', $selectedRequestId)); ?>
                <?php ($firstSampleId = optional($selectedRequest?->samples->first())->id); ?>

                <form action="<?php echo e(route('samples.test.store')); ?>" method="POST" class="space-y-6">
                    <?php echo csrf_field(); ?>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="request_id" class="block text-sm font-medium text-gray-700">Pilih Permintaan</label>
                            <select id="request_id" name="request_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">-- pilih --</option>
                                <?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($req->id); ?>" <?php if($selectedId == $req->id): echo 'selected'; endif; ?>>
                                        <?php echo e($req->request_number); ?> - <?php echo e($req->investigator->name ?? 'Tanpa Penyidik'); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div>
                            <label for="test_date" class="block text-sm font-medium text-gray-700">Tanggal Pengujian</label>
                            <input id="test_date" name="test_date" type="date" required
                                value="<?php echo e(old('test_date') ?? optional($selectedRequest?->test_date)->format('Y-m-d')); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                    </div>

                    <?php if($selectedRequest): ?>
                        <?php ($requestSamples = $selectedRequest->samples); ?>
                        <?php if($requestSamples->isEmpty()): ?>
                            <div class="rounded border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                                Tidak ada sampel yang terdaftar pada permintaan ini.
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php $__currentLoopData = $requestSamples; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sample): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php ($sampleIndex = $loop->index); ?>
                                    <?php ($selectedMethods = collect(old("samples.$sampleIndex.test_methods", $sample->test_methods ?? []))->filter()->all()); ?>
                                    <?php ($selectedOtherCategory = old("samples.$sampleIndex.other_sample_category", $sample->other_sample_category)); ?>
                                    <div class="rounded-lg border border-gray-200 p-5 shadow-sm">
                                        <div class="flex flex-col gap-2 border-b border-gray-100 pb-3 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900"><?php echo e($sample->sample_name); ?></h3>
                                                <p class="text-sm text-gray-500">Kode Sampel: <span class="font-medium text-primary-700"><?php echo e($sample->sample_code); ?></span></p>
                                            </div>
                                                <div class="mt-2">
                                                    <label class="block text-xs font-medium text-gray-600">Kategori Sampel</label>
                                                    <select name="samples[<?php echo e($sampleIndex); ?>][other_sample_category]"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        required>
                                                        <option value="">-- pilih kategori --</option>
                                                        <?php $__currentLoopData = $otherSampleOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $optionValue => $optionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <option value="<?php echo e($optionValue); ?>" <?php if($selectedOtherCategory === $optionValue): echo 'selected'; endif; ?>><?php echo e($optionLabel); ?></option>
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                    </select>
                                                    <?php $__errorArgs = ['samples.' . $sampleIndex . '.other_sample_category'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                        <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                                </div>
                                        </div>

                                        <input type="hidden" name="samples[<?php echo e($sampleIndex); ?>][id]" value="<?php echo e($sample->id); ?>">

                                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Penguji / Analis</label>

                                                <?php if($analysts->isEmpty()): ?>
                                                    <p class="mt-2 rounded border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
                                                        Belum ada data analis yang tersedia. Silakan tambah pengguna dengan peran analis terlebih dahulu.
                                                    </p>
                                                <?php else: ?>
                                                    <?php ($selectedAnalystId = (int) old("samples.$sampleIndex.assigned_analyst_id", $sample->assigned_analyst_id)); ?>
                                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                        <?php $__currentLoopData = $analysts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $analyst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <?php ($inputId = 'sample-' . $sample->id . '-analyst-' . $analyst->id); ?>
                                                            <label for="<?php echo e($inputId); ?>" class="relative block cursor-pointer">
                                                                <input type="radio"
                                                                    id="<?php echo e($inputId); ?>"
                                                                    name="samples[<?php echo e($sampleIndex); ?>][assigned_analyst_id]"
                                                                    value="<?php echo e($analyst->id); ?>"
                                                                    class="peer sr-only"
                                                                    <?php if($selectedAnalystId === $analyst->id): echo 'checked'; endif; ?>
                                                                    required>
                                                                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-500 hover:shadow-md peer-checked:border-primary-600 peer-checked:ring-2 peer-checked:ring-primary-200">
                                                                    <p class="text-sm font-semibold text-gray-900">
                                                                        <?php echo e($analyst->display_name_with_title); ?>

                                                                    </p>
                                                                    <div class="mt-2 space-y-1 text-xs text-gray-600">
                                                                        <div><span class="font-medium text-gray-500">Pangkat:</span> <?php echo e($analyst->rank ?? '-'); ?></div>
                                                                        <div><span class="font-medium text-gray-500">NRP/NIP:</span> <?php echo e($analyst->identification_number ?? '-'); ?></div>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php $__errorArgs = ["samples.$sampleIndex.assigned_analyst_id"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                    <p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p>
                                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Metode Pengujian</label>
                                                <div class="mt-2 flex flex-wrap gap-3">
                                                    <?php $__currentLoopData = $methodOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $methodKey => $methodLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                            <input type="checkbox"
                                                                name="samples[<?php echo e($sampleIndex); ?>][test_methods][]"
                                                                value="<?php echo e($methodKey); ?>"
                                                                <?php if(in_array($methodKey, $selectedMethods, true)): echo 'checked'; endif; ?>
                                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                            <?php echo e($methodLabel); ?>

                                                        </label>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </div>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Identifikasi Sampel / Barang Bukti</label>
                                                <textarea name="samples[<?php echo e($sampleIndex); ?>][physical_identification]" rows="3" required
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    placeholder="Contoh: Tablet putih dalam kemasan blister dengan garis hijau ..."><?php echo e(old("samples.$sampleIndex.physical_identification", $sample->physical_identification)); ?></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Jumlah Sampel untuk Pengujian</label>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <input type="number" name="samples[<?php echo e($sampleIndex); ?>][quantity]" step="0.01" min="0.01" required
                                                        value="<?php echo e(old('samples.$sampleIndex.quantity', $sample->quantity)); ?>"
                                                        class="block w-1/2 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                    <input type="text" name="samples[<?php echo e($sampleIndex); ?>][quantity_unit]" placeholder="satuan"
                                                        value="<?php echo e(old('samples.$sampleIndex.quantity_unit', $sample->quantity_unit)); ?>"
                                                        class="block w-1/2 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">No Batch</label>
                                                <input type="text" name="samples[<?php echo e($sampleIndex); ?>][batch_number]"
                                                    value="<?php echo e(old('samples.$sampleIndex.batch_number', $sample->batch_number)); ?>"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Exp Date</label>
                                                <input type="date" name="samples[<?php echo e($sampleIndex); ?>][expiry_date]"
                                                    value="<?php echo e(old('samples.$sampleIndex.expiry_date', optional($sample->expiry_date)->format('Y-m-d'))); ?>"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Jenis / Fokus Pengujian</label>
                                                <select name="samples[<?php echo e($sampleIndex); ?>][test_type]"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                    <option value="">-- pilih --</option>
                                                    <?php $__currentLoopData = [
                                                        'kualitatif' => 'Analisis Kualitatif',
                                                        'kuantitatif' => 'Analisis Kuantitatif',
                                                        'both' => 'Kualitatif & Kuantitatif',
                                                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <option value="<?php echo e($key); ?>" <?php if(old("samples.$sampleIndex.test_type", $sample->test_type) === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </select>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Catatan Tambahan</label>
                                                <textarea name="samples[<?php echo e($sampleIndex); ?>][notes]" rows="2"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    placeholder="Catatan khusus pengujian jika diperlukan"><?php echo e(old("samples.$sampleIndex.notes", $sample->notes)); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="rounded border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                            Tidak ada permintaan yang tersedia. Silakan buat permintaan baru terlebih dahulu.
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center rounded-md px-6 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 <?php echo e($selectedRequest ? 'bg-primary-600 hover:bg-primary-700' : 'bg-primary-600/60 cursor-not-allowed'); ?>"
                            aria-disabled="<?php echo e($selectedRequest ? 'false' : 'true'); ?>"
                            <?php echo e($selectedRequest ? '' : 'disabled'); ?>>
                            Simpan Pengujian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
        <script>
            document.getElementById('request_id').addEventListener('change', function () {
                const value = this.value;
                if (!value) {
                    window.location.href = '<?php echo e(url('/samples/test')); ?>';
                    return;
                }
                const url = new URL('<?php echo e(url('/samples/test')); ?>', window.location.origin);
                url.searchParams.set('request_id', value);
                window.location.href = url.toString();
            });
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


<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/samples/test.blade.php ENDPATH**/ ?>