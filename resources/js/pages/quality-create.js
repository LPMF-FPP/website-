export function qmhCreatePage(config = {}) {
    return {
        templatesUrl: config.templatesUrl,
        currentStep: 1,
        docCode: config.initialDocCode || "",
        title: config.initialTitle || "",
        changeSummary: config.initialChangeSummary || "",
        clause: config.initialClause,
        docType: config.initialDocType,
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
            this.fetchTemplates();
        },

        onStructureChanged() {
            this.currentStep = 1;
            this.stepError = "";
            this.handleHierarchyDependencies();
            this.fetchTemplates();
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
                const params = new URLSearchParams({
                    clause: String(this.clause),
                    doc_type: this.docType,
                });

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
                    this.currentStep = 1;

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
                    this.currentStep = 1;
                }
            } catch {
                this.templates = [];
                this.templateId = 0;
                this.templatesError =
                    "Terjadi gangguan jaringan saat memuat template.";
                this.currentStep = 1;
            } finally {
                this.templatesLoading = false;
            }
        },

        canProceedStep1() {
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

        goToPreview() {
            this.stepError = "";

            if (!this.canProceedStep1()) {
                this.stepError =
                    "Lengkapi struktur dokumen terlebih dahulu sebelum lanjut ke preview.";

                return;
            }

            this.currentStep = 2;
        },

        selectedTemplate() {
            return (
                this.templates.find(
                    (item) => Number(item.id) === Number(this.templateId),
                ) || null
            );
        },

        selectedTemplateName() {
            const template = this.selectedTemplate();

            return template ? template.name : "-";
        },

        selectedTemplateVersion() {
            const template = this.selectedTemplate();

            return template ? `v${template.version}` : "-";
        },

        selectedTemplateSourcePath() {
            const template = this.selectedTemplate();

            return template?.source_docx_path || "-";
        },

        selectedTemplateUpdatedAt() {
            const template = this.selectedTemplate();
            if (!template?.updated_at) {
                return "-";
            }

            const date = new Date(template.updated_at);
            if (Number.isNaN(date.getTime())) {
                return template.updated_at;
            }

            return date.toLocaleString("id-ID", { hour12: false });
        },

        selectedParentSopLabel() {
            if (!this.parentSopId) {
                return this.requiresParentSop()
                    ? "Belum dipilih"
                    : "Tidak wajib";
            }

            const sop = this.sopOptions.find(
                (item) => Number(item.id) === Number(this.parentSopId),
            );

            return sop ? sop.label : "-";
        },

        selectedPairedIkLabel() {
            if (!this.pairedIkId) {
                return this.requiresPairedIk()
                    ? "Tanpa pasangan IK"
                    : "Tidak wajib";
            }

            const ik = this.ikOptions.find(
                (item) => Number(item.id) === Number(this.pairedIkId),
            );

            return ik ? ik.label : "-";
        },

        templatePreviewSummary() {
            const template = this.selectedTemplate();
            if (!template) {
                return "Template belum dipilih.";
            }

            return `Template ${template.name} (${this.selectedTemplateVersion()}) akan menjadi dasar draft awal. Konten lengkap dapat diisi pada halaman Edit Dokumen setelah draft dibuat.`;
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
