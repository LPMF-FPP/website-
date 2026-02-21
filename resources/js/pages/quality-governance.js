export function qmhGovernancePage() {
    return {
        summary: {
            rapat_count: 0,
            audit_count: 0,
            kum_count: 0,
            due_soon_count: 0,
            overdue_count: 0,
        },
        loading: false,

        init() {
            this.refreshSummary();
        },

        async refreshSummary() {
            this.loading = true;

            try {
                const response = await fetch(
                    "/api/quality/governance/summary",
                    {
                        credentials: "same-origin",
                        headers: {
                            Accept: "application/json",
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                this.summary = {
                    ...this.summary,
                    ...payload,
                };
            } catch {
                // noop - keep initial server-rendered values
            } finally {
                this.loading = false;
            }
        },
    };
}
