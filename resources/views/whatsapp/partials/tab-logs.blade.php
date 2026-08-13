<div>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Log Pengiriman</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Riwayat setiap pesan GOWA dan setiap percobaan pengirimannya.</p>
        </div>
        <button type="button"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="loadTabData('logs')">
            Muat ulang
        </button>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div x-show="logsData.messages.data.length === 0" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
            Belum ada log pengiriman WhatsApp.
        </div>

        <ul x-show="logsData.messages.data.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
            <template x-for="message in logsData.messages.data" :key="message.id">
                <li class="px-4 py-4 sm:px-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="message.recipient_name || message.recipient_jid"></p>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                      :class="messageStatusClass(message.status)"
                                      x-text="message.status"></span>
                            </div>
                            <p class="mt-1 break-all text-xs text-gray-500 dark:text-gray-400" x-text="message.recipient_jid"></p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="message.source_label || (message.is_legacy_log ? 'Log historis' : 'Pesan WhatsApp')"></span>
                                <span x-text="'Percobaan: ' + (message.attempt_count || 0)"></span>
                                <span x-text="message.created_at ? new Date(message.created_at).toLocaleString() : '-'"></span>
                            </div>
                            <div x-show="message.message_preview" class="mt-3 rounded-md border border-primary-100 bg-primary-50/60 px-3 py-2 text-sm text-gray-700 dark:border-primary-900/50 dark:bg-primary-900/10 dark:text-gray-200">
                                <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300"
                                   x-text="message.message_preview_source === 'historical_batch' ? 'Pratinjau batch historis' : (message.message_preview_source === 'historical_outbox' ? 'Pratinjau outbox historis' : 'Isi pesan tersimpan')"></p>
                                <p class="mt-1 whitespace-pre-wrap break-words" x-text="message.message_preview"></p>
                                <p x-show="message.message_preview_source === 'historical_batch'" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Pratinjau ini berasal dari batch lama; payload per pesan tidak tersimpan.
                                </p>
                                <p x-show="message.message_preview_source === 'historical_outbox'" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Pratinjau ini diimpor dari outbox lama; pengiriman ulang tetap diblokir.
                                </p>
                            </div>
                            <p x-show="!message.message_preview" class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                Pratinjau isi pesan tidak tersedia untuk log ini.
                            </p>
                            <p x-show="message.error_message" class="mt-2 text-sm text-red-700 dark:text-red-300" x-text="message.error_message"></p>
                            <p x-show="!message.retry_available && message.retry_block_reason" class="mt-2 text-xs text-amber-700 dark:text-amber-300"
                               :id="'retry-reason-' + message.id"
                               x-text="message.retry_block_reason"></p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button type="button"
                                    class="inline-flex min-h-9 items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                    :aria-expanded="expandedMessage === message.id"
                                    @click="toggleMessage(message.id)">
                                <span x-text="expandedMessage === message.id ? 'Tutup percobaan' : 'Lihat percobaan'"></span>
                            </button>
                            <button type="button"
                                    class="inline-flex min-h-9 items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-800"
                                    :disabled="!message.retry_available || retryingMessageIds[message.id]"
                                    :title="message.retry_available ? 'Kirim ulang payload yang tersimpan' : message.retry_block_reason"
                                    :aria-describedby="message.retry_available ? null : 'retry-reason-' + message.id"
                                    @click="retryMessage(message)">
                                <span x-text="retryingMessageIds[message.id] ? 'Mengantrikan...' : (message.retry_available ? 'Coba ulang' : 'Tidak dapat diulang')"></span>
                            </button>
                        </div>
                    </div>

                    <div x-show="expandedMessage === message.id" x-transition.opacity class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <p x-show="loadingMessageAttempts" class="text-sm text-gray-500 dark:text-gray-400">Memuat riwayat percobaan...</p>
                        <div x-show="!loadingMessageAttempts && messageAttempts[message.id]">
                            <ol class="space-y-3">
                                <template x-for="attempt in (messageAttempts[message.id]?.attempts || [])" :key="attempt.attempt_number">
                                    <li class="rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-900/50">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-gray-800 dark:text-gray-200" x-text="'Percobaan ' + attempt.attempt_number"></span>
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="messageStatusClass(attempt.status)" x-text="attempt.status"></span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="attempt.started_at ? new Date(attempt.started_at).toLocaleString() : '-'"></span>
                                        </div>
                                        <p x-show="attempt.error_message" class="mt-1 text-xs text-red-700 dark:text-red-300" x-text="attempt.error_message"></p>
                                    </li>
                                </template>
                            </ol>
                        </div>
                    </div>
                </li>
            </template>
        </ul>

        <div x-show="logsData.messages.last_page > 1" class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:text-gray-400">
            <p x-text="'Menampilkan ' + (logsData.messages.from || 0) + '-' + (logsData.messages.to || 0) + ' dari ' + (logsData.messages.total || 0) + ' pesan'"></p>
            <nav class="flex items-center gap-2" aria-label="Navigasi halaman log pengiriman">
                <button type="button"
                        class="inline-flex min-h-9 items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="loading || !logsData.messages.prev_page_url"
                        @click="loadLogsPage((logsData.messages.current_page || 1) - 1)">
                    Sebelumnya
                </button>
                <span class="min-w-24 text-center text-xs font-medium text-gray-500 dark:text-gray-400" aria-live="polite"
                      x-text="'Halaman ' + (logsData.messages.current_page || 1) + ' dari ' + (logsData.messages.last_page || 1)"></span>
                <button type="button"
                        class="inline-flex min-h-9 items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="loading || !logsData.messages.next_page_url"
                        @click="loadLogsPage((logsData.messages.current_page || 1) + 1)">
                    Berikutnya
                </button>
            </nav>
        </div>
    </div>
</div>
