import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pages/settings/index.js',
                // Scoped UI entry (opt-in per page)
                'resources/css/ui-scope.css',
            ],
            refresh: true,
        }),
    ],
    optimizeDeps: {},
});
