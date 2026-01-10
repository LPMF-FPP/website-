export default {
    notifications: [],

    show(message, type = "info", duration = 3000) {
        const id =
            Date.now().toString(36) + Math.random().toString(36).substr(2);

        this.notifications.push({
            id,
            message,
            type,
            duration,
        });

        this.announce(message, type);

        if (duration > 0) {
            setTimeout(() => {
                this.dismiss(id);
            }, duration);
        }

        return id;
    },

    dismiss(id) {
        this.notifications = this.notifications.filter((n) => n.id !== id);
    },

    success(message, duration = 3000) {
        return this.show(message, "success", duration);
    },

    error(message, duration = 5000) {
        return this.show(message, "error", duration);
    },

    warning(message, duration = 4000) {
        return this.show(message, "warning", duration);
    },

    info(message, duration = 3000) {
        return this.show(message, "info", duration);
    },

    announce(message, type) {
        const announcer = document.getElementById("toast-announcer");
        if (announcer) {
            const prefix =
                type === "error"
                    ? "Error: "
                    : type === "warning"
                      ? "Warning: "
                      : "";
            announcer.textContent = prefix + message;

            // Clear text content to allow re-announcing same message if needed
            setTimeout(() => {
                announcer.textContent = "";
            }, 1000);
        }
    },
};
