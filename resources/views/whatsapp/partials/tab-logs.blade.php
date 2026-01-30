<div>
    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">Message Logs</h2>

    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg border border-gray-200 dark:border-gray-700">
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            <template x-for="batch in logsData.logs.data" :key="batch.id">
                <li>
                    <div class="block hover:bg-gray-50 dark:hover:bg-gray-750 transition duration-150 ease-in-out">
                        <div class="px-4 py-4 sm:px-6 cursor-pointer" @click="toggleBatch(batch.id)">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-primary-600 truncate" x-text="batch.title"></div>
                                <div class="ml-2 flex-shrink-0 flex">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                          :class="{
                                              'bg-green-100 text-green-800': batch.failed_count === 0 && batch.sent_count > 0,
                                              'bg-yellow-100 text-yellow-800': batch.failed_count > 0 && batch.failed_count < batch.total_recipients,
                                              'bg-red-100 text-red-800': batch.failed_count === batch.total_recipients,
                                              'bg-gray-100 text-gray-800': batch.sent_count === 0 && batch.failed_count === 0
                                          }">
                                        <span x-text="batch.sent_count + ' sent, ' + batch.failed_count + ' failed'"></span>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2 sm:flex sm:justify-between">
                                <div class="sm:flex">
                                    <p class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                        <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                        </svg>
                                        <span x-text="batch.type"></span>
                                        <span x-show="batch.mention_all" class="ml-2 text-xs bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">Mention All</span>
                                    </p>
                                </div>
                                <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400 sm:mt-0">
                                    <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <p x-text="new Date(batch.created_at).toLocaleString()"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Details Accordion -->
                        <div x-show="expandedBatch === batch.id" class="px-4 pb-4 sm:px-6 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                            <div x-show="loadingDetails" class="py-4 text-center text-sm text-gray-500">Loading details...</div>
                            <div x-show="!loadingDetails && batchDetails[batch.id]">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-2">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipient</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        <template x-for="msg in batchDetails[batch.id]" :key="msg.id">
                                            <tr>
                                                <td class="px-3 py-2 text-sm text-gray-500">
                                                    <div x-text="msg.recipient_name || msg.recipient_jid"></div>
                                                    <div class="text-xs text-gray-400" x-text="msg.recipient_jid"></div>
                                                </td>
                                                <td class="px-3 py-2 text-sm">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize"
                                                          :class="{
                                                              'bg-green-100 text-green-800': msg.status === 'sent',
                                                              'bg-red-100 text-red-800': msg.status === 'failed',
                                                              'bg-gray-100 text-gray-800': msg.status === 'pending'
                                                          }"
                                                          x-text="msg.status">
                                                    </span>
                                                    <div x-show="msg.error_message" class="text-xs text-red-500 mt-1" x-text="msg.error_message"></div>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-500" x-text="msg.sent_at ? new Date(msg.sent_at).toLocaleTimeString() : '-'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
