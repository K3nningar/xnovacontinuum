/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Configures the Vite asset build process.
 */

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

/** Bundle the Inertia/Vue entry points with Laravel refresh, font provisioning, and Tailwind v4. */
export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rolldownOptions: {
            checks: {
                bundlerTimings: false,
            },
            output: {
                codeSplitting: {
                    groups: [
                        {
                            name: 'vendor',
                            test: /[\\/]node_modules[\\/]/,
                            tags: ['$initial'],
                        },
                    ],
                },
            },
        },
    },
});
