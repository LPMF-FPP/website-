export function qmhFormBuilder(config = {}) {
    return {
        schemaVersion: 1,
        schemaDocType: String(config?.docType || "fr"),
        questions: [],
        showRawJson: false,
        jsonError: "",
        _syncQueued: false,

        init() {
            const initialSchema = config?.initialSchema;
            const initialJson =
                typeof config?.initialJson === "string"
                    ? config.initialJson
                    : "";

            const schema =
                initialSchema && typeof initialSchema === "object"
                    ? initialSchema
                    : this.parseSchemaJson(initialJson);

            this.loadSchema(schema);
            this.syncJsonNow();
        },

        loadSchema(schema) {
            const s = schema && typeof schema === "object" ? schema : null;

            this.schemaVersion = Number(s?.version || 1);
            this.schemaDocType = String(s?.doc_type || this.schemaDocType);

            const questions = Array.isArray(s?.questions) ? s.questions : [];
            this.questions = questions
                .map((q) => this.normalizeQuestion(q))
                .filter((q) => q.id !== "");
        },

        parseSchemaJson(raw) {
            const txt = typeof raw === "string" ? raw.trim() : "";
            if (!txt)
                return {
                    version: 1,
                    doc_type: this.schemaDocType,
                    questions: [],
                };

            try {
                const decoded = JSON.parse(txt);
                if (!decoded || typeof decoded !== "object") {
                    this.jsonError = "Schema JSON tidak valid.";
                    return {
                        version: 1,
                        doc_type: this.schemaDocType,
                        questions: [],
                    };
                }

                this.jsonError = "";
                return decoded;
            } catch {
                this.jsonError = "Schema JSON tidak valid.";
                return {
                    version: 1,
                    doc_type: this.schemaDocType,
                    questions: [],
                };
            }
        },

        normalizeQuestion(q) {
            const id = typeof q?.id === "string" ? q.id.trim() : "";
            const label = typeof q?.label === "string" ? q.label.trim() : "";
            const type = String(q?.type || "text");
            const required = Boolean(q?.required);
            const help = typeof q?.help === "string" ? q.help : "";
            const placeholder =
                typeof q?.placeholder === "string" ? q.placeholder : "";
            const options = Array.isArray(q?.options)
                ? q.options
                      .map((opt) => ({
                          value:
                              typeof opt?.value === "string" ? opt.value : "",
                          label:
                              typeof opt?.label === "string" ? opt.label : "",
                      }))
                      .filter((opt) => opt.value.trim() !== "")
                : [];

            return {
                id,
                label,
                type,
                required: type === "section" ? false : required,
                help,
                placeholder,
                options,
                auto_id: false,
            };
        },

        addQuestion(type = "text") {
            this.questions.push({
                id: "",
                label: "",
                type,
                required: false,
                help: "",
                placeholder: "",
                options: [],
                auto_id: true,
            });
            this.syncJson();
        },

        deleteQuestion(idx) {
            if (idx < 0 || idx >= this.questions.length) return;
            this.questions.splice(idx, 1);
            this.syncJson();
        },

        moveUp(idx) {
            if (idx <= 0 || idx >= this.questions.length) return;
            const tmp = this.questions[idx - 1];
            this.questions[idx - 1] = this.questions[idx];
            this.questions[idx] = tmp;
            this.syncJson();
        },

        moveDown(idx) {
            if (idx < 0 || idx >= this.questions.length - 1) return;
            const tmp = this.questions[idx + 1];
            this.questions[idx + 1] = this.questions[idx];
            this.questions[idx] = tmp;
            this.syncJson();
        },

        onLabelChanged(idx) {
            const q = this.questions[idx];
            if (!q) return;

            if (!q.auto_id) {
                this.syncJson();
                return;
            }

            const base = this.slugifyId(q.label);
            if (base) {
                q.id = this.ensureUniqueId(base, idx);
            } else {
                q.id = this.ensureUniqueId("field", idx);
            }
            this.syncJson();
        },

        onTypeChanged(idx) {
            const q = this.questions[idx];
            if (!q) return;
            if (q.type === "section") {
                q.required = false;
                q.options = [];
            }
            if (q.type !== "select") {
                q.options = [];
            }
            this.syncJson();
        },

        addSelectOption(idx) {
            const q = this.questions[idx];
            if (!q || q.type !== "select") return;
            q.options.push({ value: "", label: "" });
            this.syncJson();
        },

        deleteSelectOption(qIdx, optIdx) {
            const q = this.questions[qIdx];
            if (!q || q.type !== "select") return;
            if (optIdx < 0 || optIdx >= q.options.length) return;
            q.options.splice(optIdx, 1);
            this.syncJson();
        },

        schemaObject() {
            return {
                version: this.schemaVersion,
                doc_type: this.schemaDocType,
                questions: this.questions.map((q) => {
                    const base = {
                        id: String(q.id || "").trim(),
                        label: String(q.label || "").trim(),
                        type: String(q.type || "text"),
                        required: Boolean(q.required),
                    };

                    if (q.help) base.help = String(q.help);
                    if (q.placeholder) base.placeholder = String(q.placeholder);

                    if (base.type === "select") {
                        base.options = Array.isArray(q.options)
                            ? q.options
                                  .map((opt) => ({
                                      value: String(opt?.value || "").trim(),
                                      label: String(opt?.label || "").trim(),
                                  }))
                                  .filter((opt) => opt.value !== "")
                            : [];
                    }

                    if (base.type === "section") {
                        base.required = false;
                    }

                    return base;
                }),
            };
        },

        schemaJson() {
            return JSON.stringify(this.schemaObject(), null, 2);
        },

        syncJson() {
            if (this._syncQueued) return;
            this._syncQueued = true;
            queueMicrotask(() => {
                this._syncQueued = false;
                this.syncJsonNow();
            });
        },

        syncJsonNow() {
            if (!this.$refs?.schemaJson) return;
            this.$refs.schemaJson.value = this.schemaJson();

            if (typeof this.$dispatch === "function") {
                this.$dispatch("qmh-form-schema-change", {
                    schema: this.schemaObject(),
                    json: this.schemaJson(),
                });
            }
        },

        slugifyId(label) {
            const raw = String(label || "")
                .toLowerCase()
                .replaceAll(/[^a-z0-9]+/g, "_")
                .replaceAll(/^_+|_+$/g, "")
                .replaceAll(/_+/g, "_");

            return raw.slice(0, 64);
        },

        ensureUniqueId(base, exceptIdx) {
            const clean = this.slugifyId(base) || "field";
            const existing = new Set(
                this.questions
                    .map((q, idx) => ({ id: String(q?.id || ""), idx }))
                    .filter((row) => row.id && row.idx !== exceptIdx)
                    .map((row) => row.id),
            );

            if (!existing.has(clean)) return clean;

            for (let i = 2; i < 200; i += 1) {
                const candidate = `${clean}_${i}`.slice(0, 64);
                if (!existing.has(candidate)) return candidate;
            }

            return `${clean}_${Date.now()}`.slice(0, 64);
        },
    };
}
