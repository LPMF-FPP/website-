{{-- Partial: Notifikasi & Security --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Notifikasi & Security</h2>
        <p class="text-sm text-gray-500 mt-1">Konfigurasi notifikasi dan manajemen role</p>
    </div>
    <div class="p-6 space-y-6">
        <div class="space-y-4">
            {{-- SMTP Configuration --}}
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Konfigurasi SMTP
                </h3>
                
                {{-- Preset Selector --}}
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

                {{-- Gmail Instructions --}}
                <div x-show="smtpPreset === 'gmail'" class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs text-yellow-800">
                    <strong>Untuk Gmail:</strong>
                    <ol class="list-decimal ml-4 mt-1 space-y-1">
                        <li>Aktifkan 2-Step Verification di Google Account</li>
                        <li>Buat App Password: <a href="https://myaccount.google.com/apppasswords" target="_blank" class="underline text-blue-600">myaccount.google.com/apppasswords</a></li>
                        <li>Gunakan App Password (16 karakter) sebagai password</li>
                    </ol>
                </div>

                {{-- Mailpit Instructions --}}
                <div x-show="smtpPreset === 'mailpit'" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800">
                    <strong>Mailpit (Development):</strong>
                    <p class="mt-1">Email akan ditangkap di <a href="http://127.0.0.1:8025" target="_blank" class="underline">http://127.0.0.1:8025</a></p>
                    <p class="mt-1">Jalankan: <code class="bg-blue-100 px-1 rounded">mailpit</code></p>
                </div>
            </div>

            {{-- Email Notification --}}
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

            {{-- WhatsApp Notification --}}
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    Konfigurasi WhatsApp
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 mb-3">
                            <input type="checkbox" class="rounded border-gray-300" 
                                   x-model="client.state.form.notifications.whatsapp.enabled">
                            <span>Aktifkan Notifikasi WhatsApp</span>
                        </label>
                    </div>

                    <div x-show="client.state.form.notifications.whatsapp.enabled" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">GOWA Service URL</label>
                            <input type="url" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                   x-model="client.state.form.notifications.whatsapp.base_url"
                                   placeholder="http://localhost:3000">
                            <p class="text-xs text-gray-500 mt-1">URL untuk go-whatsapp-web-multidevice service</p>
                        </div>

                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Basic Auth User (Optional)</label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                       x-model="client.state.form.notifications.whatsapp.basic_user"
                                       placeholder="admin">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Basic Auth Password (Optional)</label>
                                <input type="password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                       x-model="client.state.form.notifications.whatsapp.basic_pass"
                                       placeholder="••••••••">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-2">Milestone Aktif</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <template x-for="milestone in whatsappMilestones" :key="milestone.key">
                                    <label class="inline-flex items-center gap-2 text-xs text-gray-700 bg-white px-3 py-2 rounded border border-gray-200">
                                        <input type="checkbox" 
                                               class="rounded border-gray-300"
                                               :value="milestone.key"
                                               x-model="client.state.form.notifications.whatsapp.enabled_milestones">
                                        <span x-text="milestone.label"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-2">Template Pesan</label>
                            <p class="text-xs text-gray-500 mb-3">
                                Gunakan <code class="bg-gray-100 px-1 py-0.5 rounded text-gray-700">{greeting}</code> untuk sapaan dinamis dan <code class="bg-gray-100 px-1 py-0.5 rounded text-gray-700">{resi}</code> untuk nomor resi.
                            </p>
                            
                            <div class="space-y-4">
                                <template x-for="milestone in whatsappMilestones" :key="'tpl-' + milestone.key">
                                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="block text-xs font-medium text-gray-700" x-text="milestone.label"></label>
                                            <button type="button"
                                                    class="px-2 py-1 text-xs font-medium text-white bg-blue-500 rounded hover:bg-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                                    :disabled="!client.state.form.notifications.whatsapp.enabled || !client.state.notificationsTest.whatsapp.target"
                                                    @click="client.testMilestone(milestone.key)"
                                                    title="Test template ini">
                                                <span x-show="!client.state.notificationsTest.milestones?.[milestone.key]?.loading">🧪 Test</span>
                                                <span x-show="client.state.notificationsTest.milestones?.[milestone.key]?.loading">⏳</span>
                                            </button>
                                        </div>
                                        <textarea 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:text-gray-500"
                                            rows="3"
                                            x-model="client.state.form.notifications.whatsapp.templates[milestone.key]"
                                            :disabled="!client.state.form.notifications.whatsapp.enabled"
                                            placeholder="Masukkan template pesan..."></textarea>
                                        <div x-show="client.state.notificationsTest.milestones?.[milestone.key]?.message" 
                                             class="mt-2 text-xs"
                                             :class="client.state.notificationsTest.milestones?.[milestone.key]?.success ? 'text-green-600' : 'text-red-600'"
                                             x-text="client.state.notificationsTest.milestones?.[milestone.key]?.message"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Test WhatsApp</label>
                            <p class="text-xs text-gray-500 mb-2">Masukkan nomor untuk test semua milestone</p>
                            <div class="flex gap-2">
                                <input type="text" 
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                       x-model="client.state.notificationsTest.whatsapp.target"
                                       placeholder="08123456789 atau +6285956592404">
                                <button type="button"
                                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50"
                                        :disabled="client.state.notificationsTest.whatsapp.loading"
                                        @click="client.testNotification('whatsapp')">
                                    <span x-show="!client.state.notificationsTest.whatsapp.loading">Test General</span>
                                    <span x-show="client.state.notificationsTest.whatsapp.loading">Sending...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800">
                        <strong>Persyaratan:</strong>
                        <ul class="list-disc ml-4 mt-1 space-y-1">
                            <li>Install <a href="https://github.com/aldinokemal/go-whatsapp-web-multidevice" target="_blank" class="underline">go-whatsapp-web-multidevice</a></li>
                            <li>Scan QR code untuk autentikasi WhatsApp</li>
                            <li>Pastikan service berjalan di URL yang dikonfigurasi</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Test Email --}}
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

            {{-- Role Management --}}
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
    
    {{-- Footer with Save Button --}}
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
