export function qmhKumPage() {
    return {
        periodLabel(period) {
            const labels = {
                q1: "Triwulan 1",
                q2: "Triwulan 2",
                q3: "Triwulan 3",
                q4: "Triwulan 4",
                annual: "Tahunan",
            };

            return labels[period] ?? period;
        },

        statusClass(status) {
            const map = {
                draft: "bg-gray-100 text-gray-700",
                scheduled: "bg-blue-100 text-blue-700",
                in_progress: "bg-amber-100 text-amber-800",
                completed: "bg-emerald-100 text-emerald-700",
                closed: "bg-indigo-100 text-indigo-700",
            };

            return map[status] ?? map.draft;
        },
    };
}
