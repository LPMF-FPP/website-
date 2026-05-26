export function qmhPendukungPicker(config = {}) {
    return {
        open: false,
        loading: false,
        error: "",
        clause: null,
        editorId: "",
        query: "",
        selectedId: null,
        items: [],
        endpoint:
            typeof config.endpoint === "string" && config.endpoint !== ""
                ? config.endpoint
                : "/api/quality/pendukung",

        init() {
            this.onOpenRequest = (event) => {
                const detail =
                    event?.detail && typeof event.detail === "object"
                        ? event.detail
                        : {};

                this.editorId =
                    typeof detail.editorId === "string" ? detail.editorId : "";
                this.clause = Number.isFinite(Number(detail.clause))
                    ? Number(detail.clause)
                    : null;
                this.query = "";
                this.selectedId = null;
                this.open = true;
                this.fetchItems();
            };

            window.addEventListener(
                "qmh-pendukung-picker:open",
                this.onOpenRequest,
            );
        },

        destroy() {
            if (this.onOpenRequest) {
                window.removeEventListener(
                    "qmh-pendukung-picker:open",
                    this.onOpenRequest,
                );
            }
        },

        async fetchItems() {
            this.loading = true;
            this.error = "";

            try {
                const url = new URL(this.endpoint, window.location.origin);
                if (this.clause) {
                    url.searchParams.set("clause", String(this.clause));
                }
                url.searchParams.set("per_page", "100");

                const response = await fetch(url.toString(), {
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                    },
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                const rows = Array.isArray(payload?.data) ? payload.data : [];

                this.items = rows.map((item) => ({
                    id: Number(item.id),
                    doc_code: String(item.doc_code || ""),
                    title: String(item.title || ""),
                    clause: Number(item.clause || 0),
                    mime: String(item?.current_revision?.source_pdf_mime || ""),
                    revisionId: Number(item?.current_revision?.id || 0),
                }));
            } catch {
                this.error = "Gagal memuat daftar dokumen pendukung.";
                this.items = [];
            } finally {
                this.loading = false;
            }
        },

        filteredItems() {
            const keyword = this.query.trim().toLowerCase();
            if (keyword === "") {
                return this.items;
            }

            return this.items.filter((item) => {
                const haystack = `${item.doc_code} ${item.title}`.toLowerCase();
                return haystack.includes(keyword);
            });
        },

        select(item) {
            this.selectedId = Number(item.id);
        },

        isSelected(item) {
            return Number(item.id) === this.selectedId;
        },

        selectedItem() {
            return (
                this.items.find(
                    (item) => Number(item.id) === this.selectedId,
                ) || null
            );
        },

        isPdf(item) {
            return item.mime === "application/pdf";
        },

        buildFileUrl(item) {
            const revisionId = Number(item?.revisionId || 0);
            const versionQuery = revisionId > 0 ? `?v=${revisionId}` : "";

            return `/quality/pendukung/${item.id}/file${versionQuery}`;
        },

        confirmSelection() {
            const item = this.selectedItem();
            if (!item) {
                return;
            }

            window.dispatchEvent(
                new CustomEvent("qmh-pendukung-picker:selected", {
                    detail: {
                        editorId: this.editorId,
                        url: this.buildFileUrl(item),
                        title: `${item.doc_code} - ${item.title}`,
                        isPdf: this.isPdf(item),
                    },
                }),
            );

            this.close();
        },

        close() {
            this.open = false;
            this.selectedId = null;
        },
    };
}
