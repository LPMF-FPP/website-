import { Editor } from "@tiptap/core";
import { Table } from "@tiptap/extension-table";
import TableCell from "@tiptap/extension-table-cell";
import TableHeader from "@tiptap/extension-table-header";
import TableRow from "@tiptap/extension-table-row";
import TextAlign from "@tiptap/extension-text-align";
import StarterKit from "@tiptap/starter-kit";

export function qmhEditor(config = {}) {
    let editorInstance = null;

    return {
        contentHtml: config.initialContent || "",
        readOnly: Boolean(config.readOnly),
        editorId: config.editorId || "",
        setContentListener: null,

        init() {
            this.$nextTick(() => {
                editorInstance = new Editor({
                    element: this.$refs.editor,
                    editable: !this.readOnly,
                    content: this.contentHtml || "<p></p>",
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

                this.registerSetContentListener();

                if (this.editorId) {
                    window.dispatchEvent(
                        new CustomEvent("qmh-editor:ready", {
                            detail: { target: this.editorId },
                        }),
                    );
                }
            });
        },

        registerSetContentListener() {
            if (!this.editorId) {
                return;
            }

            this.setContentListener = (event) => {
                const detail = event?.detail || {};
                if (detail.target !== this.editorId) {
                    return;
                }

                this.setContent(detail.html || "<p></p>");
            };

            window.addEventListener(
                "qmh-editor:set-content",
                this.setContentListener,
            );
        },

        setContent(html) {
            if (!editorInstance) {
                this.contentHtml = html;

                return;
            }

            const nextHtml = html || "<p></p>";
            if (editorInstance.getHTML() === nextHtml) {
                return;
            }

            window.requestAnimationFrame(() => {
                if (!editorInstance) {
                    return;
                }

                editorInstance.commands.setContent(nextHtml, false);
                this.contentHtml = editorInstance.getHTML();

                if (this.$refs.hiddenInput) {
                    this.$refs.hiddenInput.value = this.contentHtml;
                }

                this.$dispatch("qmh-editor-change", {
                    html: this.contentHtml,
                    editor_json: editorInstance.getJSON(),
                });
            });
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

        getHTML() {
            return editorInstance ? editorInstance.getHTML() : this.contentHtml;
        },

        getJSON() {
            return editorInstance ? editorInstance.getJSON() : null;
        },

        destroy() {
            if (this.setContentListener) {
                window.removeEventListener(
                    "qmh-editor:set-content",
                    this.setContentListener,
                );
                this.setContentListener = null;
            }

            if (editorInstance) {
                editorInstance.destroy();
                editorInstance = null;
            }
        },
    };
}
