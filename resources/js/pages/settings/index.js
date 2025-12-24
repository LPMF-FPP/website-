/**
 * SettingsClient - Modular JS untuk halaman /settings
 * Menangani semua API requests, state management, dan error handling per section
 */

export class SettingsClient {
    constructor(config = {}) {
        this.api = {
            settings: '/api/settings',
            numberingCurrent: '/api/settings/numbering/current',
            numbering: '/api/settings/numbering',
            numberingPreview: '/api/settings/numbering/preview',
            templates: '/api/settings/templates',
            templateUpload: '/api/settings/templates/upload',
            branding: '/api/settings/branding',
            pdfPreview: '/api/settings/pdf/preview',
            localization: '/api/settings/localization-retention',
            notificationsSecurity: '/api/settings/notifications-security',
            notificationsTest: '/api/settings/notifications/test',
            documents: '/api/settings/documents',
            ...config.api
        };

        this.csrf = config.csrf || document.querySelector('meta[name=csrf-token]')?.content || '';
        this.state = this.initializeState(config);
    }

    initializeState(config) {
        return {
            pageLoading: true,
            loadError: '',
            form: {},
            currentNumbering: { sample_code: '', ba: '', lhu: '', ba_penyerahan: '', tracking: '' },
            currentNumberingLoading: false,
            numberingPreview: { sample_code: '', ba: '', lhu: '', ba_penyerahan: '', tracking: '' },
            previewLoading: { sample_code: false, ba: false, lhu: false, ba_penyerahan: false, tracking: false },
            previewState: { numbering: false, sample_code: false, ba: false, lhu: false, ba_penyerahan: false, tracking: false },
            sectionStatus: {
                numbering: { message: '', intentClass: 'text-primary-600' },
                templates: { message: '', intentClass: 'text-primary-600' },
                branding: { message: '', intentClass: 'text-primary-600' },
                localization: { message: '', intentClass: 'text-primary-600' },
                notifications: { message: '', intentClass: 'text-primary-600' },
                documents: { message: '', intentClass: 'text-primary-600' },
            },
            sectionErrors: {
                numbering: '',
                templates: '',
                branding: '',
                localization: '',
                notifications: '',
                documents: '',
            },
            scopeErrors: {
                sample_code: {},
                ba: {},
                lhu: {},
                ba_penyerahan: {},
                tracking: {},
            },
            scopeStatus: {
                sample_code: { message: '', intentClass: 'text-primary-600' },
                ba: { message: '', intentClass: 'text-primary-600' },
                lhu: { message: '', intentClass: 'text-primary-600' },
                ba_penyerahan: { message: '', intentClass: 'text-primary-600' },
                tracking: { message: '', intentClass: 'text-primary-600' },
            },
            scopeLoading: {
                sample_code: false,
                ba: false,
                lhu: false,
                ba_penyerahan: false,
                tracking: false,
            },
            loadingSections: {
                numbering: false,
                templates: false,
                branding: false,
                localization: false,
                notifications: false,
                documents: false,
            },
            templates: config.templates || [],
            activeTemplates: {},
            templateForm: { code: '', name: '', file: null },
            templateError: '',
            templateUploading: false,
            templatesLoading: false,
            templateActionLoading: {},
            pdfPreviewUrl: '',
            pdfPreviewLoading: false,
            pdfPreviewError: '',
            pdfPreviewObjectUrl: null,
            roles: {
                manage: config.initialManageRoles || [],
                issue: config.initialIssueRoles || [],
            },
            notificationsTest: {
                email: { target: '', loading: false, message: '', intentClass: 'text-primary-600' },
                whatsapp: { target: '', loading: false, message: '', intentClass: 'text-primary-600' },
            },
            documents: [],
            documentsLoading: false,
            documentsError: '',
            documentDeleting: {},
            selectedDocuments: [],
            bulkDeleteLoading: false,
            documentsFilters: {
                query: '',
                request_number: '',
                type: '',
                source: '',
                per_page: 25,
                page: 1,
            },
            documentsPagination: {
                current_page: 1,
                last_page: 1,
                per_page: 25,
                total: 0,
            },
            documentDeleting: {},
        };
    }

    /**
     * Generic API fetch dengan error handling
     */
    async apiFetch(url, options = {}) {
        const { method = 'GET', body = null, headers = {} } = options;
        
        const requestHeaders = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...headers
        };

        const upper = method.toUpperCase();
        const isFormData = typeof FormData !== 'undefined' && body instanceof FormData;

        if (!isFormData && body !== null && !['GET', 'HEAD'].includes(upper)) {
            requestHeaders['Content-Type'] = 'application/json';
        }

        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(upper)) {
            requestHeaders['X-CSRF-TOKEN'] = this.csrf;
        }

        const fetchOptions = {
            method: upper,
            headers: requestHeaders,
            credentials: 'same-origin',
        };

        if (body !== null && !['GET', 'HEAD'].includes(upper)) {
            fetchOptions.body = isFormData ? body : JSON.stringify(body);
        }

        const response = await fetch(url, fetchOptions);
        const contentType = response.headers.get('Content-Type') || '';

        // Handle non-JSON responses (e.g., PDF preview, HTML error pages)
        if (!contentType.includes('application/json')) {
            if (response.ok) {
                return response; // Return raw response for blob/pdf handling
            }
            
            // For error responses with non-JSON content (like HTML error pages),
            // read text and create a meaningful error message
            const textBody = await response.text().catch(() => '');
            const snippet = textBody.length > 200 ? textBody.substring(0, 200) + '...' : textBody;
            throw new Error(
                `Server returned ${response.status} with non-JSON response. ` +
                `Content-Type: ${contentType}. ` +
                (snippet ? `Body snippet: ${snippet}` : 'No body content.')
            );
        }

        // Safely parse JSON with proper error handling
        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            // If JSON parsing fails, read the raw text for debugging
            const textBody = await response.text().catch(() => '');
            const snippet = textBody.length > 200 ? textBody.substring(0, 200) + '...' : textBody;
            throw new Error(
                `Failed to parse JSON response from ${url}. ` +
                `Status: ${response.status}. ` +
                `Parse error: ${parseError.message}. ` +
                (snippet ? `Body snippet: ${snippet}` : 'Empty response body.')
            );
        }

        if (!response.ok) {
            // Handle 422 validation errors
            if (response.status === 422 && data.errors) {
                const errorMessages = Object.entries(data.errors)
                    .map(([field, messages]) => `${field}: ${Array.isArray(messages) ? messages.join(', ') : messages}`)
                    .join('; ');
                throw new Error(errorMessages || data.message || 'Validation failed');
            }
            throw new Error(data.message || `Request failed with status ${response.status}`);
        }

        return data;
    }

    /**
     * Load all initial data
     */
    async loadAll() {
        this.state.pageLoading = true;
        this.state.loadError = '';
        
        try {
            await Promise.all([
                this.fetchSettings(),
                this.fetchTemplates(),
                this.fetchCurrentNumbering(),
                this.fetchDocuments(),
            ]);
        } catch (error) {
            this.state.loadError = error.message || 'Gagal memuat data awal.';
            throw error;
        } finally {
            this.state.pageLoading = false;
        }
    }

    /**
     * Fetch settings from API
     */
    async fetchSettings() {
        try {
            const data = await this.apiFetch(this.api.settings);
            this.applyServerData(data);
        } catch (error) {
            this.state.loadError = error.message || 'Gagal memuat pengaturan.';
            throw error;
        }
    }

    /**
     * Fetch current numbering
     */
    async fetchCurrentNumbering() {
        this.state.currentNumberingLoading = true;
        try {
            const data = await this.apiFetch(this.api.numberingCurrent);
            
            // Backend now returns plain strings { sample_code: "W001XII2025", ba: "BA/2025/12/0001", ... }
            // No need for complex extraction logic
            this.state.currentNumbering = {
                sample_code: data.sample_code || data.sample || '',
                ba: data.ba || '',
                lhu: data.lhu || '',
                ba_penyerahan: data.ba_penyerahan || '',
                tracking: data.tracking || '',
            };
        } catch (error) {
            this.setSectionError('numbering', error.message || 'Gagal memuat penomoran saat ini.');
        } finally {
            this.state.currentNumberingLoading = false;
        }
    }

    /**
     * Fetch templates list
     */
    async fetchTemplates() {
        this.state.templatesLoading = true;
        try {
            const data = await this.apiFetch(this.api.templates);
            this.state.templates = Array.isArray(data) ? data : (Array.isArray(data.list) ? data.list : (data.data || []));
            this.hydrateActiveTemplates(data.active ?? this.state.activeTemplates);
        } catch (error) {
            this.state.templateError = error.message || 'Gagal memuat template.';
        } finally {
            this.state.templatesLoading = false;
        }
    }

    /**
     * Test numbering preview
     */
    async testPreview(scope) {
        console.log('SettingsClient.testPreview called', { scope, currentForm: this.state.form.numbering?.[scope] });
        
        // Ensure previewState exists
        this.state.previewState = this.state.previewState || { numbering: false };
        
        // Special case: 'numbering' means test all scopes
        if (scope === 'numbering') {
            this.state.previewState.numbering = true;
            try {
                const scopes = ['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking'];
                await Promise.all(scopes.map(s => this.testPreview(s)));
                this.setSectionStatus('numbering', 'Preview berhasil untuk semua jenis dokumen.', 'text-green-600');
            } catch (error) {
                this.setSectionError('numbering', error.message || 'Gagal melakukan preview penomoran.');
            } finally {
                this.state.previewState.numbering = false;
            }
            return;
        }
        
        // Individual scope preview
        // Use object spread to trigger Alpine reactivity
        this.state.previewLoading = { ...this.state.previewLoading, [scope]: true };
        this.clearScopeError(scope);
        
        try {
            // CRITICAL: Deep clone to plain object (avoid Alpine Proxy serialization issues)
            const scopeConfig = this.toPlainObject(this.state.form.numbering?.[scope]) || {};
            
            console.log('🔍 [testPreview] Starting preview for scope:', scope);
            console.log('📋 Config from state:', scopeConfig);
            
            // Backend expects: { scope: string, config: { numbering: { [scope]: {...} } } }
            // OR simpler: { scope: string, pattern: string }
            const payload = {
                scope,
                config: {
                    numbering: {
                        [scope]: {
                            pattern: scopeConfig.pattern || '',
                            reset: scopeConfig.reset || 'never',
                            start_from: scopeConfig.start_from || 1
                        }
                    }
                }
            };
            
            console.log('→ POST /api/settings/numbering/preview', JSON.stringify(payload, null, 2));
            
            const data = await this.apiFetch(this.api.numberingPreview, {
                method: 'POST',
                body: payload,
            });
            
            // Backend returns: { example: "..." }
            const previewValue = data.example ?? data.preview ?? data.value ?? data.data?.example ?? '';
            
            console.log('✓ Preview response:', data);
            console.log('✓ Extracted preview value:', previewValue);
            
            if (!previewValue || previewValue === '') {
                throw new Error('Preview kosong. Periksa pattern penomoran.');
            }
            
            // CRITICAL: Reassign entire object to trigger Alpine reactivity
            this.state.numberingPreview = {
                ...this.state.numberingPreview,
                [scope]: previewValue
            };
            
            console.log('✓ State updated:', { scope, value: this.state.numberingPreview[scope] });
            this.setScopeStatus(scope, 'Preview berhasil!', 'text-green-600');
        } catch (error) {
            console.error('✗ Preview error:', error);
            const errorMessage = error.message || 'Gagal melakukan preview penomoran.';
            this.setScopeError(scope, errorMessage);
            
            // Set explicit error marker (not empty string)
            this.state.numberingPreview = {
                ...this.state.numberingPreview,
                [scope]: null  // null indicates error, empty string is ambiguous
            };
        } finally {
            // Use spread for loading state too
            this.state.previewLoading = { ...this.state.previewLoading, [scope]: false };
        }
    }

    /**
     * Helper to check if preview is loading for a scope
     */
    isPreviewLoading(scope) {
        return this.state.previewLoading?.[scope] === true;
    }

    /**
     * Upload template
     */
    async uploadTemplate(templateForm, fileInputRef) {
        if (!templateForm.file) {
            this.state.templateError = 'Pilih file .docx terlebih dahulu.';
            return;
        }

        this.state.templateUploading = true;
        this.state.templateError = '';

        try {
            const formData = new FormData();
            formData.append('code', templateForm.code || '');
            formData.append('name', templateForm.name || '');
            formData.append('file', templateForm.file);

            await this.apiFetch(this.api.templateUpload, {
                method: 'POST',
                body: formData,
            });

            // Reset form
            templateForm.code = '';
            templateForm.name = '';
            templateForm.file = null;
            if (fileInputRef) {
                fileInputRef.value = '';
            }

            await this.fetchTemplates();
        } catch (error) {
            this.state.templateError = error.message || 'Gagal mengunggah template.';
        } finally {
            this.state.templateUploading = false;
        }
    }

    /**
     * Activate template
     */
    async activateTemplate(template) {
        if (!template?.id) return;

        this.state.templateActionLoading[template.id] = true;
        try {
            const body = template.type ? { type: template.type } : {};
            const data = await this.apiFetch(`${this.api.templates}/${template.id}/activate`, {
                method: 'PUT',
                body,
            });

            if (data?.active) {
                this.state.activeTemplates = this.clone(data.active);
            }

            await this.fetchTemplates();
        } catch (error) {
            this.setSectionError('templates', error.message || 'Gagal mengaktifkan template.');
        } finally {
            this.state.templateActionLoading[template.id] = false;
        }
    }

    /**
     * Delete template
     */
    async deleteTemplate(template) {
        if (!template?.id) return;
        if (!confirm(`Yakin hapus template ${template.name}?`)) return;

        this.state.templateActionLoading[template.id] = true;
        try {
            await this.apiFetch(`${this.api.templates}/${template.id}`, { method: 'DELETE' });
            await this.fetchTemplates();
        } catch (error) {
            this.setSectionError('templates', error.message || 'Gagal menghapus template.');
        } finally {
            this.state.templateActionLoading[template.id] = false;
        }
    }

    /**
     * Preview PDF
     */
    async previewPdf() {
        console.log('SettingsClient.previewPdf called', { branding: this.state.form.branding, pdf: this.state.form.pdf });
        
        this.state.pdfPreviewLoading = true;
        this.state.pdfPreviewError = '';

        if (this.state.pdfPreviewObjectUrl) {
            URL.revokeObjectURL(this.state.pdfPreviewObjectUrl);
            this.state.pdfPreviewObjectUrl = null;
        }

        try {
            console.log('→ POST /api/settings/pdf/preview', { branding: this.state.form.branding, pdf: this.state.form.pdf });
            
            const response = await fetch(this.api.pdfPreview, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/pdf,application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    branding: this.state.form.branding,
                    pdf: this.state.form.pdf,
                }),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                console.error('✗ PDF preview error:', errorData);
                throw new Error(errorData.message || 'Gagal membuat preview PDF.');
            }

            const contentType = response.headers.get('Content-Type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json();
                console.log('✓ PDF preview URL:', data.url);
                this.state.pdfPreviewUrl = data.url || '';
            } else {
                const blob = await response.blob();
                this.state.pdfPreviewObjectUrl = URL.createObjectURL(blob);
                this.state.pdfPreviewUrl = this.state.pdfPreviewObjectUrl;
                console.log('✓ PDF preview blob URL created:', this.state.pdfPreviewUrl);
            }
        } catch (error) {
            console.error('✗ PDF preview exception:', error);
            this.state.pdfPreviewError = error.message || 'Gagal membuat preview PDF.';
        } finally {
            this.state.pdfPreviewLoading = false;
        }
    }

    /**
     * Test notification (email/whatsapp)
     */
    async testNotification(channel) {
        const controller = this.state.notificationsTest[channel];
        if (!controller) return;

        if (!controller.target) {
            controller.message = 'Isi target test terlebih dahulu.';
            controller.intentClass = 'text-red-600';
            return;
        }

        controller.loading = true;
        controller.message = '';

        try {
            const data = await this.apiFetch(this.api.notificationsTest, {
                method: 'POST',
                body: { channel, target: controller.target },
            });

            controller.message = data.message || 'Pengiriman test berhasil.';
            controller.intentClass = 'text-emerald-600';
        } catch (error) {
            controller.message = error.message || 'Pengiriman test gagal.';
            controller.intentClass = 'text-red-600';
        } finally {
            controller.loading = false;
        }
    }

    /**
     * Save numbering for a specific scope
     */
    async saveNumberingScope(scope) {
        // Clear previous errors and status
        this.state.scopeErrors[scope] = {};
        this.state.scopeStatus[scope] = { message: '', intentClass: 'text-primary-600' };
        this.state.scopeLoading[scope] = true;

        try {
            const config = this.state.form.numbering?.[scope];
            if (!config) {
                throw new Error('Konfigurasi penomoran tidak ditemukan.');
            }

            // Validate locally before sending
            if (!config.pattern || config.pattern.trim() === '') {
                throw new Error('Pattern wajib diisi.');
            }
            if (!config.reset) {
                throw new Error('Reset period wajib dipilih.');
            }
            if (!config.start_from || config.start_from < 1) {
                throw new Error('Start from minimal 1.');
            }

            const data = await this.apiFetch(`${this.api.numbering}/${scope}`, {
                method: 'PUT',
                body: {
                    pattern: config.pattern,
                    reset: config.reset,
                    start_from: parseInt(config.start_from) || 1,
                },
            });

            this.state.scopeStatus[scope] = {
                message: data.message || 'Pengaturan penomoran berhasil disimpan.',
                intentClass: 'text-emerald-600'
            };

            // Refresh current numbering display
            await this.fetchCurrentNumbering();

        } catch (error) {
            // Parse validation errors if available
            const errorMessage = error.message || 'Gagal menyimpan pengaturan penomoran.';
            
            // Check if it's a validation error with field-specific messages
            if (errorMessage.includes(':')) {
                // Parse field:message format
                const errors = {};
                errorMessage.split(';').forEach(part => {
                    const [field, ...msgParts] = part.trim().split(':');
                    if (field && msgParts.length > 0) {
                        errors[field.trim()] = msgParts.join(':').trim();
                    }
                });
                this.state.scopeErrors[scope] = errors;
            }

            this.state.scopeStatus[scope] = {
                message: errorMessage,
                intentClass: 'text-red-600'
            };
        } finally {
            this.state.scopeLoading[scope] = false;
        }
    }

    /**
     * Save section
     */
    async saveSection(key) {
        const config = this.sectionEndpoint(key);
        if (!config) return;

        this.setSectionError(key, '');
        this.setSectionStatus(key, '', 'text-primary-600');
        this.setSectionLoading(key, true);

        try {
            await this.apiFetch(config.url, {
                method: config.method,
                body: this.sanitizePayload(config.body),
            });

            this.setSectionStatus(key, 'Pengaturan tersimpan.', 'text-emerald-600');

            if (key === 'templates') {
                await this.fetchTemplates();
            } else {
                await this.fetchSettings();
            }
        } catch (error) {
            this.setSectionError(key, error.message || 'Gagal menyimpan pengaturan.');
            this.setSectionStatus(key, 'Gagal menyimpan.', 'text-red-600');
        } finally {
            this.setSectionLoading(key, false);
        }
    }

    /**
     * Sanitize payload: convert empty strings to null for numeric fields
     */
    sanitizePayload(payload) {
        if (!payload || typeof payload !== 'object') return payload;

        const sanitized = JSON.parse(JSON.stringify(payload));

        // Recursively sanitize numeric fields
        const sanitizeObject = (obj) => {
            if (!obj || typeof obj !== 'object') return obj;

            for (const key in obj) {
                if (typeof obj[key] === 'object' && obj[key] !== null) {
                    sanitizeObject(obj[key]);
                } else if (obj[key] === '' && ['start_from', 'purge_after_days'].includes(key)) {
                    obj[key] = null;
                }
            }
            return obj;
        };

        return sanitizeObject(sanitized);
    }

    /**
     * Build endpoint config per section
     */
    sectionEndpoint(key) {
        switch (key) {
            case 'numbering':
                return {
                    url: this.api.numbering,
                    method: 'PUT',
                    body: { numbering: this.clone(this.state.form.numbering) }
                };
            case 'templates':
                return {
                    url: this.api.templates,
                    method: 'PUT',
                    body: { active: this.serializeActiveTemplates() }
                };
            case 'branding':
                return {
                    url: this.api.branding,
                    method: 'PUT',
                    body: {
                        branding: this.clone(this.state.form.branding),
                        pdf: this.clone(this.state.form.pdf)
                    }
                };
            case 'localization':
                return {
                    url: this.api.localization,
                    method: 'PUT',
                    body: {
                        localization: this.clone(this.state.form.locale),
                        retention: {
                            storage_driver: this.state.form.retention.storage_driver,
                            storage_folder_path: this.state.form.retention.storage_folder_path,
                            purge_after_days: this.state.form.retention.purge_after_days,
                            export_filename_pattern: this.state.form.retention.export_filename_pattern || '',
                        }
                    }
                };
            case 'notifications':
                return {
                    url: this.api.notificationsSecurity,
                    method: 'PUT',
                    body: {
                        notifications: this.clone(this.state.form.notifications),
                        security: {
                            roles: {
                                can_manage_settings: [...this.state.roles.manage],
                                can_issue_number: [...this.state.roles.issue],
                            },
                        },
                    }
                };
            default:
                return null;
        }
    }

    // Helper methods
    setSectionLoading(key, state) {
        this.state.loadingSections[key] = !!state;
    }

    setSectionStatus(key, message, intentClass) {
        this.state.sectionStatus[key] = { message, intentClass };
    }

    setSectionError(key, message) {
        this.state.sectionErrors[key] = message;
    }

    setScopeStatus(scope, message, intentClass = 'text-green-600') {
        if (this.state.scopeStatus[scope]) {
            this.state.scopeStatus[scope] = { message, intentClass };
        }
    }

    setScopeError(scope, message) {
        if (this.state.scopeStatus[scope]) {
            this.state.scopeStatus[scope] = { message, intentClass: 'text-red-600' };
        }
    }

    clearScopeError(scope) {
        if (this.state.scopeStatus[scope]) {
            this.state.scopeStatus[scope] = { message: '', intentClass: 'text-primary-600' };
        }
        if (this.state.scopeErrors[scope]) {
            this.state.scopeErrors[scope] = {};
        }
    }

    applyServerData(payload) {
        const data = (payload && (payload.settings || payload.data)) || payload || {};
        // Merge form data instead of replacing to preserve default state structure
        this.state.form = { ...this.state.form, ...this.mergeDefaults(this.clone(data)) };
        this.hydrateActiveTemplates(data.templates?.active ?? this.state.activeTemplates);

        const security = data.security ?? data.roles ?? {};
        if (Array.isArray(security.can_manage_settings)) {
            this.state.roles.manage = [...security.can_manage_settings];
        }
        if (Array.isArray(security.can_issue_number)) {
            this.state.roles.issue = [...security.can_issue_number];
        }

        const notifications = data.notifications ?? data.automation ?? null;
        if (notifications) {
            this.state.form.notifications = this.mergeNotifications(notifications);
        }

        // Ensure previewState is always defined after hydration
        this.state.previewState = this.state.previewState || { numbering: false };
    }

    mergeDefaults(form) {
        form.numbering ??= {};
        ['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking'].forEach((scope) => {
            if (!form.numbering[scope]) {
                form.numbering[scope] = { pattern: '', reset: 'never', start_from: 1 };
            } else {
                // Preserve server values, only fill missing fields
                form.numbering[scope].pattern = form.numbering[scope].pattern ?? '';
                form.numbering[scope].reset = form.numbering[scope].reset ?? 'never';
                form.numbering[scope].start_from = form.numbering[scope].start_from ?? 1;
            }
        });

        form.branding ??= {};
        form.pdf ??= { header: {}, footer: {}, qr: {} };
        form.pdf.header ??= {};
        form.pdf.footer ??= {};
        form.pdf.qr ??= { enabled: false };
        form.locale ??= {};
        form.retention ??= {};
        // Only set defaults if field doesn't exist, preserve server values including empty strings
        if (!('storage_driver' in form.retention)) form.retention.storage_driver = 'local';
        if (!('storage_folder_path' in form.retention)) form.retention.storage_folder_path = '';
        if (!('purge_after_days' in form.retention)) form.retention.purge_after_days = 365;
        if (!('export_filename_pattern' in form.retention)) form.retention.export_filename_pattern = '';
        form.notifications = this.mergeNotifications(form.notifications ?? form.automation ?? {});
        form.security ??= { roles: { can_manage_settings: [], can_issue_number: [] } };

        return form;
    }

    mergeNotifications(source) {
        return {
            email: {
                enabled: !!source?.email?.enabled,
                address: source?.email?.address || '',
            },
            whatsapp: {
                enabled: !!source?.whatsapp?.enabled,
                number: source?.whatsapp?.number || '',
            },
        };
    }

    hydrateActiveTemplates(source) {
        const normalized = {};
        if (source && typeof source === 'object') {
            Object.entries(source).forEach(([type, value]) => {
                if (value && typeof value === 'object') {
                    normalized[type] = this.clone(value);
                } else if (value) {
                    const match = this.state.templates.find((tpl) => tpl.id === value || (tpl.code && tpl.code === value));
                    normalized[type] = match ? this.clone(match) : { code: value };
                }
            });
        }
        this.state.activeTemplates = normalized;
    }

    buildDocumentsQuery(params = {}) {
        const searchParams = new URLSearchParams();
        Object.entries(params).forEach(([key, value]) => {
            if (value === undefined || value === null) {
                return;
            }
            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (trimmed === '') {
                    return;
                }
                searchParams.append(key, trimmed);
                return;
            }
            if (typeof value === 'number') {
                if (!Number.isFinite(value)) {
                    return;
                }
                searchParams.append(key, value.toString());
                return;
            }
            if (typeof value === 'boolean' && value) {
                searchParams.append(key, '1');
            }
        });

        return searchParams.toString();
    }

    async fetchDocuments(overrides = {}) {
        this.state.documentsLoading = true;
        this.state.documentsError = '';
        this.setSectionError('documents', '');

        const filters = {
            ...this.state.documentsFilters,
            ...this.toPlainObject(overrides),
        };

        if (!filters.per_page || filters.per_page < 5) {
            filters.per_page = 25;
        }
        if (!filters.page || filters.page < 1) {
            filters.page = 1;
        }

        this.state.documentsFilters = filters;

        const query = this.buildDocumentsQuery(filters);
        const url = query ? `${this.api.documents}?${query}` : this.api.documents;

        try {
            const data = await this.apiFetch(url);
            const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);

            this.state.documents = list;
            this.state.documentsPagination = {
                current_page: data.current_page ?? filters.page,
                last_page: data.last_page ?? 1,
                per_page: data.per_page ?? filters.per_page,
                total: data.total ?? list.length,
            };
        } catch (error) {
            const message = error.message || 'Gagal memuat dokumen.';
            this.state.documentsError = message;
            this.setSectionError('documents', message);
            // Don't re-throw to prevent Alpine crashes
            console.error('fetchDocuments error:', error);
        } finally {
            this.state.documentsLoading = false;
        }
    }

    async changeDocumentsPage(page) {
        if (!page || page < 1 || page === this.state.documentsPagination.current_page) {
            return;
        }
        await this.fetchDocuments({ page });
    }

    resetDocumentsFilters() {
        this.state.documentsFilters = {
            query: '',
            request_number: '',
            type: '',
            source: '',
            per_page: 25,
            page: 1,
        };
    }

    isDocumentDeleting(path) {
        return !!this.state.documentDeleting?.[path];
    }

    toggleDocumentSelection(path) {
        const index = this.state.selectedDocuments.indexOf(path);
        if (index > -1) {
            this.state.selectedDocuments.splice(index, 1);
        } else {
            this.state.selectedDocuments.push(path);
        }
    }

    toggleAllDocuments() {
        if (this.state.selectedDocuments.length === this.state.documents.length) {
            this.state.selectedDocuments = [];
        } else {
            this.state.selectedDocuments = this.state.documents.map(doc => doc.path);
        }
    }

    isDocumentSelected(path) {
        return this.state.selectedDocuments.includes(path);
    }

    get hasSelectedDocuments() {
        return this.state.selectedDocuments.length > 0;
    }

    get allDocumentsSelected() {
        return this.state.documents.length > 0 && 
               this.state.selectedDocuments.length === this.state.documents.length;
    }

    async bulkDeleteDocuments() {
        if (this.state.selectedDocuments.length === 0) return;

        const count = this.state.selectedDocuments.length;
        if (!confirm(`Yakin hapus ${count} dokumen yang dipilih?`)) return;

        this.state.bulkDeleteLoading = true;
        this.setSectionError('documents', '');

        const selectedPaths = [...this.state.selectedDocuments];
        const documents = this.state.documents.filter(doc => 
            selectedPaths.includes(doc.path)
        );

        let successCount = 0;
        let failCount = 0;
        const errors = [];

        for (const doc of documents) {
            try {
                await this.apiFetch(this.api.documents, {
                    method: 'DELETE',
                    body: {
                        path: doc.path,
                        document_id: doc.document?.id || null,
                    },
                });
                successCount++;
            } catch (error) {
                failCount++;
                errors.push(`${doc.name}: ${error.message}`);
            }
        }

        this.state.bulkDeleteLoading = false;
        this.state.selectedDocuments = [];

        if (successCount > 0) {
            this.setSectionStatus(
                'documents',
                `${successCount} dokumen berhasil dihapus.` + 
                (failCount > 0 ? ` ${failCount} gagal.` : ''),
                failCount > 0 ? 'text-amber-600' : 'text-emerald-600'
            );
        }

        if (errors.length > 0) {
            this.setSectionError('documents', errors.join('; '));
        }

        await this.fetchDocuments({ page: this.state.documentsPagination.current_page });
    }

    toggleDocumentSelection(path) {
        const index = this.state.selectedDocuments.indexOf(path);
        if (index > -1) {
            this.state.selectedDocuments.splice(index, 1);
        } else {
            this.state.selectedDocuments.push(path);
        }
    }

    toggleAllDocuments() {
        if (this.state.selectedDocuments.length === this.state.documents.length) {
            this.state.selectedDocuments = [];
        } else {
            this.state.selectedDocuments = this.state.documents.map(doc => doc.path);
        }
    }

    isDocumentSelected(path) {
        return this.state.selectedDocuments.includes(path);
    }

    get hasSelectedDocuments() {
        return this.state.selectedDocuments.length > 0;
    }

    get allDocumentsSelected() {
        return this.state.documents.length > 0 && 
               this.state.selectedDocuments.length === this.state.documents.length;
    }

    async bulkDeleteDocuments() {
        if (this.state.selectedDocuments.length === 0) return;

        const count = this.state.selectedDocuments.length;
        if (!confirm(`Yakin hapus ${count} dokumen yang dipilih?`)) return;

        this.state.bulkDeleteLoading = true;
        this.setSectionError('documents', '');

        const selectedPaths = [...this.state.selectedDocuments];
        const documents = this.state.documents.filter(doc => 
            selectedPaths.includes(doc.path)
        );

        let successCount = 0;
        let failCount = 0;
        const errors = [];

        for (const doc of documents) {
            try {
                await this.apiFetch(this.api.documents, {
                    method: 'DELETE',
                    body: {
                        path: doc.path,
                        document_id: doc.document?.id || null,
                    },
                });
                successCount++;
            } catch (error) {
                failCount++;
                errors.push(`${doc.name}: ${error.message}`);
            }
        }

        this.state.bulkDeleteLoading = false;
        this.state.selectedDocuments = [];

        if (successCount > 0) {
            this.setSectionStatus(
                'documents',
                `${successCount} dokumen berhasil dihapus.` + 
                (failCount > 0 ? ` ${failCount} gagal.` : ''),
                failCount > 0 ? 'text-amber-600' : 'text-emerald-600'
            );
        }

        if (errors.length > 0) {
            this.setSectionError('documents', errors.join('; '));
        }

        await this.fetchDocuments({ page: this.state.documentsPagination.current_page });
    }

    async deleteDocumentEntry(entry) {
        if (!entry?.path) return;
        if (!entry.can_delete) {
            this.setSectionError('documents', 'Anda tidak memiliki izin menghapus dokumen ini.');
            return;
        }

        const name = entry.name || entry.type_label || entry.path;
        if (!confirm(`Yakin hapus ${name}?`)) return;

        this.state.documentDeleting = { ...this.state.documentDeleting, [entry.path]: true };
        this.setSectionError('documents', '');

        try {
            await this.apiFetch(this.api.documents, {
                method: 'DELETE',
                body: {
                    path: entry.path,
                    document_id: entry.document?.id || null,
                },
            });
            this.setSectionStatus('documents', `${name} berhasil dihapus.`, 'text-emerald-600');
            await this.fetchDocuments({ page: this.state.documentsPagination.current_page });
        } catch (error) {
            const message = error.message || 'Gagal menghapus dokumen.';
            this.setSectionError('documents', message);
        } finally {
            this.state.documentDeleting = { ...this.state.documentDeleting, [entry.path]: false };
        }
    }

    serializeActiveTemplates() {
        const payload = {};
        Object.entries(this.state.activeTemplates || {}).forEach(([type, tpl]) => {
            if (!tpl) return;
            payload[type] = tpl.code || tpl.id || tpl;
        });
        return payload;
    }

    clone(value) {
        return JSON.parse(JSON.stringify(value ?? {}));
    }

    /**
     * Convert Alpine Proxy to plain object
     * Ensures payload is serializable for API requests
     */
    toPlainObject(obj) {
        if (obj === null || obj === undefined) return {};
        
        // Use structuredClone if available (modern browsers)
        if (typeof structuredClone === 'function') {
            try {
                return structuredClone(obj);
            } catch (e) {
                // Fallback to JSON method
            }
        }
        
        // Fallback: JSON stringify/parse
        try {
            return JSON.parse(JSON.stringify(obj));
        } catch (e) {
            console.error('Failed to convert to plain object:', e);
            return {};
        }
    }
}
