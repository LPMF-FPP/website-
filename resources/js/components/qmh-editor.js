import { Editor } from "@tiptap/core";
import { Table } from "@tiptap/extension-table";
import TableCell from "@tiptap/extension-table-cell";
import TableHeader from "@tiptap/extension-table-header";
import TableRow from "@tiptap/extension-table-row";
import TextAlign from "@tiptap/extension-text-align";
import StarterKit from "@tiptap/starter-kit";

export function qmhEditor(config = {}) {
    return {
        editor: null,
        contentHtml: config.initialContent || "",
        readOnly: Boolean(config.readOnly),
        editorId: config.editorId || "",
        setContentListener: null,

        init() {
            this.$nextTick(() => {
                this.editor = new Editor({
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
                    this.$refs.hiddenInput.value = this.editor.getHTML();
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
            if (!this.editor) {
                this.contentHtml = html;

                return;
            }

            this.editor.commands.setContent(html || "<p></p>", false);
            this.contentHtml = this.editor.getHTML();

            if (this.$refs.hiddenInput) {
                this.$refs.hiddenInput.value = this.contentHtml;
            }

            this.$dispatch("qmh-editor-change", {
                html: this.contentHtml,
                editor_json: this.editor.getJSON(),
            });
        },

        isActive(name, attrs = {}) {
            if (!this.editor) {
                return false;
            }

            return this.editor.isActive(name, attrs);
        },

        toggleBold() {
            this.editor?.chain().focus().toggleBold().run();
        },

        toggleItalic() {
            this.editor?.chain().focus().toggleItalic().run();
        },

        toggleUnderline() {
            this.editor?.chain().focus().toggleUnderline().run();
        },

        toggleBulletList() {
            this.editor?.chain().focus().toggleBulletList().run();
        },

        toggleOrderedList() {
            this.editor?.chain().focus().toggleOrderedList().run();
        },

        setHeading(level) {
            this.editor?.chain().focus().toggleHeading({ level }).run();
        },

        setParagraph() {
            this.editor?.chain().focus().setParagraph().run();
        },

        setAlign(alignment) {
            this.editor?.chain().focus().setTextAlign(alignment).run();
        },

        insertTable() {
            this.editor
                ?.chain()
                .focus()
                .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                .run();
        },

        getHTML() {
            return this.editor ? this.editor.getHTML() : this.contentHtml;
        },

        getJSON() {
            return this.editor ? this.editor.getJSON() : null;
        },

        destroy() {
            if (this.setContentListener) {
                window.removeEventListener(
                    "qmh-editor:set-content",
                    this.setContentListener,
                );
                this.setContentListener = null;
            }

            if (this.editor) {
                this.editor.destroy();
                this.editor = null;
            }
        },
    };
}
