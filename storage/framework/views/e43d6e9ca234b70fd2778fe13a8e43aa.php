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
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Proses Pengujian</h2>
     <?php $__env->endSlot(); ?>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <a href="<?php echo e(route('sample-processes.show', ['sample_process' => $process->id])); ?>"
                class="inline-flex items-center text-sm font-semibold text-primary-700 hover:text-primary-800">&larr; Kembali ke detail</a>

            <form method="POST" action="<?php echo e(route('sample-processes.destroy', ['sample_process' => $process->id])); ?>"
                onsubmit="return confirm('Hapus proses ini?');">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700">Hapus</button>
            </form>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-gray-600">
                    <?php if(($process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage) === 'administration'): ?>
                        <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-inset ring-yellow-200">Tahap Administrasi tidak lagi digunakan</span>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" action="<?php echo e(route('sample-processes.update', ['sample_process' => $process->id])); ?>" class="space-y-6" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <?php echo $__env->make('sample-processes._form', ['showNotes' => true, 'showMetadata' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <?php
                    $currentStageValue = $process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage;
                    $selectedStage = old('stage', $currentStageValue);
                ?>

                <?php if(isset($activeSubstances) && $selectedStage === 'interpretation'): ?>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Data Interpretasi Hasil</h3>
                        <p class="mt-1 text-xs text-gray-500">Pilih instrumen pengujian, hasil interpretasi dan zat aktif yang terdeteksi.</p>

                        
                        <div class="mt-4">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-700">Instrumen Pengujian yang Digunakan <span class="text-red-500">*</span></label>
                            <?php
                                $currentInstrument = old('instrument', $currentInstrument ?? null);
                                $instrumentOptions = [
                                    'UV-VIS Spectrophotometer' => 'UV-VIS Spectrophotometer',
                                    'GC-MS (Gas Chromatography-Mass Spectrometry)' => 'GC-MS (Gas Chromatography-Mass Spectrometry)',
                                    'LC-MS (Liquid Chromatography-Mass Spectrometry)' => 'LC-MS (Liquid Chromatography-Mass Spectrometry)',
                                ];
                            ?>
                            <select name="instrument"
                                class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">-- Pilih Instrumen Pengujian --</option>
                                <?php $__currentLoopData = $instrumentOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($value); ?>" <?php if($currentInstrument === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <?php $__errorArgs = ['instrument'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <p class="mt-2 text-xs text-red-600"><?php echo e($message); ?></p>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            <p class="mt-1 text-xs text-gray-500">Pilih instrumen laboratorium yang digunakan untuk pengujian sampel ini.</p>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <span class="text-xs uppercase tracking-wide text-gray-500">Status Hasil</span>
                                <?php $testResultValue = old('test_result', $currentTestResult ?? null); ?>
                                <div class="mt-2 flex flex-wrap gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="radio" name="test_result" value="positive" <?php if($testResultValue === 'positive'): echo 'checked'; endif; ?>
                                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        Positif
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="radio" name="test_result" value="negative" <?php if($testResultValue === 'negative'): echo 'checked'; endif; ?>
                                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        Negatif
                                    </label>
                                </div>
                                <?php $__errorArgs = ['test_result'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="mt-2 text-xs text-red-600"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>

                            <div>
                                <label class="block text-xs uppercase tracking-wide text-gray-500">Zat Aktif Terdeteksi</label>
                                <?php
                                    $detectedValue = old('detected_substance', $currentDetectedSubstance ?? '');
                                    $activeSubstanceList = $activeSubstances instanceof \Illuminate\Support\Collection
                                        ? $activeSubstances
                                        : collect($activeSubstances);
                                ?>
                                <?php if($activeSubstanceList->isEmpty()): ?>
                                    <p class="mt-2 text-xs text-gray-500">Belum ada data zat aktif tersimpan. Tambahkan melalui permintaan sampel terlebih dahulu.</p>
                                <?php else: ?>
                                    <select name="detected_substance"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">-- pilih zat aktif --</option>
                                        <?php $__currentLoopData = $activeSubstanceList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $substance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($substance); ?>" <?php if($detectedValue === $substance): echo 'selected'; endif; ?>><?php echo e($substance); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                <?php endif; ?>
                                <?php $__errorArgs = ['detected_substance'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="mt-2 text-xs text-red-600"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-semibold text-gray-900">Unggah Hasil Pengujian</h4>
                            <p class="mt-1 text-xs text-gray-500">Unggah dokumen pendukung hasil pengujian (PDF, DOCX, XLSX, atau gambar – maksimum 20 MB).</p>
                            <?php
                                $resultAttachmentName = $currentResultAttachmentOriginal
                                    ?? ($currentResultAttachmentPath ? basename($currentResultAttachmentPath) : null);
                            ?>
                            <?php if(!empty($currentResultAttachmentUrl)): ?>
                                <div class="mt-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600">
                                    <span class="font-medium text-gray-700">File saat ini:</span>
                                    <a href="<?php echo e($currentResultAttachmentUrl); ?>" target="_blank" class="ml-1 text-primary-600 hover:text-primary-700 underline">
                                        <?php echo e($resultAttachmentName ?? 'Lihat dokumen'); ?>

                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <input type="file" name="test_result_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                    class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                                <?php $__errorArgs = ['test_result_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="mt-2 text-xs text-red-600"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        </div>
                    </div>

                    
                    <div class="mt-6 rounded-lg border border-dashed border-gray-300 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">Instrumen Ke-2 (Opsional)</h3>
                            <span class="text-xs text-gray-500">Untuk permintaan pengujian dengan lebih dari satu instrumen</span>
                        </div>
                        <div class="mt-4">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-700">Instrumen Pengujian</label>
                            <?php
                                $currentInstrument2 = old('instrument_2', $secondaryInstrument ?? null);
                                $instrumentOptions = [
                                    'UV-VIS Spectrophotometer' => 'UV-VIS Spectrophotometer',
                                    'GC-MS (Gas Chromatography-Mass Spectrometry)' => 'GC-MS (Gas Chromatography-Mass Spectrometry)',
                                    'LC-MS (Liquid Chromatography-Mass Spectrometry)' => 'LC-MS (Liquid Chromatography-Mass Spectrometry)',
                                ];
                            ?>
                            <select name="instrument_2"
                                class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">-- Pilih Instrumen Pengujian --</option>
                                <?php $__currentLoopData = $instrumentOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($value); ?>" <?php if($currentInstrument2 === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <span class="text-xs uppercase tracking-wide text-gray-500">Status Hasil</span>
                                <?php $testResultValue2 = old('test_result_2', $secondaryTestResult ?? null); ?>
                                <div class="mt-2 flex flex-wrap gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="radio" name="test_result_2" value="positive" <?php if($testResultValue2 === 'positive'): echo 'checked'; endif; ?>
                                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        Positif
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="radio" name="test_result_2" value="negative" <?php if($testResultValue2 === 'negative'): echo 'checked'; endif; ?>
                                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        Negatif
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs uppercase tracking-wide text-gray-500">Zat Aktif Terdeteksi</label>
                                <?php
                                    $detectedValue2 = old('detected_substance_2', $secondaryDetectedSubstance ?? '');
                                    $activeSubstanceList = $activeSubstances instanceof \Illuminate\Support\Collection
                                        ? $activeSubstances
                                        : collect($activeSubstances);
                                ?>
                                <?php if($activeSubstanceList->isEmpty()): ?>
                                    <p class="mt-2 text-xs text-gray-500">Belum ada data zat aktif tersimpan.</p>
                                <?php else: ?>
                                    <select name="detected_substance_2"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">-- pilih zat aktif --</option>
                                        <?php $__currentLoopData = $activeSubstanceList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $substance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($substance); ?>" <?php if($detectedValue2 === $substance): echo 'selected'; endif; ?>><?php echo e($substance); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-semibold text-gray-900">Unggah Hasil Pengujian (Instrumen Ke-2)</h4>
                            <?php
                                $resultAttachmentName2 = $secondaryResultAttachmentOriginal
                                    ?? ($secondaryResultAttachmentPath ? basename($secondaryResultAttachmentPath) : null);
                            ?>
                            <?php if(!empty($secondaryResultAttachmentUrl)): ?>
                                <div class="mt-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600">
                                    <span class="font-medium text-gray-700">File saat ini:</span>
                                    <a href="<?php echo e($secondaryResultAttachmentUrl); ?>" target="_blank" class="ml-1 text-primary-600 hover:text-primary-700 underline">
                                        <?php echo e($resultAttachmentName2 ?? 'Lihat dokumen'); ?>

                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <input type="file" name="test_result_file_2" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                    class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="flex justify-end gap-3">
                    <a href="<?php echo e(route('sample-processes.show', ['sample_process' => $process->id])); ?>"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">Batal</a>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Perbarui</button>
                </div>
            </form>
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
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/sample-processes/edit.blade.php ENDPATH**/ ?>