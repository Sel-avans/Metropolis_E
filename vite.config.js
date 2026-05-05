import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/grid.css', 'resources/css/app.css', 'resources/js/app.js', 'resources/js/grid.js', 'resources/js/effects.js'],
            refresh: true,
        }),
    ],
});
