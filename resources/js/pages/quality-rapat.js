export function qmhRapatPage() {
    return {
        statusBadgeClass(status) {
            switch (status) {
                case "completed":
                case "verified":
                case "closed":
                    return "bg-green-100 text-green-700";
                case "in_progress":
                case "resolved":
                    return "bg-blue-100 text-blue-700";
                case "overdue":
                case "cancelled":
                    return "bg-red-100 text-red-700";
                case "scheduled":
                case "open":
                    return "bg-amber-100 text-amber-700";
                default:
                    return "bg-gray-100 text-gray-700";
            }
        },
    };
}
