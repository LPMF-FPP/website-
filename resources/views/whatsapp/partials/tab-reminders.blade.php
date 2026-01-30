<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Scheduled Reminders</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Automated messages based on schedule</p>
        </div>
        <button
            @click="$dispatch('open-fetch-groups-modal')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Fetch Groups
        </button>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <template x-for="reminder in remindersData.reminders" :key="reminder.id">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center justify-center h-10 w-10 rounded-lg"
                                      :class="reminder.is_enabled ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </span>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="reminder.name"></h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="reminder.type"></p>
                            </div>
                        </div>
                        
                        <!-- Toggle Switch -->
                        <button 
                            @click="toggleReminder(reminder.id)"
                            type="button" 
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                            :class="reminder.is_enabled ? 'bg-green-600' : 'bg-gray-200 dark:bg-gray-700'"
                            role="switch" 
                            :aria-checked="reminder.is_enabled"
                        >
                            <span class="sr-only">Use setting</span>
                            <span 
                                aria-hidden="true" 
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                :class="reminder.is_enabled ? 'translate-x-5' : 'translate-x-0'"
                            ></span>
                        </button>
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span x-text="'Schedule: ' + reminder.schedule_time.substring(0, 5)"></span>
                        </div>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                             <span x-text="reminder.recipients ? reminder.recipients.length + ' recipients' : 'No recipients'"></span>
                             <span x-show="reminder.mention_all" class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Mentions All</span>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button 
                            @click="$dispatch('open-reminder-modal', { reminder: reminder })"
                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            Edit
                        </button>
                        <button 
                            @click="triggerReminder(reminder.id)"
                            class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-primary-700 bg-primary-100 hover:bg-primary-200 dark:bg-primary-900/30 dark:text-primary-400 dark:hover:bg-primary-900/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            Run Now
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
