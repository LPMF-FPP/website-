export function qmhAuditPage() {
    return {
        badgeClass(status) {
            const map = {
                draft: "bg-gray-100 text-gray-700",
                scheduled: "bg-blue-100 text-blue-700",
                in_progress: "bg-amber-100 text-amber-800",
                closed: "bg-emerald-100 text-emerald-700",
                cancelled: "bg-rose-100 text-rose-700",
            };

            return map[status] ?? map.draft;
        },

        severityClass(severity) {
            const map = {
                minor: "bg-blue-100 text-blue-700",
                major: "bg-amber-100 text-amber-800",
                kritis: "bg-rose-100 text-rose-700",
            };

            return map[severity] ?? "bg-gray-100 text-gray-700";
        },
    };
}
