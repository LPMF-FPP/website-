<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Edit Template QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Template QMH', 'route' => 'quality.templates.index'],
                    ['label' => 'Edit Template'],
                ]"
            />

            <x-qmh-subnav active="templates" />
        </div>
    </x-slot>

    @php
        $rawLayoutMetadata = is_array($template->metadata) ? $template->metadata : [];
        $layoutMeta = \App\Support\QmhFrLayoutProfile::fromMetadata($rawLayoutMetadata);
        $hasExplicitLayoutProfile = array_key_exists('layout_profile', $rawLayoutMetadata);
        $layoutProfileInput = old('layout_profile', $hasExplicitLayoutProfile ? $layoutMeta['layout_profile'] : '');
        $riskMatrixColumnsCsv = implode(', ', is_array($layoutMeta['risk_matrix_columns'] ?? null) ? $layoutMeta['risk_matrix_columns'] : []);
        $initialSchema = data_get($template->metadata, 'form_schema');
        $initialJson = old('form_schema_json', $initialSchema ? json_encode($initialSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
    @endphp

    <div
        class="container mx-auto px-4 py-6"
        x-data="qmhTemplateEditor({
            templateId: @js((int) $template->id),
            templateName: @js((string) $template->name),
            docType: @js((string) strtoupper($template->doc_type)),
            versionLabel: @js('v' . (int) $template->version),
            updateUrl: @js(route('quality.templates.update', $template)),
            csrfToken: @js(csrf_token()),
            initialContent: @js(old('content_html', $resolvedContentHtml ?? '<p></p>')),
            initialVersions: @js($relatedVersions),
        })"
        x-init="init()"
    >
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Editor Template QMH</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Edit template QMH secara langsung. Tampilan editor disamakan dengan editor template blade.
            </p>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-medium">Terjadi kesalahan validasi:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <template x-for="tpl in versions" :key="tpl.id">
                        <button
                            type="button"
                            @click="selectTemplate(tpl.edit_url)"
                            :class="{
                                'ring-2 ring-blue-500': selectedTemplateId === tpl.id,
                                'hover:border-blue-300': selectedTemplateId !== tpl.id
                            }"
                            class="relative rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-4 shadow-sm focus:outline-none transition-all"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1 text-left min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" x-text="tpl.name"></p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span x-text="docType"></span> •
                                        <span x-text="tpl.updated_at_label"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Versi <span x-text="tpl.version"></span></p>
                                </div>
                                <div x-show="selectedTemplateId === tpl.id" class="ml-3">
                                    <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="mt-2" x-show="tpl.is_active">
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-medium text-green-700">Active</span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <form id="qmh-template-edit-form" method="POST" action="{{ route('quality.templates.update', $template) }}">
            @csrf
            @method('PATCH')

            <input type="hidden" name="name" value="{{ old('name', $template->name) }}">
            <input type="hidden" name="doc_type" value="{{ old('doc_type', $template->doc_type) }}">
            <input type="hidden" name="clause" value="{{ old('clause', (string) $template->clause) }}">
            <input type="hidden" name="version_notes" value="{{ old('version_notes', data_get($template->metadata, 'version_notes')) }}">

            <input type="hidden" name="layout_profile" value="{{ old('layout_profile', $layoutProfileInput) }}">
            <input type="hidden" name="logo_source" value="{{ old('logo_source', $layoutMeta['logo_source']) }}">
            <input type="hidden" name="logo_path" value="{{ old('logo_path', $layoutMeta['logo_path']) }}">
            <input type="hidden" name="declaration_header" value="{{ old('declaration_header', $layoutMeta['declaration_header']) }}">
            <input type="hidden" name="risk_matrix_columns_csv" value="{{ old('risk_matrix_columns_csv', $riskMatrixColumnsCsv) }}">
            <textarea id="form_schema_json" name="form_schema_json" class="hidden">{{ $initialJson }}</textarea>

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center space-x-2">
                            <button
                                type="button"
                                @click="saveTemplate()"
                                :disabled="saving || !hasChanges"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
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
                                type="button"
                                @click="showPreview = true; generatePreview()"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Preview
                            </button>

                            <button
                                type="button"
                                @click="showHistory = true"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Riwayat
                            </button>

                            <button
                                type="button"
                                @click="revertChanges()"
                                :disabled="!hasChanges"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                                Batal
                            </button>
                        </div>

                        <div class="flex items-center space-x-4">
                            <span x-show="hasChanges" class="text-sm text-amber-600 dark:text-amber-400">• Belum disimpan</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Baris: <span x-text="editorInfo.line"></span> |
                                Kolom: <span x-text="editorInfo.column"></span>
                            </span>
                        </div>
                    </div>

                    <div class="relative">
                        <textarea
                            id="content_html"
                            name="content_html"
                            x-ref="editor"
                            x-model="currentContent"
                            @input="hasChanges = true; updateEditorInfo()"
                            @click="updateEditorInfo()"
                            @keyup="updateEditorInfo()"
                            class="w-full h-96 font-mono text-sm p-4 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500"
                            style="tab-size: 4; font-family: 'Courier New', Courier, monospace; line-height: 1.5;"
                            spellcheck="false"
                        >{{ old('content_html', $resolvedContentHtml ?? '<p></p>') }}</textarea>
                    </div>

                    @error('content_html')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                    <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                        <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">Tips:</h3>
                        <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-1">
                            <li>• Edit template HTML secara langsung di editor.</li>
                            <li>• Gunakan Preview untuk melihat hasil draft sebelum disimpan.</li>
                            <li>• Perubahan disimpan sebagai versi baru dan versi aktif lama otomatis diarsipkan.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>

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

                                <div x-show="previewError" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                                    <p class="text-sm font-medium text-red-800 dark:text-red-200" x-text="previewError"></p>
                                </div>

                                <div x-show="!previewLoading && !previewError" class="mt-4">
                                    <div class="overflow-auto border border-gray-200 dark:border-gray-700 rounded-md bg-white" style="max-height: 70vh;">
                                        <iframe
                                            x-ref="previewFrame"
                                            class="w-full border-0"
                                            style="min-height: 600px;"
                                            sandbox="allow-same-origin"
                                        ></iframe>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Preview menggunakan konten draft saat ini.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            @click="showPreview = false"
                            class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="showHistory"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             @keydown.escape.window="showHistory = false">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showHistory = false"></div>

                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="px-4 pt-5 pb-4 bg-white dark:bg-gray-800 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="w-full mt-3 text-center sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                    Riwayat Versi
                                </h3>
                                <div class="mt-4">
                                    <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-md">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 dark:text-gray-400 uppercase">Versi</th>
                                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 dark:text-gray-400 uppercase">Tanggal</th>
                                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-right text-gray-500 dark:text-gray-400 uppercase">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                <template x-for="tpl in versions" :key="tpl.id">
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">v<span x-text="tpl.version"></span></td>
                                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap"><span x-text="tpl.updated_at_label"></span></td>
                                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                            <span x-show="tpl.is_active" class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Active</span>
                                                            <span x-show="!tpl.is_active" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                                            <button
                                                                type="button"
                                                                @click="selectTemplate(tpl.edit_url)"
                                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                            >
                                                                Buka
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            @click="showHistory = false"
                            class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="notification.show"
             x-transition
             @click="notification.show = false"
             class="fixed bottom-4 right-4 z-50 max-w-sm">
            <div :class="{
                'bg-green-50 border-green-200 text-green-800': notification.type === 'success',
                'bg-red-50 border-red-200 text-red-800': notification.type === 'error'
            }" class="p-4 border rounded-lg shadow-lg">
                <p class="text-sm font-medium" x-text="notification.message"></p>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function qmhTemplateEditor(config) {
            return {
                selectedTemplateId: Number(config.templateId || 0),
                templateName: String(config.templateName || ''),
                docType: String(config.docType || ''),
                versionLabel: String(config.versionLabel || ''),
                updateUrl: String(config.updateUrl || ''),
                csrfToken: String(config.csrfToken || ''),
                versions: Array.isArray(config.initialVersions) ? config.initialVersions : [],
                currentContent: String(config.initialContent || '<p></p>'),
                originalContent: String(config.initialContent || '<p></p>'),
                hasChanges: false,
                saving: false,
                showPreview: false,
                showHistory: false,
                previewLoading: false,
                previewError: '',
                editorInfo: { line: 1, column: 1 },
                notification: { show: false, type: 'success', message: '' },

                init() {
                    this.$nextTick(() => {
                        this.updateEditorInfo();
                    });
                },

                async selectTemplate(url) {
                    if (!url) return;

                    if (this.hasChanges) {
                        const proceed = window.confirm('Ada perubahan yang belum disimpan. Yakin ingin pindah template?');
                        if (!proceed) {
                            return;
                        }
                    }

                    window.location.assign(url);
                },

                async saveTemplate() {
                    if (this.saving) return;

                    const form = document.getElementById('qmh-template-edit-form');
                    if (!form) return;

                    this.saving = true;
                    try {
                        const formData = new FormData(form);
                        formData.set('_method', 'PATCH');

                        const response = await fetch(this.updateUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json, text/html',
                            },
                            body: formData,
                        });

                        const contentType = response.headers.get('content-type') || '';
                        if (response.ok) {
                            this.originalContent = this.currentContent;
                            this.hasChanges = false;
                            this.showNotification('success', 'Template berhasil disimpan sebagai versi baru. Membuka versi terbaru...');

                            if (contentType.includes('application/json')) {
                                const data = await response.json().catch(() => null);
                                const nextUrl = data?.redirect_url || data?.redirect;
                                if (typeof nextUrl === 'string' && nextUrl.length > 0) {
                                    window.location.assign(nextUrl);
                                    return;
                                }
                            }

                            const redirectedTo = response.url || '';
                            if (redirectedTo) {
                                window.location.assign(redirectedTo);
                                return;
                            }

                            window.location.reload();
                            return;
                        }

                        if (contentType.includes('application/json')) {
                            const payload = await response.json().catch(() => null);
                            this.showNotification('error', payload?.message || 'Gagal menyimpan template.');
                        } else {
                            this.showNotification('error', 'Gagal menyimpan template. Silakan cek form lalu coba lagi.');
                        }
                    } catch (error) {
                        this.showNotification('error', 'Gagal menyimpan template: ' + (error?.message || 'unknown error'));
                    } finally {
                        this.saving = false;
                    }
                },

                generatePreview() {
                    this.previewLoading = true;
                    this.previewError = '';

                    try {
                        this.$nextTick(() => {
                            const iframe = this.$refs.previewFrame;
                            if (!iframe) {
                                this.previewError = 'Komponen preview tidak ditemukan.';
                                this.previewLoading = false;
                                return;
                            }

                            const html = this.currentContent || '<p></p>';
                            if ('srcdoc' in iframe) {
                                iframe.srcdoc = html;
                            } else {
                                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                                iframeDoc.open();
                                iframeDoc.write(html);
                                iframeDoc.close();
                            }

                            setTimeout(() => {
                                try {
                                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                                    const contentHeight = iframeDoc.body?.scrollHeight || 0;
                                    iframe.style.height = Math.max(contentHeight, 600) + 'px';
                                } catch (_) {
                                    iframe.style.height = '600px';
                                } finally {
                                    this.previewLoading = false;
                                }
                            }, 80);
                        });
                    } catch (error) {
                        this.previewError = error?.message || 'Gagal membuat preview';
                        this.previewLoading = false;
                    }
                },

                revertChanges() {
                    const proceed = window.confirm('Yakin ingin membatalkan semua perubahan?');
                    if (!proceed) return;

                    this.currentContent = this.originalContent;
                    this.hasChanges = false;
                    this.updateEditorInfo();
                },

                updateEditorInfo() {
                    const textarea = this.$refs.editor;
                    if (!textarea) return;

                    const text = textarea.value.substring(0, textarea.selectionStart);
                    const lines = text.split('\n');
                    this.editorInfo.line = lines.length;
                    this.editorInfo.column = lines[lines.length - 1].length + 1;
                },

                showNotification(type, message) {
                    this.notification = { show: true, type, message };
                    setTimeout(() => {
                        this.notification.show = false;
                    }, 5000);
                }
            };
        }
    </script>
    @endpush

    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
