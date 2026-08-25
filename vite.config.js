import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/public.css',
                'resources/css/admin.css',
                'resources/js/public.js',
                'resources/js/admin.js',
            ],
            refresh: true,
        }),
    ],
});
