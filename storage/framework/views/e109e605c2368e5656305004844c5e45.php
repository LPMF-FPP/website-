<?php
    $stageOptions = $stages ?? [];
    $showNotes = $showNotes ?? true;
    $showMetadata = $showMetadata ?? true;
?>

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Sampel</label>
            <select name="sample_id" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">-- pilih sampel --</option>
                <?php $__currentLoopData = $samples; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sample): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($sample->id); ?>" <?php if(old('sample_id', $process->sample_id ?? $selectedSample ?? null) == $sample->id): echo 'selected'; endif; ?>>
                        <?php echo e($sample->sample_name); ?> (<?php echo e($sample->testRequest?->request_number ?? 'Tanpa Permintaan'); ?>)
                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['sample_id'];
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
            <label class="block text-sm font-medium text-gray-700">Tahapan Proses</label>
            <select name="stage" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">-- pilih tahapan --</option>
                <?php ($currentStage = $process && $process->stage ? ($process->stage instanceof \App\Enums\TestProcessStage ? $process->stage->value : $process->stage) : null); ?>
                <?php $__currentLoopData = $stageOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stageKey => $stageLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($stageKey === 'administration') continue; ?>
                    <option value="<?php echo e($stageKey); ?>" <?php if(old('stage', $currentStage) === $stageKey): echo 'selected'; endif; ?>><?php echo e($stageLabel); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['stage'];
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
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Pelaksana</label>
            <select name="performed_by"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">-- belum ditentukan --</option>
                <?php $__currentLoopData = $analysts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $analyst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($analyst->id); ?>" <?php if((int) old('performed_by', $process?->performed_by) === $analyst->id): echo 'selected'; endif; ?>>
                        <?php echo e($analyst->display_name_with_title); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['performed_by'];
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
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Mulai</label>
                <input type="datetime-local" name="started_at"
                    value="<?php echo e(old('started_at', $process?->started_at?->format('Y-m-d\TH:i'))); ?>"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <?php $__errorArgs = ['started_at'];
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
                <label class="block text-sm font-medium text-gray-700">Selesai</label>
                <input type="datetime-local" name="completed_at"
                    value="<?php echo e(old('completed_at', $process?->completed_at?->format('Y-m-d\TH:i'))); ?>"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <?php $__errorArgs = ['completed_at'];
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
        </div>
    </div>

    <?php if($showNotes): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700">Catatan</label>
            <textarea name="notes" rows="4"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                placeholder="Ringkasan progres, kondisi sampel, atau temuan penting"><?php echo e(old('notes', $process?->notes)); ?></textarea>
            <?php $__errorArgs = ['notes'];
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
    <?php endif; ?>

    <?php if($showMetadata): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700">Metadata Tambahan (opsional)</label>
            <p class="mt-1 text-xs text-gray-500">Isi sebagai pasangan kunci-nilai. Contoh: <code>{"suhu": "25&deg;C", "alat": "GC-MS"}</code></p>
            <textarea name="metadata_raw" rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                placeholder='{"parameter": "nilai"}'><?php echo e(old('metadata_raw', $process?->metadata ? json_encode($process->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')); ?></textarea>
            <?php $__errorArgs = ['metadata_raw'];
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
    <?php endif; ?>

</div>

<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/sample-processes/_form.blade.php ENDPATH**/ ?>