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

        <h2 class="font-semibold text-xl text-gray-800 leading-tight">

            Formulir Permintaan Pengujian Sampel

        </h2>

     <?php $__env->endSlot(); ?>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        <div class="bg-white shadow-sm sm:rounded-lg">

            <div class="p-6 bg-white border-b border-gray-200">

                <?php if($errors->any()): ?>

                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">

                        <ul class="list-disc list-inside space-y-1">

                            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                <li class="text-sm"><?php echo e($error); ?></li>

                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        </ul>

                    </div>

                <?php endif; ?>

                <form id="request-create-form" action="<?php echo e(route('requests.store')); ?>" method="POST" enctype="multipart/form-data" class="space-y-8">

                    <?php echo csrf_field(); ?>

                    <!-- 1. Data Penyidik Section (Direvisi) -->

                    <div class="bg-blue-50 p-6 rounded-lg border border-blue-200">

                        <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">

                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">

                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>

                            </svg>

                            Data Penyidik

                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                            <!-- Nama Penyidik -->

                            <div>

                                <label for="investigator_name" class="block text-sm font-medium text-gray-700 mb-2">

                                    Nama Penyidik <span class="text-red-500">*</span>

                                </label>

                                <input type="text"

                                       name="investigator_name"

                                       id="investigator_name"

                                       required

                                       value="<?php echo e(old('investigator_name')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['investigator_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"

                                       placeholder="Masukkan nama penyidik">

                                <?php $__errorArgs = ['investigator_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- NRP Penyidik (BARU) -->

                            <div>

                                <label for="investigator_nrp" class="block text-sm font-medium text-gray-700 mb-2">

                                    NRP Penyidik <span class="text-red-500">*</span>

                                </label>

                                <input type="text"

                                       name="investigator_nrp"

                                       id="investigator_nrp"

                                       required

                                       value="<?php echo e(old('investigator_nrp')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['investigator_nrp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"

                                       placeholder="Contoh: 87010123"

                                       pattern="[0-9]{8}"

                                       maxlength="8">

                                <?php $__errorArgs = ['investigator_nrp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                                <p class="mt-1 text-xs text-gray-500">Format: 8 digit angka</p>

                            </div>

                            <!-- Pangkat -->

                            <div>

                                <label for="investigator_rank" class="block text-sm font-medium text-gray-700 mb-2">

                                    Pangkat <span class="text-red-500">*</span>

                                </label>

                                <select name="investigator_rank"

                                        id="investigator_rank"

                                        required

                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['investigator_rank'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">

                                    <option value="">Pilih Pangkat</option>

                                    <option value="BRIPKA" <?php echo e(old('investigator_rank') == 'BRIPKA' ? 'selected' : ''); ?>>BRIPKA</option>

                                    <option value="BRIPDA" <?php echo e(old('investigator_rank') == 'BRIPDA' ? 'selected' : ''); ?>>BRIPDA</option>

                                    <option value="BRIGADIR" <?php echo e(old('investigator_rank') == 'BRIGADIR' ? 'selected' : ''); ?>>BRIGADIR</option>

                                    <option value="AIPDA" <?php echo e(old('investigator_rank') == 'AIPDA' ? 'selected' : ''); ?>>AIPDA</option>

                                    <option value="AIPTU" <?php echo e(old('investigator_rank') == 'AIPTU' ? 'selected' : ''); ?>>AIPTU</option>

                                    <option value="IPDA" <?php echo e(old('investigator_rank') == 'IPDA' ? 'selected' : ''); ?>>IPDA</option>

                                    <option value="IPTU" <?php echo e(old('investigator_rank') == 'IPTU' ? 'selected' : ''); ?>>IPTU</option>

                                    <option value="AKP" <?php echo e(old('investigator_rank') == 'AKP' ? 'selected' : ''); ?>>AKP</option>

                                    <option value="KOMPOL" <?php echo e(old('investigator_rank') == 'KOMPOL' ? 'selected' : ''); ?>>KOMPOL</option>

                                    <option value="AKBP" <?php echo e(old('investigator_rank') == 'AKBP' ? 'selected' : ''); ?>>AKBP</option>

                                </select>

                                <?php $__errorArgs = ['investigator_rank'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- Satuan / Wilayah Hukum -->

                            <div>

                                <label for="investigator_jurisdiction" class="block text-sm font-medium text-gray-700 mb-2">

                                    Satuan / Wilayah Hukum <span class="text-red-500">*</span>

                                </label>

                                <input type="text"

                                       name="investigator_jurisdiction"

                                       id="investigator_jurisdiction"

                                       required

                                       value="<?php echo e(old('investigator_jurisdiction')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['investigator_jurisdiction'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"

                                       placeholder="Contoh: Polres Jakarta Pusat">

                                <?php $__errorArgs = ['investigator_jurisdiction'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- Alamat Penyidik (BARU) -->

                            <div class="md:col-span-2">

                                <label for="investigator_address" class="block text-sm font-medium text-gray-700 mb-2">

                                    Alamat Penyidik <span class="text-red-500">*</span>

                                </label>

                                <textarea name="investigator_address"

                                          id="investigator_address"

                                          rows="3"

                                          required

                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['investigator_address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"

                                          placeholder="Alamat lengkap penyidik"><?php echo e(old('investigator_address')); ?></textarea>

                                <?php $__errorArgs = ['investigator_address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- Nomor Telepon -->

                            <div>

                                <label for="investigator_phone" class="block text-sm font-medium text-gray-700 mb-2">

                                    Nomor Telepon <span class="text-red-500">*</span>

                                </label>

                                <input type="tel"

                                       name="investigator_phone"

                                       id="investigator_phone"

                                       required

                                       value="<?php echo e(old('investigator_phone')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['investigator_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"

                                       placeholder="08XX-XXXX-XXXX">

                                <?php $__errorArgs = ['investigator_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                        </div>

                    </div>

                    <!-- 2. Informasi Surat Section (Direvisi) -->

                    <div class="bg-primary-50 p-6 rounded-lg border border-primary-200">

                        <h3 class="text-lg font-semibold text-primary-900 mb-4 flex items-center">

                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">

                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>

                            </svg>

                            Informasi Surat Permintaan

                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <!-- Ditujukan Kepada -->

                            <div>

                                <label for="to_office" class="block text-sm font-medium text-gray-700 mb-2">

                                    Ditujukan Kepada <span class="text-red-500">*</span>

                                </label>

                                <input type="text"

                                       name="to_office"

                                       id="to_office"

                                       required

                                       value="<?php echo e(old('to_office', 'Kepala Sub Satker Farmapol Pusdokkes Polri')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['to_office'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">

                                <?php $__errorArgs = ['to_office'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- Nomor Surat -->

                            <div>

                                <label for="case_number" class="block text-sm font-medium text-gray-700 mb-2">

                                    Nomor Surat

                                </label>

                                <input type="text"

                                       name="case_number"

                                       id="case_number"

                                       value="<?php echo e(old('case_number')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['case_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"

                                       placeholder="Contoh: S/123/IV/2025/RESKRIM">

                                <?php $__errorArgs = ['case_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- Tanggal Surat -->

                            <div>

                                <label for="letter_date" class="block text-sm font-medium text-gray-700 mb-2">

                                    Tanggal Surat

                                </label>

                                <input type="date"

                                       name="letter_date"

                                       id="letter_date"

                                       value="<?php echo e(old('letter_date')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['letter_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">

                                <?php $__errorArgs = ['letter_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- Tersangka (BARU) -->

                            <div>

                                <label for="suspect_name" class="block text-sm font-medium text-gray-700 mb-2">

                                    Nama Tersangka <span class="text-red-500">*</span>

                                </label>

                                <input type="text"

                                       name="suspect_name"

                                       id="suspect_name"

                                       required

                                       value="<?php echo e(old('suspect_name')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['suspect_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"

                                       placeholder="Nama lengkap tersangka">

                                <?php $__errorArgs = ['suspect_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- Jenis Kelamin Tersangka -->

                            <div>

                                <label for="suspect_gender" class="block text-sm font-medium text-gray-700 mb-2">

                                    Jenis Kelamin Tersangka

                                </label>

                                <select name="suspect_gender"

                                        id="suspect_gender"

                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['suspect_gender'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">

                                    <option value="">Pilih Jenis Kelamin</option>

                                    <option value="male" <?php echo e(old('suspect_gender') == 'male' ? 'selected' : ''); ?>>Laki-laki</option>

                                    <option value="female" <?php echo e(old('suspect_gender') == 'female' ? 'selected' : ''); ?>>Perempuan</option>

                                </select>

                                <?php $__errorArgs = ['suspect_gender'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                            <!-- Umur Tersangka -->

                            <div>

                                <label for="suspect_age" class="block text-sm font-medium text-gray-700 mb-2">

                                    Umur Tersangka

                                </label>

                                <input type="number"

                                       name="suspect_age"

                                       id="suspect_age"

                                       min="0"

                                       max="120"

                                       value="<?php echo e(old('suspect_age')); ?>"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php $__errorArgs = ['suspect_age'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"

                                       placeholder="Contoh: 35">

                                <?php $__errorArgs = ['suspect_age'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                    <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                            </div>

                        </div>

                        <!-- Upload Surat Permintaan -->

                        <div class="mt-6">

                            <label for="request_letter" class="block text-sm font-medium text-gray-700 mb-2">

                                Surat Permintaan Pengujian <span class="text-red-500">*</span>

                            </label>

                            <div id="request_letter_dropzone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-blue-400 transition-colors duration-200">

                                <div class="space-y-1 text-center">

                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">

                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

                                    </svg>

                                    <div class="flex text-sm text-gray-600 justify-center">

                                        <label for="request_letter" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">

                                            <span>Upload file PDF</span>

                                            <input id="request_letter"

                                                   name="request_letter"

                                                   type="file"

                                                   class="sr-only"

                                                   accept=".pdf"

                                                   required

                                                   onchange="displayRequestLetterFileName(this)">

                                        </label>

                                        <p class="pl-1">atau drag and drop</p>

                                    </div>

                                    <p id="request_letter_filename" class="text-xs text-gray-500">PDF hingga 10MB</p>

                                </div>

                            </div>

                            <?php $__errorArgs = ['request_letter'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>

                                <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>

                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

                        </div>

                    </div>

                    <!-- 3. Daftar Sampel Section (Direvisi dengan Foto) -->

                    <div class="bg-orange-50 p-6 rounded-lg border border-orange-200">

                        <h3 class="text-lg font-semibold text-orange-900 mb-4 flex items-center">

                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">

                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>

                            </svg>

                            Daftar Sampel yang Diajukan

                        </h3>

                                                <div id="samples-container">

                            <!-- Sample Item Template -->

                            <div class="sample-item bg-white p-6 rounded-lg border border-gray-200 mb-4">

                                <div class="flex justify-between items-start mb-4">

                                    <h4 class="text-md font-medium text-gray-900">Sampel #1</h4>

                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                                    <!-- Nama Sampel -->

                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Nama Sampel <span class="text-red-500">*</span>

                                        </label>

                                        <input type="text"

                                               name="samples[0][name]"

                                               required

                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"

                                               placeholder="Contoh: Tablet Putih">

                                    </div>

                                    <!-- Jumlah -->

                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Jumlah yang Diserahkan <span class="text-red-500">*</span>

                                        </label>

                                        <input type="number"

                                               name="samples[0][quantity]"

                                               required

                                               min="1"

                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"

                                               placeholder="1">

                                    </div>

                                    <!-- Jumlah dalam Kemasan -->

                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Jumlah dalam Kemasan

                                        </label>

                                        <input type="number"

                                               name="samples[0][package_quantity]"

                                               min="1"

                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"

                                               placeholder="Contoh: 10">

                                    </div>

                                    <!-- Jenis Pengujian -->

                                    <div class="md:col-span-3">

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Jenis Pengujian <span class="text-red-500">*</span>

                                        </label>

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sample-test-type-group" data-sample-index="0">

                                            <label class="flex items-center space-x-2 rounded-md border border-gray-200 px-3 py-2 bg-gray-50 hover:bg-gray-100">

                                                <input type="checkbox" name="samples[0][test_types][]" value="uv_vis" class="sample-test-type-checkbox">

                                                <span class="text-sm text-gray-700">Identifikasi Spektrofotometri UV-VIS</span>

                                            </label>

                                            <label class="flex items-center space-x-2 rounded-md border border-gray-200 px-3 py-2 bg-gray-50 hover:bg-gray-100">

                                                <input type="checkbox" name="samples[0][test_types][]" value="gc_ms" class="sample-test-type-checkbox">

                                                <span class="text-sm text-gray-700">Identifikasi GC-MS</span>

                                            </label>

                                            <label class="flex items-center space-x-2 rounded-md border border-gray-200 px-3 py-2 bg-gray-50 hover:bg-gray-100">

                                                <input type="checkbox" name="samples[0][test_types][]" value="lc_ms" class="sample-test-type-checkbox">

                                                <span class="text-sm text-gray-700">Identifikasi LC-MS</span>

                                            </label>

                                        </div>

                                        <p class="text-xs text-gray-500 mt-1">Pilih minimal satu jenis pengujian.</p>

                                    </div>

                                    <!-- Zat Aktif -->

                                    <div class="md:col-span-3">

                                        <label class="block text-sm font-medium text-gray-700 mb-1">

                                            Zat Aktif <span class="text-red-500">*</span>

                                        </label>

                                        <input type="text"

                                               name="samples[0][active_substance]"

                                               required

                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"

                                               placeholder="Contoh: Amfetamin">

                                    </div>

                                    <!-- Foto Sampel (BARU) -->

                                    <div class="md:col-span-3">

                                        <label class="block text-sm font-medium text-gray-700 mb-2">

                                            Foto Sampel <span class="text-red-500">*</span>

                                        </label>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                                            <!-- Upload Area -->

                                            <div class="md:col-span-2">

                                                <div class="border-2 border-gray-300 border-dashed rounded-md p-4 hover:border-blue-400 transition-colors duration-200">

                                                    <div class="text-center">

                                                        <svg class="mx-auto h-8 w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">

                                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

                                                        </svg>

                                                        <div class="mt-2">

                                                            <label for="sample_photos_0" class="cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500">

                                                                <span>Upload foto sampel</span>

                                                                <input id="sample_photos_0"

                                                                       name="samples[0][photos][]"

                                                                       type="file"

                                                                       class="sr-only"

                                                                       accept="image/*"

                                                                       multiple

                                                                       required

                                                                       onchange="previewSampleImages(this, 0)">

                                                            </label>

                                                        </div>

                                                        <p class="text-xs text-gray-500 mt-1">JPG, PNG, max 5MB per file. Dapat unggah beberapa file sekaligus</p>

                                                    </div>

                                                </div>

                                            </div>

                                            <!-- Preview Area -->

                                            <div>

                                                <div id="sample_preview_0" class="grid grid-cols-2 gap-2 min-h-[100px] p-2 border border-gray-200 rounded-md bg-gray-50">

                                                    <div class="flex items-center justify-center text-gray-400 text-xs col-span-2">

                                                        Preview foto akan muncul di sini

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <button type="button"

                                id="add-sample"

                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">

                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">

                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>

                            </svg>

                            Tambah Sampel

                        </button>

                    </div>

                    <!-- Submit dan Cetak BA Button -->

                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">

                        <div class="flex space-x-3">
                            <button type="button"
                                    onclick="window.history.back()"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                Kembali
                            </button>
                            <a href="<?php echo e(route('dashboard')); ?>"
                               class="hidden sm:inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                Ke Dashboard
                            </a>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" name="action" value="save"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                Simpan
                            </button>
                            <button type="submit" name="action" value="save_and_print"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                                Simpan & Cetak BA
                            </button>
                        </div>
                    </div>

                </form>

            </div>

        </div>

    </div>

<script>
// Display filename when file is selected for request letter
function displayRequestLetterFileName(input) {
    const filenameDisplay = document.getElementById('request_letter_filename');
    const dropzone = document.getElementById('request_letter_dropzone');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSize = (file.size / (1024 * 1024)).toFixed(2); // Size in MB
        
        filenameDisplay.innerHTML = `<span class="text-green-600 font-medium">✓ ${file.name}</span> <span class="text-gray-500">(${fileSize} MB)</span>`;
        dropzone.classList.remove('border-gray-300');
        dropzone.classList.add('border-green-500', 'bg-green-50');
        
        console.log('File selected:', file.name, 'Size:', fileSize, 'MB');
    } else {
        filenameDisplay.textContent = 'PDF hingga 10MB';
        dropzone.classList.remove('border-green-500', 'bg-green-50');
        dropzone.classList.add('border-gray-300');
    }
}

// Preview sample images
function previewSampleImages(input, sampleIndex) {
    const previewContainer = document.getElementById(`sample_preview_${sampleIndex}`);
    
    if (!previewContainer) {
        console.error('Preview container not found for sample index:', sampleIndex);
        return;
    }
    
    // Clear previous previews
    previewContainer.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imgWrapper = document.createElement('div');
                    imgWrapper.className = 'relative';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'h-20 w-20 object-cover rounded border border-gray-300';
                    img.alt = file.name;
                    
                    imgWrapper.appendChild(img);
                    previewContainer.appendChild(imgWrapper);
                };
                
                reader.readAsDataURL(file);
            }
        });
        
        console.log(`Preview ${input.files.length} images for sample ${sampleIndex}`);
    } else {
        previewContainer.innerHTML = '<div class="flex items-center justify-center text-gray-400 text-xs col-span-2">Preview foto akan muncul di sini</div>';
    }
}

// Drag and drop support for request letter
(function() {
    const dropzone = document.getElementById('request_letter_dropzone');
    const fileInput = document.getElementById('request_letter');
    
    if (!dropzone || !fileInput) return;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.add('border-blue-500', 'bg-blue-50');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.remove('border-blue-500', 'bg-blue-50');
        }, false);
    });
    
    dropzone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            fileInput.files = files;
            displayRequestLetterFileName(fileInput);
        }
    }, false);
})();

// Sample management
let sampleIndex = 1;

document.getElementById('add-sample').addEventListener('click', function() {
    const container = document.getElementById('samples-container');
    const firstSample = container.querySelector('.sample-item');
    
    if (!firstSample) {
        console.error('Sample template not found');
        return;
    }
    
    // Clone the first sample
    const newSample = firstSample.cloneNode(true);
    
    // Update the sample number in the header
    const header = newSample.querySelector('h4');
    header.textContent = `Sampel #${sampleIndex + 1}`;
    
    // Add remove button if this is not the first sample
    const headerContainer = newSample.querySelector('.flex.justify-between');
    if (!headerContainer.querySelector('.remove-sample')) {
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-sample text-red-600 hover:text-red-800 text-sm font-medium';
        removeBtn.innerHTML = '✕ Hapus';
        removeBtn.onclick = function() {
            newSample.remove();
            updateSampleNumbers();
        };
        headerContainer.appendChild(removeBtn);
    }
    
    // Update all input names and IDs with new index
    const inputs = newSample.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        const oldId = input.id;
        
        // Update name attribute
        if (input.name) {
            input.name = input.name.replace(/\[0\]/g, `[${sampleIndex}]`);
        }
        
        // Update ID attribute
        if (input.id) {
            input.id = input.id.replace(/_0($|_)/, `_${sampleIndex}$1`);
        }
        
        // Clear values except for default values
        if (input.type === 'text' || input.type === 'number') {
            input.value = '';
        } else if (input.type === 'checkbox') {
            input.checked = false;
        } else if (input.type === 'file') {
            input.value = '';
        }
        
        // Update onchange handlers
        if (input.onchange) {
            const onchangeStr = input.onchange.toString();
            if (onchangeStr.includes('previewSampleImages')) {
                input.setAttribute('onchange', `previewSampleImages(this, ${sampleIndex})`);
            }
        }
    });
    
    // Update all label 'for' attributes
    const labels = newSample.querySelectorAll('label[for]');
    labels.forEach(label => {
        if (label.htmlFor) {
            label.htmlFor = label.htmlFor.replace(/_0($|_)/, `_${sampleIndex}$1`);
        }
    });
    
    // Update preview container ID
    const previewContainer = newSample.querySelector('[id^="sample_preview_"]');
    if (previewContainer) {
        previewContainer.id = `sample_preview_${sampleIndex}`;
        previewContainer.innerHTML = '<div class="flex items-center justify-center text-gray-400 text-xs col-span-2">Preview foto akan muncul di sini</div>';
    }
    
    // Update test type group data attribute
    const testTypeGroup = newSample.querySelector('.sample-test-type-group');
    if (testTypeGroup) {
        testTypeGroup.setAttribute('data-sample-index', sampleIndex);
    }
    
    // Append to container
    container.appendChild(newSample);
    
    sampleIndex++;
    
    console.log('Added new sample with index:', sampleIndex - 1);
});

function updateSampleNumbers() {
    const samples = document.querySelectorAll('.sample-item');
    samples.forEach((sample, idx) => {
        const header = sample.querySelector('h4');
        if (header) {
            header.textContent = `Sampel #${idx + 1}`;
        }
    });
}

// Add remove button to first sample if there are multiple samples
function checkRemoveButtons() {
    const samples = document.querySelectorAll('.sample-item');
    if (samples.length > 1) {
        samples.forEach(sample => {
            const headerContainer = sample.querySelector('.flex.justify-between');
            if (!headerContainer.querySelector('.remove-sample')) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-sample text-red-600 hover:text-red-800 text-sm font-medium';
                removeBtn.innerHTML = '✕ Hapus';
                removeBtn.onclick = function() {
                    if (document.querySelectorAll('.sample-item').length > 1) {
                        sample.remove();
                        updateSampleNumbers();
                        checkRemoveButtons();
                    } else {
                        alert('Minimal harus ada satu sampel');
                    }
                };
                headerContainer.appendChild(removeBtn);
            }
        });
    } else if (samples.length === 1) {
        // Remove the remove button if only one sample left
        const removeBtn = samples[0].querySelector('.remove-sample');
        if (removeBtn) {
            removeBtn.remove();
        }
    }
}
</script>

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
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/requests/create.blade.php ENDPATH**/ ?>