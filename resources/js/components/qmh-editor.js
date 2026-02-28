import { Editor } from "@tiptap/core";
import Color from "@tiptap/extension-color";
import Image from "@tiptap/extension-image";
import { Table } from "@tiptap/extension-table";
import TableCell from "@tiptap/extension-table-cell";
import TableHeader from "@tiptap/extension-table-header";
import TableRow from "@tiptap/extension-table-row";
import TextAlign from "@tiptap/extension-text-align";
import { TextStyle } from "@tiptap/extension-text-style";
import StarterKit from "@tiptap/starter-kit";

export function qmhEditor(config = {}) {
    let editorInstance = null;
    let pickerSelectionListener = null;
    const fallbackHtml = "<p></p>";

    const escapeHtml = (value) =>
        String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#39;");

    const normalizeHtml = (value) => {
        if (typeof value !== "string") {
            return fallbackHtml;
        }

        const trimmed = value.trim();

        return trimmed.length > 0 ? trimmed : fallbackHtml;
    };

    const toEditorCompatibleHtml = (value) => {
        const normalized = normalizeHtml(value);

        if (/^<(table|img)\b/i.test(normalized)) {
            return `<p></p>${normalized}<p></p>`;
        }

        return normalized;
    };

    const resolveCursor = (editor) => {
        const selectionFrom = Number(editor?.state?.selection?.from ?? 0);
        const textBeforeCursor = editor?.state?.doc?.textBetween(
            0,
            selectionFrom,
            "\n",
            "\n",
        );
        const normalizedText =
            typeof textBeforeCursor === "string" ? textBeforeCursor : "";
        const lines = normalizedText.split("\n");

        return {
            line: Math.max(1, lines.length),
            column: (lines[lines.length - 1] ?? "").length + 1,
        };
    };

    return {
        editorId:
            typeof config.editorId === "string" && config.editorId.trim() !== ""
                ? config.editorId.trim()
                : `qmh-editor-${Math.random().toString(36).slice(2, 10)}`,
        contentHtml: normalizeHtml(config.initialContent),
        readOnly: Boolean(config.readOnly),

        init() {
            this.$nextTick(() => {
                if (editorInstance) {
                    editorInstance.destroy();
                    editorInstance = null;
                }

                editorInstance = new Editor({
                    element: this.$refs.editor,
                    editable: !this.readOnly,
                    content: toEditorCompatibleHtml(this.contentHtml),
                    extensions: [
                        StarterKit,
                        TextAlign.configure({
                            types: ["heading", "paragraph"],
                        }),
                        Table.configure({
                            resizable: false,
                        }),
                        TableRow,
                        TableHeader,
                        TableCell,
                        TextStyle,
                        Color.configure({
                            types: ["textStyle"],
                        }),
                        Image.configure({
                            allowBase64: true,
                        }),
                    ],
                    onUpdate: ({ editor }) => {
                        this.contentHtml = editor.getHTML();
                        if (this.$refs.hiddenInput) {
                            this.$refs.hiddenInput.value = this.contentHtml;
                        }

                        const cursor = resolveCursor(editor);

                        this.$dispatch("qmh-editor-change", {
                            html: this.contentHtml,
                            editor_json: editor.getJSON(),
                            cursor,
                        });

                        this.$dispatch("qmh-editor-cursor", cursor);
                    },
                    onSelectionUpdate: ({ editor }) => {
                        this.$dispatch(
                            "qmh-editor-cursor",
                            resolveCursor(editor),
                        );
                    },
                });

                if (this.$refs.hiddenInput) {
                    this.$refs.hiddenInput.value = editorInstance.getHTML();
                }

                this.$dispatch(
                    "qmh-editor-cursor",
                    resolveCursor(editorInstance),
                );

                if (pickerSelectionListener) {
                    window.removeEventListener(
                        "qmh-pendukung-picker:selected",
                        pickerSelectionListener,
                    );
                }

                pickerSelectionListener = (event) => {
                    const detail =
                        event?.detail && typeof event.detail === "object"
                            ? event.detail
                            : {};

                    if (
                        typeof detail.editorId === "string" &&
                        detail.editorId !== this.editorId
                    ) {
                        return;
                    }

                    this.insertPendukungLink(
                        String(detail.url || ""),
                        String(detail.title || ""),
                        Boolean(detail.isPdf),
                    );
                };

                window.addEventListener(
                    "qmh-pendukung-picker:selected",
                    pickerSelectionListener,
                );
            });
        },

        setContent(nextHtml) {
            const normalized = toEditorCompatibleHtml(nextHtml);
            this.contentHtml = normalized;

            if (!editorInstance) {
                return;
            }

            if (editorInstance.getHTML() !== normalized) {
                editorInstance.commands.setContent(normalized, false);
            }

            if (this.$refs.hiddenInput) {
                this.$refs.hiddenInput.value = editorInstance.getHTML();
            }
        },

        isActive(name, attrs = {}) {
            if (!editorInstance) {
                return false;
            }

            return editorInstance.isActive(name, attrs);
        },

        toggleBold() {
            editorInstance?.chain().focus().toggleBold().run();
        },

        toggleItalic() {
            editorInstance?.chain().focus().toggleItalic().run();
        },

        toggleUnderline() {
            editorInstance?.chain().focus().toggleUnderline().run();
        },

        toggleBulletList() {
            editorInstance?.chain().focus().toggleBulletList().run();
        },

        toggleOrderedList() {
            editorInstance?.chain().focus().toggleOrderedList().run();
        },

        setHeading(level) {
            editorInstance?.chain().focus().toggleHeading({ level }).run();
        },

        setParagraph() {
            editorInstance?.chain().focus().setParagraph().run();
        },

        setAlign(alignment) {
            editorInstance?.chain().focus().setTextAlign(alignment).run();
        },

        insertTable() {
            editorInstance
                ?.chain()
                .focus()
                .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                .run();
        },

        addTableRowBefore() {
            editorInstance?.chain().focus().addRowBefore().run();
        },

        addTableRowAfter() {
            editorInstance?.chain().focus().addRowAfter().run();
        },

        deleteTableRow() {
            editorInstance?.chain().focus().deleteRow().run();
        },

        addTableColumnBefore() {
            editorInstance?.chain().focus().addColumnBefore().run();
        },

        addTableColumnAfter() {
            editorInstance?.chain().focus().addColumnAfter().run();
        },

        deleteTableColumn() {
            editorInstance?.chain().focus().deleteColumn().run();
        },

        mergeTableCells() {
            const merged = editorInstance?.chain().focus().mergeCells().run();
            if (!merged) {
                editorInstance?.chain().focus().mergeOrSplit().run();
            }
        },

        splitTableCell() {
            const split = editorInstance?.chain().focus().splitCell().run();
            if (!split) {
                editorInstance?.chain().focus().mergeOrSplit().run();
            }
        },

        toggleTableHeaderRow() {
            editorInstance?.chain().focus().toggleHeaderRow().run();
        },

        toggleTableHeaderColumn() {
            editorInstance?.chain().focus().toggleHeaderColumn().run();
        },

        deleteTable() {
            editorInstance?.chain().focus().deleteTable().run();
        },

        openPendukungPicker(options = {}) {
            const clause = Number.isFinite(Number(options?.clause))
                ? Number(options.clause)
                : null;

            window.dispatchEvent(
                new CustomEvent("qmh-pendukung-picker:open", {
                    detail: {
                        editorId: this.editorId,
                        clause,
                    },
                }),
            );
        },

        insertPendukungLink(url, title, isPdf = false) {
            const normalizedUrl = typeof url === "string" ? url.trim() : "";
            if (normalizedUrl === "") {
                return;
            }

            const normalizedTitle =
                typeof title === "string" && title.trim() !== ""
                    ? title.trim()
                    : normalizedUrl;

            const label = isPdf ? `${normalizedTitle} (PDF)` : normalizedTitle;
            const linkHtml = `<a href="${escapeHtml(normalizedUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(label)}</a>`;

            editorInstance?.chain().focus().insertContent(linkHtml).run();
        },

        getHTML() {
            return editorInstance ? editorInstance.getHTML() : this.contentHtml;
        },

        getJSON() {
            return editorInstance ? editorInstance.getJSON() : null;
        },

        getCursorPosition() {
            return editorInstance
                ? resolveCursor(editorInstance)
                : { line: 1, column: 1 };
        },

        destroy() {
            if (pickerSelectionListener) {
                window.removeEventListener(
                    "qmh-pendukung-picker:selected",
                    pickerSelectionListener,
                );
                pickerSelectionListener = null;
            }

            if (editorInstance) {
                editorInstance.destroy();
                editorInstance = null;
            }
        },
    };
}
