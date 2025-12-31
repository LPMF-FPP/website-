
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Notifikasi & Security</h2>
        <p class="text-sm text-gray-500 mt-1">Konfigurasi notifikasi dan manajemen role</p>
    </div>
    <div class="p-6 space-y-6">
        <div class="space-y-4">
            
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Konfigurasi SMTP
                </h3>
                
                
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Preset</label>
                    <select class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                            x-model="smtpPreset"
                            @change="applySmtpPreset()">
                        <option value="mailpit">Mailpit (Development)</option>
                        <option value="gmail">Gmail SMTP</option>
                        <option value="custom">Custom SMTP</option>
                    </select>
                </div>

                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">SMTP Host</label>
                        <input type="text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                               x-model="client.state.form.smtp.host"
                               :class="smtpPreset !== 'custom' ? 'bg-gray-100' : ''"
                               :readonly="smtpPreset !== 'custom'"
                               placeholder="smtp.example.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Port</label>
                        <input type="number" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                               x-model.number="client.state.form.smtp.port"
                               :class="smtpPreset !== 'custom' ? 'bg-gray-100' : ''"
                               :readonly="smtpPreset !== 'custom'"
                               placeholder="587">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Username</label>
                        <input type="text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                               x-model="client.state.form.smtp.username"
                               placeholder="user@gmail.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Password / App Password</label>
                        <input type="password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                               x-model="client.state.form.smtp.password"
                               placeholder="••••••••">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">From Address</label>
                        <input type="email" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                               x-model="client.state.form.smtp.from_address"
                               placeholder="noreply@lpmf.go.id">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">From Name</label>
                        <input type="text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                               x-model="client.state.form.smtp.from_name"
                               placeholder="LPMF LIMS">
                    </div>
                </div>

                
                <div x-show="smtpPreset === 'gmail'" class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs text-yellow-800">
                    <strong>Untuk Gmail:</strong>
                    <ol class="list-decimal ml-4 mt-1 space-y-1">
                        <li>Aktifkan 2-Step Verification di Google Account</li>
                        <li>Buat App Password: <a href="https://myaccount.google.com/apppasswords" target="_blank" class="underline text-blue-600">myaccount.google.com/apppasswords</a></li>
                        <li>Gunakan App Password (16 karakter) sebagai password</li>
                    </ol>
                </div>

                
                <div x-show="smtpPreset === 'mailpit'" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800">
                    <strong>Mailpit (Development):</strong>
                    <p class="mt-1">Email akan ditangkap di <a href="http://127.0.0.1:8025" target="_blank" class="underline">http://127.0.0.1:8025</a></p>
                    <p class="mt-1">Jalankan: <code class="bg-blue-100 px-1 rounded">mailpit</code></p>
                </div>
            </div>

            
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

            
            <div class="opacity-60">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 mb-3">
                    <input type="checkbox" class="rounded border-gray-300" x-model="client.state.form.notifications.whatsapp.enabled" disabled>
                    <span>Enable WhatsApp</span>
                    <span class="text-xs bg-gray-200 px-1.5 py-0.5 rounded">Planned</span>
                </label>
                <input 
                    type="text" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm" 
                    x-model="client.state.form.notifications.whatsapp.number" 
                    placeholder="6281234567890"
                    disabled>
                <p class="text-xs text-gray-500 mt-1">Fitur WhatsApp akan tersedia di versi mendatang</p>
            </div>

            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Target Test Email</label>
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
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Role yang Boleh Mengelola Settings</h3>
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
    
    
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
        <div>
            <p class="text-sm" 
               :class="client.state.sectionStatus['notifications']?.intentClass" 
               x-text="client.state.sectionStatus['notifications']?.message" 
               x-show="client.state.sectionStatus['notifications']?.message"></p>
            <p class="text-xs text-red-600" 
               x-text="client.state.sectionErrors['notifications']" 
               x-show="client.state.sectionErrors['notifications']"></p>
        </div>
        <button type="button"
                class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                :disabled="client.state.loadingSections['notifications']"
                @click="client.saveSection('notifications')">
            <span x-show="!client.state.loadingSections['notifications']">Simpan</span>
            <span x-show="client.state.loadingSections['notifications']">Menyimpan...</span>
        </button>
    </div>
</div>
<?php /**PATH /home/lpmf-dev/website-/resources/views/settings/partials/notifications-security.blade.php ENDPATH**/ ?>