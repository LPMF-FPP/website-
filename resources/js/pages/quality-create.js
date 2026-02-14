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
        previewBeforeSubmitOpen: false,
        previewMode: null,
        submitConfirmed: false,
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

        selectedTemplateContentHtml() {
            const template = this.selectedTemplate();

            return typeof template?.content_html === "string"
                ? template.content_html
                : "";
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
                        ? existing
                              .map((val) => this.normalizePlainAnswer(val))
                              .filter((val) => val !== "")
                        : [];

                    this.answers[qid] = items;
                    nextListText[qid] = items.join("\n");
                } else {
                    const existing = this.answers[qid];
                    this.answers[qid] = this.normalizePlainAnswer(existing);
                }
            });

            this.listAnswerText = nextListText;
        },

        syncListAnswer(qid) {
            const raw =
                typeof this.listAnswerText[qid] === "string"
                    ? this.listAnswerText[qid]
                    : "";
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
                        const normalized = this.normalizePlainAnswer(item);
                        if (!normalized) return;
                        fields.push({
                            name: `answers_json[${qid}][]`,
                            value: normalized,
                        });
                    });

                    return;
                }

                const val =
                    typeof this.answers[qid] === "string"
                        ? this.answers[qid]
                        : "";
                const normalized = this.normalizePlainAnswer(val);
                if (!normalized) return;

                fields.push({
                    name: `answers_json[${qid}]`,
                    value: normalized,
                });
            });

            return fields;
        },

        escapeHtml(value) {
            return String(value ?? "")
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#39;");
        },

        htmlToPlainText(value) {
            if (typeof value !== "string") {
                return "";
            }

            const container = document.createElement("div");
            container.innerHTML = value;

            const text = String(
                container.innerText || container.textContent || "",
            );

            return text
                .replaceAll("\u00a0", " ")
                .replaceAll("\r\n", "\n")
                .replaceAll("\r", "\n")
                .replaceAll(/\n{3,}/g, "\n\n")
                .trim();
        },

        normalizePlainAnswer(value) {
            const raw = typeof value === "string" ? value : "";
            const trimmed = raw.trim();

            if (!trimmed) {
                return "";
            }

            if (/<\/?[a-z][\s\S]*>/i.test(trimmed)) {
                return this.htmlToPlainText(trimmed);
            }

            return trimmed;
        },

        plainTextToEditorHtml(value) {
            const normalized = this.normalizePlainAnswer(value);

            if (!normalized) {
                return "<p></p>";
            }

            return `<p>${this.escapeHtml(normalized).replaceAll("\n", "<br>")}</p>`;
        },

        listToEditorHtml(value) {
            const items = Array.isArray(value) ? value : [];
            const normalizedItems = items
                .map((item) => this.normalizePlainAnswer(item))
                .filter((item) => item !== "");

            if (normalizedItems.length === 0) {
                return "<p></p>";
            }

            return `<ul>${normalizedItems
                .map((item) => `<li><p>${this.escapeHtml(item)}</p></li>`)
                .join("")}</ul>`;
        },

        isPlainBlank(value) {
            return this.normalizePlainAnswer(value) === "";
        },

        answerEditorInitialValue(qid) {
            const current = this.answers[qid];

            if (Array.isArray(current)) {
                return this.listToEditorHtml(current);
            }

            return this.plainTextToEditorHtml(current);
        },

        onRichTextAnswerChange(qid, html) {
            if (typeof qid !== "string" || !qid) {
                return;
            }

            this.answers[qid] = this.htmlToPlainText(html);
        },

        onRichTextListAnswerChange(qid, html) {
            if (typeof qid !== "string" || !qid) {
                return;
            }

            const plain = this.htmlToPlainText(html);
            const items = plain
                .split("\n")
                .map((line) => line.trim())
                .filter((line) => line !== "");

            this.answers[qid] = items;
            this.listAnswerText[qid] = items.join("\n");
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
            const questions = this.schemaQuestions();
            if (questions.length > 0) {
                return this.structuredPreviewHtml(questions);
            }

            const templateHtml = this.selectedTemplateContentHtml();
            if (!templateHtml) {
                return this.fallbackPreviewHtml();
            }

            return this.applyPreviewTokens(templateHtml);
        },

        structuredPreviewHtml(questions) {
            const rows = questions
                .map((q, idx) => {
                    const qid = typeof q?.id === "string" ? q.id : "";
                    if (!qid) return "";

                    const label = this.escapeHtml(q?.label || qid);

                    if (q.type === "list") {
                        const items = Array.isArray(this.answers[qid])
                            ? this.answers[qid]
                                  .map((val) => this.normalizePlainAnswer(val))
                                  .filter((val) => val !== "")
                            : [];

                        const listHtml =
                            items.length > 0
                                ? `<ul class=\"list-disc pl-5\">${items
                                      .map(
                                          (item) =>
                                              `<li>${this.escapeHtml(item)}</li>`,
                                      )
                                      .join("")}</ul>`
                                : `<p class=\"text-gray-500\">-</p>`;

                        return `<div class=\"space-y-1\"><div class=\"text-xs font-semibold text-gray-900\">${idx + 1}. ${label}</div>${listHtml}</div>`;
                    }

                    const raw = this.normalizePlainAnswer(this.answers[qid]);
                    const answerHtml = raw
                        ? `<p class=\"whitespace-pre-line\">${this.escapeHtml(raw).replaceAll("\n", "<br>")}</p>`
                        : `<p class=\"text-gray-500\">-</p>`;

                    return `<div class=\"space-y-1\"><div class=\"text-xs font-semibold text-gray-900\">${idx + 1}. ${label}</div>${answerHtml}</div>`;
                })
                .filter((row) => row !== "")
                .join("");

            return `<div class=\"space-y-4 text-sm text-gray-700\">${rows}</div>`;
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
                              .map((val) => this.normalizePlainAnswer(val))
                              .filter((val) => val !== "")
                        : [];

                    if (items.length === 0) {
                        this.fieldErrors[qid] = "Wajib diisi.";
                    }

                    return;
                }

                const val =
                    typeof this.answers[qid] === "string"
                        ? this.answers[qid]
                        : "";
                if (this.isPlainBlank(val)) {
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

            if (!this.submitConfirmed) {
                this.previewMode = "submit";
                this.previewBeforeSubmitOpen = true;

                return false;
            }

            this.isSubmitting = true;
            this.submitConfirmed = false;
            this.previewBeforeSubmitOpen = false;

            return true;
        },

        cancelSubmitPreview() {
            this.previewBeforeSubmitOpen = false;
            this.submitConfirmed = false;
            this.previewMode = null;
        },

        confirmSubmitPreview() {
            this.submitConfirmed = true;
            this.previewBeforeSubmitOpen = false;
            this.previewMode = null;

            this.$nextTick(() => {
                const form = this.$refs?.draftForm;
                if (!form) {
                    return;
                }

                if (typeof form.requestSubmit === "function") {
                    form.requestSubmit();

                    return;
                }

                form.submit();
            });
        },

        openQuickPreview() {
            this.previewMode = "manual";
            this.submitConfirmed = false;
            this.previewBeforeSubmitOpen = true;
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
