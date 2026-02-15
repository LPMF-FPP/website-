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
    const fallbackHtml = "<p></p>";

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

    return {
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

                        this.$dispatch("qmh-editor-change", {
                            html: this.contentHtml,
                            editor_json: editor.getJSON(),
                        });
                    },
                });

                if (this.$refs.hiddenInput) {
                    this.$refs.hiddenInput.value = editorInstance.getHTML();
                }
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

        getHTML() {
            return editorInstance ? editorInstance.getHTML() : this.contentHtml;
        },

        getJSON() {
            return editorInstance ? editorInstance.getJSON() : null;
        },

        destroy() {
            if (editorInstance) {
                editorInstance.destroy();
                editorInstance = null;
            }
        },
    };
}
