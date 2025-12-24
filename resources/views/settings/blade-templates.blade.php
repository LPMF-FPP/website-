@extends('layouts.app')

@section('title', 'Editor Template Blade')

@section('content')
<div class="container mx-auto px-4 py-6" x-data="bladeTemplateEditor()">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Editor Template Blade</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Edit template PDF Blade secara langsung. Perubahan akan langsung mempengaruhi dokumen yang dihasilkan.
        </p>
    </div>

    <!-- Template Selector -->
    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <template x-for="tpl in templates" :key="tpl.key">
                    <button
                        @click="selectTemplate(tpl.key)"
                        :class="{
                            'ring-2 ring-blue-500': selectedTemplate === tpl.key,
                            'hover:border-blue-300': selectedTemplate !== tpl.key
                        }"
                        class="relative rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-4 shadow-sm focus:outline-none transition-all"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1 text-left">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="tpl.name"></p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="formatBytes(tpl.size)"></span> • 
                                    <span x-text="formatDate(tpl.modified_at)"></span>
                                </p>
                            </div>
                            <div x-show="selectedTemplate === tpl.key" class="ml-3">
                                <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- Editor -->
    <div x-show="selectedTemplate" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <!-- Toolbar -->
            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-2">
                    <button
                        @click="saveTemplate()"
                        :disabled="saving || !hasChanges"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg x-show="!saving" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <svg x-show="saving" class="animate-spin mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan'"></span>
                    </button>

                    <button
                        @click="showPreview = true; generatePreview()"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>

                    <button
                        @click="showBackups = true; loadBackups()"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Riwayat
                    </button>

                    <button
                        @click="revertChanges()"
                        :disabled="!hasChanges"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Batal
                    </button>
                </div>

                <div class="flex items-center space-x-4">
                    <span x-show="hasChanges" class="text-sm text-amber-600 dark:text-amber-400">
                        • Belum disimpan
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Baris: <span x-text="editorInfo.line"></span> | 
                        Kolom: <span x-text="editorInfo.column"></span>
                    </span>
                </div>
            </div>

            <!-- Code Editor -->
            <div class="relative">
                <textarea
                    x-ref="editor"
                    x-model="currentContent"
                    @input="hasChanges = true; updateEditorInfo()"
                    class="w-full h-96 font-mono text-sm p-4 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500"
                    style="tab-size: 4; font-family: 'Courier New', Courier, monospace; line-height: 1.5;"
                    spellcheck="false"
                ></textarea>
            </div>

            <!-- Syntax Help -->
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">Tips:</h3>
                <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-1">
                    <li>• Gunakan sintaks Blade: <code class="bg-white dark:bg-gray-800 px-1 py-0.5 rounded">@@foreach</code>, <code class="bg-white dark:bg-gray-800 px-1 py-0.5 rounded">@{{ $variable }}</code></li>
                    <li>• Backup otomatis dibuat setiap kali menyimpan</li>
                    <li>• Hindari menggunakan fungsi PHP berbahaya seperti <code class="bg-white dark:bg-gray-800 px-1 py-0.5 rounded">exec()</code>, <code class="bg-white dark:bg-gray-800 px-1 py-0.5 rounded">eval()</code></li>
                    <li>• Cache view akan di-clear otomatis setelah menyimpan</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div x-show="showPreview" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showPreview = false">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showPreview = false"></div>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                <div class="px-4 pt-5 pb-4 bg-white dark:bg-gray-800 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full mt-3 text-center sm:mt-0 sm:text-left">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                    Preview Template
                                </h3>
                                <button
                                    @click="showPreview = false"
                                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            
                            <div x-show="previewLoading" class="flex items-center justify-center py-12">
                                <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>

                            <div x-show="previewError?.message || Object.keys(previewError || {}).length > 0" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Preview Gagal</h3>
                                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                            <p x-text="previewError?.message || 'Terjadi kesalahan'"></p>
                                            <template x-if="previewError?.error">
                                                <div class="mt-2 p-2 bg-red-100 dark:bg-red-900/40 rounded text-xs font-mono">
                                                    <p x-text="previewError.error"></p>
                                                    <template x-if="previewError?.line != null">
                                                        <p class="mt-1 text-red-600 dark:text-red-400">Baris: <span x-text="previewError.line"></span></p>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="previewError?.hint">
                                                <p class="mt-2 text-xs italic" x-text="previewError.hint"></p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div x-show="!previewLoading && (!previewError?.message && Object.keys(previewError || {}).length === 0)" class="mt-4">
                                <div class="overflow-auto border border-gray-200 dark:border-gray-700 rounded-md bg-white" style="max-height: 70vh;">
                                    <iframe 
                                        x-ref="previewFrame"
                                        class="w-full border-0"
                                        style="min-height: 600px;"
                                        sandbox="allow-same-origin"
                                    ></iframe>
                                </div>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Preview menggunakan data contoh. Data sebenarnya mungkin berbeda.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button
                        @click="showPreview = false"
                        class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup Modal -->
    <div x-show="showBackups" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showBackups = false">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showBackups = false"></div>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="px-4 pt-5 pb-4 bg-white dark:bg-gray-800 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full mt-3 text-center sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                Riwayat Backup
                            </h3>
                            <div class="mt-4">
                                <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-md">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-900">
                                            <tr>
                                                <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 dark:text-gray-400 uppercase">
                                                    Tanggal
                                                </th>
                                                <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 dark:text-gray-400 uppercase">
                                                    Ukuran
                                                </th>
                                                <th class="px-4 py-3 text-xs font-medium tracking-wider text-right text-gray-500 dark:text-gray-400 uppercase">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <template x-for="backup in backups" :key="backup.path">
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                                        <span x-text="formatDate(backup.created_at)"></span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                        <span x-text="formatBytes(backup.size)"></span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                                        <button
                                                            @click="restoreBackup(backup.path)"
                                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                        >
                                                            Pulihkan
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr x-show="backups.length === 0">
                                                <td colspan="3" class="px-4 py-8 text-sm text-center text-gray-500 dark:text-gray-400">
                                                    Belum ada backup
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button
                        @click="showBackups = false"
                        class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div x-show="notification.show"
         x-transition
         @click="notification.show = false"
         class="fixed bottom-4 right-4 z-50 max-w-sm">
        <div :class="{
            'bg-green-50 border-green-200 text-green-800': notification.type === 'success',
            'bg-red-50 border-red-200 text-red-800': notification.type === 'error',
            'bg-blue-50 border-blue-200 text-blue-800': notification.type === 'info'
        }" class="p-4 border rounded-lg shadow-lg">
            <p class="text-sm font-medium" x-text="notification.message"></p>
        </div>
    </div>
</div>

@push('scripts')
<script>
function bladeTemplateEditor() {
    return {
        templates: [],
        selectedTemplate: null,
        currentContent: '',
        originalContent: '',
        hasChanges: false,
        saving: false,
        showBackups: false,
        showPreview: false,
        previewLoading: false,
        previewError: {},
        backups: [],
        editorInfo: {
            line: 1,
            column: 1
        },
        notification: {
            show: false,
            type: 'info',
            message: ''
        },

        async init() {
            await this.loadTemplates();
        },

        async loadTemplates() {
            try {
                const response = await fetch('/api/settings/blade-templates');
                const data = await response.json();
                
                if (data.success) {
                    this.templates = data.templates;
                    if (this.templates.length > 0 && !this.selectedTemplate) {
                        this.selectTemplate(this.templates[0].key);
                    }
                }
            } catch (error) {
                this.showNotification('error', 'Gagal memuat template: ' + error.message);
            }
        },

        async selectTemplate(key) {
            if (this.hasChanges) {
                if (!confirm('Ada perubahan yang belum disimpan. Yakin ingin pindah template?')) {
                    return;
                }
            }

            this.selectedTemplate = key;
            await this.loadTemplateContent(key);
        },

        async loadTemplateContent(key) {
            try {
                const response = await fetch(`/api/settings/blade-templates/${key}`);
                const data = await response.json();
                
                if (data.success) {
                    this.currentContent = data.template.content;
                    this.originalContent = data.template.content;
                    this.hasChanges = false;
                    this.updateEditorInfo();
                }
            } catch (error) {
                this.showNotification('error', 'Gagal memuat konten template: ' + error.message);
            }
        },

        async saveTemplate() {
            if (!this.selectedTemplate || this.saving) return;

            this.saving = true;
            try {
                const response = await fetch(`/api/settings/blade-templates/${this.selectedTemplate}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        content: this.currentContent,
                        create_backup: true
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    this.originalContent = this.currentContent;
                    this.hasChanges = false;
                    this.showNotification('success', 'Template berhasil disimpan!');
                    await this.loadTemplates(); // Refresh metadata
                } else {
                    this.showNotification('error', data.message || 'Gagal menyimpan template');
                }
            } catch (error) {
                this.showNotification('error', 'Error: ' + error.message);
            } finally {
                this.saving = false;
            }
        },

        async loadBackups() {
            if (!this.selectedTemplate) return;

            try {
                const response = await fetch(`/api/settings/blade-templates/${this.selectedTemplate}/backups`);
                const data = await response.json();
                
                if (data.success) {
                    this.backups = data.backups;
                }
            } catch (error) {
                this.showNotification('error', 'Gagal memuat backup: ' + error.message);
            }
        },

        async generatePreview() {
            if (!this.selectedTemplate) return;

            this.previewLoading = true;
            this.previewError = {};

            try {
                const response = await fetch(`/api/settings/blade-templates/${this.selectedTemplate}/preview`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        content: this.currentContent
                    })
                });

                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Write HTML to iframe
                    const iframe = this.$refs.previewFrame;
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    iframeDoc.open();
                    iframeDoc.write(data.html);
                    iframeDoc.close();
                    
                    // Auto-resize iframe to content
                    setTimeout(() => {
                        try {
                            const contentHeight = iframeDoc.body.scrollHeight;
                            iframe.style.height = Math.max(contentHeight, 600) + 'px';
                        } catch (e) {
                            iframe.style.height = '600px';
                        }
                    }, 100);
                } else if (response.status === 422) {
                    // Validation or compilation error
                    console.error('Preview validation error:', data);
                    this.previewError = {
                        message: data.message || 'Template tidak valid',
                        error: data.error || '',
                        line: data.line,
                        file: data.file,
                        hint: data.hint || '',
                        slug: data.slug
                    };
                } else {
                    // Other errors
                    console.error('Preview failed:', data);
                    this.previewError = {
                        message: data.message || 'Gagal membuat preview',
                        error: data.error || '',
                        hint: data.hint || ''
                    };
                }
            } catch (error) {
                console.error('Preview request failed:', error);
                this.previewError = {
                    message: 'Gagal menghubungi server',
                    error: error.message,
                    hint: 'Periksa koneksi jaringan Anda'
                };
            } finally {
                this.previewLoading = false;
            }
        },

        async restoreBackup(backupPath) {
            if (!confirm('Yakin ingin memulihkan template dari backup ini? Template saat ini akan diganti.')) {
                return;
            }

            try {
                const response = await fetch(`/api/settings/blade-templates/${this.selectedTemplate}/restore`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        backup_file: backupPath
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('success', 'Template berhasil dipulihkan!');
                    this.showBackups = false;
                    await this.loadTemplateContent(this.selectedTemplate);
                } else {
                    this.showNotification('error', data.message || 'Gagal memulihkan template');
                }
            } catch (error) {
                this.showNotification('error', 'Error: ' + error.message);
            }
        },

        revertChanges() {
            if (confirm('Yakin ingin membatalkan semua perubahan?')) {
                this.currentContent = this.originalContent;
                this.hasChanges = false;
            }
        },

        updateEditorInfo() {
            const textarea = this.$refs.editor;
            if (!textarea) return;

            const text = textarea.value.substring(0, textarea.selectionStart);
            const lines = text.split('\n');
            this.editorInfo.line = lines.length;
            this.editorInfo.column = lines[lines.length - 1].length + 1;
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('id-ID', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        showNotification(type, message) {
            this.notification = { show: true, type, message };
            setTimeout(() => {
                this.notification.show = false;
            }, 5000);
        }
    }
}
</script>
@endpush

<style>
[x-cloak] { display: none !important; }
</style>
@endsection
