import { defineConfig } from 'vite'
import laravel, { refreshPaths } from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                './resources/js/pos.js',
            ],
            refresh: true,
        }),
    ],
});
