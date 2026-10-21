import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
            fonts: [
                google('Archivo', {
                    weights: [400, 600, 800],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        react(),
    ],
    build: {
        // HP guru/orang tua bisa memakai iOS/Android lama: tulis media query sebagai
        // (max-width: …), bukan (width <= …) yang baru didukung Safari 16.4.
        cssTarget: ['safari14', 'ios14', 'chrome87', 'firefox78', 'edge88'],
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
