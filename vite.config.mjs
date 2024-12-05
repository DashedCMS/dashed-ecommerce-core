import { defineConfig } from 'vite';
import laravel, { refreshPaths } from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                './resources/js/pos.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        outDir: 'resources/dist', // Output directory (Laravel's public folder)
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/js/pos.js'),
            output: {
                entryFileNames: '[name].js', // Custom output path
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
        emptyOutDir: false, // Prevent clearing the public directory on build
    },
});
