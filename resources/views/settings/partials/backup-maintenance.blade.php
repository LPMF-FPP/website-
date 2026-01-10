{{-- Partial: Backup & Maintenance --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Backup & Maintenance</h2>
        <p class="text-sm text-gray-500 mt-1">Emergency backup system untuk pre-deployment</p>
    </div>
    <div class="p-6 space-y-6">
        
        {{-- Emergency Backup Button --}}
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Emergency Backup</h3>
            <p class="text-xs text-gray-600 mb-4">Backup lengkap database + storage sebelum deploy/update</p>
            
            <button 
                @click="startEmergencyBackup()" 
                :disabled="client.state.backupRunning"
                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors">
                <span x-show="!client.state.backupRunning">Emergency Backup Now</span>
                <span x-show="client.state.backupRunning" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                        <path class="opacity-75" stroke-width="4" d="M4 12a8 8 0 018-8" stroke-linecap="round"></path>
                    </svg>
                    <span x-text="client.state.backupProgress"></span>
                </span>
            </button>

            {{-- Backup Progress --}}
            <div x-show="client.state.backupRunning" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-xs">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="animate-spin h-4 w-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                        <path class="opacity-75" stroke-width="4" d="M4 12a8 8 0 018-8" stroke-linecap="round"></path>
                    </svg>
                    <span class="font-medium text-blue-900" x-text="client.state.backupProgress"></span>
                </div>
                <div class="w-full bg-blue-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                         :style="`width: ${client.state.backupProgressPercent}%`"></div>
                </div>
            </div>
        </div>

        {{-- Backup List --}}
        <div class="border border-gray-200 rounded-lg">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700">Recent Backups</h3>
            </div>
            <div class="divide-y divide-gray-200">
                <template x-if="client.state.backups.length === 0">
                    <div class="p-4 text-sm text-gray-500 text-center">No backups yet</div>
                </template>
                <template x-for="backup in client.state.backups" :key="backup.id">
                    <div class="p-4 hover:bg-gray-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900" x-text="new Date(backup.created_at).toLocaleString('id-ID')"></span>
                                    <span 
                                        class="px-2 py-0.5 text-xs font-medium rounded-full"
                                        :class="{
                                            'bg-green-100 text-green-700': backup.status === 'success',
                                            'bg-yellow-100 text-yellow-700': backup.status === 'running',
                                            'bg-red-100 text-red-700': backup.status === 'failed',
                                            'bg-gray-100 text-gray-700': backup.status === 'queued'
                                        }"
                                        x-text="backup.status"></span>
                                </div>
                                <div class="mt-1 flex items-center gap-4 text-xs text-gray-500">
                                    <span x-text="`Size: ${backup.size}`"></span>
                                    <span x-show="backup.git_commit" x-text="`Git: ${backup.git_commit}`"></span>
                                    <span x-show="backup.triggered_by" x-text="`By: ${backup.triggered_by}`"></span>
                                </div>
                                <div x-show="backup.error_message" class="mt-1 text-xs text-red-600" x-text="backup.error_message"></div>
                            </div>
                            <div x-show="backup.status === 'success'" class="flex gap-2">
                                <a :href="`/api/settings/emergency-backup/${backup.id}/download/db`" 
                                   class="text-xs text-blue-600 hover:text-blue-700">DB</a>
                                <a :href="`/api/settings/emergency-backup/${backup.id}/download/storage`" 
                                   class="text-xs text-blue-600 hover:text-blue-700">Storage</a>
                                <a :href="`/api/settings/emergency-backup/${backup.id}/download/manifest`" 
                                   class="text-xs text-blue-600 hover:text-blue-700">Manifest</a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Retention Settings --}}
        <div class="border border-gray-200 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Retention Policy</h3>
            <div class="flex items-center gap-3">
                <label class="text-xs text-gray-600">Keep backups for</label>
                <input 
                    type="number" 
                    class="w-20 px-2 py-1 border border-gray-300 rounded text-sm"
                    x-model.number="client.state.form.backup.retention_days"
                    min="1"
                    max="90">
                <span class="text-xs text-gray-600">days</span>
            </div>
            <p class="text-xs text-gray-500 mt-2">Older backups will be automatically deleted</p>
        </div>

    </div>
</div>
