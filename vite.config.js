import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { nativephpMobile, nativephpHotFile } from './vendor/nativephp/mobile/resources/js/vite-plugin.js';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: false,
            hotFile: nativephpHotFile(),
        }),
        svelte(),
        tailwindcss(),
        nativephpMobile(),
    ],
    server: {
        host: '0.0.0.0',
        hmr: false,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
