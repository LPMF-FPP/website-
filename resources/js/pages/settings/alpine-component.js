/**
 * Alpine.js component registration for Settings page
 * Registers settingsPageAlpine component that uses SettingsClient
 */

import Alpine from 'alpinejs';
import { SettingsClient } from './index.js';

const DOCUMENT_TYPE_LABELS = {
    sample_receipt: 'Tanda Terima Sampel',
    request_letter: 'Surat Permintaan Pengujian',
    request_letter_receipt: 'Tanda Terima Surat Permintaan',
    handover_report: 'Berita Acara Serah Terima',
    ba_penerimaan: 'Berita Acara Penerimaan Sampel',
    ba_penyerahan: 'Berita Acara Penyerahan Sampel',
    laporan_hasil_uji: 'Laporan Hasil Uji (LHU)',
    lab_report: 'Laporan Lab',
    cover_letter: 'Surat Pengantar',
    sample_photo: 'Foto Sampel',
    evidence_photo: 'Foto Barang Bukti',
    form_preparation: 'Form Persiapan',
    instrument_uv_vis: 'Hasil Instrumen UV-VIS',
    instrument_gc_ms: 'Hasil Instrumen GC-MS',
    instrument_lc_ms: 'Hasil Instrumen LC-MS',
    instrument_result: 'Hasil Instrumen',
    report_receipt: 'Tanda Terima Laporan',
    letter_receipt: 'Tanda Terima Surat',
    sample_handover: 'Serah Terima Sampel',
    test_results: 'Hasil Pengujian',
    qr_code: 'QR Code',
};

const formatDocumentType = (type) => {
    if (!type) return 'Dokumen';
    return DOCUMENT_TYPE_LABELS[type] || type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};

const normalizeDocumentTypes = (list) => {
    if (!Array.isArray(list) || list.length === 0) {
        return Object.entries(DOCUMENT_TYPE_LABELS).map(([value, label]) => ({
            code: value,
            value,
            label,
        }));
    }

    return list
        .map((item) => {
            if (typeof item === 'string') {
                return { code: item, value: item, label: formatDocumentType(item) };
            }
            if (item && typeof item === 'object') {
                const value = item.value || item.code || '';
                if (!value) return null;
                return {
                    ...item,
                    code: value,
                    value,
                    label: item.label || formatDocumentType(value),
                };
            }
            return null;
        })
        .filter(Boolean);
};

const DOCUMENT_SOURCES = [
    { value: '', label: 'Semua sumber' },
    { value: 'generated', label: 'Generated' },
    { value: 'upload', label: 'Upload Manual' },
    { value: 'filesystem', label: 'Tanpa metadata (file saja)' },
];

/**
 * Factory function for settingsPageAlpine component
 * Reads initial data from window.__SETTINGS_INITIAL_DATA__ (injected by Blade)
 */
export function registerSettingsComponent() {
    Alpine.data('settingsPageAlpine', () => {
        // Read initial data from window (passed from Blade)
        const initialData = window.__SETTINGS_INITIAL_DATA__ || {};
        const initialForm = initialData.initialForm || {};
        const initialTemplates = initialData.initialTemplates || [];
        const optionValues = initialData.optionValues || {};
        const initialManageRoles = initialData.initialManageRoles || [];
        const initialIssueRoles = initialData.initialIssueRoles || [];
        const initialNowPreview = initialData.initialNowPreview || '';

        // Initialize client immediately to prevent "client is not defined" errors
        const client = new SettingsClient({
            templates: initialTemplates,
            initialManageRoles,
            initialIssueRoles,
        });

        return {
            client,
            get activeSection() {
                return this._activeSection || 'numbering';
            },
            set activeSection(value) {
                const previousSection = this._activeSection;
                this._activeSection = value;
                
                // Load document templates when switching to templates section
                if (value === 'templates') {
                    if (!this.documentTemplatesLoading && this.documentTemplateState.templates.length === 0) {
                        this.loadDocumentTemplates();
                    }
                    // Refresh template editor when section becomes visible
                    if (previousSection !== 'templates' && this.templateEditorInstance) {
                        this.$nextTick(() => {
                            this.refreshTemplateEditor();
                        });
                    }
                }
            },
            _activeSection: 'numbering', // Default active section
            labels: {
                sample_code: 'Kode Sampel',
                ba: 'BA Penerimaan',
                lhu: 'Laporan Hasil Uji (LHU)',
                ba_penyerahan: 'BA Penyerahan',
                tracking: 'Nomor Resi Tracking',
            },
            templateTypeLabels: {
                lhu: 'LHU (Laporan Hasil Uji)',
                ba_penerimaan: 'BA Penerimaan',
                ba_penyerahan: 'BA Penyerahan',
            },
            documentTypeLabels: DOCUMENT_TYPE_LABELS,
            documentTypes: normalizeDocumentTypes(optionValues.document_types),
            documentSources: DOCUMENT_SOURCES,
            getDefaultPattern(scope) {
                const patterns = {
                    sample_code: 'SMP-{YYYY}{MM}-{SEQ:4}',
                    ba: 'BA/{YYYY}/{MM}/{SEQ:4}',
                    lhu: 'LHU/{YYYY}/{MM}/{TEST}/{SEQ:4}',
                    ba_penyerahan: 'BAP/{YYYY}/{MM}/{SEQ:4}',
                    tracking: 'RESI/{YYYY}{MM}{DD}/{SEQ:5}',
                };
                return patterns[scope] || '{YYYY}-{SEQ:4}';
            },
            getTemplatesByType(type) {
                return (this.client.state.templates || []).filter(tpl => tpl.type === type);
            },
            timezones: Array.isArray(optionValues.timezones) && optionValues.timezones.length 
                ? optionValues.timezones 
                : ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'],
            dateFormats: Array.isArray(optionValues.date_formats) && optionValues.date_formats.length 
                ? optionValues.date_formats 
                : ['DD/MM/YYYY', 'YYYY-MM-DD', 'DD-MM-YYYY'],
            numberFormats: (Array.isArray(optionValues.number_formats) && optionValues.number_formats.length 
                ? optionValues.number_formats 
                : ['1.234,56', '1,234.56']).map((fmt) => ({ value: fmt, label: fmt })),
            languages: (Array.isArray(optionValues.languages) && optionValues.languages.length 
                ? optionValues.languages 
                : ['id', 'en']).map((code) => ({ 
                    value: code, 
                    label: code === 'id' ? 'Bahasa Indonesia' : (code === 'en' ? 'English' : code.toUpperCase()) 
                })),
            storageDrivers: Array.isArray(optionValues.storage_drivers) && optionValues.storage_drivers.length 
                ? optionValues.storage_drivers 
                : ['local', 'public', 's3'],
            availableRoles: ['admin', 'supervisor', 'analyst', 'lab_analyst', 'petugas_lab'],
            roleLabels: {
                admin: 'Admin',
                supervisor: 'Supervisor',
                analyst: 'Analis',
                lab_analyst: 'Petugas Lab (Analis)',
                petugas_lab: 'Petugas Lab',
            },
            nowPreview: initialNowPreview,
            get selectedDocType() {
                return this.documentTemplateState.selectedDocumentType;
            },
            set selectedDocType(value) {
                this.documentTemplateState.selectedDocumentType = value;
            },
            get selectedTemplateId() {
                return this.documentTemplateState.selectedTemplateId;
            },
            set selectedTemplateId(value) {
                this.documentTemplateState.selectedTemplateId = value;
            },
            get selectedFormat() {
                return this.documentTemplateEditor.format;
            },
            set selectedFormat(value) {
                this.documentTemplateEditor.format = value;
            },
            previewHtml: '',
            previewPdfUrl: '',
            previewLoading: false,
            previewObjectUrl: null,
            templateEditorModal: {
                open: false,
                mode: 'edit',
                loading: false,
                saving: false,
                editorReady: false,
                tpl: null,
                documentType: '',
                format: 'html',
                engine: 'dompdf',
                name: '',
                id: null,
                meta: {
                    doc_type: '',
                    version: '',
                    status: '',
                    is_active: false,
                },
                error: '',
                previewHtml: '',
                previewPdfUrl: '',
                previewLoading: false,
                previewObjectUrl: null,
            },

            init() {
                // Guard against double initialization
                if (this._initialized) {
                    console.warn('⚠️ [Alpine] init already called, skipping duplicate initialization');
                    return;
                }
                this._initialized = true;
                
                console.log('🚀 [Alpine] settingsPageAlpine init started');
                console.log('📊 Initial state.numberingPreview:', this.client.state.numberingPreview);
                console.log('📊 Initial state.previewLoading:', this.client.state.previewLoading);
                
                // Client is already initialized above
                this.client.onTemplateSelected = async () => {
                    await this.onTemplateSelected();
                };
                this.client.refreshTemplatePreview = async () => {
                    await this.refreshTemplatePreview();
                };
                
                // Inject template types and labels
                this.client.state.templateTypes = ['sample_code', 'ba', 'lhu'];
                this.client.state.templateLabels = {
                    sample_code: 'Template Sample',
                    ba: 'Template Berita Acara',
                    lhu: 'Template LHU',
                };

                // Merge defaults from initial form
                this.client.state.form = this.client.mergeDefaults(this.client.clone(initialForm));
                this.ensureLocaleDefaults();

                console.log('✅ [Alpine] State initialized, loading data...');
                
                // Load all data
                this.client.loadAll();
                
                // Backward-compatible deep link: /settings#template-dokumen -> /settings/blade-templates
                if (window.location.hash === '#template-dokumen') {
                    window.location.replace('/settings/blade-templates');
                    return;
                }
            },

            ensureLocaleDefaults() {
                this.client.state.form.locale ??= {};
                if (!this.client.state.form.locale.timezone) this.client.state.form.locale.timezone = this.timezones[0] ?? 'Asia/Jakarta';
                if (!this.client.state.form.locale.date_format) this.client.state.form.locale.date_format = this.dateFormats[0] ?? 'DD/MM/YYYY';
                if (!this.client.state.form.locale.number_format) this.client.state.form.locale.number_format = this.numberFormats[0]?.value ?? '1.234,56';
                if (!this.client.state.form.locale.language) this.client.state.form.locale.language = this.languages[0]?.value ?? 'id';
                this.client.state.form.retention ??= {};
                if (!this.client.state.form.retention.storage_driver) this.client.state.form.retention.storage_driver = this.storageDrivers[0] ?? 'local';
                if (!this.client.state.form.retention.storage_folder_path) this.client.state.form.retention.storage_folder_path = '';
                if (!this.client.state.form.retention.purge_after_days) this.client.state.form.retention.purge_after_days = 365;
                if (!this.client.state.form.retention.export_filename_pattern) this.client.state.form.retention.export_filename_pattern = '';
            },

            updateNowPreview() {
                try {
                    const tz = this.client.state.form?.locale?.timezone || 'Asia/Jakarta';
                    const fmt = this.client.state.form?.locale?.date_format || 'DD/MM/YYYY';
                    const now = new Date();
                    const options = { 
                        timeZone: tz, 
                        year: 'numeric', 
                        month: '2-digit', 
                        day: '2-digit', 
                        hour: '2-digit', 
                        minute: '2-digit', 
                        second: '2-digit', 
                        hour12: false 
                    };
                    const formatter = new Intl.DateTimeFormat('en-GB', options);
                    const parts = formatter.formatToParts(now).reduce((acc, part) => { 
                        acc[part.type] = part.value; 
                        return acc; 
                    }, {});
                    let datePart;
                    if (fmt === 'YYYY-MM-DD') {
                        datePart = `${parts.year}-${parts.month}-${parts.day}`;
                    } else if (fmt === 'DD-MM-YYYY') {
                        datePart = `${parts.day}-${parts.month}-${parts.year}`;
                    } else {
                        datePart = `${parts.day}/${parts.month}/${parts.year}`;
                    }
                    this.nowPreview = `${datePart} ${parts.hour}:${parts.minute}:${parts.second}`;
                } catch (e) {
                    // ignore
                }
            },

            // Template helpers
            promptActivate(type) {
                if (!this.client.state.templates.length) {
                    this.client.setSectionError('templates', 'Belum ada template diunggah.');
                    return;
                }
                const code = prompt(
                    `Masukkan kode template untuk ${this.client.state.templateLabels[type] || type}`, 
                    this.client.state.activeTemplates[type]?.code || ''
                );
                if (!code) return;
                const tpl = this.client.state.templates.find((t) => (t.code || '').toLowerCase() === code.toLowerCase());
                if (!tpl) {
                    this.client.setSectionError('templates', 'Template tidak ditemukan.');
                    return;
                }
                tpl.type = type;
                this.client.activateTemplate(tpl);
            },

            previewActiveTemplate(type) {
                const tpl = this.client.state.activeTemplates[type];
                if (!tpl) {
                    this.client.setSectionError('templates', 'Tidak ada template aktif untuk tipe ini.');
                    return;
                }
                let targetId = tpl.id;
                if (!targetId && tpl.code) {
                    const match = this.client.state.templates.find((item) => item.code === tpl.code && item.id);
                    if (match) {
                        targetId = match.id;
                        this.client.state.activeTemplates = { 
                            ...this.client.state.activeTemplates, 
                            [type]: this.client.clone(match) 
                        };
                    }
                }
                if (!targetId) {
                    this.client.setSectionError('templates', 'Template aktif belum memiliki file.');
                    return;
                }
                const url = `/api/settings/templates/${targetId}/preview`;
                window.open(url, '_blank');
            },

            canPreviewTemplate(type) {
                const tpl = this.client.state.activeTemplates?.[type];
                if (!tpl) return false;
                if (tpl.id) return true;
                if (!tpl.code) return false;
                return this.client.state.templates.some((item) => item.code === tpl.code && item.id);
            },

            // Wrapper methods to expose client methods with debugging
            testPreview(scope) {
                console.log('🔍 [Alpine Wrapper] testPreview called', { scope });
                console.log('📊 Current preview state:', this.client.state.numberingPreview);
                console.log('⚙️ Current form config:', this.client.state.form.numbering?.[scope]);
                
                const result = this.client.testPreview(scope);
                
                // Log after call initiated
                console.log('▶️ testPreview promise initiated for scope:', scope);
                return result;
            },

            previewPdf() {
                console.log('📄 previewPdf called', { branding: this.client.state.form.branding, pdf: this.client.state.form.pdf });
                return this.client.previewPdf();
            },

            previewTemplate(type) {
                console.log('📝 previewTemplate called', { type, activeTemplate: this.client.state.activeTemplates?.[type] });
                return this.previewActiveTemplate(type);
            },

            // Helper method for displaying preview text with better error handling
            getPreviewText(scope) {
                const value = this.client.state.numberingPreview?.[scope];
                
                // null = error state
                if (value === null) {
                    return '❌ Preview gagal dibuat';
                }
                
                // undefined = not yet tested
                if (value === undefined || value === '') {
                    return 'Click Test Preview';
                }
                
                // Valid preview
                return value;
            },

            formatDocumentTypeValue(type) {
                return formatDocumentType(type);
            },

            formatDocumentTimestamp(value) {
                if (!value) return '-';
                try {
                    return new Intl.DateTimeFormat('id-ID', {
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                    }).format(new Date(value));
                } catch (e) {
                    return value;
                }
            },

            formatDocumentSourceLabel(source) {
                if (!source) return 'Tidak diketahui';
                const match = this.documentSources.find((item) => item.value === source);
                if (match) return match.label;
                return source === 'generated' ? 'Generated' : 'Upload Manual';
            },

            formatDocumentSizeLabel(bytes, fallback = '-') {
                if (typeof bytes !== 'number' || bytes <= 0) return fallback;
                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                const exp = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
                const value = bytes / Math.pow(1024, exp);
                return `${value >= 10 ? value.toFixed(0) : value.toFixed(1)} ${units[exp]}`;
            },

            // Document Templates (unified system) state and methods
            documentTemplateForm: {
                type: '',
                format: 'pdf',
                name: '',
                file: null
            },
            documentTemplateState: {
                templates: [],
                templatesByType: {},
                documentTypes: normalizeDocumentTypes(optionValues.document_types),
                activeTemplateByType: {},
                selectedDocumentType: '',
                selectedTemplateId: '',
            },
            documentTemplateGroups: {},
            documentTemplatesLoading: false,
            documentTemplateUploading: false,
            documentTemplateError: '',
            documentTemplateSuccess: '',
            documentTemplateEditor: {
                ready: false,
                loading: false,
                saving: false,
                name: '',
                format: 'pdf',
                renderEngine: 'dompdf',
                templateId: null,
                previewUrls: { html: '', pdf: '' },
                error: '',
                success: '',
            },
            templateEditorInstance: null,
            templateEditorDirty: false,
            templateEditorInitPromise: null,

            normalizeTemplateResponse(data) {
                const rawTemplates = Array.isArray(data?.data)
                    ? data.data
                    : (Array.isArray(data?.templates) ? data.templates : (Array.isArray(data?.list) ? data.list : []));
                const templates = rawTemplates.filter(Boolean).map((tpl, idx) => {
                    // ✅ Add stable __key for x-for to prevent duplicate key errors
                    if (!tpl.__key) {
                        if (tpl.id) {
                            tpl.__key = String(tpl.id);
                        } else {
                            const type = tpl.type || 'unknown';
                            const format = tpl.format || 'pdf';
                            const version = tpl.version || '1';
                            tpl.__key = `${type}-${format}-${version}-${idx}-${Date.now()}`;
                        }
                    }
                    return tpl;
                });
                const templatesByType = {};
                const activeTemplateByType = {};

                templates.forEach((tpl) => {
                    const key = (tpl.type || '').toLowerCase();
                    if (!key) return;
                    if (!templatesByType[key]) templatesByType[key] = [];
                    templatesByType[key].push(tpl);
                    if (tpl.is_active && tpl.id) {
                        activeTemplateByType[key] = tpl.id;
                    }
                });

                const activeFromResponse = data?.active || data?.active_templates || data?.activeTemplates;
                if (activeFromResponse && typeof activeFromResponse === 'object') {
                    Object.entries(activeFromResponse).forEach(([type, tpl]) => {
                        const key = (type || '').toLowerCase();
                        if (!key) return;
                        if (tpl && typeof tpl === 'object') {
                            const id = tpl.id ?? tpl.template_id ?? tpl.templateId;
                            if (id) activeTemplateByType[key] = id;
                        } else if (tpl) {
                            activeTemplateByType[key] = tpl;
                        }
                    });
                }

                const documentTypes = normalizeDocumentTypes(
                    data?.documentTypes || data?.document_types || data?.types || this.documentTemplateState.documentTypes
                );

                return {
                    templates,
                    templatesByType,
                    documentTypes,
                    activeTemplateByType,
                };
            },

            pickActiveDocumentType() {
                const keys = Object.keys(this.documentTemplateState.activeTemplateByType || {});
                return keys.length ? keys[0] : '';
            },

            async loadDocumentTemplates(options = {}) {
                this.documentTemplatesLoading = true;
                this.documentTemplateError = '';
                try {
                    const response = await fetch('/api/settings/document-templates', {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        credentials: 'same-origin',
                    });
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('API Error:', response.status, errorText);
                        throw new Error(`Gagal memuat template (${response.status})`);
                    }
                    
                    const data = await response.json();
                    console.log('✅ Templates loaded:', data);
                    
                    // Handle both old and new response formats
                    const normalized = this.normalizeTemplateResponse(data);
                    const groups = data.groups || {};

                    // Store groups: penerimaan, pengujian, penyerahan
                    this.documentTemplateGroups = groups;

                    this.documentTemplateState.templates = normalized.templates;
                    this.documentTemplateState.templatesByType = normalized.templatesByType;
                    this.documentTemplateState.documentTypes = normalized.documentTypes;
                    this.documentTemplateState.activeTemplateByType = normalized.activeTemplateByType;

                    console.log('📋 Loaded templates:', this.documentTemplateState.templates.length);
                    console.log('📋 Document types:', this.documentTemplateState.documentTypes.length);

                    await this.syncTemplateSelection(options);
                    await this.refreshTemplatePreview();
                } catch (error) {
                    console.error('Error loading document templates:', error);
                    this.documentTemplateError = error.message || 'Gagal memuat daftar template. Silakan coba lagi.';
                } finally {
                    this.documentTemplatesLoading = false;
                }
            },

            async syncTemplateSelection(options = {}) {
                const {
                    selectedDocumentType = '',
                    selectedTemplateId = '',
                    preserveSelection = true,
                } = options;

                const currentType = this.documentTemplateState.selectedDocumentType;
                const fallbackType =
                    selectedDocumentType ||
                    (preserveSelection ? currentType : '') ||
                    this.pickActiveDocumentType() ||
                    this.documentTemplateState.documentTypes[0]?.code ||
                    '';

                this.documentTemplateState.selectedDocumentType = fallbackType;
                // ✅ Only sync UI state, do NOT auto-create templates
                if (fallbackType) {
                    const key = fallbackType.toLowerCase();
                    const templatesForType = this.getTemplatesForEditor(fallbackType, { ignoreFilters: true });
                    const activeId = this.documentTemplateState.activeTemplateByType?.[key];
                    const activeTemplate = activeId
                        ? templatesForType.find((tpl) => String(tpl.id) === String(activeId))
                        : null;

                    let targetId = selectedTemplateId || (preserveSelection ? this.documentTemplateState.selectedTemplateId : '');
                    if (targetId && !this.findTemplateById(targetId, fallbackType)) {
                        targetId = '';
                    }

                    if (!targetId && activeTemplate?.id) {
                        targetId = activeTemplate.id;
                    }

                    if (!targetId && templatesForType.length) {
                        const first = templatesForType.find((tpl) => !tpl.is_draft) || templatesForType[0];
                        targetId = first?.id ?? '';
                    }

                    this.documentTemplateState.selectedTemplateId = targetId ? String(targetId) : '';
                    console.log('✅ Template selection synced:', { type: fallbackType, templateId: targetId });
                }
            },

            async uploadDocumentTemplate() {
                if (!this.documentTemplateForm.type || !this.documentTemplateForm.name || !this.documentTemplateForm.file) {
                    this.documentTemplateError = 'Please fill all required fields';
                    return;
                }

                this.documentTemplateUploading = true;
                this.documentTemplateError = '';
                this.documentTemplateSuccess = '';

                try {
                    const formData = new FormData();
                    formData.append('type', this.documentTemplateForm.type);
                    formData.append('format', this.documentTemplateForm.format);
                    formData.append('name', this.documentTemplateForm.name);
                    formData.append('file', this.documentTemplateForm.file);

                    const response = await fetch('/api/settings/document-templates/upload', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        const error = await response.json();
                        throw new Error(error.message || 'Upload failed');
                    }

                    this.documentTemplateSuccess = 'Template uploaded successfully!';
                    
                    // Reset form
                    this.documentTemplateForm = {
                        type: '',
                        format: 'pdf',
                        name: '',
                        file: null
                    };
                    if (this.$refs.documentTemplateFile) {
                        this.$refs.documentTemplateFile.value = '';
                    }

                    // Reload templates list
                    await this.loadDocumentTemplates();

                    // Clear success message after 3 seconds
                    setTimeout(() => {
                        this.documentTemplateSuccess = '';
                    }, 3000);
                } catch (error) {
                    console.error('Error uploading template:', error);
                    this.documentTemplateError = error.message;
                } finally {
                    this.documentTemplateUploading = false;
                }
            },

            getTemplatesForEditor(type = null, options = {}) {
                const { ignoreFilters = false } = options;
                const targetType = type ?? this.documentTemplateState.selectedDocumentType;
                if (!targetType) {
                    return [];
                }
                const key = targetType.toLowerCase();
                const templates = this.documentTemplateState.templatesByType[key] || [];

                if (ignoreFilters) {
                    return templates;
                }

                const format = this.documentTemplateEditor.format;
                const engine = this.documentTemplateEditor.renderEngine;
                const selectedId = this.documentTemplateState.selectedTemplateId;
                return templates.filter((tpl) => {
                    if (tpl.is_draft) return true;
                    if (selectedId && String(tpl.id) === String(selectedId)) return true;
                    if (format && tpl.format && tpl.format !== format) return false;
                    if (engine && tpl.render_engine && tpl.render_engine !== engine) return false;
                    return !!tpl.id;
                });
            },

            findTemplateById(templateId, type = null) {
                if (!templateId) return null;
                const targetType = type ?? this.documentTemplateState.selectedDocumentType;
                const list = targetType
                    ? (this.documentTemplateState.templatesByType[targetType.toLowerCase()] || [])
                    : this.documentTemplateState.templates;
                return list.find((tpl) => String(tpl.id) === String(templateId)) || null;
            },

            getDocumentTypeMeta(type) {
                return (this.documentTemplateState.documentTypes || []).find((option) => option.code === type || option.value === type);
            },

            templateEditorFormats() {
                const meta = this.getDocumentTypeMeta(this.documentTemplateState.selectedDocumentType);
                if (meta?.supportedFormats?.length) {
                    return meta.supportedFormats;
                }
                return ['pdf', 'html'];
            },

            formatTemplateOption(tpl) {
                const activeId = this.documentTemplateState.activeTemplateByType?.[(tpl.type || '').toLowerCase()];
                const badge = tpl.is_draft ? 'Draft' : (tpl.id && String(tpl.id) === String(activeId) ? 'Aktif' : '');
                const versionLabel = tpl.version ? `v${tpl.version}` : (tpl.is_draft ? 'Draft' : '');
                const suffix = [versionLabel, badge].filter(Boolean).join(' • ');
                return suffix ? `${tpl.name} • ${suffix}` : tpl.name;
            },

            getModalTemplates() {
                const type = this.templateEditorModal.documentType;
                if (!type) return [];
                const templates = this.getTemplatesForEditor(type, { ignoreFilters: true });
                // ✅ Always return array, never undefined/null
                return Array.isArray(templates) ? templates : [];
            },

            async onModalTemplateChange() {
                const templateId = this.templateEditorModal.id;
                if (!templateId) return;
                const tpl = this.findTemplateById(templateId, this.templateEditorModal.documentType);
                if (tpl) {
                    this.templateEditorModal.tpl = tpl;
                    this.templateEditorModal.name = tpl.name || this.templateEditorModal.name;
                    this.templateEditorModal.format = tpl.format || this.templateEditorModal.format;
                    this.templateEditorModal.engine = tpl.render_engine || this.templateEditorModal.engine;
                    this.templateEditorModal.meta = {
                        doc_type: tpl.type || this.templateEditorModal.documentType || '',
                        version: tpl.version || '',
                        status: tpl.status || (tpl.is_active ? 'active' : 'inactive'),
                        is_active: !!tpl.is_active,
                    };
                }
                await this.loadTemplate(templateId);
            },

            removeDraftsForType(type) {
                const key = (type || '').toLowerCase();
                const list = this.documentTemplateState.templatesByType[key] || [];
                const draftIds = list.filter((tpl) => tpl.is_draft).map((tpl) => String(tpl.id));

                this.documentTemplateState.templatesByType[key] = list.filter((tpl) => !tpl.is_draft);
                if (draftIds.length) {
                    this.documentTemplateState.templates = this.documentTemplateState.templates.filter(
                        (tpl) => !draftIds.includes(String(tpl.id))
                    );
                }
            },

            async onDocumentTypeChange(options = {}) {
                const { selectedTemplateId = '', preserveSelection = true, autoCreateIfEmpty = false } = options;
                const type = this.documentTemplateState.selectedDocumentType;
                if (!type) return;

                const key = type.toLowerCase();
                const meta = this.getDocumentTypeMeta(type);
                const templatesForType = this.getTemplatesForEditor(type, { ignoreFilters: true });
                const activeId = this.documentTemplateState.activeTemplateByType?.[key];
                const activeTemplate = activeId
                    ? templatesForType.find((tpl) => String(tpl.id) === String(activeId))
                    : null;

                if (activeTemplate?.format) {
                    this.documentTemplateEditor.format = activeTemplate.format;
                } else if (meta?.defaultFormat) {
                    this.documentTemplateEditor.format = meta.defaultFormat;
                }

                if (activeTemplate?.render_engine) {
                    this.documentTemplateEditor.renderEngine = activeTemplate.render_engine;
                }

                let targetId = selectedTemplateId || (preserveSelection ? this.documentTemplateState.selectedTemplateId : '');
                if (targetId && !this.findTemplateById(targetId, type)) {
                    targetId = '';
                }

                if (!targetId && activeTemplate?.id) {
                    targetId = activeTemplate.id;
                }

                if (!targetId && templatesForType.length) {
                    const first = templatesForType.find((tpl) => !tpl.is_draft) || templatesForType[0];
                    targetId = first?.id ?? '';
                }

                this.documentTemplateState.selectedTemplateId = targetId ? String(targetId) : '';
                
                // ✅ Only create new template if explicitly requested (e.g. user clicks "+ New Template")
                // Do NOT auto-create during load/sync operations
                if (this.documentTemplateState.selectedTemplateId) {
                    await this.onTemplateChange();
                } else if (autoCreateIfEmpty) {
                    console.log('⚠️ No templates found, auto-creating one...');
                    await this.createNewTemplate({ addToList: true });
                } else {
                    console.log('ℹ️ No template selected, waiting for user action');
                }
                await this.refreshTemplatePreview();
            },

            async onTemplateChange() {
                const rawId = this.documentTemplateState.selectedTemplateId;
                if (!rawId) {
                    console.log('ℹ️ No template selected');
                    return;
                }

                const template = this.findTemplateById(rawId);
                if (!template) {
                    console.log('⚠️ Template not found:', rawId);
                    return;
                }

                await this.editDocumentTemplate(template);
            },

            async onTemplateSelected() {
                await this.onTemplateChange();
                await this.refreshTemplatePreview();
            },

            async editDocumentTemplate(tpl) {
                await this.openTemplateEditorModal(tpl);
            },

            loadTemplateIntoEditor({ html = '', css = '' } = {}) {
                if (!this.templateEditorInstance) return;
                import('./template-editor.js').then(({ isAlive, refreshTemplateEditor }) => {
                    if (!isAlive(this.templateEditorInstance)) {
                        this.templateEditorInstance = null;
                        return;
                    }
                    this.templateEditorInstance.setComponents(html);
                    this.templateEditorInstance.setStyle(css || '');
                    refreshTemplateEditor('modal');
                }).catch(() => {});
                this.templateEditorDirty = false;
            },

            async openTemplateEditorModal(tpl) {
                if (!tpl) return;
                this.templateEditorModal.open = true;
                this.templateEditorModal.mode = 'edit';
                this.templateEditorModal.loading = true;
                this.templateEditorModal.error = '';
                this.templateEditorModal.tpl = tpl;
                this.templateEditorModal.documentType = tpl.type || this.documentTemplateState.documentTypes[0]?.code || '';
                this.templateEditorModal.format = tpl.format || 'html';
                this.templateEditorModal.engine = tpl.render_engine || 'dompdf';
                this.templateEditorModal.name = tpl.name || '';
                this.templateEditorModal.id = tpl.id ?? null;
                this.templateEditorModal.meta = {
                    doc_type: tpl.type || '',
                    version: tpl.version || '',
                    status: tpl.status || (tpl.is_active ? 'active' : 'inactive'),
                    is_active: !!tpl.is_active,
                };
                this.templateEditorModal.previewHtml = '';
                this.templateEditorModal.previewPdfUrl = '';
                this.revokeModalPreviewUrl();

                if (this.activeSection !== 'templates') {
                    this.activeSection = 'templates';
                }

                // Wait for Alpine x-show transition to complete and container to be truly visible
                await this.$nextTick();
                await new Promise(resolve => requestAnimationFrame(resolve));
                await new Promise(resolve => requestAnimationFrame(resolve));

                // Wait for container to be visible (x-show renders it)
                const container = this.$refs.templateEditorModalCanvas;
                
                if (!container) {
                    this.templateEditorModal.loading = false;
                    this.templateEditorModal.error = 'Container editor tidak ditemukan.';
                    console.error('Template editor container not found in DOM');
                    return;
                }

                // Wait for container to be truly visible (not display:none)
                let attempts = 0;
                while (container.offsetParent === null && attempts < 20) {
                    await new Promise(resolve => setTimeout(resolve, 50));
                    attempts++;
                }
                
                if (container.offsetParent === null) {
                    this.templateEditorModal.loading = false;
                    this.templateEditorModal.error = 'Container editor belum visible setelah ' + attempts + ' attempts.';
                    console.error('Template editor container still not visible after waiting');
                    return;
                }

                const editor = await this.ensureTemplateEditorInModal();
                if (!editor) {
                    this.templateEditorModal.loading = false;
                    return;
                }

                if (typeof editor.render === 'function') {
                    editor.render();
                }
                await this.$nextTick();
                requestAnimationFrame(() => {
                    if (typeof editor.refresh === 'function') {
                        editor.refresh();
                    }
                });

                await this.loadTemplate(this.templateEditorModal.id);
                this.templateEditorModal.loading = false;
            },

            closeTemplateEditorModal() {
                this.templateEditorModal.open = false;
                this.templateEditorModal.mode = 'edit';
                this.templateEditorModal.editorReady = false;
                this.templateEditorModal.loading = false;
                this.templateEditorModal.error = '';
                this.templateEditorModal.tpl = null;
                this.templateEditorModal.previewHtml = '';
                this.templateEditorModal.previewPdfUrl = '';
                this.templateEditorModal.previewLoading = false;
                this.templateEditorModal.meta = {
                    doc_type: '',
                    version: '',
                    status: '',
                    is_active: false,
                };
                this.revokeModalPreviewUrl();
            },

            async ensureTemplateEditorInModal() {
                if (!this.templateEditorModal.open || this.templateEditorModal.mode !== 'edit') {
                    return null;
                }

                if (this.templateEditorInstance) {
                    const { isAlive, refreshTemplateEditor, destroyTemplateEditor, waitForEditorLoad } = await import('./template-editor.js');
                    if (!isAlive(this.templateEditorInstance)) {
                        destroyTemplateEditor('modal');
                        this.templateEditorInstance = null;
                    } else {
                        await waitForEditorLoad('modal');
                        this.templateEditorModal.editorReady = true;
                        refreshTemplateEditor('modal');
                        return this.templateEditorInstance;
                    }
                }

                await this.$nextTick();

                const container = this.$refs.templateEditorModalCanvas;

                if (!container) {
                    this.templateEditorModal.error = 'Container editor tidak ditemukan.';
                    console.error('Template editor container not found in ensureTemplateEditorInModal');
                    return null;
                }

                // Wait for visibility
                let attempts = 0;
                while (container.offsetParent === null && attempts < 10) {
                    await new Promise(resolve => setTimeout(resolve, 50));
                    attempts++;
                }

                if (container.offsetParent === null) {
                    this.templateEditorModal.error = 'Container editor belum visible.';
                    console.error('Template editor container still hidden in ensureTemplateEditorInModal');
                    return null;
                }

                this.templateEditorModal.loading = true;

                try {
                    const { createTemplateEditor, waitForEditorLoad, isAlive } = await import('./template-editor.js');
                    this.templateEditorInstance = await createTemplateEditor({
                        key: 'modal',
                        container,
                        options: {
                            height: '100%',
                            blocksContainer: this.$refs.templateEditorModalBlocks,
                            stylesContainer: this.$refs.templateEditorModalStyles,
                            traitsContainer: this.$refs.templateEditorModalTraits,
                            layersContainer: this.$refs.templateEditorModalLayers,
                            onChange: () => {
                                this.templateEditorDirty = true;
                            },
                        },
                    });
                    await waitForEditorLoad('modal');
                    if (!isAlive(this.templateEditorInstance)) {
                        this.templateEditorInstance = null;
                        this.templateEditorModal.error = 'Editor belum siap.';
                        return null;
                    }
                    this.templateEditorModal.editorReady = true;
                    return this.templateEditorInstance;
                } catch (error) {
                    this.templateEditorModal.error = `Gagal memuat editor: ${error.message}`;
                    return null;
                } finally {
                    this.templateEditorModal.loading = false;
                }
            },

            async fetchTemplateDetail(templateId) {
                console.log('🌐 Fetching template detail:', `/api/settings/document-templates/${templateId}`);

                const response = await fetch(`/api/settings/document-templates/${templateId}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                console.log('📡 Response status:', response.status, response.statusText);

                const contentType = response.headers.get('content-type') || '';
                console.log('📄 Content-Type:', contentType);

                if (!contentType.includes('application/json')) {
                    const htmlSnippet = await response.text();
                    const preview = htmlSnippet.substring(0, 200);
                    alert(`API returned HTML (redirect/auth/route mismatch)\n\n${preview}`);
                    throw new Error('API returned HTML (redirect/auth/route mismatch)');
                }

                if (!response.ok) {
                    let errorMessage = 'Gagal memuat detail template';
                    try {
                        const error = await response.json();
                        errorMessage = error.message || errorMessage;
                    } catch (e) {
                        const text = await response.text().catch(() => '');
                        console.error('❌ Non-JSON error response:', text.substring(0, 200));
                        errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                    }
                    throw new Error(errorMessage);
                }

                const data = await response.json();
                console.log('✅ Template detail fetched successfully');
                return data;
            },

            clearEditor(editor) {
                if (!editor) return;
                try {
                    editor.DomComponents?.clear();
                } catch (e) {
                    console.warn('Could not clear components:', e);
                }
                try {
                    editor.CssComposer?.clear();
                } catch (e) {
                    console.warn('Could not clear styles:', e);
                }
            },

            parseHtmlDocument(rawHtml = '') {
                if (!rawHtml || typeof rawHtml !== 'string') {
                    return { bodyHtml: '', cssText: '', title: '' };
                }

                try {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(rawHtml, 'text/html');
                    const bodyHtml = doc.body?.innerHTML || rawHtml;
                    const cssText = Array.from(doc.querySelectorAll('style'))
                        .map((styleEl) => styleEl.textContent || '')
                        .join('\n');
                    const title = doc.title || '';
                    return { bodyHtml, cssText, title };
                } catch (e) {
                    console.warn('Failed to parse HTML, using as-is:', e);
                    return { bodyHtml: rawHtml, cssText: '', title: '' };
                }
            },

            setComponentsFromHtmlDocument(rawHtml = '', css = '', editor = null) {
                const targetEditor = editor || this.templateEditorInstance;
                if (!targetEditor) return;

                const { bodyHtml, cssText } = this.parseHtmlDocument(rawHtml);
                targetEditor.setComponents(bodyHtml || '<div></div>');

                const appliedCss = css && css.trim().length ? css : cssText;
                if (appliedCss && typeof targetEditor.setStyle === 'function') {
                    targetEditor.setStyle(appliedCss);
                }

                if (typeof targetEditor.refresh === 'function') {
                    targetEditor.refresh();
                }
            },

            async loadTemplate(templateId) {
                if (!templateId) {
                    console.warn('❌ loadTemplate: No template id provided');
                    return;
                }

                console.log('📄 Loading template to editor:', { id: templateId });
                this.templateEditorModal.error = '';

                let editor = this.templateEditorInstance;
                if (!editor) {
                    editor = await this.ensureTemplateEditorInModal();
                }

                if (!editor) {
                    console.error('❌ Failed to get editor instance');
                    return;
                }

                const { isAlive, refreshTemplateEditor, waitForEditorLoad } = await import('./template-editor.js');
                await waitForEditorLoad('modal');
                if (!isAlive(editor)) {
                    console.error('❌ Editor is not alive');
                    return;
                }

                let detail = null;
                try {
                    detail = await this.fetchTemplateDetail(templateId);
                } catch (error) {
                    this.templateEditorModal.error = error.message;
                    return;
                }

                this.templateEditorModal.name = detail.name || this.templateEditorModal.name;
                this.templateEditorModal.format = detail.format || this.templateEditorModal.format;
                this.templateEditorModal.engine = detail.render_engine || this.templateEditorModal.engine;
                this.templateEditorModal.id = detail.id ?? this.templateEditorModal.id;
                this.templateEditorModal.documentType = detail.type || this.templateEditorModal.documentType;
                this.templateEditorModal.meta = {
                    doc_type: detail.type || this.templateEditorModal.documentType || '',
                    version: detail.version || '',
                    status: detail.status || (detail.is_active ? 'active' : 'inactive'),
                    is_active: !!detail.is_active,
                };

                const htmlRaw = detail.html ?? detail.content_html ?? '';
                const cssRaw = detail.css ?? detail.content_css ?? '';

                console.log('🎨 Loading content into template editor:', {
                    htmlLength: htmlRaw?.length || 0,
                    cssLength: cssRaw?.length || 0,
                });

                this.clearEditor(editor);
                this.setComponentsFromHtmlDocument(htmlRaw, cssRaw, editor);

                refreshTemplateEditor('modal');
                if (typeof editor.refresh === 'function') {
                    editor.refresh();
                }
                this.templateEditorDirty = false;
                console.log('✅ Template loaded successfully');
            },

            normalizeTemplateHtml(html, css) {
                if (!html || typeof html !== 'string') {
                    return { html: '', css: css || '' };
                }

                // If HTML contains <html>, <head>, <body> tags, extract body content
                const hasHtmlTag = /<html[^>]*>/i.test(html);
                const hasBodyTag = /<body[^>]*>/i.test(html);
                
                if (hasHtmlTag || hasBodyTag) {
                    console.log('🔧 Normalizing HTML with <html>/<body> tags...');
                    
                    try {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Extract body content
                        const bodyContent = doc.body ? doc.body.innerHTML : html;
                        
                        // Extract <style> tags from head if CSS is empty
                        let finalCss = css || '';
                        if (!finalCss) {
                            const styleTags = doc.querySelectorAll('style');
                            const styles = Array.from(styleTags).map(s => s.textContent).join('\n');
                            if (styles) {
                                finalCss = styles;
                                console.log('📝 Extracted CSS from <style> tags:', styles.length, 'chars');
                            }
                        }
                        
                        return { html: bodyContent, css: finalCss };
                    } catch (e) {
                        console.warn('Failed to parse HTML, using as-is:', e);
                        return { html, css: css || '' };
                    }
                }
                
                return { html, css: css || '' };
            },

            revokeModalPreviewUrl() {
                if (this.templateEditorModal.previewObjectUrl) {
                    URL.revokeObjectURL(this.templateEditorModal.previewObjectUrl);
                    this.templateEditorModal.previewObjectUrl = null;
                }
            },

            async saveTemplateFromEditor() {
                const editor = await this.ensureTemplateEditorInModal();
                if (!editor) {
                    this.templateEditorModal.error = 'Editor belum siap.';
                    return;
                }

                const payload = {
                    type: this.templateEditorModal.documentType,
                    format: this.templateEditorModal.format,
                    name: this.templateEditorModal.name,
                    render_engine: this.templateEditorModal.engine,
                    content_html: editor.getHtml(),
                    content_css: editor.getCss(),
                };

                if (!payload.type || !payload.name) {
                    this.templateEditorModal.error = 'Lengkapi tipe dan nama template.';
                    return;
                }

                const templateId = this.templateEditorModal.id;
                const url = templateId
                    ? `/api/settings/document-templates/${templateId}`
                    : '/api/settings/document-templates';
                const method = templateId ? 'PUT' : 'POST';

                this.templateEditorModal.saving = true;
                this.templateEditorModal.error = '';

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || 'Gagal menyimpan template');
                    }

                    const template = data.template || data;
                    this.templateEditorModal.id = template.id;
                    this.templateEditorModal.name = template.name || this.templateEditorModal.name;
                    await this.loadDocumentTemplates({
                        preserveSelection: true,
                        selectedDocumentType: template.type || payload.type,
                        selectedTemplateId: template.id,
                    });
                } catch (error) {
                    this.templateEditorModal.error = error.message;
                } finally {
                    this.templateEditorModal.saving = false;
                }
            },

            async previewFromModal(format) {
                const type = this.templateEditorModal.documentType;
                if (!type) {
                    this.templateEditorModal.error = 'Pilih tipe dokumen terlebih dahulu.';
                    return;
                }
                const templateId = this.templateEditorModal.id;
                this.templateEditorModal.format = format;
                this.templateEditorModal.previewLoading = true;
                this.templateEditorModal.error = '';

                // ✅ Map type to backend slug
                const typeSlug = this.mapTypeToSlug(type);
                if (!typeSlug) {
                    this.templateEditorModal.error = `Tipe dokumen tidak dikenal: ${type}`;
                    console.error('❌ Unknown document type for preview:', type);
                    this.templateEditorModal.previewLoading = false;
                    return;
                }

                // TODO: backend should support `template_id` to preview non-active templates.
                const query = templateId ? `?template_id=${encodeURIComponent(templateId)}` : '';
                const url = `/api/settings/document-templates/preview/${typeSlug}/${format}${query}`;

                try {
                    if (format === 'html') {
                        const response = await fetch(url, {
                            headers: { Accept: 'text/html' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            throw new Error(`Gagal memuat preview (${response.status})`);
                        }
                        const html = await response.text();
                        this.templateEditorModal.previewHtml = html;
                        this.revokeModalPreviewUrl();
                        this.templateEditorModal.previewPdfUrl = '';
                    } else if (format === 'pdf') {
                        const response = await fetch(url, {
                            headers: { Accept: 'application/pdf' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            throw new Error(`Gagal memuat preview (${response.status})`);
                        }
                        const blob = await response.blob();
                        this.revokeModalPreviewUrl();
                        this.templateEditorModal.previewObjectUrl = URL.createObjectURL(blob);
                        this.templateEditorModal.previewPdfUrl = this.templateEditorModal.previewObjectUrl;
                        this.templateEditorModal.previewHtml = '';
                    }
                } catch (error) {
                    this.templateEditorModal.error = error.message || 'Gagal memuat preview.';
                } finally {
                    this.templateEditorModal.previewLoading = false;
                }
            },

            async activateFromModal() {
                const templateId = this.templateEditorModal.id;
                if (!templateId) {
                    this.templateEditorModal.error = 'Template belum tersimpan.';
                    return;
                }
                await this.activateDocumentTemplate(templateId);
            },

            revokePreviewUrl() {
                if (this.previewObjectUrl) {
                    URL.revokeObjectURL(this.previewObjectUrl);
                    this.previewObjectUrl = null;
                }
            },

            async refreshTemplatePreview() {
                const docType = this.documentTemplateState.selectedDocumentType;
                const format = this.documentTemplateEditor.format || 'html';
                if (!docType || !format) {
                    return;
                }

                this.previewLoading = true;
                this.documentTemplateEditor.error = '';

                // ✅ Map type to backend slug
                const typeSlug = this.mapTypeToSlug(docType);
                if (!typeSlug) {
                    console.error('❌ Unknown document type for preview:', docType);
                    this.documentTemplateEditor.error = `Tipe dokumen tidak dikenal: ${docType}`;
                    this.previewLoading = false;
                    return;
                }

                const templateId = this.documentTemplateState.selectedTemplateId;
                // TODO: backend should support `template_id` to preview non-active templates.
                const query = templateId ? `?template_id=${encodeURIComponent(templateId)}` : '';
                const url = `/api/settings/document-templates/preview/${typeSlug}/${format}${query}`;

                try {
                    if (format === 'html') {
                        const response = await fetch(url, {
                            headers: { Accept: 'text/html' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            throw new Error(`Gagal memuat preview (${response.status})`);
                        }
                        const html = await response.text();
                        this.previewHtml = html;
                        this.revokePreviewUrl();
                        this.previewPdfUrl = '';

                        if (this.templateEditorInstance) {
                            this.templateEditorInstance.setComponents(html);
                            this.templateEditorInstance.refresh();
                        }
                    } else if (format === 'pdf') {
                        const response = await fetch(url, {
                            headers: { Accept: 'application/pdf' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            throw new Error(`Gagal memuat preview (${response.status})`);
                        }
                        const blob = await response.blob();
                        this.revokePreviewUrl();
                        this.previewObjectUrl = URL.createObjectURL(blob);
                        this.previewPdfUrl = this.previewObjectUrl;
                        this.previewHtml = '';
                    }
                } catch (error) {
                    this.documentTemplateEditor.error = error.message || 'Gagal memuat preview template.';
                } finally {
                    this.previewLoading = false;
                }
            },

            async refreshTemplatePreviewFor({ type, format, templateId = null } = {}) {
                if (!type || !format) return;
                const previousType = this.documentTemplateState.selectedDocumentType;
                const previousFormat = this.documentTemplateEditor.format;
                const previousTemplateId = this.documentTemplateState.selectedTemplateId;

                this.documentTemplateState.selectedDocumentType = type;
                this.documentTemplateEditor.format = format;
                if (templateId) {
                    this.documentTemplateState.selectedTemplateId = templateId.toString();
                }

                await this.refreshTemplatePreview();

                // Restore selection if preview is for list-only actions.
                this.documentTemplateState.selectedDocumentType = previousType;
                this.documentTemplateEditor.format = previousFormat;
                this.documentTemplateState.selectedTemplateId = previousTemplateId;
            },

            async ensureTemplateEditor() {
                if (!this.templateEditorModal.open) {
                    return null;
                }
                // Return existing editor if already initialized
                if (this.templateEditorInstance) {
                    this.documentTemplateEditor.ready = true;
                    // Refresh editor to ensure proper layout
                    this.refreshTemplateEditor();
                    console.log('♻️ Reusing existing template editor');
                    return this.templateEditorInstance;
                }

                // Wait for pending initialization
                if (this.templateEditorInitPromise) {
                    console.log('⏳ Waiting for pending editor initialization...');
                    await this.templateEditorInitPromise;
                    return this.templateEditorInstance;
                }

                // ✅ Check if templates section is active
                if (this.activeSection !== 'templates') {
                    const errorMsg = 'Section Template Dokumen belum aktif. Aktifkan section terlebih dahulu.';
                    console.error('❌', errorMsg);
                    this.documentTemplateEditor.error = errorMsg;
                    return null;
                }

                // Wait for x-show to keep the editor container mounted before init.
                await this.$nextTick();
                
                // ✅ Try multiple ways to get container
                const container = this.$refs.documentTemplateEditorCanvas;
                
                if (!container) {
                    const errorMsg = 'Container element tidak ditemukan. Pastikan section Template Dokumen aktif.';
                    console.error('❌', errorMsg, {
                        activeSection: this.activeSection,
                        hasRefs: !!this.$refs.documentTemplateEditorCanvas,
                    });
                    this.documentTemplateEditor.error = errorMsg;
                    return null;
                }

                // Wait for container to be visible (using x-show)
                if (container.offsetParent === null) {
                    console.warn('⚠️ Container hidden, waiting...');
                    // Container is hidden, wait a bit for x-show transition
                    await new Promise(resolve => setTimeout(resolve, 150));
                    
                    // Check again after delay
                    if (container.offsetParent === null) {
                        const errorMsg = 'Container harus visible sebelum init editor.';
                        console.error('❌', errorMsg, 'offsetParent:', container.offsetParent);
                        this.documentTemplateEditor.error = errorMsg;
                        return null;
                    }
                }
                
                console.log('✅ Container found and visible:', {
                    element: container.tagName,
                    id: container.id,
                    clientHeight: container.clientHeight,
                    offsetParent: !!container.offsetParent
                });

                this.documentTemplateEditor.loading = true;
                this.documentTemplateEditor.error = '';
                console.log('🚀 Starting template editor initialization...');

                this.templateEditorInitPromise = import('./template-editor.js')
                    .then(({ createTemplateEditor }) => {
                        console.log('📦 Template editor module loaded');
                        return createTemplateEditor({
                            key: 'inline',
                            container,
                            options: {
                                blocksContainer: this.$refs.documentTemplateBlocks,
                                stylesContainer: this.$refs.documentTemplateStyles,
                                traitsContainer: this.$refs.documentTemplateTraits,
                                layersContainer: this.$refs.documentTemplateLayers,
                                onChange: () => {
                                    this.templateEditorDirty = true;
                                },
                            },
                        });
                    })
                    .then((editor) => {
                        this.templateEditorInstance = editor;
                        this.documentTemplateEditor.ready = true;
                        this.templateEditorDirty = false;
                        console.log('✅ Template editor ready');
                        return editor;
                    })
                    .catch((error) => {
                        console.error('❌ Failed to initialize template editor', error);
                        this.documentTemplateEditor.error = 'Gagal memuat editor template: ' + error.message;
                        return null;
                    })
                    .finally(() => {
                        this.documentTemplateEditor.loading = false;
                        this.templateEditorInitPromise = null;
                    });

                await this.templateEditorInitPromise;
                return this.templateEditorInstance;
            },

            refreshTemplateEditor() {
                if (!this.templateEditorInstance) return;
                import('./template-editor.js').then(({ isAlive, refreshTemplateEditor }) => {
                    if (!isAlive(this.templateEditorInstance)) {
                        this.templateEditorInstance = null;
                        return;
                    }
                    refreshTemplateEditor('modal');
                    console.log('Template editor refreshed');
                }).catch((error) => {
                    console.warn('Failed to refresh template editor:', error);
                });
            },

            setEditorContent(html = '', css = '') {
                if (!this.templateEditorInstance) {
                    return;
                }
                const defaultHtml = html && html.trim().length
                    ? html
                    : '<section class="doc-section"><h2>Judul Dokumen</h2><p>Masukkan konten di sini.</p></section>';
                this.templateEditorInstance.setComponents(defaultHtml);
                this.templateEditorInstance.setStyle(css || '');
                this.templateEditorDirty = false;
            },

            async createNewTemplate(options = {}) {
                const { addToList = true } = options;
                if (!this.documentTemplateState.selectedDocumentType && this.documentTemplateState.documentTypes.length) {
                    this.documentTemplateState.selectedDocumentType = this.documentTemplateState.documentTypes[0].code;
                }
                const type = this.documentTemplateState.selectedDocumentType;
                if (!type) {
                    this.documentTemplateEditor.error = 'Pilih tipe dokumen terlebih dahulu.';
                    return;
                }

                const key = type.toLowerCase();
                this.removeDraftsForType(key);

                const meta = this.getDocumentTypeMeta(type);
                const format = meta?.defaultFormat ?? this.documentTemplateEditor.format ?? 'pdf';
                const renderEngine = this.documentTemplateEditor.renderEngine || 'dompdf';
                const draftId = `draft-${Date.now()}`;
                const draft = {
                    id: draftId,
                    name: `${formatDocumentType(type)} Draft`,
                    type,
                    format,
                    render_engine: renderEngine,
                    version: 'draft',
                    is_draft: true,
                };

                if (addToList) {
                    this.documentTemplateState.templates.push(draft);
                    const existing = this.documentTemplateState.templatesByType[key] || [];
                    this.documentTemplateState.templatesByType[key] = [draft, ...existing.filter((tpl) => !tpl.is_draft)];
                    this.documentTemplateState.selectedTemplateId = draftId;
                }

                await this.startNewEditorTemplate(draft);
            },

            async startNewEditorTemplate(draft = null) {
                console.log('📝 Starting new template creation...');
                try {
                    // ✅ CRITICAL: Ensure section is active first so container exists
                    if (this.activeSection !== 'templates') {
                        console.log('🔄 Activating templates section...');
                        this.activeSection = 'templates';
                        // Wait for Alpine to update DOM
                        await this.$nextTick();
                        // Additional delay for x-show transition
                        await new Promise(resolve => setTimeout(resolve, 50));
                    }
                    
                    const editor = await this.ensureTemplateEditor();
                    if (!editor) {
                        console.error('❌ Failed to ensure editor for new template');
                        return;
                    }
                    
                    const type = this.documentTemplateState.selectedDocumentType;
                    const typeLabel = formatDocumentType(type);
                    const meta = this.getDocumentTypeMeta(type);
                    this.documentTemplateEditor.templateId = null;
                    this.documentTemplateEditor.name = draft?.name || `${typeLabel} Draft`;
                    this.documentTemplateEditor.renderEngine = draft?.render_engine || this.documentTemplateEditor.renderEngine || 'dompdf';
                    this.documentTemplateEditor.format = draft?.format || meta?.defaultFormat || 'pdf';
                    this.documentTemplateEditor.previewUrls = { html: '', pdf: '' };
                    this.documentTemplateEditor.error = '';
                    this.documentTemplateEditor.success = '';
                    this.setEditorContent('', '');
                    
                    console.log('✅ New template draft ready');
                } catch (error) {
                    console.error('❌ Error starting new template:', error);
                    this.documentTemplateEditor.error = 'Gagal memulai template baru: ' + error.message;
                }
            },

            async loadTemplateDetail(templateId) {
                if (!templateId) {
                    return;
                }
                
                // ✅ CRITICAL: Ensure section is active first so container exists
                if (this.activeSection !== 'templates') {
                    console.log('🔄 Activating templates section for template load...');
                    this.activeSection = 'templates';
                    // Wait for Alpine to update DOM
                    await this.$nextTick();
                    // Additional delay for x-show transition
                    await new Promise(resolve => setTimeout(resolve, 50));
                }
                
                await this.ensureTemplateEditor();
                this.documentTemplateEditor.loading = true;
                this.documentTemplateEditor.error = '';
                try {
                    const response = await fetch(`/api/settings/document-templates/${templateId}`, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        throw new Error(error.message || 'Gagal memuat detail template');
                    }

                    const data = await response.json();
                    this.documentTemplateEditor.templateId = data.id;
                    this.documentTemplateEditor.name = data.name;
                    this.documentTemplateEditor.format = data.format || this.documentTemplateEditor.format || 'pdf';
                    this.documentTemplateEditor.renderEngine = data.render_engine || this.documentTemplateEditor.renderEngine || 'dompdf';
                    this.documentTemplateEditor.previewUrls = data.preview_urls || { html: '', pdf: '' };
                    this.documentTemplateState.selectedTemplateId = data.id?.toString() ?? '';
                    this.setEditorContent(data.content_html || '', data.content_css || '');
                    this.templateEditorDirty = false;
                } catch (error) {
                    console.error('Failed to load template detail', error);
                    this.documentTemplateEditor.error = error.message;
                } finally {
                    this.documentTemplateEditor.loading = false;
                }
            },

            buildTemplatePayload() {
                if (!this.templateEditorInstance) {
                    return null;
                }

                return {
                    type: this.documentTemplateState.selectedDocumentType,
                    format: this.documentTemplateEditor.format,
                    name: this.documentTemplateEditor.name,
                    render_engine: this.documentTemplateEditor.renderEngine,
                    content_html: this.templateEditorInstance.getHtml(),
                    content_css: this.templateEditorInstance.getCss(),
                };
            },

            async saveEditorTemplate() {
                await this.ensureTemplateEditor();
                const payload = this.buildTemplatePayload();
                if (!payload) {
                    this.documentTemplateEditor.error = 'Editor belum siap.';
                    return;
                }

                const templateId = this.documentTemplateEditor.templateId;
                const url = templateId
                    ? `/api/settings/document-templates/${templateId}`
                    : '/api/settings/document-templates';
                const method = templateId ? 'PUT' : 'POST';

                this.documentTemplateEditor.saving = true;
                this.documentTemplateEditor.error = '';
                this.documentTemplateEditor.success = '';

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(data.message || 'Gagal menyimpan template');
                    }

                    const template = data.template || data;
                    this.documentTemplateEditor.templateId = template.id;
                    this.documentTemplateState.selectedTemplateId = template.id?.toString() ?? '';
                    this.documentTemplateEditor.previewUrls = template.preview_urls ?? {
                        html: `/api/settings/document-templates/${template.id}/preview/html`,
                        pdf: `/api/settings/document-templates/${template.id}/preview/pdf`,
                    };
                    this.documentTemplateEditor.success = data.message || 'Template tersimpan';
                    this.templateEditorDirty = false;

                    await this.loadDocumentTemplates({
                        preserveSelection: true,
                        selectedDocumentType: template.type || payload.type,
                        selectedTemplateId: template.id,
                    });
                    await this.refreshTemplatePreview();
                    setTimeout(() => {
                        this.documentTemplateEditor.success = '';
                    }, 3000);
                } catch (error) {
                    console.error('Failed to save template:', error);
                    this.documentTemplateEditor.error = error.message;
                } finally {
                    this.documentTemplateEditor.saving = false;
                }
            },

            async previewEditorTemplate(kind = 'html') {
                const docType = this.documentTemplateState.selectedDocumentType;
                if (!docType) {
                    this.documentTemplateEditor.error = 'Pilih tipe dokumen terlebih dahulu.';
                    return;
                }

                this.documentTemplateEditor.format = kind;
                await this.refreshTemplatePreview();
            },

            async activateEditorTemplate() {
                const templateId = this.documentTemplateEditor.templateId;
                if (!templateId) {
                    this.documentTemplateEditor.error = 'Tidak ada template yang dipilih.';
                    return;
                }
                await this.activateDocumentTemplate(templateId);
                await this.loadDocumentTemplates({
                    preserveSelection: true,
                    selectedDocumentType: this.documentTemplateState.selectedDocumentType,
                    selectedTemplateId: templateId,
                });
                await this.refreshTemplatePreview();
            },

            mapTypeToSlug(type) {
                // ✅ Map document type labels/codes to backend-accepted slugs
                const typeMap = {
                    'sample_receipt': 'sample_receipt',
                    'request_letter': 'request_letter',
                    'request_letter_receipt': 'request_letter_receipt',
                    'handover_report': 'handover_report',
                    'ba_penerimaan': 'ba_penerimaan',
                    'ba_penyerahan': 'ba_penyerahan',
                    'laporan_hasil_uji': 'lhu',
                    'lhu': 'lhu',
                    'lab_report': 'lab_report',
                    'cover_letter': 'cover_letter',
                    'sample_photo': 'sample_photo',
                    'evidence_photo': 'evidence_photo',
                    'form_preparation': 'form_preparation',
                    'instrument_uv_vis': 'instrument_uv_vis',
                    'instrument_gc_ms': 'instrument_gc_ms',
                    'instrument_lc_ms': 'instrument_lc_ms',
                    'instrument_result': 'instrument_result',
                    'report_receipt': 'report_receipt',
                    'letter_receipt': 'letter_receipt',
                    'sample_handover': 'sample_handover',
                    'test_results': 'test_results',
                    'qr_code': 'qr_code',
                };
                const slug = typeMap[type] || type;
                return slug.toLowerCase();
            },

            async previewDocumentTemplate(type, format, templateId = null) {
                this.templateEditorModal.open = true;
                this.templateEditorModal.mode = 'preview';
                this.templateEditorModal.error = '';
                this.templateEditorModal.documentType = type;
                this.templateEditorModal.format = format;
                this.templateEditorModal.id = templateId;
                this.templateEditorModal.previewHtml = '';
                this.templateEditorModal.previewPdfUrl = '';
                this.revokeModalPreviewUrl();

                await this.$nextTick();
                await this.previewFromModal(format);
            },

            async activateDocumentTemplate(templateId) {
                if (!confirm('Activate this template? It will replace the currently active template.')) {
                    return;
                }

                try {
                    const response = await fetch(`/api/settings/document-templates/${templateId}/activate`, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Activation failed');
                    }

                    this.documentTemplateSuccess = 'Template activated successfully!';
                    await this.loadDocumentTemplates({
                        preserveSelection: true,
                        selectedDocumentType: this.documentTemplateState.selectedDocumentType,
                        selectedTemplateId: templateId,
                    });
                    await this.refreshTemplatePreview();

                    setTimeout(() => {
                        this.documentTemplateSuccess = '';
                    }, 3000);
                } catch (error) {
                    console.error('Error activating template:', error);
                    this.documentTemplateError = error.message;
                }
            },

            destroyTemplateEditor() {
                if (!this.templateEditorInstance) return;
                import('./template-editor.js')
                    .then(({ destroyTemplateEditor }) => {
                        destroyTemplateEditor('modal');
                    })
                    .catch((error) => {
                        console.warn('Error destroying template editor:', error);
                    })
                    .finally(() => {
                        this.templateEditorInstance = null;
                        this.documentTemplateEditor.ready = false;
                        this.revokePreviewUrl();
                    });
            },

            destroy() {
                this.destroyTemplateEditor();
            },
        };
    });
}
