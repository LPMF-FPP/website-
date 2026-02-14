export function qmhCreatePage(config = {}) {
    return {
        templatesUrl: config.templatesUrl,
        docCode: config.initialDocCode || "",
        title: config.initialTitle || "",
        changeSummary: config.initialChangeSummary || "",
        clause: config.initialClause,
        docType: config.initialDocType || "",
        templateId: config.initialTemplateId,
        parentSopId: config.initialParentSopId,
        pairedIkId: config.initialPairedIkId,
        templateManageUrl: config.templateManageUrl,
        canManageTemplate: Boolean(config.canManageTemplate),
        sopOptions: config.sopOptions || [],
        ikOptions: config.ikOptions || [],
        templates: [],
        templatesLoading: false,
        templatesError: "",
        stepError: "",
        isSubmitting: false,

        init() {
            this.handleHierarchyDependencies();
            if (this.docType) {
                this.fetchTemplates();
            }
        },

        onStructureChanged() {
            this.stepError = "";
            this.handleHierarchyDependencies();
            if (this.docType) {
                this.fetchTemplates();
            } else {
                this.templates = [];
                this.templateId = 0;
            }
        },

        handleHierarchyDependencies() {
            if (!this.requiresParentSop()) {
                this.parentSopId = 0;
                this.pairedIkId = 0;

                return;
            }

            const hasParent = this.filteredSopOptions().some(
                (item) => Number(item.id) === Number(this.parentSopId),
            );
            if (!hasParent) {
                this.parentSopId = 0;
                this.pairedIkId = 0;

                return;
            }

            if (!this.requiresPairedIk()) {
                this.pairedIkId = 0;

                return;
            }

            const hasPairedIk = this.filteredIkOptions().some(
                (item) => Number(item.id) === Number(this.pairedIkId),
            );
            if (!hasPairedIk) {
                this.pairedIkId = 0;
            }
        },

        async fetchTemplates() {
            this.templatesLoading = true;
            this.templatesError = "";

            try {
                const params = new URLSearchParams({ doc_type: this.docType });
                const response = await fetch(
                    `${this.templatesUrl}?${params.toString()}`,
                    {
                        credentials: "same-origin",
                        headers: { Accept: "application/json" },
                    },
                );

                if (!response.ok) {
                    this.templates = [];
                    this.templateId = 0;
                    this.templatesError =
                        "Gagal memuat template. Silakan coba lagi.";

                    return;
                }

                const payload = await response.json();
                this.templates = Array.isArray(payload.data)
                    ? payload.data
                    : [];

                const hasCurrent = this.templates.some(
                    (item) => Number(item.id) === Number(this.templateId),
                );
                if (!hasCurrent) {
                    this.templateId =
                        this.templates.length > 0
                            ? Number(this.templates[0].id)
                            : 0;
                }
            } catch {
                this.templates = [];
                this.templateId = 0;
                this.templatesError =
                    "Terjadi gangguan jaringan saat memuat template.";
            } finally {
                this.templatesLoading = false;
            }
        },

        onTemplateChanged() {
            // Template changed — future: could sync schema questions
        },

        selectedTemplate() {
            return (
                this.templates.find(
                    (item) => Number(item.id) === Number(this.templateId),
                ) || null
            );
        },

        selectedTemplatePreviewUrl() {
            const template = this.selectedTemplate();

            return template?.preview_url || "";
        },

        selectedTemplateContentHtml() {
            const template = this.selectedTemplate();

            return typeof template?.content_html === "string"
                ? template.content_html
                : "";
        },

        escapeHtml(value) {
            return String(value ?? "")
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#39;");
        },

        applyPreviewTokens(html) {
            let output = String(html || "");
            const title = this.escapeHtml(this.title || "-");
            const docCode = this.escapeHtml(this.docCode || "-");
            const clause = this.escapeHtml(this.clause || "-");
            const docType = this.escapeHtml(
                (this.docType || "-").toUpperCase(),
            );
            const changeSummary = this.escapeHtml(
                this.changeSummary || "-",
            ).replaceAll("\n", "<br>");

            const tokenMap = {
                "{{title}}": title,
                "{{ title }}": title,
                "{{doc_code}}": docCode,
                "{{ doc_code }}": docCode,
                "{{clause}}": clause,
                "{{ clause }}": clause,
                "{{doc_type}}": docType,
                "{{ doc_type }}": docType,
                "{{change_summary}}": changeSummary,
                "{{ change_summary }}": changeSummary,
                "[TITLE]": title,
                "[DOC_CODE]": docCode,
                "[CLAUSE]": clause,
                "[DOC_TYPE]": docType,
                "[CHANGE_SUMMARY]": changeSummary,
            };

            for (const [token, value] of Object.entries(tokenMap)) {
                output = output.split(token).join(value);
            }

            return output;
        },

        fallbackPreviewHtml() {
            const title = this.escapeHtml(this.title || "(Belum diisi)");
            const docCode = this.escapeHtml(this.docCode || "(Belum diisi)");
            const clause = this.escapeHtml(this.clause || "-");
            const docType = this.escapeHtml(
                (this.docType || "-").toUpperCase(),
            );
            const changeSummary = this.escapeHtml(
                this.changeSummary || "-",
            ).replaceAll("\n", "<br>");

            return `<div class="space-y-3 text-sm text-gray-700"><div class="rounded-md border border-gray-200 bg-white p-3"><p><strong>Jenis:</strong> ${docType}</p><p><strong>Klausul:</strong> ${clause}</p><p><strong>Kode:</strong> ${docCode}</p><p><strong>Judul:</strong> ${title}</p><p><strong>Ringkasan Perubahan:</strong><br>${changeSummary}</p></div><p class="text-xs text-gray-500">Preview template HTML belum tersedia. Konten akan mengikuti template aktif saat dokumen dibuat.</p></div>`;
        },

        livePreviewHtml() {
            const templateHtml = this.selectedTemplateContentHtml();
            if (!templateHtml) {
                return this.fallbackPreviewHtml();
            }

            return this.applyPreviewTokens(templateHtml);
        },

        canSubmit() {
            if (
                !this.docCode ||
                !this.title ||
                !this.clause ||
                !this.docType ||
                !this.templateId
            ) {
                return false;
            }

            if (this.requiresParentSop() && !this.parentSopId) {
                return false;
            }

            return true;
        },

        onSubmit() {
            this.stepError = "";
            if (!this.canSubmit()) {
                this.stepError =
                    "Lengkapi struktur dokumen dan pastikan template aktif tersedia sebelum menyimpan.";

                return false;
            }

            this.isSubmitting = true;

            return true;
        },

        requiresParentSop() {
            return this.docType === "ik" || this.docType === "fr";
        },

        requiresPairedIk() {
            return this.docType === "fr";
        },

        filteredSopOptions() {
            return this.sopOptions.filter(
                (item) => Number(item.clause) === Number(this.clause),
            );
        },

        filteredIkOptions() {
            if (!this.parentSopId) {
                return [];
            }

            return this.ikOptions.filter(
                (item) =>
                    Number(item.parent_sop_id) === Number(this.parentSopId),
            );
        },
    };
}
