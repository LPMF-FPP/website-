import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/js/pages/qmh-docx-editor.jsx",
                "resources/js/pages/settings/index.js",
                "resources/js/pages/requests-form.js",
                // Scoped UI entry (opt-in per page)
                "resources/css/ui-scope.css",
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes("@tiptap/")) {
                        return "qmh-editor";
                    }

                    if (id.includes("@eigenpal/docx-js-editor")) {
                        return "qmh-docx-editor";
                    }

                    if (id.includes("alpinejs") || id.includes("@alpinejs/")) {
                        return "alpine";
                    }

                    if (id.includes("axios")) {
                        return "http";
                    }

                    if (id.includes("node_modules")) {
                        return "vendor";
                    }

                    return undefined;
                },
            },
        },
    },
    optimizeDeps: {},
});
