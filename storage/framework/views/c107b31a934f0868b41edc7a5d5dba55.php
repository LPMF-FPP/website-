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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Permintaan Pengujian #' . $request->request_number,'breadcrumbs' => [[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Detail' ]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Permintaan Pengujian #' . $request->request_number),'breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Detail' ]])]); ?>
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
        <?php if(session('success')): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                <p class="font-semibold"><?php echo e(session('success')); ?></p>
                
            </div>
        <?php endif; ?>

        <?php if(session('error')): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <p class="font-semibold">Error!</p>
                <p class="text-sm mt-1"><?php echo e(session('error')); ?></p>
            </div>
        <?php endif; ?>

        
        <div class="flex items-center justify-between bg-white shadow-sm sm:rounded-lg p-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Detail Permintaan</h3>
                <p class="text-sm text-gray-600">Status: <span class="font-medium"><?php echo e(ucfirst(str_replace('_', ' ', $request->status))); ?></span></p>
            </div>
            <div class="flex space-x-2">
                <a href="<?php echo e(route('requests.edit', $request)); ?>"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Data
                </a>
                <a href="<?php echo e(route('requests.index')); ?>"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali
                </a>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Data Penyidik</h3>
                    <dl class="space-y-2 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">Nama</dt>
                            <dd><?php echo e($request->investigator->name ?? '-'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">NRP</dt>
                            <dd><?php echo e($request->investigator->nrp ?? '-'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">Pangkat</dt>
                            <dd><?php echo e($request->investigator->rank ?? '-'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">Satuan</dt>
                            <dd><?php echo e($request->investigator->jurisdiction ?? '-'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">Kontak</dt>
                            <dd><?php echo e($request->investigator->phone ?? '-'); ?></dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Informasi Kasus</h3>
                    <dl class="space-y-2 text-sm text-gray-700">
                        <div>
                            <dt class="font-medium text-gray-600">Nama Tersangka</dt>
                            <dd><?php echo e($request->suspect_name ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-600">Jenis Kelamin Tersangka</dt>
                            <dd><?php echo e($request->suspect_gender === 'male' ? 'Laki-laki' : ($request->suspect_gender === 'female' ? 'Perempuan' : '-')); ?></dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-600">Umur Tersangka</dt>
                            <dd><?php echo e($request->suspect_age !== null ? $request->suspect_age . ' tahun' : '-'); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daftar Sampel</h3>

                <?php if($request->samples->isNotEmpty()): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Kode</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Jenis Pengujian</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Zat Aktif</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Hasil Pengujian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php $__currentLoopData = $request->samples; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sample): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $methodLabels = [
                                            'uv_vis' => 'Identifikasi Spektrofotometri UV-VIS',
                                            'gc_ms' => 'Identifikasi GC-MS',
                                            'lc_ms' => 'Identifikasi LC-MS',
                                        ];
                                        $methods = collect($sample->test_methods ?? [])->map(fn ($value) => $methodLabels[$value] ?? ucfirst(str_replace('_', ' ', $value)));
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 font-medium text-gray-900"><?php echo e($sample->sample_code); ?></td>
                                        <td class="px-4 py-2 text-gray-700"><?php echo e($sample->sample_name); ?></td>
                                        <td class="px-4 py-2 text-gray-700">
                                            <?php if($methods->isNotEmpty()): ?>
                                                <ul class="list-disc list-inside space-y-1 text-gray-700">
                                                    <?php $__currentLoopData = $methods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <li><?php echo e($method); ?></li>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2 text-gray-700"><?php echo e($sample->active_substance ?? '-'); ?></td>
                                        <td class="px-4 py-2 text-gray-700">
                                            <?php if($sample->testResult): ?>
                                                <?php echo e($sample->testResult->summary ?? '-'); ?>

                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Belum ada hasil</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500">Belum ada sampel terdaftar.</p>
                <?php endif; ?>

                
                <h3 class="text-lg font-semibold text-gray-900 mb-4 mt-6">Berita Acara Penerimaan</h3>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h4 class="font-semibold text-blue-900">Berita Acara Penerimaan Sampel</h4>
                            </div>
                            <p class="text-sm text-blue-800">
                                Dokumen resmi penerimaan sampel dari penyidik.
                                <span id="ba-status" class="font-medium">Checking...</span>
                            </p>
                        </div>
                        <div class="flex flex-col space-y-2 ml-4">
                            <button
                                id="btn-generate-ba"
                                type="button"
                                onclick="generateBeritaAcara()"
                                class="hidden px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="btn-generate-text">Generate Dokumen</span>
                                <span id="btn-generate-loading" class="hidden">
                                    <svg class="animate-spin h-4 w-4 inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Generating...
                                </span>
                            </button>
                            <div id="ba-actions" class="hidden space-x-2">
                                <a
                                    id="ba-view-link"
                                    href="<?php echo e(route('requests.berita-acara.view', $request)); ?>"
                                    target="_blank"
                                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 text-center">
                                    Lihat
                                </a>
                                <a
                                    id="ba-download-link"
                                    href="<?php echo e(route('requests.berita-acara.download', $request)); ?>"
                                    class="px-4 py-2 border border-blue-600 text-blue-600 text-sm font-medium rounded-md hover:bg-blue-50 text-center">
                                    Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-2">
            <a href="<?php echo e(route('requests.edit', $request)); ?>" class="inline-flex items-center px-4 py-2 border border-indigo-600 text-indigo-600 text-sm font-medium rounded-md hover:bg-indigo-50">
                Edit Permintaan
            </a>
            <a href="<?php echo e(route('requests.index')); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50">
                Kembali ke Daftar
            </a>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="notification-toast" class="hidden fixed top-4 right-4 z-50 max-w-sm w-full">
        <div id="toast-content" class="rounded-lg shadow-lg p-4"></div>
    </div>

    <script>
        const requestId = <?php echo e($request->id); ?>;
        const csrfToken = '<?php echo e(csrf_token()); ?>';

        function showNotification(type, message) {
            const toast = document.getElementById('notification-toast');
            const toastContent = document.getElementById('toast-content');

            const bgColor = type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
            const textColor = type === 'success' ? 'text-green-800' : 'text-red-800';
            const iconPath = type === 'success'
                ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
                : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';

            toastContent.innerHTML = `
                <div class="border ${bgColor} ${textColor} px-4 py-3 rounded flex items-start" role="alert" aria-live="polite">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${iconPath}"></path>
                    </svg>
                    <p class="text-sm font-medium flex-1">${message}</p>
                    <button onclick="hideNotification()" class="ml-2 flex-shrink-0" aria-label="Tutup notifikasi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;

            toast.classList.remove('hidden');

            // Auto-hide after 5 seconds
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            document.getElementById('notification-toast').classList.add('hidden');
        }

        // Berita Acara Functions
        function checkBeritaAcaraStatus() {
            fetch(`/requests/<?php echo e($request->id); ?>/berita-acara/check`)
                .then(response => response.json())
                .then(data => {
                    const statusEl = document.getElementById('ba-status');
                    const generateBtn = document.getElementById('btn-generate-ba');
                    const actionsDiv = document.getElementById('ba-actions');

                    if (data.exists) {
                        // Add cache-busting timestamp to URLs
                        const timestamp = new Date().getTime();
                        const viewLink = document.getElementById('ba-view-link');
                        const downloadLink = document.getElementById('ba-download-link');

                        if (viewLink) {
                            const baseUrl = viewLink.getAttribute('href').split('?')[0];
                            viewLink.setAttribute('href', `${baseUrl}?v=${timestamp}`);
                        }
                        if (downloadLink) {
                            const baseUrl = downloadLink.getAttribute('href').split('?')[0];
                            downloadLink.setAttribute('href', `${baseUrl}?v=${timestamp}`);
                        }

                        statusEl.textContent = 'Dokumen sudah tersedia.';
                        statusEl.classList.add('text-green-600');
                        generateBtn.classList.add('hidden');
                        actionsDiv.classList.remove('hidden');
                        actionsDiv.classList.add('flex');
                    } else {
                        statusEl.textContent = 'Dokumen belum di-generate.';
                        statusEl.classList.add('text-orange-600');
                        generateBtn.classList.remove('hidden');
                        actionsDiv.classList.add('hidden');
                        actionsDiv.classList.remove('flex');
                    }
                })
                .catch(error => {
                    console.error('Error checking BA status:', error);
                    const statusEl = document.getElementById('ba-status');
                    statusEl.textContent = 'Error checking status.';
                    statusEl.classList.add('text-red-600');
                });
        }

        function generateBeritaAcara() {
            const generateBtn = document.getElementById('btn-generate-ba');
            const btnText = document.getElementById('btn-generate-text');
            const btnLoading = document.getElementById('btn-generate-loading');

            // Disable button and show loading
            generateBtn.disabled = true;
            btnText.classList.add('hidden');
            btnLoading.classList.remove('hidden');

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/requests/<?php echo e($request->id); ?>/berita-acara/generate`;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            document.body.appendChild(form);
            form.submit();
        }

        // Check BA status on page load
        window.addEventListener('DOMContentLoaded', () => {
            checkBeritaAcaraStatus();
        });
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
<?php /**PATH C:\Users\Farma\pusdokkes-subunit\resources\views/requests/show.blade.php ENDPATH**/ ?>