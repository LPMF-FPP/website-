<div class="space-y-6">
    <section class="bg-slate-900 text-white rounded-lg border border-slate-700 p-5 sm:p-6" aria-labelledby="gowa-update-heading">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">Pemeliharaan GOWA</p>
                <h2 id="gowa-update-heading" class="mt-2 text-xl font-semibold">Versi dan pembaruan terverifikasi</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">Pembaruan hanya tersedia jika rilis, runtime, dan jalur pemeliharaan telah lulus pemeriksaan. Kredensial dan sesi perangkat tidak dikirim dari halaman ini.</p>
            </div>
            <div class="min-w-0 lg:w-80">
                <template x-if="!overviewData?.gowa_update">
                    <p class="rounded-md bg-slate-800 px-4 py-3 text-sm text-slate-300">Status pembaruan belum tersedia.</p>
                </template>
                <div x-show="overviewData?.gowa_update" class="space-y-3 rounded-md bg-slate-800 px-4 py-3">
                    <p class="text-sm text-slate-300">Versi terpasang: <span class="font-medium text-white" x-text="overviewData?.gowa_update?.runtime?.version || 'Belum terverifikasi'"></span></p>
                    <p x-show="overviewData?.gowa_update && !overviewData.gowa_update.available" class="text-sm text-amber-200" role="status" x-text="gowaAvailabilityMessage(overviewData?.gowa_update?.reason)"></p>
                    <button type="button" @click="checkGowaUpdate()" :disabled="gowaUpdateChecking" class="min-h-11 w-full rounded-md border border-slate-500 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-text="gowaUpdateChecking ? 'Memeriksa rilis...' : 'Periksa pembaruan'"></span>
                    </button>
                    <div x-show="gowaUpdateChecking || gowaUpdateCheck || gowaUpdateCheckError || gowaPreparation" class="space-y-3 rounded-md border border-slate-600 bg-slate-900 px-3 py-3 text-sm" aria-live="polite">
                        <p x-show="gowaUpdateChecking" class="text-slate-300">Memeriksa versi terpasang dan rilis terbaru.</p>
                        <p x-show="gowaUpdateCheckError" class="text-amber-200" x-text="gowaUpdateCheckError"></p>
                        <p x-show="gowaUpdateCheck?.current_version" class="text-slate-300">Versi terpasang: <span class="font-medium text-white" x-text="gowaUpdateCheck?.current_version"></span></p>
                        <p x-show="gowaUpdateCheck?.latest_version" class="text-slate-200">Rilis terbaru: <span class="font-semibold text-white" x-text="gowaUpdateCheck?.latest_version"></span></p>
                        <template x-if="gowaUpdateCheck?.blocked_reason === 'runtime_stale'">
                            <p class="text-amber-200">Bukti runtime sudah kedaluwarsa. Tunggu pemeriksaan runtime berikutnya.</p>
                        </template>
                        <template x-if="gowaUpdateCheck?.blocked_reason === 'current_version_unknown'">
                            <p class="text-amber-200">Versi terpasang tidak dapat dicocokkan dengan katalog terverifikasi.</p>
                        </template>
                        <template x-if="gowaUpdateCheck?.blocked_reason === 'already_latest'">
                            <p class="text-slate-300">GOWA sudah menggunakan rilis terbaru.</p>
                        </template>
                        <template x-if="gowaUpdateCheck?.blocked_reason === 'release_not_approved' && !overviewData?.gowa_update?.preparation_ready">
                            <p class="text-amber-200">Rilis baru ditemukan. Jalur persiapan otomatis belum siap di server.</p>
                        </template>
                        <template x-if="gowaUpdateCheck?.update_available && overviewData?.gowa_update?.preparation_ready && !gowaPreparation">
                            <p class="text-slate-300" role="status">Paket akan disiapkan otomatis.</p>
                        </template>
                        <template x-if="gowaUpdateCheck?.update_available && overviewData?.gowa_update?.preparation_ready && !overviewData?.gowa_update?.can_request">
                            <p class="text-amber-200" role="status">Akun ini tidak memiliki izin untuk menyiapkan pembaruan.</p>
                        </template>
                        <template x-if="gowaPreparation && ['queued', 'preparing'].includes(gowaPreparation.status)">
                            <div class="space-y-2">
                                <p class="text-slate-200" role="status" x-text="gowaPreparation.status === 'queued' ? 'Menunggu persiapan paket...' : 'Memverifikasi rilis, mengunduh image, dan menyiapkan rollback. GOWA tetap berjalan.'"></p>
                                <progress class="h-2 w-full" aria-label="Persiapan paket pembaruan"></progress>
                            </div>
                        </template>
                        <template x-if="gowaPreparation?.status === 'failed'">
                            <p class="text-amber-200" role="alert">Persiapan gagal (<span x-text="gowaPreparation.failure_code || 'preparation_failed'"></span>). Perbaiki pemeriksaan operasional lalu mulai pemeriksaan lagi.</p>
                        </template>
                        <template x-if="gowaPreparation?.ready">
                            <div class="space-y-3 border-t border-slate-700 pt-3">
                                <p class="text-emerald-200">Paket <span class="font-semibold" x-text="gowaPreparation.version"></span> siap dipasang.</p>
                                <p class="break-all font-mono text-xs text-slate-300" x-text="gowaPreparation.digest"></p>
                                <p class="text-xs text-slate-400">Persiapan berlaku sampai <span x-text="gowaPreparation.expires_at"></span>. GOWA belum diubah.</p>
                                <label class="flex min-h-11 items-center gap-3 text-sm text-slate-300" for="gowa-confirm">
                                    <input id="gowa-confirm" type="checkbox" x-model="gowaUpdateConfirmed" class="h-4 w-4 rounded border-slate-500 bg-slate-900 text-primary-500 focus:ring-primary-400">
                                    <span>Saya mengonfirmasi pemasangan pembaruan GOWA.</span>
                                </label>
                            </div>
                        </template>
                        <template x-if="gowaPreparation?.status === 'ready' && !gowaPreparation.ready">
                            <p class="text-amber-200" role="status">Persiapan paket kedaluwarsa. Periksa pembaruan lagi untuk menyiapkan ulang.</p>
                        </template>
                        <button x-show="gowaPreparation?.ready" type="button" @click="requestGowaUpdate()" :disabled="!gowaUpdateConfirmed || gowaUpdateSubmitting || !overviewData?.gowa_update?.can_install" class="min-h-11 w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-text="gowaUpdateSubmitting ? 'Mengirim permintaan...' : 'Instal pembaruan'"></span>
                        </button>
                        <p x-show="gowaUpdateMessage" class="text-sm text-slate-200" x-text="gowaUpdateMessage" role="status"></p>
                    </div>
                </div>
            </div>
        </div>
        <template x-if="overviewData?.gowa_update?.latest_operation">
            <div class="mt-5 border-t border-slate-700 pt-4" aria-live="polite">
                <p class="text-sm text-slate-300">Operasi terakhir: <span class="font-semibold text-white" x-text="gowaStatusLabel(overviewData.gowa_update.latest_operation.status)"></span></p>
                <p class="mt-1 text-xs text-slate-400" x-text="overviewData.gowa_update.latest_operation.message || 'Menunggu rekonsiliasi status.'"></p>
                <p x-show="overviewData.gowa_update.latest_operation.stale" class="mt-2 text-sm text-amber-200">Status operasi tidak diperbarui pada batas waktu yang diharapkan. Rekonsiliasi akan menentukan hasilnya.</p>
                <div x-show="['failed', 'rolled_back', 'degraded'].includes(overviewData.gowa_update.latest_operation.status)" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p class="text-sm text-slate-300">Periksa layanan dan log operasional sebelum mencoba ulang.</p>
                    <button type="button" @click="retryGowaUpdate()" :disabled="gowaUpdateSubmitting || !overviewData.gowa_update.can_retry" class="min-h-11 rounded-md border border-slate-500 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-50">Ajukan percobaan ulang</button>
                </div>
            </div>
        </template>
    </section>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Sent Today</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100" x-text="overviewData?.stats?.sent_today ?? 0"></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Scheduled</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100" x-text="overviewData?.stats?.scheduled ?? 0"></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Failed Today</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100" x-text="overviewData?.stats?.failed_today ?? 0"></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Pending Tasks</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100" x-text="overviewData?.stats?.pending_tasks ?? 0"></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                Recent Activity
            </h3>
        </div>
        <div class="overflow-hidden">
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                <template x-if="!overviewData?.recent_activity || overviewData.recent_activity.length === 0">
                    <li class="px-4 py-4 sm:px-6 text-center text-gray-500">
                        No recent activity
                    </li>
                </template>
                <template x-for="item in overviewData?.recent_activity || []" :key="item.key || `${item.type}-${item.id}`">
                    <li class="px-4 py-4 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-750 transition duration-150 ease-in-out">
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col">
                                <p class="text-sm font-medium text-primary-600 truncate" x-text="item.title"></p>
                                <div class="flex items-center mt-2">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                          :class="{
                                              'bg-green-100 text-green-800': item.status === 'success',
                                              'bg-yellow-100 text-yellow-800': item.status === 'warning',
                                              'bg-red-100 text-red-800': item.status === 'failed',
                                              'bg-blue-100 text-blue-800': item.status === 'info'
                                          }">
                                        <span x-text="item.type"></span>
                                    </span>
                                    <span class="ml-2 text-sm text-gray-500" x-text="item.details || '-'"></span>
                                </div>
                            </div>
                            <div class="ml-2 flex-shrink-0 flex flex-col items-end">
                                <p class="text-sm text-gray-500" x-text="item.time"></p>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-4 sm:px-6 rounded-b-lg">
            <div class="text-sm">
                <a href="#" @click.prevent="activeTab = 'logs'; loadTabData('logs')" class="font-medium text-primary-600 hover:text-primary-500">
                    View full history &rarr;
                </a>
            </div>
        </div>
    </div>
</div>
