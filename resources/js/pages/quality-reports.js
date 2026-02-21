export function qmhReports(config = {}) {
    return {
        csrfToken: config.csrfToken || "",
        activeTab: "revision-history",
        loading: false,
        errorMessage: "",
        rows: [],
        filters: {
            search: "",
            clause: "",
            doc_type: "",
            actor_id: "",
            from: "",
            to: "",
            per_page: "15",
        },
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 0,
        },

        init() {
            this.fetchRows();
        },

        tabClass(tab) {
            if (this.activeTab === tab) {
                return "bg-primary-100 text-primary-700";
            }

            return "text-gray-600 hover:bg-gray-100";
        },

        switchTab(tab) {
            if (this.activeTab === tab) {
                return;
            }

            this.activeTab = tab;
            this.pagination.current_page = 1;
            this.fetchRows();
        },

        applyFilters() {
            this.pagination.current_page = 1;
            this.fetchRows();
        },

        resetFilters() {
            this.filters = {
                search: "",
                clause: "",
                doc_type: "",
                actor_id: "",
                from: "",
                to: "",
                per_page: "15",
            };

            this.pagination.current_page = 1;
            this.fetchRows();
        },

        goToPage(page) {
            if (page < 1 || page > this.pagination.last_page) {
                return;
            }

            this.pagination.current_page = page;
            this.fetchRows();
        },

        buildEndpoint() {
            if (this.activeTab === "revision-history") {
                return "/api/quality/reports/revision-history";
            }

            if (this.activeTab === "download-history") {
                return "/api/quality/reports/download-history";
            }

            return "/api/quality/reports/controlled-distribution";
        },

        buildExportEndpoint() {
            if (this.activeTab === "revision-history") {
                return "/api/quality/reports/revision-history/export";
            }

            if (this.activeTab === "download-history") {
                return "/api/quality/reports/download-history/export";
            }

            return "/api/quality/reports/controlled-distribution/export";
        },

        buildParams(page = null) {
            const params = new URLSearchParams();
            params.set("per_page", this.filters.per_page || "15");
            params.set("page", String(page || this.pagination.current_page));

            const keys = [
                "search",
                "clause",
                "doc_type",
                "actor_id",
                "from",
                "to",
            ];
            for (const key of keys) {
                const value = this.filters[key];
                if (
                    value !== null &&
                    value !== undefined &&
                    String(value).trim() !== ""
                ) {
                    params.set(key, String(value));
                }
            }

            return params;
        },

        buildRowKey(row, index) {
            const primaryId = row.id ?? row.event_id ?? row.log_id ?? row.uuid;
            if (
                primaryId !== null &&
                primaryId !== undefined &&
                String(primaryId).trim() !== ""
            ) {
                return `${this.activeTab}-id-${String(primaryId)}`;
            }

            const occurredAt = row.occurred_at ?? "na";
            const documentCode = row.document_code ?? "na";
            const actorId = row.actor_id ?? "na";
            const versionLabel = row.version_label ?? "na";
            const eventMarker = row.status_transition ?? row.copy_type ?? "na";

            return `${this.activeTab}-${occurredAt}-${documentCode}-${actorId}-${versionLabel}-${eventMarker}-${index}`;
        },

        normalizeRows(rawRows) {
            if (!Array.isArray(rawRows)) {
                return [];
            }

            return rawRows
                .filter((row) => row !== null && typeof row === "object")
                .map((row, index) => ({
                    ...row,
                    __rowKey: this.buildRowKey(row, index),
                }));
        },

        async fetchRows() {
            this.loading = true;
            this.errorMessage = "";

            const endpoint = this.buildEndpoint();
            const params = this.buildParams();

            try {
                const response = await fetch(
                    `${endpoint}?${params.toString()}`,
                    {
                        credentials: "same-origin",
                        headers: {
                            Accept: "application/json",
                        },
                    },
                );

                if (!response.ok) {
                    this.errorMessage = await this.extractErrorMessage(
                        response,
                        "Gagal memuat data laporan.",
                    );
                    return;
                }

                const payload = await response.json();
                this.rows = this.normalizeRows(payload?.data);
                this.pagination = {
                    current_page: payload.current_page || 1,
                    last_page: payload.last_page || 1,
                    total: payload.total || 0,
                };
            } catch {
                this.errorMessage =
                    "Terjadi gangguan jaringan saat memuat laporan.";
            } finally {
                this.loading = false;
            }
        },

        exportCsv() {
            const endpoint = this.buildExportEndpoint();
            const params = this.buildParams(1);
            window.location.href = `${endpoint}?${params.toString()}`;
        },

        formatDate(value) {
            if (!value) {
                return "-";
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString("id-ID", { hour12: false });
        },

        async extractErrorMessage(response, fallback) {
            try {
                const payload = await response.json();
                if (payload?.message) {
                    return payload.message;
                }

                if (payload?.errors) {
                    const firstKey = Object.keys(payload.errors)[0];
                    if (firstKey && payload.errors[firstKey]?.length) {
                        return payload.errors[firstKey][0];
                    }
                }
            } catch (error) {
                void error;
            }

            return fallback;
        },
    };
}
