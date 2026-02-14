export function qmhCreatePage(config = {}) {
    return {
        templatesUrl: config.templatesUrl,
        docCode: config.initialDocCode || "",
        title: config.initialTitle || "",
        changeSummary: config.initialChangeSummary || "",
        effectiveDate: config.initialEffectiveDate || "",
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
        schema: null,
        answers: {},
        listAnswerText: {},
        fieldErrors: {},

        init() {
            const initialAnswers = config.initialAnswersJson;
            if (
                initialAnswers &&
                typeof initialAnswers === "object" &&
                !Array.isArray(initialAnswers)
            ) {
                this.answers = { ...initialAnswers };
            }

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

                this.syncSchemaFromTemplate();
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
            this.syncSchemaFromTemplate();
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

        schemaQuestions() {
            const template = this.selectedTemplate();
            const schema = template?.form_schema || this.schema || null;
            const questions = schema?.questions;
            return Array.isArray(questions) ? questions : [];
        },

        syncSchemaFromTemplate() {
            const template = this.selectedTemplate();
            this.schema = template?.form_schema || null;

            this.fieldErrors = {};

            const questions = this.schemaQuestions();
            const nextListText = { ...this.listAnswerText };

            questions.forEach((q) => {
                const qid = typeof q?.id === "string" ? q.id : "";
                if (!qid) return;

                if (q.type === "list") {
                    const existing = this.answers[qid];
                    const items = Array.isArray(existing)
                        ? existing.filter(
                              (val) =>
                                  typeof val === "string" &&
                                  val.trim() !== "",
                          )
                        : [];

                    this.answers[qid] = items;
                    nextListText[qid] = items.join("\n");
                } else {
                    const existing = this.answers[qid];
                    if (typeof existing !== "string") {
                        this.answers[qid] = "";
                    }
                }
            });

            this.listAnswerText = nextListText;
        },

        syncListAnswer(qid) {
            const raw = typeof this.listAnswerText[qid] === "string" ? this.listAnswerText[qid] : "";
            const items = raw
                .split("\n")
                .map((line) => line.trim())
                .filter((line) => line !== "");

            this.answers[qid] = items;
        },

        answerFormFields() {
            const fields = [];
            const questions = this.schemaQuestions();
            questions.forEach((q) => {
                const qid = typeof q?.id === "string" ? q.id : "";
                if (!qid) return;

                if (q.type === "list") {
                    const items = Array.isArray(this.answers[qid])
                        ? this.answers[qid]
                        : [];
                    items.forEach((item) => {
                        if (typeof item !== "string") return;
                        const trimmed = item.trim();
                        if (!trimmed) return;
                        fields.push({
                            name: `answers_json[${qid}][]`,
                            value: trimmed,
                        });
                    });

                    return;
                }

                const val = typeof this.answers[qid] === "string" ? this.answers[qid] : "";
                if (!val.trim()) return;

                fields.push({
                    name: `answers_json[${qid}]`,
                    value: val,
                });
            });

            return fields;
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

        validateAnswers() {
            this.fieldErrors = {};

            const questions = this.schemaQuestions();
            questions.forEach((q) => {
                const qid = typeof q?.id === "string" ? q.id : "";
                if (!qid) return;

                if (!q.required) return;

                if (q.type === "list") {
                    const items = Array.isArray(this.answers[qid])
                        ? this.answers[qid]
                              .filter(
                                  (val) =>
                                      typeof val === "string" &&
                                      val.trim() !== "",
                              )
                        : [];

                    if (items.length === 0) {
                        this.fieldErrors[qid] = "Wajib diisi.";
                    }

                    return;
                }

                const val = typeof this.answers[qid] === "string" ? this.answers[qid] : "";
                if (!val.trim()) {
                    this.fieldErrors[qid] = "Wajib diisi.";
                }
            });

            return Object.keys(this.fieldErrors).length === 0;
        },

        onSubmit() {
            this.stepError = "";
            if (!this.canSubmit()) {
                this.stepError =
                    "Lengkapi struktur dokumen dan pastikan template aktif tersedia sebelum menyimpan.";

                return false;
            }

            if (!this.validateAnswers()) {
                this.stepError =
                    "Lengkapi jawaban wajib pada bagian pertanyaan sebelum menyimpan.";

                return false;
            }

            this.isSubmitting = true;

            return true;
        },

        previewDocTypeLabel() {
            if (this.docType === "sop") return "PROSEDUR";
            if (this.docType === "ik") return "INSTRUKSI KERJA";
            if (this.docType === "fr") return "FORMULIR";
            return "DOKUMEN";
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
