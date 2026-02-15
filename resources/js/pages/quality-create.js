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
        pdfPreviewLoading: false,
        pdfPreviewError: "",
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

                if (q.type === "section") {
                    delete this.answers[qid];
                    delete nextListText[qid];
                    return;
                }

                if (q.type === "checkbox") {
                    const existing = this.answers[qid];
                    this.answers[qid] = this.coerceBoolean(existing);
                    return;
                }

                if (q.type === "list") {
                    const existing = this.answers[qid];
                    if (typeof existing === "string") {
                        if (this.looksLikeHtml(existing)) {
                            const normalized =
                                this.normalizeEditorHtml(existing);
                            const listHtml =
                                this.extractListContainerHtml(normalized);

                            this.answers[qid] = listHtml || normalized;
                            nextListText[qid] = this.htmlToPlainText(
                                this.answers[qid],
                            );

                            return;
                        }

                        const plain = this.normalizePlainText(existing);
                        const lines = plain
                            .split("\n")
                            .map((line) => line.trim())
                            .filter((line) => line !== "");
                        const items = lines
                            .map(
                                (line) =>
                                    `<li><p>${this.escapeHtml(line)}</p></li>`,
                            )
                            .join("");

                        const fallback = items
                            ? `<ul>${items}</ul>`
                            : "<p></p>";

                        this.answers[qid] = fallback;
                        nextListText[qid] = this.htmlToPlainText(fallback);

                        return;
                    }

                    const items = Array.isArray(existing)
                        ? existing
                              .map((val) =>
                                  typeof val === "string" ? val : "",
                              )
                              .filter((val) => val.trim() !== "")
                        : [];

                    this.answers[qid] = items;
                    nextListText[qid] = items
                        .map((val) => this.htmlToPlainText(val))
                        .join("\n");
                } else {
                    const existingRaw = this.answers[qid];
                    const existing =
                        typeof existingRaw === "string"
                            ? existingRaw
                            : typeof existingRaw === "number"
                              ? String(existingRaw)
                              : "";
                    this.answers[qid] = existing;

                    if (this.docType === "fr") {
                        // Formulir is form-first: keep plain text even if legacy HTML exists.
                        this.answers[qid] = this.normalizePlainText(
                            this.looksLikeHtml(existing)
                                ? this.htmlToPlainText(existing)
                                : existing,
                        );
                        return;
                    }

                    if (this.looksLikeHtml(existing)) {
                        this.answers[qid] = this.normalizeEditorHtml(existing);
                        return;
                    }

                    this.answers[qid] = this.normalizePlainText(existing);
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

                if (q.type === "section") {
                    return;
                }

                if (q.type === "checkbox") {
                    const checked = this.coerceBoolean(this.answers[qid]);
                    if (!checked) {
                        return;
                    }

                    fields.push({
                        name: `answers_json[${qid}]`,
                        value: "1",
                    });

                    return;
                }

                if (q.type === "list") {
                    const current = this.answers[qid];

                    if (typeof current === "string") {
                        const normalizedHtml =
                            this.normalizeEditorHtml(current);
                        const listHtml =
                            this.extractListContainerHtml(normalizedHtml) ||
                            normalizedHtml;

                        if (this.isEditorBlank(listHtml)) {
                            return;
                        }

                        fields.push({
                            name: `answers_json[${qid}]`,
                            value: listHtml,
                        });

                        return;
                    }

                    const items = Array.isArray(current) ? current : [];
                    items.forEach((item) => {
                        if (typeof item !== "string") {
                            return;
                        }

                        if (this.looksLikeHtml(item)) {
                            const normalizedHtml =
                                this.normalizeEditorHtml(item);
                            if (this.isEditorBlank(normalizedHtml)) {
                                return;
                            }

                            fields.push({
                                name: `answers_json[${qid}][]`,
                                value: normalizedHtml,
                            });

                            return;
                        }

                        const normalized = this.normalizePlainText(item);
                        if (!normalized) {
                            return;
                        }
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
                        : typeof this.answers[qid] === "number"
                          ? String(this.answers[qid])
                          : "";
                if (this.looksLikeHtml(val)) {
                    const normalizedHtml = this.normalizeEditorHtml(val);
                    if (this.isEditorBlank(normalizedHtml)) {
                        return;
                    }

                    fields.push({
                        name: `answers_json[${qid}]`,
                        value: normalizedHtml,
                    });

                    return;
                }

                const normalized = this.normalizePlainText(val);
                if (!normalized) {
                    return;
                }

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

        looksLikeHtml(value) {
            if (typeof value !== "string") {
                return false;
            }

            return /<\/?[a-z][\s\S]*>/i.test(value);
        },

        normalizePlainText(value) {
            const raw = typeof value === "string" ? value : "";

            return raw.trim();
        },

        normalizeEditorHtml(value) {
            const raw = typeof value === "string" ? value : "";
            const trimmed = raw.trim();

            if (!trimmed) {
                return "<p></p>";
            }

            return trimmed;
        },

        isEditorBlank(value) {
            if (typeof value !== "string") {
                return true;
            }

            if (!value.trim()) {
                return true;
            }

            return this.htmlToPlainText(value) === "";
        },

        plainTextToEditorHtml(value) {
            const normalized = this.normalizePlainText(value);

            if (!normalized) {
                return "<p></p>";
            }

            return `<p>${this.escapeHtml(normalized).replaceAll("\n", "<br>")}</p>`;
        },

        listToEditorHtml(value) {
            const items = Array.isArray(value) ? value : [];
            const normalizedItems = items
                .map((item) =>
                    typeof item === "string" ? item.trim() : String(item ?? ""),
                )
                .filter((item) => item !== "")
                .map((item) => {
                    if (this.looksLikeHtml(item)) {
                        return item;
                    }

                    return `<p>${this.escapeHtml(item)}</p>`;
                });

            if (normalizedItems.length === 0) {
                return "<p></p>";
            }

            return `<ul>${normalizedItems
                .map((item) => `<li>${item}</li>`)
                .join("")}</ul>`;
        },

        answerEditorInitialValue(qid) {
            const current = this.answers[qid];

            if (Array.isArray(current)) {
                return this.listToEditorHtml(current);
            }

            if (this.looksLikeHtml(current)) {
                return this.normalizeEditorHtml(current);
            }

            return this.plainTextToEditorHtml(current);
        },

        onRichTextAnswerChange(qid, html) {
            if (typeof qid !== "string" || !qid) {
                return;
            }

            this.answers[qid] = this.normalizeEditorHtml(html);
        },

        onRichTextListAnswerChange(qid, html) {
            if (typeof qid !== "string" || !qid) {
                return;
            }

            const normalized = this.normalizeEditorHtml(html);
            const listHtml = this.extractListContainerHtml(normalized);

            if (listHtml) {
                this.answers[qid] = listHtml;
                this.listAnswerText[qid] = this.htmlToPlainText(listHtml);

                return;
            }

            const plain = this.htmlToPlainText(normalized);
            const items = plain
                .split("\n")
                .map((line) => line.trim())
                .filter((line) => line !== "")
                .map((line) => `<li><p>${this.escapeHtml(line)}</p></li>`)
                .join("");

            const fallback = items ? `<ul>${items}</ul>` : "<p></p>";

            this.answers[qid] = fallback;
            this.listAnswerText[qid] = this.htmlToPlainText(fallback);
        },

        extractListContainerHtml(html) {
            if (typeof html !== "string") {
                return "";
            }

            const container = document.createElement("div");
            container.innerHTML = html;

            const list = container.querySelector("ol, ul");
            if (!list) {
                return "";
            }

            return this.normalizeEditorHtml(list.outerHTML || "");
        },

        sanitizePreviewHtml(value) {
            const raw = typeof value === "string" ? value : "";
            if (!raw.trim()) {
                return "";
            }

            const allowedTags = new Set([
                "p",
                "br",
                "strong",
                "b",
                "em",
                "i",
                "u",
                "ul",
                "ol",
                "li",
            ]);

            const doc = document.createElement("div");
            doc.innerHTML = raw;

            doc.querySelectorAll("script, style").forEach((node) =>
                node.remove(),
            );

            const walker = document.createTreeWalker(
                doc,
                NodeFilter.SHOW_ELEMENT,
            );
            const nodes = [];
            while (walker.nextNode()) {
                nodes.push(walker.currentNode);
            }

            nodes.forEach((node) => {
                const tag = String(node.tagName || "").toLowerCase();

                Array.from(node.attributes || []).forEach((attr) => {
                    const name = String(attr.name || "").toLowerCase();
                    node.removeAttribute(attr.name);

                    if (name === "href" || name === "src") {
                        node.removeAttribute(attr.name);
                    }
                });

                if (!allowedTags.has(tag)) {
                    const parent = node.parentNode;
                    if (!parent) return;
                    while (node.firstChild) {
                        parent.insertBefore(node.firstChild, node);
                    }
                    parent.removeChild(node);
                }
            });

            return doc.innerHTML;
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
            if (this.docType === "fr") {
                return this.structuredFormPreviewHtml(questions);
            }

            const rows = questions
                .map((q, idx) => {
                    const qid = typeof q?.id === "string" ? q.id : "";
                    if (!qid) return "";

                    const label = this.escapeHtml(q?.label || qid);

                    if (q.type === "list") {
                        const current = this.answers[qid];

                        if (typeof current === "string") {
                            if (this.looksLikeHtml(current)) {
                                const normalized =
                                    this.normalizeEditorHtml(current);
                                const listHtml =
                                    this.extractListContainerHtml(normalized) ||
                                    normalized;

                                const previewHtml = this.isEditorBlank(listHtml)
                                    ? `<p class=\"text-gray-500\">-</p>`
                                    : this.sanitizePreviewHtml(listHtml);

                                return `<div class=\"space-y-1\"><div class=\"text-xs font-semibold text-gray-900\">${idx + 1}. ${label}</div>${previewHtml}</div>`;
                            }

                            const plain = this.normalizePlainText(current);
                            if (!plain) {
                                return `<div class=\"space-y-1\"><div class=\"text-xs font-semibold text-gray-900\">${idx + 1}. ${label}</div><p class=\"text-gray-500\">-</p></div>`;
                            }

                            const lines = plain
                                .split("\n")
                                .map((line) => line.trim())
                                .filter((line) => line !== "");
                            const items = lines
                                .map(
                                    (line) =>
                                        `<li>${this.escapeHtml(line)}</li>`,
                                )
                                .join("");

                            const previewHtml = items
                                ? `<ul class=\"list-disc pl-5\">${items}</ul>`
                                : `<p class=\"text-gray-500\">-</p>`;

                            return `<div class=\"space-y-1\"><div class=\"text-xs font-semibold text-gray-900\">${idx + 1}. ${label}</div>${previewHtml}</div>`;
                        }

                        const items = Array.isArray(current)
                            ? current.filter((val) => typeof val === "string")
                            : [];

                        const filledItems = items
                            .map((item) => {
                                if (this.looksLikeHtml(item)) {
                                    const normalized =
                                        this.normalizeEditorHtml(item);
                                    if (this.isEditorBlank(normalized)) {
                                        return "";
                                    }

                                    return this.sanitizePreviewHtml(normalized);
                                }

                                const normalized =
                                    this.normalizePlainText(item);
                                if (!normalized) {
                                    return "";
                                }

                                return this.escapeHtml(normalized);
                            })
                            .filter((item) => item !== "");

                        const listHtml =
                            filledItems.length > 0
                                ? `<ul class=\"list-disc pl-5\">${filledItems
                                      .map((item) => `<li>${item}</li>`)
                                      .join("")}</ul>`
                                : `<p class=\"text-gray-500\">-</p>`;

                        return `<div class=\"space-y-1\"><div class=\"text-xs font-semibold text-gray-900\">${idx + 1}. ${label}</div>${listHtml}</div>`;
                    }

                    const raw =
                        typeof this.answers[qid] === "string"
                            ? this.answers[qid]
                            : "";

                    let answerHtml = '<p class="text-gray-500">-</p>';

                    if (this.looksLikeHtml(raw)) {
                        const normalized = this.normalizeEditorHtml(raw);
                        if (!this.isEditorBlank(normalized)) {
                            answerHtml = this.sanitizePreviewHtml(normalized);
                        }
                    } else {
                        const normalized = this.normalizePlainText(raw);
                        if (normalized) {
                            answerHtml = `<p>${this.escapeHtml(normalized).replaceAll("\n", "<br>")}</p>`;
                        }
                    }

                    return `<div class=\"space-y-1\"><div class=\"text-xs font-semibold text-gray-900\">${idx + 1}. ${label}</div>${answerHtml}</div>`;
                })
                .filter((row) => row !== "")
                .join("");

            return `<div class=\"space-y-4 text-sm text-gray-700\">${rows}</div>`;
        },

        structuredFormPreviewHtml(questions) {
            const rows = questions
                .map((q, idx) => {
                    const qid = typeof q?.id === "string" ? q.id : "";
                    if (!qid) return "";

                    const label = this.escapeHtml(q?.label || qid);
                    const type = String(q?.type || "text");
                    const val = this.answers[qid];

                    let cell = '<div class="text-gray-400">&nbsp;</div>';

                    if (type === "section") {
                        const text = label.toUpperCase();
                        return `<tr><td class="border border-gray-200 bg-gray-50 px-2 py-2 text-xs font-semibold text-gray-600 text-center">${idx + 1}</td><td class="border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-900" colspan="2">${text}</td></tr>`;
                    }

                    if (type === "checkbox") {
                        const checked = this.coerceBoolean(val);
                        cell = checked
                            ? '<div class="text-sm font-semibold text-gray-900">YA</div>'
                            : '<div class="text-sm text-gray-500">TIDAK</div>';

                        return `<tr><td class="border border-gray-200 px-2 py-2 text-xs font-semibold text-gray-700 text-center">${idx + 1}</td><td class="border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-800">${label.toUpperCase()}</td><td class="border border-gray-200 px-3 py-2 text-sm text-gray-700">${cell}</td></tr>`;
                    }

                    if (type === "list") {
                        if (
                            typeof val === "string" &&
                            this.looksLikeHtml(val)
                        ) {
                            const normalized =
                                this.extractListContainerHtml(val) || val;
                            cell = this.isEditorBlank(normalized)
                                ? cell
                                : this.sanitizePreviewHtml(normalized);
                        } else if (Array.isArray(val)) {
                            const items = val
                                .filter((v) => typeof v === "string")
                                .map((v) => String(v).trim())
                                .filter((v) => v !== "");
                            if (items.length > 0) {
                                cell = `<ul class=\"list-disc pl-5\">${items
                                    .map(
                                        (v) => `<li>${this.escapeHtml(v)}</li>`,
                                    )
                                    .join("")}</ul>`;
                            }
                        }
                    } else if (typeof val === "string") {
                        if (this.looksLikeHtml(val)) {
                            const normalized = this.normalizeEditorHtml(val);
                            cell = this.isEditorBlank(normalized)
                                ? cell
                                : this.sanitizePreviewHtml(normalized);
                        } else {
                            const normalized = this.normalizePlainText(val);
                            if (normalized) {
                                cell = `<div class=\"whitespace-pre-line\">${this.escapeHtml(normalized).replaceAll("\n", "<br>")}</div>`;
                            }
                        }
                    }

                    if (type === "select") {
                        const opts = Array.isArray(q?.options) ? q.options : [];
                        const value = typeof val === "string" ? val : "";
                        const match = opts.find(
                            (opt) =>
                                typeof opt?.value === "string" &&
                                opt.value === value,
                        );
                        const labelText =
                            typeof match?.label === "string" && match.label
                                ? match.label
                                : value;
                        if (labelText) {
                            cell = `<div class=\"text-sm text-gray-800\">${this.escapeHtml(labelText)}</div>`;
                        }
                    }

                    if (type === "date") {
                        const value = typeof val === "string" ? val.trim() : "";
                        if (value) {
                            cell = `<div class=\"text-sm text-gray-800\">${this.escapeHtml(value)}</div>`;
                        }
                    }

                    if (type === "number") {
                        const value =
                            typeof val === "string"
                                ? val.trim()
                                : typeof val === "number"
                                  ? String(val)
                                  : "";
                        if (value) {
                            cell = `<div class=\"text-sm text-gray-800\">${this.escapeHtml(value)}</div>`;
                        }
                    }

                    return `<tr><td class=\"border border-gray-200 px-2 py-2 text-xs font-semibold text-gray-700 text-center\">${idx + 1}</td><td class=\"border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-800\">${label.toUpperCase()}</td><td class=\"border border-gray-200 px-3 py-2 text-sm text-gray-700\">${cell}</td></tr>`;
                })
                .filter((row) => row !== "")
                .join("");

            return `<div class=\"overflow-hidden rounded-lg border border-gray-200\"><table class=\"w-full border-collapse\"><tbody>${rows}</tbody></table></div>`;
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

                const isRequired = Boolean(q.required);

                if (q.type === "section") {
                    return;
                }

                if (q.type === "checkbox") {
                    if (isRequired && !this.coerceBoolean(this.answers[qid])) {
                        this.fieldErrors[qid] = "Wajib dicentang.";
                    }
                    return;
                }

                if (q.type === "select") {
                    const val =
                        typeof this.answers[qid] === "string"
                            ? this.normalizePlainText(this.answers[qid])
                            : "";
                    if (isRequired && !val) {
                        this.fieldErrors[qid] = "Wajib diisi.";
                        return;
                    }
                    if (!val) {
                        return;
                    }
                    const opts = Array.isArray(q?.options) ? q.options : [];
                    const allowed = opts
                        .map((opt) =>
                            typeof opt?.value === "string" ? opt.value : "",
                        )
                        .filter((v) => v !== "");
                    if (allowed.length > 0 && !allowed.includes(val)) {
                        this.fieldErrors[qid] = "Pilihan tidak valid.";
                    }
                    return;
                }

                if (q.type === "date") {
                    const val =
                        typeof this.answers[qid] === "string"
                            ? this.normalizePlainText(this.answers[qid])
                            : "";
                    if (isRequired && !val) {
                        this.fieldErrors[qid] = "Wajib diisi.";
                        return;
                    }
                    if (!val) {
                        return;
                    }
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(val)) {
                        this.fieldErrors[qid] = "Tanggal tidak valid.";
                    }
                    return;
                }

                if (q.type === "number") {
                    const valRaw = this.answers[qid];
                    const val =
                        typeof valRaw === "string"
                            ? this.normalizePlainText(valRaw)
                            : typeof valRaw === "number"
                              ? String(valRaw)
                              : "";
                    if (isRequired && !val) {
                        this.fieldErrors[qid] = "Wajib diisi.";
                        return;
                    }
                    if (!val) {
                        return;
                    }
                    if (!/^[+-]?\d+(\.\d+)?$/.test(val)) {
                        this.fieldErrors[qid] = "Angka tidak valid.";
                    }
                    return;
                }

                if (q.type === "list") {
                    const current = this.answers[qid];

                    if (typeof current === "string") {
                        if (this.looksLikeHtml(current)) {
                            const normalized =
                                this.normalizeEditorHtml(current);
                            const listHtml =
                                this.extractListContainerHtml(normalized) ||
                                normalized;
                            if (this.isEditorBlank(listHtml)) {
                                if (isRequired) {
                                    this.fieldErrors[qid] = "Wajib diisi.";
                                }
                            }

                            return;
                        }

                        if (isRequired && !this.normalizePlainText(current)) {
                            this.fieldErrors[qid] = "Wajib diisi.";
                        }

                        return;
                    }

                    const items = Array.isArray(current)
                        ? current
                              .filter((val) => typeof val === "string")
                              .filter((val) => this.htmlToPlainText(val) !== "")
                        : [];

                    if (items.length === 0) {
                        if (isRequired) {
                            this.fieldErrors[qid] = "Wajib diisi.";
                        }
                    }

                    return;
                }

                const val =
                    typeof this.answers[qid] === "string"
                        ? this.answers[qid]
                        : "";
                if (this.looksLikeHtml(val)) {
                    if (this.isEditorBlank(val)) {
                        if (isRequired) {
                            this.fieldErrors[qid] = "Wajib diisi.";
                        }
                    }

                    return;
                }

                if (isRequired && !this.normalizePlainText(val)) {
                    this.fieldErrors[qid] = "Wajib diisi.";
                }
            });

            return Object.keys(this.fieldErrors).length === 0;
        },

        questionTypeLabel(type) {
            const t = String(type || "text");
            if (t === "section") return "Section";
            if (t === "text") return "Teks";
            if (t === "textarea") return "Paragraf";
            if (t === "list") return "Daftar";
            if (t === "select") return "Pilihan";
            if (t === "checkbox") return "Centang";
            if (t === "date") return "Tanggal";
            if (t === "number") return "Angka";
            return "Isian";
        },

        coerceBoolean(value) {
            if (typeof value === "boolean") return value;
            if (typeof value === "number") return value === 1;
            if (typeof value === "string") {
                const v = value.trim().toLowerCase();
                if (["1", "true", "on", "yes", "y"].includes(v)) return true;
                if (["0", "false", "off", "no", "n", ""].includes(v))
                    return false;
            }
            return false;
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

        previewPdfPayload() {
            const answers = {};
            const questions = this.schemaQuestions();
            questions.forEach((q) => {
                const qid = typeof q?.id === "string" ? q.id : "";
                if (!qid) return;
                if (!Object.prototype.hasOwnProperty.call(this.answers, qid)) {
                    return;
                }

                answers[qid] = this.answers[qid];
            });

            return {
                doc_type: this.docType,
                clause: this.clause,
                doc_code: this.docCode,
                title: this.title,
                template_id: this.templateId || null,
                parent_sop_id: this.parentSopId || null,
                paired_ik_id: this.pairedIkId || null,
                effective_date: this.effectiveDate || null,
                change_summary: this.changeSummary || null,
                answers_json: answers,
            };
        },

        async openPdfPreview() {
            this.pdfPreviewError = "";
            if (!this.docType) {
                this.pdfPreviewError =
                    "Pilih jenis dokumen terlebih dahulu sebelum preview PDF.";
                return;
            }

            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");

            const previewWindow = window.open("about:blank", "_blank");
            if (!previewWindow) {
                this.pdfPreviewError =
                    "Preview PDF diblokir browser. Izinkan pop-up untuk membuka preview.";
                return;
            }

            this.pdfPreviewLoading = true;
            try {
                const response = await fetch("/api/quality/preview/pdf", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/pdf",
                        "Content-Type": "application/json",
                        ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                    },
                    body: JSON.stringify(this.previewPdfPayload()),
                });

                if (!response.ok) {
                    const message = await response.text();
                    previewWindow.close();
                    this.pdfPreviewError =
                        message?.trim() ||
                        "Gagal membuat preview PDF. Silakan coba lagi.";
                    return;
                }

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                previewWindow.location.href = url;
                setTimeout(() => URL.revokeObjectURL(url), 60000);
            } catch {
                previewWindow.close();
                this.pdfPreviewError =
                    "Terjadi gangguan jaringan saat membuat preview PDF.";
            } finally {
                this.pdfPreviewLoading = false;
            }
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
