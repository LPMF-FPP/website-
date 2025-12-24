
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Notifikasi & Security</h2>
        <p class="text-sm text-gray-500 mt-1">PUT /notifications-security • POST /notifications/test</p>
    </div>
    <div class="p-6 space-y-6">
        <div class="space-y-4">
            <div>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 mb-3">
                    <input type="checkbox" class="rounded border-gray-300" x-model="client.state.form.notifications.email.enabled">
                    <span>Enable Email</span>
                </label>
                <input 
                    type="email" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" 
                    x-model="client.state.form.notifications.email.address" 
                    placeholder="ops@lab.go.id"
                    :disabled="!client.state.form.notifications.email.enabled">
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 mb-3">
                    <input type="checkbox" class="rounded border-gray-300" x-model="client.state.form.notifications.whatsapp.enabled">
                    <span>Enable WhatsApp</span>
                </label>
                <input 
                    type="text" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" 
                    x-model="client.state.form.notifications.whatsapp.number" 
                    placeholder="6281234567890"
                    :disabled="!client.state.form.notifications.whatsapp.enabled">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Target Test</label>
                <input 
                    type="text" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm mb-2" 
                    x-model="client.state.notificationsTest.email.target" 
                    placeholder="test@example.com">
                <button 
                    type="button"
                    class="w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                    :disabled="client.state.notificationsTest.email.loading"
                    @click="client.testNotification('email')">
                    <span x-show="!client.state.notificationsTest.email.loading">Test Email</span>
                    <span x-show="client.state.notificationsTest.email.loading">Sending...</span>
                </button>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Role yang Boleh Mengelola</h3>
                <div class="space-y-2">
                    <template x-for="role in availableRoles" :key="'manage-'+role">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" class="rounded border-gray-300" :value="role" x-model="client.state.roles.manage">
                            <span x-text="roleLabels[role] || role"></span>
                        </label>
                    </template>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Role yang Boleh Issue Number</h3>
                <div class="space-y-2">
                    <template x-for="role in availableRoles" :key="'issue-'+role">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" class="rounded border-gray-300" :value="role" x-model="client.state.roles.issue">
                            <span x-text="roleLabels[role] || role"></span>
                        </label>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-primary-100 bg-primary-25/60 px-6 py-4">
        <div>
            <p class="text-sm" :class="client.state.sectionStatus['notifications'].intentClass" x-text="client.state.sectionStatus['notifications'].message" x-show="client.state.sectionStatus['notifications'].message"></p>
            <p class="text-xs text-red-600" x-text="client.state.sectionErrors['notifications']" x-show="client.state.sectionErrors['notifications']"></p>
        </div>
        <button type="button"
                class="btn-primary disabled:opacity-60 disabled:cursor-not-allowed"
                :disabled="client.state.loadingSections['notifications']"
                @click="client.saveSection('notifications')">
            <span x-show="!client.state.loadingSections['notifications']">Simpan</span>
            <span x-show="client.state.loadingSections['notifications']">Menyimpan...</span>
        </button>
    </div>
</section>
border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-end gap-3">
        <button 
            type="button"
            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
            :disabled="client.state.loadingSections['notifications']"
            @click="client.saveSection('notifications')">
            <span x-show="!client.state.loadingSections['notifications']">Simpan</span>
            <span x-show="client.state.loadingSections['notifications']">Saving...</span>
        </button>
    </div>
</div>

<div 
    x-show="client.state.sectionStatus['notifications']?.message" 
    x-transition
    class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
    <p class="text-sm text-green-800" x-text="client.state.sectionStatus['notifications']?.message"></p>
</div<?php /**PATH /home/lpmf-dev/website-/resources/views/settings/partials/notifications-security.blade.php ENDPATH**/ ?>